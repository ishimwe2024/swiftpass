<?php
session_start();
include('connection.php');

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'passenger') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$customer_id = $_SESSION['customer_id'] ?? null;

// If customer_id not set, try to fetch it
if (!$customer_id) {
    $email = $_SESSION['user_email'] ?? '';
    if ($email) {
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $customer_id = $row['customer_id'];
            $_SESSION['customer_id'] = $customer_id;
        }
        $stmt->close();
    }
}

// Fetch all bookings for this customer
$bookings = [];
if ($customer_id) {
    $stmt = $conn->prepare("
        SELECT 
            b.booking_id,
            b.number_of_seats,
            b.booking_date,
            t.trip_id,
            t.departure_datetime,
            t.estimated_arrival,
            t.available_seats,
            r.departure,
            r.destination,
            r.price_per_seat,
            bus.model,
            bus.plates_number,
            d.name as driver_name,
            (b.number_of_seats * r.price_per_seat) as total_amount,
            p.payment_status,
            p.payment_method,
            p.time_paid
        FROM bookings b
        JOIN trips t ON b.trip_id = t.trip_id
        JOIN routes r ON t.route_id = r.route_id
        JOIN buses bus ON t.bus_id = bus.bus_id
        JOIN drivers d ON t.driver_id = d.driver_id
        LEFT JOIN payments p ON b.booking_id = p.booking_id
        WHERE b.customer_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - SwiftPass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #191e32ff 0%, #1a151fff 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .page-header {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .page-header h2 {
            margin: 0;
            color: #2c3e50;
            font-weight: 700;
        }
        .page-header a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        .ticket-card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 5px solid #3498db;
            color: #333;
        }
        .ticket-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .ticket-card .route {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2c3e50;
        }
        .ticket-card .details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.8rem;
            margin: 1rem 0;
        }
        .ticket-card .details .item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .ticket-card .details .item i {
            color: #3498db;
            width: 20px;
            text-align: center;
        }
        .ticket-card .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        .btn-download {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(46, 204, 113, 0.4);
            color: white;
        }
        .btn-view {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4);
            color: white;
        }
        .badge-status {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .no-tickets {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            color: #333;
        }
        .no-tickets i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 1rem;
        }
        .back-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2><i class="fas fa-ticket-alt me-2"></i>My Tickets</h2>
                <p class="text-muted mb-0">View and download your booked tickets</p>
            </div>
            <a href="homepage.php"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>

        <?php if (count($bookings) > 0): ?>
            <div class="row">
                <?php foreach ($bookings as $booking): ?>
                    <div class="col-lg-6">
                        <div class="ticket-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="route">
                                    <?php echo htmlspecialchars($booking['departure'] . ' → ' . $booking['destination']); ?>
                                </span>
                                <span class="badge-status <?php echo ($booking['payment_status'] ?? 'pending') === 'completed' ? 'badge-paid' : 'badge-pending'; ?>">
                                    <?php echo ucfirst($booking['payment_status'] ?? 'Pending'); ?>
                                </span>
                            </div>

                            <div class="details">
                                <div class="item">
                                    <i class="far fa-calendar"></i>
                                    <span><?php echo date('M j, Y', strtotime($booking['departure_datetime'])); ?></span>
                                </div>
                                <div class="item">
                                    <i class="far fa-clock"></i>
                                    <span><?php echo date('H:i', strtotime($booking['departure_datetime'])); ?> – <?php echo date('H:i', strtotime($booking['estimated_arrival'])); ?></span>
                                </div>
                                <div class="item">
                                    <i class="fas fa-bus"></i>
                                    <span><?php echo htmlspecialchars($booking['model']); ?> (<?php echo htmlspecialchars($booking['plates_number']); ?>)</span>
                                </div>
                                <div class="item">
                                    <i class="fas fa-user"></i>
                                    <span>Driver: <?php echo htmlspecialchars($booking['driver_name']); ?></span>
                                </div>
                                <div class="item">
                                    <i class="fas fa-chair"></i>
                                    <span><?php echo $booking['number_of_seats']; ?> seat(s)</span>
                                </div>
                                <div class="item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span><?php echo number_format($booking['total_amount']); ?> FRW</span>
                                </div>
                            </div>

                            <div class="actions">
                                <a href="ticket_download.php?booking_id=<?php echo $booking['booking_id']; ?>" class="btn-download">
                                    <i class="fas fa-download"></i> Download Ticket
                                </a>
                                <a href="ticket_download.php?booking_id=<?php echo $booking['booking_id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-tickets">
                <i class="fas fa-ticket-alt"></i>
                <h4>No Tickets Found</h4>
                <p class="text-muted">You haven't booked any trips yet. Start exploring available trips!</p>
                <a href="homepage.php" class="btn btn-primary mt-3">
                    <i class="fas fa-search me-2"></i>Find a Trip
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>