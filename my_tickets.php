<?php
session_start();
include('connection.php');

// Redirect if not logged in or not a passenger
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'passenger') {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'] ?? 0;
if (!$customer_id) {
    // If customer_id not set, try to fetch it
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

$username = $_SESSION['user_name'] ?? 'Passenger';

// Fetch all bookings with ticket info
$bookings_query = $conn->prepare("
    SELECT 
        b.booking_id,
        b.number_of_seats,
        b.booking_date,
        t.ticket_id,
        t.checked,
        t.checked_at,
        t.created_at as ticket_created,
        r.departure,
        r.destination,
        r.price_per_seat,
        trip.departure_datetime,
        trip.estimated_arrival,
        bus.plates_number,
        bus.model
    FROM bookings b
    LEFT JOIN tickets t ON b.booking_id = t.booking_id
    LEFT JOIN trips trip ON b.trip_id = trip.trip_id
    LEFT JOIN routes r ON trip.route_id = r.route_id
    LEFT JOIN buses bus ON trip.bus_id = bus.bus_id
    WHERE b.customer_id = ?
    ORDER BY b.booking_date DESC
");

$bookings_query->bind_param("i", $customer_id);
$bookings_query->execute();
$bookings_result = $bookings_query->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
$bookings_query->close();

// Helper to get ticket status label
function getTicketStatus($checked) {
    if ($checked === 'yes') {
        return ['label' => 'Used', 'class' => 'badge-danger'];
    } else {
        return ['label' => 'Active', 'class' => 'badge-success'];
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --dark: #191e32;
            --sidebar-width: 280px;
        }

        body {
            background: linear-gradient(135deg, #191e32ff 0%, #1a151fff 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
            overflow-x: hidden;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            height: 100vh;
            position: fixed;
            transition: all 0.3s;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-brand {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            margin: -1px -1px 0 -1px;
        }

        .sidebar-brand h4 {
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            color: white;
        }

        .sidebar-brand i {
            margin-right: 12px;
            font-size: 1.8rem;
            color: var(--success);
        }

        .nav-container {
            padding: 1rem 0;
        }

        .sidebar .nav-link {
            color: var(--primary);
            padding: 1rem 1.5rem;
            margin: 0.3rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            border: none;
            text-decoration: none;
        }

        .sidebar .nav-link:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        .sidebar .nav-link i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: all 0.3s;
            min-height: 100vh;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .header h2 {
            margin: 0;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.8rem;
        }

        .header p {
            margin: 0.5rem 0 0 0;
            color: #6c757d;
            font-size: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            border: 3px solid var(--success);
        }

        .ticket-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 5px solid var(--secondary);
            color: #333;
            height: 100%;
        }

        .ticket-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-download {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4);
            color: white;
        }

        .no-tickets {
            text-align: center;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            color: #333;
        }

        .no-tickets i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            .sidebar-brand h4 span, .sidebar .nav-link span {
                display: none;
            }
            .sidebar .nav-link i {
                margin-right: 0;
                font-size: 1.3rem;
            }
            .sidebar .nav-link {
                padding: 1rem;
                justify-content: center;
            }
            .main-content {
                margin-left: 80px;
                padding: 1rem;
            }
            .header {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <h4><i class="fas fa-bus"></i> <span>SwiftPass</span></h4>
    </div>
    <div class="nav-container">
        <a href="homepage.php" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="my_tickets.php" class="nav-link active">
            <i class="fas fa-ticket-alt"></i>
            <span>My Tickets</span>
        </a>
        <a href="settings.php" class="nav-link">
            <i class="fas fa-cogs"></i>
            <span>Settings</span>
        </a>
        <a href="logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Log Out</span>
        </a>
    </div>
</div>

<div class="main-content">
    <!-- Header -->
    <div class="header">
        <div>
            <h2>My Tickets</h2>
            <p>View and download your purchased tickets</p>
        </div>
        <div class="user-info">
            <div class="text-end">
                <div class="fw-bold text-dark"><?php echo htmlspecialchars($username); ?></div>
                <small class="text-muted">Passenger</small>
            </div>
            <div class="user-avatar"><?php echo substr($username, 0, 1); ?></div>
        </div>
    </div>

    <!-- Tickets List -->
    <?php if (count($bookings) > 0): ?>
        <div class="row g-4">
            <?php foreach ($bookings as $booking): ?>
                <?php
                $status = getTicketStatus($booking['checked'] ?? 'no');
                $ticket_id = $booking['ticket_id'] ?? null;
                $booking_id = $booking['booking_id'];
                $download_link = $ticket_id ? "ticket_download.php?ticket_id=$ticket_id&booking_id=$booking_id" : "#";
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="ticket-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="fw-bold text-primary mb-0">
                                <?php echo htmlspecialchars($booking['departure'] ?? 'N/A') . ' → ' . htmlspecialchars($booking['destination'] ?? 'N/A'); ?>
                            </h6>
                            <span class="badge-status <?php echo $status['class']; ?>">
                                <?php echo $status['label']; ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Travel Date:</small>
                            <strong><?php echo date('M j, Y H:i', strtotime($booking['departure_datetime'] ?? '')); ?></strong>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Seats:</small>
                            <strong><?php echo $booking['number_of_seats']; ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Bus:</small>
                            <strong><?php echo htmlspecialchars($booking['model'] ?? 'N/A') . ' (' . htmlspecialchars($booking['plates_number'] ?? 'N/A') . ')'; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-success fw-bold"><?php echo number_format($booking['price_per_seat'] ?? 0); ?> FRW</span>
                            <?php if ($ticket_id): ?>
                                <a href="<?php echo $download_link; ?>" class="btn-download">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">No ticket generated</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-tickets">
            <i class="fas fa-ticket-alt"></i>
            <h4>No Tickets Found</h4>
            <p class="text-muted">You haven't purchased any tickets yet. Start booking a trip!</p>
            <a href="homepage.php" class="btn btn-primary mt-3">
                <i class="fas fa-search me-2"></i>Find a Trip
            </a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>