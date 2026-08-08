<?php
session_start();
include('connection.php');
require_once __DIR__ . '/mailer.php';

$error = '';
$success = '';

$createTableSql = "CREATE TABLE IF NOT EXISTS password_reset_otps (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_id int(11) NOT NULL,
    otp_code varchar(5) NOT NULL,
    expires_at datetime NOT NULL,
    used tinyint(1) NOT NULL DEFAULT 0,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
$conn->query($createTableSql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $conn->prepare('SELECT id, firstname, lastname FROM users WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            $otp = random_int(10000, 99999);
            $expires_at = date('Y-m-d H:i:s', time() + 600);
            $insert = $conn->prepare('INSERT INTO password_reset_otps (user_id, otp_code, expires_at) VALUES (?, ?, ?)');
            $insert->bind_param('iss', $user['id'], $otp, $expires_at);
            $insert->execute();
            $insert->close();

            $name = trim($user['firstname'] . ' ' . $user['lastname']);
            if (!sendOtpEmail($email, $name, $otp)) {
                $error = 'Unable to send OTP email right now. Please try again later.';
            } else {
                $success = 'If the email exists in our system, a 5-digit OTP has been sent. Check your inbox and use it on the reset page.';
            }
        } else {
            // Avoid revealing whether the email exists
            $success = 'If the email exists in our system, a 5-digit OTP has been sent. Check your inbox and use it on the reset page.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Urugendo</title>
  <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
  <style>
    body { background: #f4f7ff; }
    .card { max-width: 480px; margin: 5rem auto; }
    .form-control:focus { box-shadow: none; }
  </style>
</head>
<body>
  <div class="card shadow-sm">
    <div class="card-body">
      <h3 class="card-title text-center mb-3">Reset Password</h3>
      <p class="text-muted text-center">Enter your email to receive a 5-digit OTP.</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label for="email" class="form-label">Email Address</label>
          <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn btn-primary w-100">Send OTP</button>
      </form>

      <div class="mt-3 text-center">
        <a href="login.php">Back to login</a> · <a href="reset_password.php">Have an OTP?</a>
      </div>
    </div>
  </div>
</body>
</html>
