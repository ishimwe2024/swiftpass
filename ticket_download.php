<?php
session_start();
include('connection.php');

// Get ticket ID from URL
$ticket_id = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : '';
$booking_id = isset($_GET['booking_id']) ? $_GET['booking_id'] : '';

// If no ticket ID or booking ID, redirect to home
if (empty($ticket_id) && empty($booking_id)) {
    header("Location: homepage.php");
    exit;
}

// Fetch complete ticket details with all related information
$ticket_details = [];
$booking_details = [];
$passenger_details = [];
$payment_details = [];
$bus_details = [];
$trip_details = [];

if (!empty($ticket_id) || !empty($booking_id)) {
    if (!empty($ticket_id)) {
        $stmt = $conn->prepare("
            SELECT 
                t.ticket_id, t.booking_id, t.checked, t.checked_at, t.created_at as ticket_created,
                b.booking_id, b.customer_id, b.trip_id, b.number_of_seats, b.booking_date,
                c.firstname, c.lastname, c.contact, c.email,
                p.amount, p.payment_method, p.transaction_id, p.time_paid,
                bus.model, bus.plates_number, 
                r.departure, r.destination, r.price_per_seat,
                trip.departure_datetime, trip.estimated_arrival
            FROM tickets t 
            LEFT JOIN bookings b ON t.booking_id = b.booking_id 
            LEFT JOIN customers c ON b.customer_id = c.customer_id 
            LEFT JOIN payments p ON b.booking_id = p.booking_id 
            LEFT JOIN trips trip ON b.trip_id = trip.trip_id
            LEFT JOIN buses bus ON trip.bus_id = bus.bus_id
            LEFT JOIN routes r ON trip.route_id = r.route_id
            WHERE t.ticket_id = ?
        ");
        $stmt->bind_param("s", $ticket_id);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                t.ticket_id, t.booking_id, t.checked, t.checked_at, t.created_at as ticket_created,
                b.booking_id, b.customer_id, b.trip_id, b.number_of_seats, b.booking_date,
                c.firstname, c.lastname, c.contact, c.email,
                p.amount, p.payment_method, p.transaction_id, p.time_paid,
                bus.model, bus.plates_number, 
                r.departure, r.destination, r.price_per_seat,
                trip.departure_datetime, trip.estimated_arrival
            FROM bookings b 
            LEFT JOIN tickets t ON b.booking_id = t.booking_id 
            LEFT JOIN customers c ON b.customer_id = c.customer_id 
            LEFT JOIN payments p ON b.booking_id = p.booking_id 
            LEFT JOIN trips trip ON b.trip_id = trip.trip_id
            LEFT JOIN buses bus ON trip.bus_id = bus.bus_id
            LEFT JOIN routes r ON trip.route_id = r.route_id
            WHERE b.booking_id = ?
        ");
        $stmt->bind_param("i", $booking_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();

        $ticket_details = [
            'ticket_id' => $data['ticket_id'],
            'checked' => $data['checked'],
            'checked_at' => $data['checked_at'],
            'created_at' => $data['ticket_created']
        ];

        $booking_details = [
            'booking_id' => $data['booking_id'],
            'number_of_seats' => $data['number_of_seats'],
            'booking_date' => $data['booking_date']
        ];

        $passenger_details = [
            'name' => $data['firstname'] . ' ' . $data['lastname'],
            'phone' => $data['contact'],
            'email' => $data['email']
        ];

        $payment_details = [
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'transaction_id' => $data['transaction_id'],
            'time_paid' => $data['time_paid']
        ];

        $bus_details = [
            'model' => $data['model'],
            'plates_number' => $data['plates_number']
        ];

        $trip_details = [
            'departure' => $data['departure'],
            'destination' => $data['destination'],
            'departure_datetime' => $data['departure_datetime'],
            'estimated_arrival' => $data['estimated_arrival'],
            'price_per_seat' => $data['price_per_seat']
        ];
    }
    $stmt->close();
}

// If no data found, show error message
if (empty($ticket_details) && empty($booking_details)) {
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ticket Not Found - SwiftPass</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                background: #f8f9fa;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-container {
                text-align: center;
                max-width: 500px;
                padding: 2rem;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
            <h2 class="text-danger">Ticket Not Found</h2>
            <p class="text-muted mb-4">The ticket or booking you're looking for doesn't exist or has been removed.</p>
            <a href="homepage.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Back to Home
            </a>
        </div>
    </body>
    </html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftPass | Bus Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #e8ecf1;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .ticket-wrapper {
            max-width: 800px;
            width: 100%;
        }

        /* Main Ticket Container */
        .bus-ticket {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            position: relative;
        }

        /* Ticket Header - Company Branding */
        .ticket-header {
            background: linear-gradient(135deg, #1a237e, #0d47a1);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #ff6f00;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .company-logo {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.8rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .company-name {
            color: #fff;
        }

        .company-name h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 1px;
        }

        .company-name small {
            opacity: 0.8;
            font-size: 0.8rem;
        }

        .ticket-status {
            background: <?php echo ($ticket_details['checked'] ?? 'no') === 'yes' ? '#e65100' : '#2e7d32'; ?>;
            color: #fff;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Ticket Body */
        .ticket-body {
            padding: 25px 30px;
        }

        /* Route Information - Big Display */
        .route-display {
            background: linear-gradient(135deg, #f5f7fa, #e8ecf1);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid #0d47a1;
        }

        .route-city {
            text-align: center;
        }

        .route-city .city-name {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1a237e;
        }

        .route-city .city-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .route-arrow {
            color: #0d47a1;
            font-size: 2rem;
            padding: 0 15px;
        }

        /* Ticket Grid */
        .ticket-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .ticket-field {
            padding: 10px 0;
            border-bottom: 1px dashed #e0e0e0;
        }

        .ticket-field .label {
            font-size: 0.7rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .ticket-field .value {
            font-size: 1rem;
            font-weight: 600;
            color: #1a237e;
            margin-top: 2px;
        }

        .ticket-field .value-lg {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0d47a1;
        }

        /* Price Section */
        .price-section {
            background: linear-gradient(135deg, #1a237e, #0d47a1);
            border-radius: 12px;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
            margin-top: 10px;
        }

        .price-section .price-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .price-section .price-amount {
            font-size: 1.8rem;
            font-weight: 800;
        }

        .price-section .price-amount small {
            font-size: 1rem;
            font-weight: 400;
            opacity: 0.8;
        }

        /* Bottom Section - QR & Details */
        .ticket-bottom {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
        }

        .qr-section {
            text-align: center;
        }

        .qr-section .qr-label {
            font-size: 0.7rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .qr-code-box {
            display: inline-block;
            padding: 8px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .qr-code-box img {
            max-width: 100px;
            height: auto;
            display: block;
        }

        .ticket-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 20px;
        }

        .ticket-meta .meta-item {
            padding: 5px 0;
        }

        .ticket-meta .meta-label {
            font-size: 0.65rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .ticket-meta .meta-value {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1a237e;
        }

        /* Footer */
        .ticket-footer {
            background: #f5f7fa;
            padding: 12px 30px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }

        .ticket-footer .footer-text {
            font-size: 0.7rem;
            color: #6c757d;
            margin: 0;
        }

        .ticket-footer .footer-text i {
            margin: 0 5px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-download {
            background: linear-gradient(135deg, #0d47a1, #1a237e);
            color: #fff;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(13, 71, 161, 0.3);
            color: #fff;
        }

        .btn-print {
            background: #2e7d32;
            color: #fff;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.3);
            color: #fff;
        }

        .btn-home {
            background: #6c757d;
            color: #fff;
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
            color: #fff;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .ticket-body {
                padding: 15px 18px;
            }

            .ticket-header {
                padding: 15px 18px;
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .company-info {
                flex-direction: column;
            }

            .route-display {
                flex-direction: column;
                gap: 10px;
                padding: 15px;
            }

            .route-city .city-name {
                font-size: 1.4rem;
            }

            .route-arrow {
                transform: rotate(90deg);
                padding: 5px 0;
            }

            .ticket-grid {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .ticket-bottom {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .ticket-meta {
                grid-template-columns: 1fr 1fr;
            }

            .price-section {
                flex-direction: column;
                text-align: center;
                gap: 5px;
            }

            .price-section .price-amount {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }

        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
            }

            .ticket-wrapper {
                max-width: 100% !important;
            }

            .bus-ticket {
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .action-buttons {
                display: none !important;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Staple effect (decorative) */
        .ticket-staple {
            position: absolute;
            width: 20px;
            height: 20px;
            background: #c62828;
            border-radius: 50%;
            opacity: 0.3;
            top: 50%;
            left: -5px;
            transform: translateY(-50%);
        }

        .ticket-staple.right {
            left: auto;
            right: -5px;
        }

        /* Used ticket overlay */
        .ticket-used {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            background: rgba(230, 81, 0, 0.9);
            color: #fff;
            padding: 15px 40px;
            font-size: 2rem;
            font-weight: 900;
            border-radius: 8px;
            border: 4px solid #fff;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            letter-spacing: 3px;
            text-transform: uppercase;
            pointer-events: none;
            z-index: 10;
            display: <?php echo ($ticket_details['checked'] ?? 'no') === 'yes' ? 'block' : 'none'; ?>;
        }

        .ticket-used small {
            font-size: 0.8rem;
            font-weight: 400;
            display: block;
            letter-spacing: 1px;
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <div class="ticket-wrapper">
        <div class="bus-ticket">
            <!-- USED Stamp -->
            <div class="ticket-used">
                USED
                <small><?php echo ($ticket_details['checked'] ?? 'no') === 'yes' && !empty($ticket_details['checked_at']) ? date('M j, Y', strtotime($ticket_details['checked_at'])) : ''; ?></small>
            </div>

            <!-- Ticket Header -->
            <div class="ticket-header">
                <div class="company-info">
                    <div class="company-logo">
                        <i class="fas fa-bus"></i>
                    </div>
                    <div class="company-name">
                        <h2>SwiftPass</h2>
                        <small>Premium Bus Services</small>
                    </div>
                </div>
                <div class="ticket-status">
                    <i class="fas fa-<?php echo ($ticket_details['checked'] ?? 'no') === 'yes' ? 'check-circle' : 'circle'; ?> me-1"></i>
                    <?php echo ($ticket_details['checked'] ?? 'no') === 'yes' ? 'USED' : 'ACTIVE'; ?>
                </div>
            </div>

            <!-- Ticket Body -->
            <div class="ticket-body">
                <!-- Route Display -->
                <div class="route-display">
                    <div class="route-city">
                        <div class="city-label">Departure</div>
                        <div class="city-name"><?php echo htmlspecialchars($trip_details['departure'] ?? ''); ?></div>
                    </div>
                    <div class="route-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                    <div class="route-city">
                        <div class="city-label">Destination</div>
                        <div class="city-name"><?php echo htmlspecialchars($trip_details['destination'] ?? ''); ?></div>
                    </div>
                </div>

                <!-- Ticket Details Grid -->
                <div class="ticket-grid">
                    <div class="ticket-field">
                        <div class="label">Passenger Name</div>
                        <div class="value"><?php echo htmlspecialchars($passenger_details['name']); ?></div>
                    </div>
                    <div class="ticket-field">
                        <div class="label">Phone Number</div>
                        <div class="value"><?php echo htmlspecialchars($passenger_details['phone']); ?></div>
                    </div>
                    <div class="ticket-field">
                        <div class="label">Bus</div>
                        <div class="value"><?php echo htmlspecialchars($bus_details['model']); ?> (<?php echo htmlspecialchars($bus_details['plates_number']); ?>)</div>
                    </div>
                    <div class="ticket-field">
                        <div class="label">Seats</div>
                        <div class="value"><?php echo $booking_details['number_of_seats']; ?> seat(s)</div>
                    </div>
                    <div class="ticket-field">
                        <div class="label">Departure Date & Time</div>
                        <div class="value"><?php echo !empty($trip_details['departure_datetime']) ? date('M j, Y g:i A', strtotime($trip_details['departure_datetime'])) : 'N/A'; ?></div>
                    </div>
                    <div class="ticket-field">
                        <div class="label">Booking Reference</div>
                        <div class="value">#BK<?php echo str_pad($booking_details['booking_id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>

                <!-- Price Section -->
                <div class="price-section">
                    <div>
                        <div class="price-label"><i class="fas fa-ticket-alt me-1"></i> Total Amount Paid</div>
                        <div style="font-size:0.8rem; opacity:0.8;">
                            <?php echo $booking_details['number_of_seats']; ?> seat × <?php echo number_format($trip_details['price_per_seat'] ?? 0); ?> FRW
                        </div>
                    </div>
                    <div class="price-amount">
                        <?php echo number_format($payment_details['amount']); ?> <small>FRW</small>
                    </div>
                </div>

                <!-- Bottom Section: QR Code & Meta -->
                <div class="ticket-bottom">
                    <div class="qr-section">
                        <div class="qr-label"><i class="fas fa-qrcode me-1"></i> Scan to Verify</div>
                        <div class="qr-code-box">
                            <div id="qrCodeContainer"></div>
                        </div>
                        <div style="font-size:0.65rem; color:#6c757d; margin-top:5px;">
                            Ticket #<?php echo $ticket_details['ticket_id'] ?? 'N/A'; ?>
                        </div>
                    </div>

                    <div class="ticket-meta">
                        <div class="meta-item">
                            <div class="meta-label">Payment Method</div>
                            <div class="meta-value">
                                <i class="fas fa-<?php echo $payment_details['payment_method'] === 'momo' ? 'mobile-alt' : 'wifi'; ?> me-1"></i>
                                <?php echo strtoupper($payment_details['payment_method']); ?>
                            </div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Transaction ID</div>
                            <div class="meta-value" style="font-size:0.75rem; font-family:monospace;">
                                <?php echo $payment_details['transaction_id']; ?>
                            </div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Booking Date</div>
                            <div class="meta-value"><?php echo date('M j, Y', strtotime($booking_details['booking_date'])); ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Ticket Status</div>
                            <div class="meta-value" style="color: <?php echo ($ticket_details['checked'] ?? 'no') === 'yes' ? '#e65100' : '#2e7d32'; ?>;">
                                <?php echo ($ticket_details['checked'] ?? 'no') === 'yes' ? 'Used' : 'Active'; ?>
                            </div>
                        </div>
                        <?php if (($ticket_details['checked'] ?? 'no') === 'yes' && !empty($ticket_details['checked_at'])): ?>
                        <div class="meta-item" style="grid-column: span 2;">
                            <div class="meta-label">Verified / Checked In</div>
                            <div class="meta-value" style="color:#e65100;">
                                <i class="fas fa-check-circle me-1"></i>
                                <?php echo date('M j, Y g:i A', strtotime($ticket_details['checked_at'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Ticket Footer -->
            <div class="ticket-footer">
                <p class="footer-text">
                    <i class="fas fa-shield-alt"></i>
                    This ticket is electronically generated and valid for travel on the specified date.
                    <i class="fas fa-print"></i>
                    <?php echo date('M j, Y \a\t g:i A'); ?>
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons no-print">
            <button onclick="downloadTicket()" class="btn-action btn-download">
                <i class="fas fa-download"></i> Download Ticket
            </button>
            <button onclick="printTicket()" class="btn-action btn-print">
                <i class="fas fa-print"></i> Print Ticket
            </button>
            <a href="homepage.php" class="btn-action btn-home">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Generate QR code
        function generateQRCode() {
            const qr = qrcode(0, 'M');

            const ticket_id = '<?php echo $ticket_details['ticket_id'] ?? ''; ?>';
            const booking_id = '<?php echo $booking_details['booking_id']; ?>';
            const security_hash = '<?php echo md5(($ticket_details['ticket_id'] ?? '') . $booking_details['booking_id'] . 'swiftpass_secret'); ?>';

            // Create verification URL
            const verificationUrl = `<?php echo "https://junkyard-hardship-refill.ngrok-free.dev/urugendo/"; ?>verify-ticket.php?ticket_id=${ticket_id}&booking_id=${booking_id}&hash=${security_hash}`;

            qr.addData(verificationUrl);
            qr.make();

            const qrContainer = document.getElementById('qrCodeContainer');
            qrContainer.innerHTML = qr.createImgTag(4);
        }

        // Print function
        function printTicket() {
            window.print();
        }

        // Download as image function
        function downloadTicket() {
            const ticketElement = document.querySelector('.bus-ticket');
            const downloadBtn = document.querySelector('.btn-download');

            const originalText = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
            downloadBtn.disabled = true;

            html2canvas(ticketElement, {
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                const image = canvas.toDataURL('image/png');
                const link = document.createElement('a');
                link.download = 'swiftpass-ticket-<?php echo $ticket_details['ticket_id'] ?? $booking_details['booking_id']; ?>.png';
                link.href = image;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
            }).catch(error => {
                console.error('Error generating ticket image:', error);
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
                alert('Error downloading ticket. Please try again.');
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            generateQRCode();
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>