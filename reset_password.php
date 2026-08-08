<?php
session_start();
include('connection.php');
require_once __DIR__ . '/mailer.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $otp = trim($_POST['otp'] ?? '');
  $new_password = trim($_POST['new_password'] ?? '');
  $confirm_password = trim($_POST['confirm_password'] ?? '');

  if (empty($otp) || empty($new_password) || empty($confirm_password)) {
    $error = 'All fields are required.';
  } elseif ($new_password !== $confirm_password) {
    $error = 'Passwords do not match.';
  } else {
    // Lookup OTP entry directly and identify the user by user_id
    $stmt = $conn->prepare('SELECT id, user_id, expires_at, used FROM password_reset_otps WHERE otp_code = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->bind_param('s', $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    $otpRow = $result->fetch_assoc();
    $stmt->close();

    if (!$otpRow) {
      $error = 'Invalid OTP.';
    } elseif ($otpRow['used']) {
      $error = 'This OTP has already been used. Request a new one.';
    } elseif (new DateTime() > new DateTime($otpRow['expires_at'])) {
      $error = 'This OTP has expired. Request a new one.';
    } else {
      $userId = (int)$otpRow['user_id'];
      $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? AND status = "active" LIMIT 1');
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $result = $stmt->get_result();
      $user = $result->fetch_assoc();
      $stmt->close();

      if (!$user) {
        $error = 'Invalid OTP.';
      } else {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->bind_param('si', $hashedPassword, $userId);
        $update->execute();
        $update->close();

        $markUsed = $conn->prepare('UPDATE password_reset_otps SET used = 1 WHERE id = ?');
        $markUsed->bind_param('i', $otpRow['id']);
        $markUsed->execute();
        $markUsed->close();

        $success = 'Password reset successful. You can now log in with your new password.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Enter OTP - Reset Password</title>
  <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
  <style>
    body { background: #f4f7ff; }
    .card { max-width: 520px; margin: 5rem auto; }
    .form-control:focus { box-shadow: none; }
  </style>
</head>
<body>
  <div class="card shadow-sm">
    <div class="card-body">
      <h3 class="card-title text-center mb-3">Enter OTP</h3>
      <p class="text-muted text-center">Enter the code from your email and choose a new password.</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        
        <div class="mb-3">
          <label for="otp" class="form-label">5-digit OTP</label>
          <input type="text" class="form-control" id="otp" name="otp" maxlength="5" required value="<?php echo htmlspecialchars($_POST['otp'] ?? ''); ?>">
        </div>
        <div class="mb-3">
          <label for="new_password" class="form-label">New Password</label>
          <input type="password" class="form-control" id="new_password" name="new_password" required>
        </div>
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Confirm Password</label>
          <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
      </form>

      <div class="mt-3 text-center">
        <a href="login.php">Back to login</a> · <a href="forgot_password.php">Resend OTP</a>
      </div>
    </div>
  </div>
</body>
</html>
