<?php
session_start();
include('connection.php');

// Get parameters from URL
$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$hash = isset($_GET['hash']) ? $_GET['hash'] : '';

// If it's a POST request from QR scan (auto-verify)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    header('Content-Type: application/json');
    
    $ticket_id = (int)$_POST['ticket_id'];
    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $hash = isset($_POST['hash']) ? $_POST['hash'] : '';
    
    // Verify hash
    $expected_hash = md5($ticket_id . $booking_id . 'swiftpass_secret');
    
    if ($ticket_id > 0 && $hash === $expected_hash) {
        // Check ticket status
        $stmt = $conn->prepare("SELECT checked FROM tickets WHERE ticket_id = ? AND booking_id = ?");
        $stmt->bind_param("ii", $ticket_id, $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $ticket = $result->fetch_assoc();
            
            if ($ticket['checked'] === 'no') {
                // Update to verified
                $update_stmt = $conn->prepare("UPDATE tickets SET checked = 'yes', checked_at = NOW() WHERE ticket_id = ? AND booking_id = ? AND checked = 'no'");
                $update_stmt->bind_param("ii", $ticket_id, $booking_id);
                
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Ticket verified successfully!',
                        'verified_at' => date('M j, Y g:i A')
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Ticket already verified'
                    ]);
                }
                $update_stmt->close();
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Ticket was already verified on ' . date('M j, Y g:i A', strtotime($ticket['checked_at']))
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Ticket not found'
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid verification request'
        ]);
    }
    exit;
}

// GET request - display verification page
$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$hash = isset($_GET['hash']) ? $_GET['hash'] : '';

// Verify the hash for security
$expected_hash = md5($ticket_id . $booking_id . 'swiftpass_secret');

if ($ticket_id > 0 && $booking_id > 0 && $hash === $expected_hash) {
    // Check if ticket exists
    $stmt = $conn->prepare("SELECT * FROM tickets WHERE ticket_id = ? AND booking_id = ?");
    $stmt->bind_param("ii", $ticket_id, $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $ticket = $result->fetch_assoc();
        
        // Auto-verify if not already verified
        if ($ticket['checked'] === 'no') {
            // Auto-verify
            $update_stmt = $conn->prepare("UPDATE tickets SET checked = 'yes', checked_at = NOW() WHERE ticket_id = ? AND booking_id = ? AND checked = 'no'");
            $update_stmt->bind_param("ii", $ticket_id, $booking_id);
            
            if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                $message = "✅ Ticket verified successfully!";
                $status = "success";
                $verified_time = date('M j, Y g:i A');
            } else {
                $message = "❌ Error verifying ticket";
                $status = "error";
            }
            $update_stmt->close();
        } else {
            // Already verified
            $verified_time = date('M j, Y g:i A', strtotime($ticket['checked_at']));
            $message = "ℹ️ Ticket was already verified on: " . $verified_time;
            $status = "info";
        }
    } else {
        $message = "❌ Ticket not found.";
        $status = "error";
    }
    $stmt->close();
} else {
    $message = "❌ Invalid verification link.";
    $status = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Verification - SwiftPass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }

        .verification-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
        }

        .icon-success { color: #28a745; }
        .icon-error { color: #dc3545; }
        .icon-info { color: #17a2b8; }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            margin: 10px 0;
        }

        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.error {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge.info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .btn {
            border-radius: 50px;
            padding: 10px 30px;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }

        .btn-secondary:hover {
            background: #6c757d;
            color: white;
        }

        .ticket-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }

        .ticket-details p {
            margin: 5px 0;
        }

        .ticket-details strong {
            color: #495057;
        }
    </style>
</head>

<body>
    <div class="verification-container">
        <?php if ($status === 'success'): ?>
            <div class="icon-success mb-3">
                <i class="fas fa-check-circle fa-5x"></i>
            </div>
            <h2 class="text-success">🎉 Ticket Verified!</h2>
            <div class="status-badge success">✓ Successfully Verified</div>
        <?php elseif ($status === 'error'): ?>
            <div class="icon-error mb-3">
                <i class="fas fa-exclamation-circle fa-5x"></i>
            </div>
            <h2 class="text-danger">Verification Failed</h2>
            <div class="status-badge error">✗ Error</div>
        <?php else: ?>
            <div class="icon-info mb-3">
                <i class="fas fa-info-circle fa-5x"></i>
            </div>
            <h2 class="text-info">Already Verified</h2>
            <div class="status-badge info">ℹ Information</div>
        <?php endif; ?>

        <p class="lead my-4"><?php echo $message; ?></p>

        <?php if ($ticket_id > 0): ?>
            <div class="ticket-details">
                <?php if (isset($verified_time) && !empty($verified_time)): ?>
                    <p><strong>Verified At:</strong> <?php echo $verified_time; ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4 d-flex gap-3 justify-content-center flex-wrap">
            <a href="homepage.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Back to Home
            </a>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i>Close Window
            </button>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>