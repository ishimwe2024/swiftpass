<?php
session_start();
include('connection.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$payment_query = "
    SELECT p.payment_id, p.booking_id, p.amount, p.payment_method, p.transaction_id, p.payment_status, p.time_paid,
           b.number_of_seats, b.booking_date,
           c.firstname, c.lastname, c.contact,
           r.departure, r.destination
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.booking_id
    LEFT JOIN customers c ON b.customer_id = c.customer_id
    LEFT JOIN trips t ON b.trip_id = t.trip_id
    LEFT JOIN routes r ON t.route_id = r.route_id
    WHERE 1=1";

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $payment_query .= " AND (
        p.transaction_id LIKE '%$search%' OR
        c.firstname LIKE '%$search%' OR
        c.lastname LIKE '%$search%' OR
        c.contact LIKE '%$search%' OR
        r.departure LIKE '%$search%' OR
        r.destination LIKE '%$search%'
    )";
}

$payment_query .= " ORDER BY p.time_paid DESC";
$payments_result = $conn->query($payment_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - SwiftPass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .table-container { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="fas fa-credit-card me-2"></i>Payments</h3>
            <p class="text-muted mb-0">View payment records linked to bookings.</p>
        </div>
        <a href="admin.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Admin
        </a>
    </div>

    <div class="table-container">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search by transaction, customer, phone, route..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Search</button>
            </div>
            <div class="col-md-2">
                <a href="payments.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-2"></i>Clear</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Booking</th>
                        <th>Customer</th>
                        <th>Route</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Transaction</th>
                        <th>Status</th>
                        <th>Paid At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments_result->num_rows > 0): ?>
                        <?php while ($payment = $payments_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $payment['payment_id']; ?></strong></td>
                                <td>#BK<?php echo str_pad($payment['booking_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(trim(($payment['firstname'] ?? '') . ' ' . ($payment['lastname'] ?? ''))); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($payment['contact'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars(($payment['departure'] ?? '') . ' → ' . ($payment['destination'] ?? '')); ?></td>
                                <td><strong><?php echo number_format($payment['amount'], 2); ?> FRW</strong></td>
                                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? ''))); ?></td>
                                <td><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i><?php echo ucfirst($payment['payment_status'] ?? 'completed'); ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($payment['time_paid']) ? date('M j, Y H:i', strtotime($payment['time_paid'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-credit-card fa-3x mb-3"></i>
                                <p>No payment records found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>