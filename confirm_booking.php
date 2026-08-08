<?php
session_start();
include('connection.php');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Load user info
$passenger_name = '';
$passenger_phone = '';
$passenger_email = '';
$user_stmt = $conn->prepare("SELECT firstname, lastname, contact, email FROM users WHERE id = ? LIMIT 1");
if ($user_stmt) {
    $user_stmt->bind_param('i', $userId);
    $user_stmt->execute();
    $user_res = $user_stmt->get_result();
    $user_row = $user_res->fetch_assoc();
    if ($user_row) {
        $passenger_name = trim(($user_row['firstname'] ?? '') . ' ' . ($user_row['lastname'] ?? ''));
        $passenger_phone = $user_row['contact'] ?? '';
        $passenger_email = $user_row['email'] ?? '';
    }
    $user_stmt->close();
}

// Get POST values
$number_of_seats = isset($_POST['seatCount']) ? (int)$_POST['seatCount'] : 1;
$bus_plaque = $_POST['plate_nbr'] ?? '';
$bus_name = $_POST['bus_name'] ?? '';
$route_name = $_POST['route_name'] ?? '';
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$travel_date = $_POST['travel_date'] ?? '';
$total_amount = $price * max(1, $number_of_seats);
$trip_id = $_POST['trip_id'] ?? null;
$posted_firstname = $_POST['firstName'] ?? '';
$posted_lastname = $_POST['lastName'] ?? '';
$posted_email = $_POST['email'] ?? '';
$posted_phone = $_POST['phone'] ?? '';

// Use POST values if available, fallback to DB values
$firstname = !empty($posted_firstname) ? $posted_firstname : ($user_row['firstname'] ?? '');
$lastname = !empty($posted_lastname) ? $posted_lastname : ($user_row['lastname'] ?? '');
$email = !empty($posted_email) ? $posted_email : ($user_row['email'] ?? '');
$phone = !empty($posted_phone) ? $posted_phone : ($user_row['contact'] ?? '');

