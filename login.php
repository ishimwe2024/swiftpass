<?php
session_start();
include('connection.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $stmt = $conn->prepare("
            SELECT
                id, firstname, lastname, email, contact, password, role, status
            FROM users
            WHERE email = ? AND status = 'active'
        ");

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['lastname'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_contact'] = $user['contact'];

            if ($user['role'] === 'driver') {
                $driver_stmt = $conn->prepare("
                    SELECT driver_id, name as driver_name, license
                    FROM drivers
                    WHERE user_id = ?
                ");
                $driver_stmt->bind_param("i", $user['id']);
                $driver_stmt->execute();
                $driver_result = $driver_stmt->get_result();
                $driver_data = $driver_result->fetch_assoc();

                if ($driver_data) {
                    $_SESSION['driver_id'] = $driver_data['driver_id'];
                    $_SESSION['driver_name'] = $driver_data['driver_name'];
                    $_SESSION['driver_license'] = $driver_data['license'];
                }
                $driver_stmt->close();
            } elseif ($user['role'] === 'passenger') {
                $passenger_stmt = $conn->prepare("
                    SELECT customer_id, firstname, lastname, contact, email
                    FROM customers
                    WHERE email = ?
                ");
                $passenger_stmt->bind_param("s", $email);
                $passenger_stmt->execute();
                $passenger_result = $passenger_stmt->get_result();
                $passenger_data = $passenger_result->fetch_assoc();

                if ($passenger_data) {
                    $_SESSION['customer_id'] = $passenger_data['customer_id'];
                }
                $passenger_stmt->close();
            }

            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();

            switch ($user['role']) {
                case 'admin':
                    header("Location: admin.php");
                    exit;
                case 'driver':
                    header("Location: drivers_dashboard.php");
                    exit;
                case 'passenger':
                    header("Location: homepage.php");
                    exit;
                default:
                    header("Location: homepage.php");
                    exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SwiftPass | Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue: #2f62e8;
      --blue-dark: #2552cc;
      --navy: #27354a;
      --muted: #7d8a9c;
      --line: #dbe4f0;
      --bg: #edf2f8;
      --card: #ffffff;
      --error-bg: #fff1f1;
      --error-line: #f4c9c9;
      --error-text: #c34b4b;
      --success-bg: #eefaf1;
      --success-line: #c5e8cf;
      --success-text: #238255;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Source Sans 3', sans-serif;
      background: var(--bg);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      position: relative;
      overflow: hidden;
    }

    body::before,
    body::after {
      content: "";
      position: fixed;
      border-radius: 50%;
      background: rgba(47, 98, 232, 0.08);
      pointer-events: none;
    }

    body::before {
      width: 260px;
      height: 260px;
      top: -90px;
      right: -90px;
    }

    body::after {
      width: 190px;
      height: 190px;
      left: -70px;
      bottom: -50px;
    }

    .side-bubble {
      position: fixed;
      width: 110px;
      height: 110px;
      right: -30px;
      top: 52%;
      border-radius: 50%;
      background: rgba(99, 187, 255, 0.14);
      pointer-events: none;
    }

    .login-card {
      width: 100%;
      max-width: 430px;
      background: var(--card);
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(45, 69, 102, 0.14);
      border-top: 4px solid var(--blue);
      padding: 1.9rem 1.2rem 1.5rem;
      position: relative;
      z-index: 1;
    }

    .icon-wrap {
      width: 58px;
      height: 58px;
      margin: 0 auto 1rem;
      border-radius: 50%;
      border: 2px solid var(--blue);
      display: grid;
      place-items: center;
      color: var(--blue);
      box-shadow: 0 6px 16px rgba(47, 98, 232, 0.18);
      font-size: 1.3rem;
    }

    .title {
      margin: 0;
      text-align: center;
      font-family: 'Montserrat', sans-serif;
      font-size: 1.95rem;
      font-weight: 800;
      color: var(--blue);
    }

    .subtitle {
      margin: 0.45rem 0 1.65rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.98rem;
    }

    .alert {
      padding: 0.85rem 0.95rem;
      border-radius: 12px;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.55rem;
      font-weight: 600;
    }

    .alert-danger {
      background: var(--error-bg);
      border: 1px solid var(--error-line);
      color: var(--error-text);
    }

    .alert-success {
      background: var(--success-bg);
      border: 1px solid var(--success-line);
      color: var(--success-text);
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-label {
      display: block;
      margin-bottom: 0.35rem;
      color: #3f4f63;
      font-size: 0.82rem;
      font-weight: 700;
    }

    .form-label i {
      color: var(--blue);
      margin-right: 0.35rem;
      font-size: 0.78rem;
    }

    .form-input {
      width: 100%;
      height: 42px;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 0 0.95rem;
      font-size: 0.95rem;
      background: #fff;
      color: var(--navy);
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(47, 98, 232, 0.12);
    }

    .password-container {
      position: relative;
    }

    .password-input {
      padding-right: 2.8rem;
    }

    .password-toggle {
      position: absolute;
      top: 50%;
      right: 0.9rem;
      transform: translateY(-50%);
      color: #7f8ea3;
      cursor: pointer;
      font-size: 0.9rem;
    }

    .form-options {
      margin: 0.7rem 0 1.2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      font-size: 0.86rem;
    }

    .checkbox-container {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #6d7c90;
    }

    .custom-checkbox {
      width: 14px;
      height: 14px;
      border: 1px solid #cbd7e5;
      border-radius: 3px;
      display: grid;
      place-items: center;
      background: #fff;
      cursor: pointer;
    }

    .custom-checkbox i {
      font-size: 0.55rem;
      color: #fff;
      opacity: 0;
    }

    .custom-checkbox.checked {
      background: var(--blue);
      border-color: var(--blue);
    }

    .custom-checkbox.checked i {
      opacity: 1;
    }

    .forgot-link,
    .bottom-link {
      color: var(--blue);
      text-decoration: none;
      font-weight: 700;
    }

    .btn-primary {
      width: 100%;
      height: 46px;
      border: none;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--blue), var(--blue-dark));
      color: #fff;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.92rem;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 10px 20px rgba(47, 98, 232, 0.2);
    }

    .btn-primary i {
      margin-right: 0.4rem;
    }

    .bottom-text {
      margin-top: 1rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.88rem;
    }

    .home-link {
      margin-top: 0.8rem;
      text-align: center;
      font-size: 0.88rem;
    }
  </style>
</head>
<body>
  <div class="side-bubble"></div>

  <div class="login-card">
    <div class="icon-wrap">
      <i class="fas fa-bus"></i>
    </div>

    <h1 class="title">Welcome Back</h1>
    <p class="subtitle">Sign in to continue your journey</p>

    <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        Password reset successfully. Please login with your new password.
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <div class="form-group">
        <label class="form-label" for="email"><i class="fas fa-envelope"></i>Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-input"
          placeholder="Enter your email"
          required
          autocomplete="email"
          value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password"><i class="fas fa-lock"></i>Password</label>
        <div class="password-container">
          <input
            type="password"
            id="password"
            name="password"
            class="form-input password-input"
            placeholder="Enter your password"
            required
            autocomplete="current-password"
          >
          <i class="fas fa-eye password-toggle" id="togglePassword"></i>
        </div>
      </div>

      <div class="form-options">
        <div class="checkbox-container">
          <div class="custom-checkbox" id="rememberCheckbox">
            <i class="fas fa-check"></i>
          </div>
          <span>Remember me</span>
        </div>
        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
      </div>

      <button type="submit" class="btn-primary" id="signInBtn">
        <i class="fas fa-sign-in-alt"></i>Sign In
      </button>
    </form>

    <div class="bottom-text">
      Don't have an account? <a href="register.php" class="bottom-link">Create Account</a>
    </div>

    <div class="home-link">
      <a href="index.php" class="bottom-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
    </div>
  </div>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const rememberCheckbox = document.getElementById('rememberCheckbox');

    togglePassword.addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });

    rememberCheckbox.addEventListener('click', function() {
      this.classList.toggle('checked');
    });
  </script>
</body>
</html>
