<?php
session_start();
include('connection.php');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Get trip information from URL parameters
$trip_id = $_GET['trip_id'] ?? '';
$price = $_GET['price'] ?? '';

// Fetch trip details from database
$trip_details = [];
$customer_details = [];

if (!empty($trip_id)) {
    $trip_stmt = $conn->prepare("
        SELECT t.*, b.plates_number, b.model, r.departure, r.destination, 
               r.delay_time, d.name as driver_name
        FROM trips t
        JOIN buses b ON t.bus_id = b.bus_id
        JOIN routes r ON t.route_id = r.route_id
        JOIN drivers d ON t.driver_id = d.driver_id
        WHERE t.trip_id = ?
    ");
    $trip_stmt->bind_param("i", $trip_id);
    $trip_stmt->execute();
    $trip_result = $trip_stmt->get_result();

    if ($trip_result->num_rows > 0) {
        $trip_details = $trip_result->fetch_assoc();
    }
    $trip_stmt->close();

    // Fetch customer details
    $customer_stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $customer_stmt->bind_param("i", $userId);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();

    if ($customer_result->num_rows > 0) {
        $customer_details = $customer_result->fetch_assoc();
    }
    $customer_stmt->close();

    // Load user profile from `users` table
    $user_stmt = $conn->prepare("SELECT firstname, lastname, contact, email FROM users WHERE id = ? LIMIT 1");
    if ($user_stmt) {
        $user_stmt->bind_param('i', $userId);
        $user_stmt->execute();
        $user_res = $user_stmt->get_result();
        $user_row = $user_res->fetch_assoc();
        if ($user_row) {
            $customer_details['firstname'] = $user_row['firstname'] ?? ($customer_details['firstname'] ?? '');
            $customer_details['lastname'] = $user_row['lastname'] ?? ($customer_details['lastname'] ?? '');
            $customer_details['contact'] = $user_row['contact'] ?? ($customer_details['contact'] ?? '');
            $customer_details['email'] = $user_row['email'] ?? ($customer_details['email'] ?? '');
        }
        $user_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftPass | Complete Your Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #1abc9c;
            --dark: #191e32;
            --light: #ecf0f1;
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
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

        .booking-form {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            color: #333;
        }

        .bus-info-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            border-left: 5px solid var(--success);
            color: #333;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            color: white !important;
        }

        .badge-available {
            background: linear-gradient(135deg, var(--success), #27ae60);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #27ae60);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            color: #333;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            color: #333;
        }

        .form-text {
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            .sidebar-brand h4 span,
            .sidebar .nav-link span {
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

        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-bus"></i> <span>SwiftPass</span></h4>
        </div>
        <div class="nav-container">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="homepage.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="setting.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Log Out</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h2>Complete Your Booking 🎫</h2>
                <p>Fill in your details to secure your seat</p>
            </div>
            <div class="user-info">
                <div class="text-end">
                    <div class="fw-bold text-dark">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                    <small class="text-muted">Passenger</small>
                </div>
                <div class="user-avatar"><?php echo substr($_SESSION['username'] ?? 'U', 0, 1); ?></div>
            </div>
        </div>

        <?php if (!empty($trip_details)): ?>
            <div class="bus-info-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($trip_details['model']); ?></h6>
                    <span class="status-badge badge-available">
                        <i class="fas fa-check-circle me-1"></i>Available
                    </span>
                </div>

                <div class="mb-3">
                    <p class="mb-2"><i class="fas fa-route me-2 text-muted"></i>Route: <span class="fw-semibold"><?php echo htmlspecialchars($trip_details['departure']); ?> → <?php echo htmlspecialchars($trip_details['destination']); ?></span></p>
                    <p class="mb-2"><i class="far fa-clock me-2 text-muted"></i>Departure: <span class="fw-semibold"><?php echo date('M j, Y H:i', strtotime($trip_details['departure_datetime'])); ?></span></p>
                    <p class="mb-2"><i class="fas fa-bus me-2 text-muted"></i>Bus: <span class="fw-semibold"><?php echo htmlspecialchars($trip_details['plates_number']); ?></span></p>
                    <p class="mb-2"><i class="fas fa-user me-2 text-muted"></i>Driver: <span class="fw-semibold"><?php echo htmlspecialchars($trip_details['driver_name']); ?></span></p>
                    <p class="mb-0"><i class="fas fa-chair me-2 text-muted"></i>Available Seats: <span class="fw-semibold"><?php echo $trip_details['available_seats']; ?></span></p>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <h5 class="text-success mb-0"><?php echo number_format($price); ?> FRW</h5>
                        <small class="text-muted">per seat</small>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Ready for your journey</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="booking-form">
            <h5 class="mb-3 fw-semibold"><i class="fas fa-user me-2"></i> Passenger Information</h5>

            <form method="POST" action="confirm_booking.php" id="bookingForm" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="firstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="firstName" name="firstName"
                            value="<?php echo htmlspecialchars($customer_details['firstname'] ?? ''); ?>" 
                            required minlength="2" maxlength="50" pattern="[A-Za-z\s]+">
                        <div class="invalid-feedback">Please enter a valid first name.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lastName" name="lastName"
                            value="<?php echo htmlspecialchars($customer_details['lastname'] ?? ''); ?>" 
                            required minlength="2" maxlength="50" pattern="[A-Za-z\s]+">
                        <div class="invalid-feedback">Please enter a valid last name.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo htmlspecialchars($customer_details['email'] ?? ''); ?>" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                            value="<?php echo htmlspecialchars($customer_details['contact'] ?? ''); ?>" 
                            required pattern="^(?:\+2507\d{8}|07[2389]\d{7})$"
                            placeholder="e.g., 0781234567 or +250781234567">
                        <div class="invalid-feedback">Please enter a valid Rwandan phone number.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="seatCount" class="form-label">Number of Seats</label>
                        <select class="form-select" id="seatCount" name="seatCount" required>
                            <?php for ($i = 1; $i <= min(5, $trip_details['available_seats'] ?? 5); $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> Seat<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="form-text">Maximum <?php echo min(5, $trip_details['available_seats'] ?? 5); ?> seats allowed</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Total Amount</label>
                        <div class="form-control bg-light">
                            <strong id="totalAmount"><?php echo number_format($price); ?> FRW</strong>
                            <small class="text-muted">(Calculated based on seats)</small>
                        </div>
                    </div>

                    <input type="hidden" name="travel_date" value="<?php echo date('M j, Y H:i', strtotime($trip_details['departure_datetime'] ?? '')); ?>">
                    <input type="hidden" name="price" value="<?php echo $price; ?>">
                    <input type="hidden" name="plate_nbr" value="<?php echo htmlspecialchars($trip_details['plates_number'] ?? ''); ?>">
                    <input type="hidden" name="bus_name" value="<?php echo htmlspecialchars($trip_details['model'] ?? ''); ?>">
                    <input type="hidden" name="route_name" value="<?php echo htmlspecialchars(($trip_details['departure'] ?? '') . ' → ' . ($trip_details['destination'] ?? '')); ?>">
                    <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">
                    <input type="hidden" name="action" value="booking">
                    
                    <div class="col-12 mt-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="homepage.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Search
                            </a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-check me-2"></i> Confirm Booking
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const pricePerSeat = <?php echo $price; ?>;
        const seatCountSelect = document.getElementById('seatCount');
        const totalAmountElement = document.getElementById('totalAmount');

        function updateTotalAmount() {
            const seatCount = parseInt(seatCountSelect.value);
            const totalAmount = pricePerSeat * seatCount;
            totalAmountElement.textContent = new Intl.NumberFormat().format(totalAmount) + ' FRW';
        }

        seatCountSelect.addEventListener('change', updateTotalAmount);
        document.addEventListener('DOMContentLoaded', updateTotalAmount);
    </script>
</body>

</html>