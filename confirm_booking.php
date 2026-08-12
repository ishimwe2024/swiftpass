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

// Get POST values from booking form
$number_of_seats = isset($_POST['seatCount']) ? (int) $_POST['seatCount'] : 1;
$bus_plaque = $_POST['plate_nbr'] ?? '';
$bus_name = $_POST['bus_name'] ?? '';
$route_name = $_POST['route_name'] ?? '';
$price = isset($_POST['price']) ? (float) $_POST['price'] : 0;
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

// ===== HANDLE BOOKING CREATION (AJAX) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');

    try {
        $user_id = (int) $_POST['user_id'];
        $trip_id = (int) $_POST['trip_id'];
        $number_of_seats = (int) $_POST['number_of_seats'];
        $payment_method = $conn->real_escape_string($_POST['payment_method']);
        $amount = (float) $_POST['amount'];
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

        // 3. Create payment record (status = 'completed' because MoMo already succeeded)
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

        // 4. Create ticket record
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

// ===== HANDLE REGULAR FORM SUBMISSION (first step) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // Store in session and redirect to confirm page (already here)
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

    // Redirect to confirm page (itself) to avoid resubmission
    header("Location: confirm_booking.php");
    exit;
}

// ===== GET TICKET STATS =====
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
            --mtn-yellow: #FFCC00;
            --airtel-red: #E00000;
        }

        * {
            box-sizing: border-box;
        }

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

        .brand h4 {
            margin: 0;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .brand p {
            margin: 2px 0 0;
            font-size: 0.86rem;
            color: var(--muted);
        }

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

        .topbar p {
            margin: 0;
            color: var(--muted);
        }

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

        .section-title i {
            color: var(--primary);
            font-size: 1.05rem;
        }

        .detail-list {
            display: grid;
            gap: 10px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-row:last-child {
            border-bottom: 0;
        }

        .detail-label {
            color: var(--muted);
            font-weight: 600;
        }

        .detail-value {
            font-weight: 700;
            color: var(--text);
            text-align: right;
        }

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
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #fff;
            position: relative;
        }

        .payment-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }

        .payment-option.selected {
            border-color: var(--mtn-yellow);
            background: #fffde7;
            box-shadow: 0 12px 24px rgba(255, 204, 0, 0.15);
        }

        .payment-option.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            filter: grayscale(0.4);
        }

        .payment-option.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .payment-option .coming-soon {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #dc3545;
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 12px;
            text-transform: uppercase;
        }

        .payment-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            background: #f1f5f9;
            color: #1e293b;
            padding: 6px;
        }

        .payment-icon.mtn {
            background: var(--mtn-yellow);
        }

        .payment-icon.mtn img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .payment-icon.airtel img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .payment-icon.airtel {
            background: var(--airtel-red);
            color: #ffffff;
            font-weight: 800;
            font-size: 1.2rem;
        }

        .payment-option h6 {
            margin: 0 0 4px;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .payment-option p {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .form-check {
            margin: 0;
        }

        .form-check-input {
            cursor: pointer;
        }

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

        #paymentStatus {
            margin-top: 14px;
        }

        .alert {
            border-radius: 16px;
            border: 0;
        }

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
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .payment-options {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-shell {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .main-content {
                padding: 18px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .footer-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .footer-actions .btn {
                width: 100%;
            }

            .ticket-stats {
                grid-template-columns: 1fr;
            }
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
                        <div class="fw-bold text-dark">Welcome,
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                        <small class="text-muted">Passenger</small>
                    </div>
                </div>
            </header>

            <div class="grid-2">
                <div class="glass-card">
                    <div class="section-title"><i class="fas fa-user"></i> Passenger details</div>
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Full name</span><span
                                class="detail-value"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></span>
                        </div>
                        <div class="detail-row"><span class="detail-label">Phone</span><span
                                class="detail-value"><?php echo htmlspecialchars($phone); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Email</span><span
                                class="detail-value"><?php echo htmlspecialchars($email); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Seats</span><span
                                class="detail-value"><?php echo htmlspecialchars($number_of_seats); ?> seat(s)</span>
                        </div>
                    </div>
                </div>

                <div class="glass-card">
                    <div class="section-title"><i class="fas fa-route"></i> Trip summary</div>
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Bus</span><span
                                class="detail-value"><?php echo htmlspecialchars($bus_name ?? ''); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Plate</span><span
                                class="detail-value"><?php echo htmlspecialchars($bus_plaque ?? ''); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Route</span><span
                                class="detail-value"><?php echo htmlspecialchars($route_name ?? ''); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Travel date</span><span
                                class="detail-value"><?php echo !empty($travel_date) ? date('M j, Y', strtotime($travel_date)) : '-'; ?></span>
                        </div>
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
            <!-- Payment Form -->
            <div class="glass-card">
                <div class="section-title"><i class="fas fa-credit-card"></i> Choose payment method</div>
                <form id="paymentForm">
                    <!-- Hidden fields for booking data -->
                    <input type="hidden" name="firstname" id="firstname"
                        value="<?php echo htmlspecialchars($firstname); ?>">
                    <input type="hidden" name="lastname" id="lastname"
                        value="<?php echo htmlspecialchars($lastname); ?>">
                    <input type="hidden" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="user_id" id="user_id" value="<?php echo htmlspecialchars($userId); ?>">
                    <input type="hidden" name="trip_id" id="trip_id" value="<?php echo htmlspecialchars($trip_id); ?>">
                    <input type="hidden" name="number_of_seats" id="number_of_seats"
                        value="<?php echo htmlspecialchars($number_of_seats); ?>">
                    <input type="hidden" name="contact" id="contact" value="<?php echo htmlspecialchars($phone); ?>">
                    <input type="hidden" name="amount" id="amount"
                        value="<?php echo htmlspecialchars($total_amount); ?>">
                    <input type="hidden" name="temp_booking_ref" id="temp_booking_ref"
                        value="<?php echo htmlspecialchars($_SESSION['temp_booking_ref'] ?? ''); ?>">

                    <div class="payment-options">
                        <!-- MTN MoMo - Active with Logo -->
                        <div class="payment-option" onclick="selectPayment('momo')">
                            <div class="payment-icon mtn">
                                <img src="assets/img/mtn-mobile.png" alt="MTN Mobile Money">
                            </div>
                            <h6>MoMo Pay</h6>
                            <p>Pay instantly with MTN Mobile Money.</p>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="momo"
                                    id="momo" required>
                                <label class="form-check-label" for="momo">Select MTN MoMo</label>
                            </div>
                        </div>

                        <!-- Airtel Money - Disabled (Coming soon) -->
                        <div class="payment-option disabled" onclick="showAirtelUnavailable()">
                            <div class="coming-soon">Coming soon</div>
                            <div class="payment-icon airtel">
                                <img src="assets/img/airtel.png" alt="Airtel Money">
                            </div>
                            <h6>Airtel Money</h6>
                            <p>Pay with Airtel Money (coming soon).</p>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="airtel"
                                    id="airtel" disabled>
                                <label class="form-check-label text-muted" for="airtel">Unavailable</label>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Status Display -->
                    <div id="paymentStatus" style="display:none;">
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm me-3" role="status"></div>
                                <div>
                                    <h6 class="mb-1" id="statusTitle">Processing payment</h6>
                                    <p class="mb-0 small" id="statusMessage">Creating your booking...</p>
                                    <div class="progress mt-2" style="height:4px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                                            id="statusProgress" style="width:0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="footer-actions">
                        <a href="bookingpage.php?trip_id=<?php echo $trip_id; ?>&price=<?php echo $price; ?>"
                            class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to edit
                        </a>
                        <button type="submit" class="btn btn-primary px-4" id="confirmBtn" disabled>
                            <i class="fas fa-lock me-2"></i> Confirm & pay <?php echo number_format($total_amount); ?>
                            FRW
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // ========== UI Helpers ==========
        function selectPayment(method) {
            // Only allow 'momo' because Airtel is disabled
            if (method !== 'momo') {
                showAirtelUnavailable();
                return;
            }
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
            document.querySelector(`[onclick="selectPayment('${method}')"]`).classList.add('selected');
            document.getElementById(method).checked = true;
            document.getElementById('confirmBtn').disabled = false;
        }

        function showAirtelUnavailable() {
            alert('Airtel Money is not available yet. Please use MTN MoMo.');
            // Uncheck any radio and disable confirm
            document.getElementById('airtel').checked = false;
            document.getElementById('momo').checked = false;
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
            document.getElementById('confirmBtn').disabled = true;
        }

        // Click on disabled Airtel box triggers the alert
        document.querySelectorAll('.payment-option.disabled').forEach(el => {
            el.addEventListener('click', function (e) {
                e.stopPropagation();
                showAirtelUnavailable();
            });
        });

        // Enable click on the MTN option box
        document.querySelector('.payment-option:not(.disabled)')?.addEventListener('click', function () {
            selectPayment('momo');
        });

        function showPaymentStatus(title, message, progress, type = 'info') {
            const statusDiv = document.getElementById('paymentStatus');
            statusDiv.style.display = 'block';
            document.getElementById('statusTitle').textContent = title;
            document.getElementById('statusMessage').textContent = message;
            document.getElementById('statusProgress').style.width = Math.min(progress, 100) + '%';

            const alert = statusDiv.querySelector('.alert');
            alert.className = 'alert ';
            if (type === 'info') alert.className += 'alert-info';
            else if (type === 'warning') alert.className += 'alert-warning';
            else if (type === 'success') alert.className += 'alert-success';
            else if (type === 'danger') alert.className += 'alert-danger';
        }

        function showError(message) {
            const statusDiv = document.getElementById('paymentStatus');
            statusDiv.innerHTML = `
                <div class="alert alert-danger">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3"></i>
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

        // ========== Main Payment Flow ==========
        document.getElementById('paymentForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const confirmBtn = document.getElementById('confirmBtn');
            confirmBtn.disabled = true;

            // Get payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                showError('Please select a payment method.');
                confirmBtn.disabled = false;
                return;
            }

            // Only MTN is allowed
            if (paymentMethod.value !== 'momo') {
                showError('Only MTN MoMo is currently supported.');
                confirmBtn.disabled = false;
                return;
            }

            // Gather booking data
            const payload = {
                user_id: document.getElementById('user_id').value,
                trip_id: document.getElementById('trip_id').value,
                number_of_seats: parseInt(document.getElementById('number_of_seats').value),
                contact: document.getElementById('contact').value,
                amount: parseFloat(document.getElementById('amount').value),
                payment_method: paymentMethod.value,
                temp_booking_ref: document.getElementById('temp_booking_ref').value,
                firstname: document.getElementById('firstname').value,
                lastname: document.getElementById('lastname').value,
                email: document.getElementById('email').value
            };

            // Initiate MoMo payment
            await initiateMoMoPayment(payload, confirmBtn);
        });

        // ========== MoMo Payment Initiation ==========
        async function initiateMoMoPayment(payload, confirmBtn) {
            showPaymentStatus('Initiating Payment', 'Sending request to MTN MoMo...', 10, 'info');

            try {
                const response = await fetch('http://localhost:3000/process_payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        phoneNumber: payload.contact,
                        amount: payload.amount
                    })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Payment request failed');
                if (!data.referenceId) throw new Error('No reference ID received from payment gateway');

                console.log('Payment initiated, referenceId:', data.referenceId);
                showPaymentStatus('Payment Initiated', 'Please check your phone and approve the payment...', 30, 'warning');

                // Start polling for status
                await pollPaymentStatus(data.referenceId, payload, confirmBtn);

            } catch (error) {
                console.error('Payment initiation error:', error);
                showError(error.message || 'Failed to initiate payment. Please try again.');
                confirmBtn.disabled = false;
            }
        }

        // ========== Polling ==========
        async function pollPaymentStatus(referenceId, payload, confirmBtn) {
            let attempts = 0;
            const maxAttempts = 30; // 30 * 5s = 150s timeout

            const checkStatus = async () => {
                attempts++;
                try {
                    const response = await fetch(`http://localhost:3000/payment_status/${referenceId}`);
                    if (!response.ok) throw new Error('Failed to check payment status');

                    const statusData = await response.json();
                    console.log('Status check:', statusData);

                    const progress = Math.min(30 + attempts * 2, 90);
                    showPaymentStatus('Checking Status', `Waiting for confirmation... (${attempts}/${maxAttempts})`, progress, 'warning');

                    if (statusData.status === 'SUCCESSFUL') {
                        showPaymentStatus('Payment Successful!', 'Creating your booking...', 95, 'success');
                        await finalizeBooking(payload);
                        return;
                    } else if (statusData.status === 'FAILED') {
                        throw new Error('Payment was declined or failed.');
                    } else {
                        // Still PENDING – continue polling
                        if (attempts >= maxAttempts) {
                            throw new Error('Payment timeout. Please check your phone and try again.');
                        }
                        setTimeout(checkStatus, 5000);
                    }
                } catch (error) {
                    console.error('Polling error:', error);
                    showError(error.message || 'Error checking payment status. Please try again.');
                    confirmBtn.disabled = false;
                }
            };

            await checkStatus();
        }

        // ========== Finalize Booking (AJAX to same PHP) ==========
        async function finalizeBooking(payload) {
            try {
                const formData = new URLSearchParams();
                formData.append('action', 'create_booking');
                formData.append('user_id', payload.user_id);
                formData.append('trip_id', payload.trip_id);
                formData.append('number_of_seats', payload.number_of_seats);
                formData.append('payment_method', payload.payment_method);
                formData.append('amount', payload.amount);
                formData.append('firstname', payload.firstname);
                formData.append('lastname', payload.lastname);
                formData.append('email', payload.email);
                formData.append('contact', payload.contact);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Booking creation failed');
                }

                showPaymentStatus('Booking Confirmed!', 'Your ticket is ready.', 100, 'success');
                setTimeout(() => {
                    window.location.href = `ticket_download.php?booking_id=${result.booking_id}`;
                }, 1500);
            } catch (error) {
                console.error('Booking creation error:', error);
                showError('Payment successful, but booking creation failed. Please contact support.');
                document.getElementById('confirmBtn').disabled = false;
            }
        }

        // Initialize: disable confirm until selection
        document.addEventListener('DOMContentLoaded', function () {
            // Ensure Airtel is disabled
            document.getElementById('airtel').disabled = true;
        });
    </script>
</body>

</html>
<?php
$conn->close();
?>