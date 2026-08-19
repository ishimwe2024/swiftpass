<?php
session_start();
include('connection.php');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$total_query = "SELECT COUNT(*) as total FROM admin_notifications";
$total_result = $conn->query($total_query);
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch notifications with pagination
$notif_sql = "SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT $offset, $limit";
$notif_res = $conn->query($notif_sql);

// Handle "Mark All as Read" from this page
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE admin_notifications SET status = 'read' WHERE status = 'unread'");
    header("Location: view_notifications.php");
    exit;
}

// Handle "Mark Single as Read"
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $conn->query("UPDATE admin_notifications SET status = 'read' WHERE notification_id = $id");
    header("Location: view_notifications.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications - SwiftPass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #191e32ff 0%, #1a151fff 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
            padding: 20px;
        }
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            color: var(--primary);
        }
        .back-btn {
            background: #f39c12;
            color: white;
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: #e67e22;
            color: white;
            transform: translateY(-1px);
        }
        .badge-type-success {
            background: #2ecc71;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
        }
        .badge-type-warning {
            background: #f39c12;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
        }
        .badge-type-info {
            background: #3498db;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
        }
        .badge-status-unread {
            background: #e74c3c;
            color: white;
            padding: 0.3rem 0.7rem;
            border-radius: 50px;
            font-size: 0.75rem;
        }
        .badge-status-read {
            background: #95a5a6;
            color: white;
            padding: 0.3rem 0.7rem;
            border-radius: 50px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0 text-dark"><i class="fas fa-bell me-2"></i>All Notifications</h4>
                <div>
                    <a href="?mark_all_read=1" class="btn btn-outline-secondary btn-sm me-2">Mark all read</a>
                    <a href="admin.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($notif_res->num_rows > 0): ?>
                            <?php while($n = $notif_res->fetch_assoc()): ?>
                                <tr class="<?php echo $n['status'] == 'unread' ? 'table-light' : ''; ?>">
                                    <td><strong>#<?php echo $n['notification_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($n['title']); ?></td>
                                    <td><?php echo htmlspecialchars($n['message']); ?></td>
                                    <td>
                                        <?php 
                                        $type_class = 'badge-type-info';
                                        if ($n['type'] == 'success') $type_class = 'badge-type-success';
                                        if ($n['type'] == 'warning') $type_class = 'badge-type-warning';
                                        ?>
                                        <span class="<?php echo $type_class; ?>">
                                            <?php echo ucfirst($n['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo $n['status'] == 'unread' ? 'badge-status-unread' : 'badge-status-read'; ?>">
                                            <?php echo ucfirst($n['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($n['created_at'])); ?></td>
                                    <td>
                                        <?php if ($n['status'] == 'unread'): ?>
                                            <a href="?mark_read=<?php echo $n['notification_id']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i> Mark read
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-check-circle text-success"></i> Read</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-3"></i>
                                    <p>No notifications found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>