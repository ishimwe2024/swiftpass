<?php
session_start();
include('connection.php');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Get data from POST (already stored in session)
$passenger_name = $_POST['firstName'] . ' ' . $_POST['lastName'];
$passenger_phone = $_POST['phone'];
$passenger_email = $_POST['email'];
$number_of_seats = $_POST['seatCount'];
$bus_plaque = $_POST['plate_nbr'];
$bus_name = $_POST['bus_name'];
$route_name = $_POST['route_name'];
$price = $_POST['price'];
$travel_date = $_POST['travel_date'];
$total_amount = $price * $number_of_seats;
$trip_id = $_POST['trip_id'];

// Store booking details in session (no DB insert yet)
$_SESSION['pending_booking'] = [
    'customer_id' => $userId,
    'trip_id' => $trip_id,
    'number_of_seats' => $number_of_seats,
    'passenger_name' => $passenger_name,
    'passenger_phone' => $passenger_phone,
    'passenger_email' => $passenger_email,
    'bus_plaque' => $bus_plaque,
    'bus_name' => $bus_name,
    'route_name' => $route_name,
    'travel_date' => $travel_date,
    'total_amount' => $total_amount,
    'price_per_seat' => $price
];
$_SESSION['temp_booking_ref'] = 'TEMP_' . time() . '_' . rand(1000, 9999);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftPass | Confirm Booking & Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Your existing CSS (keep as is) -->
    <style>
        :root {
            --primary: #1f4ed8;
            --primary-dark: #102a68;
            --accent: #22c55e;
            --accent-soft: #dcfce7;
            --surface: rgba(255, 255, 255, 0.95);
            --surface-2: rgba(248, 250, 252, 0.96);
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

        .steps-row {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .step-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: rgba(255,255,255,0.9);
            color: #475569;
            border-radius: 999px;
            font-size: 0.92rem;
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.45);
            box-shadow: 0 10px 20px rgba(15,23,42,0.08);
        }

        .step-pill.active {
            background: linear-gradient(135deg, var(--primary), #2563eb);
            color: white;
        }

        .step-pill span {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 0.82rem;
            background: rgba(255,255,255,0.25);
        }

        .glass-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 22px;
            box-shadow: var(--shadow);
            margin-bottom: 18px;
        }

        .grid-2 { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 18px; }

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

        .payment-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }

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
            <a href="bookingpage.php" class="nav-link active"><i class="fas fa-ticket-alt"></i> Bookings</a>
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

            <div class="steps-row">
                <div class="step-pill active"><span>1</span> Review</div>
                <div class="step-pill active"><span>2</span> Pay securely</div>
                <div class="step-pill"><span>3</span> Receive ticket</div>
            </div>

            <div class="grid-2">
                <div class="glass-card">
                    <div class="section-title"><i class="fas fa-user"></i> Passenger details</div>
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Full name</span><span class="detail-value"><?php echo htmlspecialchars($passenger_name); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?php echo htmlspecialchars($passenger_phone); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($passenger_email); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Seats</span><span class="detail-value"><?php echo htmlspecialchars($number_of_seats); ?> seat(s)</span></div>
                    </div>
                </div>

                <div class="glass-card">
                    <div class="section-title"><i class="fas fa-route"></i> Trip summary</div>
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Bus</span><span class="detail-value"><?php echo htmlspecialchars($bus_name); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Plate</span><span class="detail-value"><?php echo htmlspecialchars($bus_plaque); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Route</span><span class="detail-value"><?php echo htmlspecialchars($route_name); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Travel date</span><span class="detail-value"><?php echo date('M j, Y', strtotime($travel_date)); ?></span></div>
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

            <div class="glass-card">
                <div class="section-title"><i class="fas fa-credit-card"></i> Choose payment method</div>
                <form id="paymentForm">
                    <input type="hidden" name="firstname" id="firstname" value="<?php echo htmlspecialchars($_POST['firstName']); ?>">
                    <input type="hidden" name="lastname" id="lastname" value="<?php echo htmlspecialchars($_POST['lastName']); ?>">
                    <input type="hidden" name="email" id="email" value="<?php echo htmlspecialchars($passenger_email); ?>">
                    <input type="hidden" name="user_id" id="user_id" value="<?php echo htmlspecialchars($userId); ?>">
                    <input type="hidden" name="trip_id" id="trip_id" value="<?php echo htmlspecialchars($trip_id); ?>">
                    <input type="hidden" name="nbr_of_seats" id="nbr_of_seats" value="<?php echo htmlspecialchars($number_of_seats); ?>">
                    <input type="hidden" name="phoneNumber" id="phoneNumber" value="<?php echo htmlspecialchars($passenger_phone); ?>">
                    <input type="hidden" name="amount" id="amount" value="<?php echo htmlspecialchars($total_amount); ?>">
                    <input type="hidden" name="tempBookingRef" id="tempBookingRef" value="<?php echo htmlspecialchars($_SESSION['temp_booking_ref']); ?>">

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
                                <div class="spinner-border spinner-border-sm me-3" role="status" id="statusSpinner"></div>
                                <div>
                                    <h6 class="mb-1" id="statusTitle">Processing payment</h6>
                                    <p class="mb-0 small" id="statusMessage">Initializing payment request...</p>
                                    <div class="progress mt-2" style="height:4px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="statusProgress" style="width:0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="footer-actions">
                        <a href="bookingpage.php?bus_plaque=<?php echo $bus_plaque; ?>&route=<?php echo urlencode($route_name); ?>&price=<?php echo $price; ?>&bus_name=<?php echo urlencode($bus_name); ?>" class="btn btn-outline-secondary">
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
        // ======== PAYMENT LOGIC ========
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

        // Main form submit
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

            // Gather data
            const payload = {
                user_id: document.getElementById('user_id').value,
                trip_id: document.getElementById('trip_id').value,
                number_of_seats: parseInt(document.getElementById('nbr_of_seats').value),
                phoneNumber: document.getElementById('phoneNumber').value,
                amount: parseFloat(document.getElementById('amount').value),
                payment_method: paymentMethod.value,
                temp_booking_ref: document.getElementById('tempBookingRef').value,
                firstname: document.getElementById('firstname').value,
                lastname: document.getElementById('lastname').value,
                email: document.getElementById('email').value
            };

            showPaymentStatus('Initiating Payment', 'Sending request...', 10);

            try {
                const response = await fetch('http://localhost:3000/process_payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        phoneNumber: payload.phoneNumber,
                        amount: payload.amount
                    })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Payment request failed');

                if (!data.referenceId) throw new Error('No reference ID received');

                console.log('Payment initiated, referenceId:', data.referenceId);
                showPaymentStatus('Payment Initiated', 'Please check your phone to approve...', 40);

                // Start polling
                await pollPaymentStatus(data.referenceId, payload);

            } catch (error) {
                console.error('Payment error:', error);
                showError(error.message || 'An error occurred');
                confirmBtn.disabled = false;
            }
        });

        // Polling function
        async function pollPaymentStatus(referenceId, payload) {
            let attempts = 0;
            const maxAttempts = 30;

            const check = async () => {
                attempts++;
                try {
                    // Build URL with pending booking data (will be used by PHP on success)
                    const params = new URLSearchParams({
                        user_id: payload.user_id,
                        trip_id: payload.trip_id,
                        number_of_seats: payload.number_of_seats,
                        payment_method: payload.payment_method,
                        amount: payload.amount,
                        firstname: payload.firstname,
                        lastname: payload.lastname,
                        email: payload.email,
                        contact: payload.phoneNumber,
                        temp_booking_ref: payload.temp_booking_ref
                    });

                    const response = await fetch(`http://localhost:3000/payment_status/${referenceId}?${params.toString()}`);
                    if (!response.ok) throw new Error('Failed to check status');

                    const statusData = await response.json();
                    console.log('Status check:', statusData);

                    const progress = Math.min(40 + attempts * 2, 90);
                    showPaymentStatus('Checking Status', `Waiting for confirmation... (${attempts}/${maxAttempts})`, progress);

                    if (statusData.status === 'SUCCESSFUL') {
                        showPaymentStatus('Payment Successful!', 'Creating booking...', 95);
                        // Now call your PHP backend to create the booking
                        await finalizeBooking(payload);
                        return;
                    } else if (statusData.status === 'FAILED') {
                        throw new Error('Payment declined or failed');
                    } else {
                        // PENDING or other – continue polling
                        if (attempts >= maxAttempts) {
                            throw new Error('Payment timeout. Please try again.');
                        }
                        setTimeout(check, 5000);
                    }
                } catch (error) {
                    console.error('Polling error:', error);
                    showError(error.message || 'Status check failed');
                    document.getElementById('confirmBtn').disabled = false;
                }
            };

            await check();
        }

        // Finalize booking (call your PHP script)
        async function finalizeBooking(payload) {
            try {
                const response = await fetch('create_booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'Booking creation failed');

                showPaymentStatus('Booking Confirmed!', 'Your ticket is ready.', 100);
                // Redirect to ticket page
                setTimeout(() => {
                    window.location.href = `ticket_download.php?booking_id=${result.booking_id}`;
                }, 1500);
            } catch (error) {
                console.error('Booking creation error:', error);
                showError('Payment successful but booking creation failed. Please contact support.');
                document.getElementById('confirmBtn').disabled = false;
            }
        }

        // UI helpers
        function showPaymentStatus(title, message, progress) {
            const statusDiv = document.getElementById('paymentStatus');
            statusDiv.style.display = 'block';
            document.getElementById('statusTitle').textContent = title;
            document.getElementById('statusMessage').textContent = message;
            document.getElementById('statusProgress').style.width = progress + '%';
            // Adjust alert colour
            const alert = statusDiv.querySelector('.alert');
            alert.className = 'alert ';
            if (progress < 40) alert.className += 'alert-info';
            else if (progress < 80) alert.className += 'alert-warning';
            else alert.className += 'alert-success';
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
    </script>
</body>
</html>
<?php
$conn->close();
?>