// ===== HANDLE BOOKING CREATION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');

    try {
        $user_id = (int)$_POST['user_id'];
        $trip_id = (int)$_POST['trip_id'];
        $number_of_seats = (int)$_POST['number_of_seats'];
        $payment_method = $conn->real_escape_string($_POST['payment_method']);
        $amount = (float)$_POST['amount'];
        $firstname = $conn->real_escape_string($_POST['firstname']);
        $lastname = $conn->real_escape_string($_POST['lastname']);
        $email = $conn->real_escape_string($_POST['email']);
        $contact = $conn->real_escape_string($_POST['contact']);

        // Validate
        if (empty($trip_id) || $trip_id <= 0) {
            throw new Exception("Invalid trip ID");
        }
        if (empty($number_of_seats) || $number_of_seats <= 0) {
            throw new Exception("Invalid number of seats");
        }
        if (empty($payment_method)) {
            throw new Exception("Please select a payment method");
        }
        if (empty($email)) {
            throw new Exception("Email is required");
        }

        $conn->begin_transaction();

        // 1. Check if customer exists
        $customer_id = null;
        $customer_check = $conn->query("SELECT customer_id FROM customers WHERE email = '$email' LIMIT 1");

        if ($customer_check && $customer_check->num_rows > 0) {
            $customer_row = $customer_check->fetch_assoc();
            $customer_id = $customer_row['customer_id'];
            // Update customer details
            $update_customer = $conn->prepare(
                "UPDATE customers SET firstname = ?, lastname = ?, contact = ? WHERE customer_id = ?"
            );
            $update_customer->bind_param("sssi", $firstname, $lastname, $contact, $customer_id);
            $update_customer->execute();
            $update_customer->close();
        } else {
            $insert_customer = $conn->prepare(
                "INSERT INTO customers (firstname, lastname, contact, email, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $insert_customer->bind_param("ssss", $firstname, $lastname, $contact, $email);
            if (!$insert_customer->execute()) {
                throw new Exception("Failed to create customer: " . $conn->error);
            }
            $customer_id = $conn->insert_id;
            $insert_customer->close();
        }

        // 2. Create booking
        $booking_date = date('Y-m-d H:i:s');
        $insert_booking = $conn->prepare(
            "INSERT INTO bookings (customer_id, trip_id, number_of_seats, booking_date) 
             VALUES (?, ?, ?, ?)"
        );
        $insert_booking->bind_param("iiis", $customer_id, $trip_id, $number_of_seats, $booking_date);
        if (!$insert_booking->execute()) {
            throw new Exception("Failed to create booking: " . $conn->error);
        }
        $booking_id = $conn->insert_id;
        $insert_booking->close();

        // 3. Create payment record
        $payment_status = 'completed';
        $transaction_id = 'TXN_' . time() . '_' . rand(10000, 99999);
        $time_paid = date('Y-m-d H:i:s');
        $insert_payment = $conn->prepare(
            "INSERT INTO payments (booking_id, amount, payment_method, transaction_id, payment_status, time_paid) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insert_payment->bind_param("idssss", $booking_id, $amount, $payment_method, $transaction_id, $payment_status, $time_paid);
        if (!$insert_payment->execute()) {
            throw new Exception("Failed to create payment: " . $conn->error);
        }
        $insert_payment->close();

        // 4. Create ticket record - SET checked = 'no' (THIS IS CORRECT)
        $checked = 'no';
        $insert_ticket = $conn->prepare(
            "INSERT INTO tickets (booking_id, checked, checked_at, created_at) 
             VALUES (?, ?, NULL, NOW())"
        );
        $insert_ticket->bind_param("is", $booking_id, $checked);
        if (!$insert_ticket->execute()) {
            throw new Exception("Failed to create ticket: " . $conn->error);
        }
        $ticket_id = $conn->insert_id;
        $insert_ticket->close();

        // 5. Update available seats
        $update_seats = $conn->prepare(
            "UPDATE trips SET available_seats = available_seats - ? WHERE trip_id = ? AND available_seats >= ?"
        );
        $update_seats->bind_param("iii", $number_of_seats, $trip_id, $number_of_seats);
        if (!$update_seats->execute()) {
            throw new Exception("Failed to update available seats: " . $conn->error);
        }
        if ($conn->affected_rows == 0) {
            throw new Exception("Not enough seats available for this trip");
        }
        $update_seats->close();

        // 6. Clear session
        unset($_SESSION['pending_booking']);
        unset($_SESSION['temp_booking_ref']);

        $conn->commit();

        // Return success with ticket_id and booking_id
        echo json_encode([
            'success' => true,
            'booking_id' => $booking_id,
            'ticket_id' => $ticket_id,
            'message' => 'Booking and ticket created successfully'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle regular form submission (not AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // Store in session and redirect to confirm page
    $_SESSION['pending_booking'] = [
        'customer_id' => $userId,
        'trip_id' => $trip_id,
        'number_of_seats' => $number_of_seats,
        'passenger_name' => trim($firstname . ' ' . $lastname),
        'passenger_phone' => $phone,
        'passenger_email' => $email,
        'bus_plaque' => $bus_plaque,
        'bus_name' => $bus_name,
        'route_name' => $route_name,
        'travel_date' => $travel_date,
        'total_amount' => $total_amount,
        'price_per_seat' => $price,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'contact' => $phone,
        'email' => $email
    ];
    $_SESSION['temp_booking_ref'] = 'TEMP_' . time() . '_' . rand(1000, 9999);

    // Redirect to confirm page
    header("Location: confirm_booking.php");
    exit;
}

// Get ticket stats
$total_tickets = 0;
$checked_tickets = 0;
$unchecked_tickets = 0;

$total_result = $conn->query("SELECT COUNT(*) as total FROM tickets");
if ($total_result) {
    $total_tickets = $total_result->fetch_assoc()['total'];
}

$checked_result = $conn->query("SELECT COUNT(*) as total FROM tickets WHERE checked = 'yes'");
if ($checked_result) {
    $checked_tickets = $checked_result->fetch_assoc()['total'];
}

$unchecked_result = $conn->query("SELECT COUNT(*) as total FROM tickets WHERE checked = 'no'");
if ($unchecked_result) {
    $unchecked_tickets = $unchecked_result->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftPass | Confirm Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1f4ed8;
            --primary-dark: #102a68;
            --accent: #22c55e;
            --accent-soft: #dcfce7;
            --surface: rgba(255, 255, 255, 0.95);
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(148, 163, 184, 0.24);
            --shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #111827 45%, #1e3a8a 100%);
            color: var(--text);
        }

        .page-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 270px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(14px);
            padding: 28px 18px;
            border-right: 1px solid var(--border);
            box-shadow: 8px 0 25px rgba(2, 6, 23, 0.08);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 8px 20px;
            margin-bottom: 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), #3b82f6);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.25);
        }

        .brand h4 { margin: 0; font-weight: 700; color: var(--primary-dark); }
        .brand p { margin: 2px 0 0; font-size: 0.86rem; color: var(--muted); }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            margin-bottom: 8px;
            border-radius: 12px;
            color: #334155;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary), #2563eb);
            color: white;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.2);
        }

        .main-content {
            flex: 1;
            padding: 28px 32px 36px;
        }

        .topbar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 24px 28px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }

        .eyebrow {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #166534;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }

        .topbar h2 {
            margin: 0 0 6px;
            color: var(--primary-dark);
            font-size: 1.6rem;
            font-weight: 800;
        }

        .topbar p { margin: 0; color: var(--muted); }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 16px;
            background: linear-gradient(135deg, #eff6ff, #f8fafc);
            border: 1px solid #dbeafe;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #60a5fa);
            color: white;
            display: grid;
            place-items: center;
            font-weight: 700;
        }

        .glass-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 22px;
            box-shadow: var(--shadow);
            margin-bottom: 18px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 18px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            color: var(--primary-dark);
            font-weight: 800;
        }

        .section-title i { color: var(--primary); font-size: 1.05rem; }

        .detail-list { display: grid; gap: 10px; }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-row:last-child { border-bottom: 0; }
        .detail-label { color: var(--muted); font-weight: 600; }
        .detail-value { font-weight: 700; color: var(--text); text-align: right; }

        .price-box {
            padding: 16px;
            background: linear-gradient(135deg, #eff6ff, #f8fafc);
            border-radius: 16px;
            border: 1px solid #dbeafe;
        }

        .price-total {
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-top: 6px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #166534;
            font-weight: 700;
            font-size: 0.78rem;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .payment-option {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #fff;
        }

        .payment-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            border-color: #93c5fd;
        }

        .payment-option.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, #eff6ff, #f8fafc);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.12);
        }

        .payment-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .payment-icon.momo { background: #fee2e2; color: #dc2626; }
        .payment-icon.airtel { background: #fef3c7; color: #d97706; }

        .payment-option h6 { margin: 0 0 4px; font-weight: 800; color: var(--primary-dark); }
        .payment-option p { margin: 0 0 12px; color: var(--muted); font-size: 0.9rem; }
        .form-check { margin: 0; }
        .form-check-input { cursor: pointer; }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #2563eb);
            border: 0;
            border-radius: 14px;
            padding: 0.8rem 1.3rem;
            font-weight: 700;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.23);
        }

        .btn-outline-secondary {
            border-radius: 14px;
            padding: 0.8rem 1rem;
            font-weight: 600;
        }

        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        #paymentStatus { margin-top: 14px; }
        .alert { border-radius: 16px; border: 0; }

        .ticket-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 12px;
        }

        .ticket-stat-box {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .ticket-stat-box .number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .ticket-stat-box .label {
            font-size: 0.8rem;
            color: var(--muted);
            font-weight: 600;
        }

        @media (max-width: 992px) {
            .grid-2 { grid-template-columns: 1fr; }
            .payment-options { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .page-shell { flex-direction: column; }
            .sidebar { width: 100%; border-right: 0; border-bottom: 1px solid var(--border); }
            .main-content { padding: 18px; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .footer-actions { flex-direction: column; align-items: stretch; }
            .footer-actions .btn { width: 100%; }
            .ticket-stats { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <div class="page-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon"><i class="fas fa-bus"></i></div>
                <div>
                    <h4>SwiftPass</h4>
                    <p>Secure travel booking</p>
                </div>
            </div>
            <a href="homepage.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="bookingpage.php" class="nav-link"><i class="fas fa-ticket-alt"></i> Bookings</a>
            <a href="setting.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
            <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Log Out</a>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div>
                    <div class="eyebrow">Secure checkout</div>
                    <h2>Confirm your booking</h2>
                    <p>Review your trip details, choose a payment method, and complete your reservation.</p>
                </div>
                <div class="user-chip">
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></div>
                    <div>
                        <div class="fw-bold text-dark">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                        <small class="text-muted">Passenger</small>
                    </div>
                </div>
            </header>

            <div class="grid-2">
                <div class="glass-card">
                    <div class="section-title"><i class="fas fa-user"></i> Passenger details</div>
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Full name</span><span class="detail-value"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?php echo htmlspecialchars($phone); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($email); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Seats</span><span class="detail-value"><?php echo htmlspecialchars($number_of_seats); ?> seat(s)</span></div>
                    </div>
                </div>

                <div class="glass-card">
                    <div class="section-title"><i class="fas fa-route"></i> Trip summary</div>
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Bus</span><span class="detail-value"><?php echo htmlspecialchars($bus_name ?? ''); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Plate</span><span class="detail-value"><?php echo htmlspecialchars($bus_plaque ?? ''); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Route</span><span class="detail-value"><?php echo htmlspecialchars($route_name ?? ''); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Travel date</span><span class="detail-value"><?php echo !empty($travel_date) ? date('M j, Y', strtotime($travel_date)) : '-'; ?></span></div>
                    </div>

                    <div class="price-box mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="detail-label">Amount due</span>
                            <span class="badge"><i class="fas fa-shield-alt me-1"></i> Secure</span>
                        </div>
                        <div class="price-total"><?php echo number_format($total_amount); ?> FRW</div>
                        <small class="text-muted">Price per seat: <?php echo number_format($price); ?> FRW</small>
                    </div>
                </div>
            </div>

            <!-- Ticket Statistics -->
            <div class="glass-card">
                <div class="section-title"><i class="fas fa-ticket-alt"></i> Ticket Statistics</div>
                <div class="ticket-stats">
                    <div class="ticket-stat-box">
                        <div class="number"><?php echo $total_tickets; ?></div>
                        <div class="label">Total Tickets</div>
                    </div>
                    <div class="ticket-stat-box">
                        <div class="number text-success"><?php echo $checked_tickets; ?></div>
                        <div class="label">Checked In</div>
                    </div>
                    <div class="ticket-stat-box">
                        <div class="number text-warning"><?php echo $unchecked_tickets; ?></div>
                        <div class="label">Pending Check-in</div>
                    </div>
                </div>
            </div>

            <div class="glass-card">
                <div class="section-title"><i class="fas fa-credit-card"></i> Choose payment method</div>
                <form id="paymentForm" method="POST">
                    <input type="hidden" name="firstname" value="<?php echo htmlspecialchars($firstname); ?>">
                    <input type="hidden" name="lastname" value="<?php echo htmlspecialchars($lastname); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId); ?>">
                    <input type="hidden" name="trip_id" value="<?php echo htmlspecialchars($trip_id); ?>">
                    <input type="hidden" name="number_of_seats" value="<?php echo htmlspecialchars($number_of_seats); ?>">
                    <input type="hidden" name="contact" value="<?php echo htmlspecialchars($phone); ?>">
                    <input type="hidden" name="amount" value="<?php echo htmlspecialchars($total_amount); ?>">
                    <input type="hidden" name="tempBookingRef" value="<?php echo htmlspecialchars($_SESSION['temp_booking_ref'] ?? ''); ?>">
                    <input type="hidden" name="action" value="create_booking">

                    <div class="payment-options">
                        <div class="payment-option" onclick="selectPayment('momo')">
                            <div class="payment-icon momo"><i class="fas fa-mobile-alt"></i></div>
                            <h6>MoMo Pay</h6>
                            <p>Pay instantly with your mobile money account.</p>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="momo" id="momo" required>
                                <label class="form-check-label" for="momo">Select MoMo Pay</label>
                            </div>
                        </div>

                        <div class="payment-option" onclick="selectPayment('airtel')">
                            <div class="payment-icon airtel"><i class="fas fa-wifi"></i></div>
                            <h6>Airtel Money</h6>
                            <p>Use Airtel Money for a fast, secure transaction.</p>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="airtel" id="airtel" required>
                                <label class="form-check-label" for="airtel">Select Airtel Money</label>
                            </div>
                        </div>
                    </div>

                    <div id="paymentStatus" style="display:none;">
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm me-3" role="status"></div>
                                <div>
                                    <h6 class="mb-1" id="statusTitle">Processing payment</h6>
                                    <p class="mb-0 small" id="statusMessage">Creating your booking...</p>
                                    <div class="progress mt-2" style="height:4px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="statusProgress" style="width:0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="footer-actions">
                        <a href="bookingpage.php?trip_id=<?php echo $trip_id; ?>&price=<?php echo $price; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to edit
                        </a>
                        <button type="submit" class="btn btn-primary px-4" id="confirmBtn" disabled>
                            <i class="fas fa-lock me-2"></i> Confirm & pay <?php echo number_format($total_amount); ?> FRW
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function selectPayment(method) {
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
            document.querySelector(`[onclick="selectPayment('${method}')"]`).classList.add('selected');
            document.getElementById(method).checked = true;
            document.getElementById('confirmBtn').disabled = false;
        }

        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) selectPayment(radio.value);
            });
        });

        document.getElementById('paymentForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const confirmBtn = document.getElementById('confirmBtn');
            confirmBtn.disabled = true;

            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                showError('Please select a payment method');
                confirmBtn.disabled = false;
                return;
            }

            const formData = new FormData(document.getElementById('paymentForm'));
            showPaymentStatus('Processing Payment', 'Creating your booking and ticket...', 30);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const text = await response.text();
                    console.error('Server response:', text);
                    throw new Error('Server error: ' + response.status);
                }

                const result = await response.json();

                if (result.success) {
                    showPaymentStatus('Booking Confirmed!', 'Your ticket has been created. Redirecting...', 100);
                    setTimeout(() => {
                        window.location.href = `ticket_download.php?booking_id=${result.booking_id}`;
                    }, 1500);
                } else {
                    throw new Error(result.error || 'Booking creation failed');
                }

            } catch (error) {
                console.error('Error:', error);
                showError(error.message || 'An error occurred. Please try again.');
                confirmBtn.disabled = false;
            }
        });

        function showPaymentStatus(title, message, progress) {
            const statusDiv = document.getElementById('paymentStatus');
            statusDiv.style.display = 'block';
            document.getElementById('statusTitle').textContent = title;
            document.getElementById('statusMessage').textContent = message;
            document.getElementById('statusProgress').style.width = progress + '%';
        }

        function showError(message) {
            const statusDiv = document.getElementById('paymentStatus');
            statusDiv.innerHTML = `
                <div class="alert alert-danger">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3 fa-2x"></i>
                        <div>
                            <h6 class="mb-1">Payment Error</h6>
                            <p class="mb-0 small">${message}</p>
                        </div>
                    </div>
                </div>
            `;
            statusDiv.style.display = 'block';
            document.getElementById('confirmBtn').disabled = false;
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>