<?php
declare(strict_types=1);
session_start();
require_once('connection.php');

// --- Security headers -------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// --- Auth guard ---------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- CSRF token -----------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Constants ------------------------------------------------------------
const MIN_PASSWORD_LENGTH = 8;
const MAX_NAME_LENGTH     = 100;
const MAX_PHONE_LENGTH    = 20;
const VALID_ROLES         = ['admin', 'driver', 'passenger'];

// --- Helpers ----------------------------------------------------------
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fetch_user(mysqli $conn, int $user_id): ?array
{
    $stmt = $conn->prepare('SELECT id, firstname, lastname, email, contact, role, status, created_at FROM users WHERE id = ?');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $user;
}

function safe_count(mysqli $conn, string $sql, string $key): int
{
    $result = $conn->query($sql);
    if ($result === false) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int) ($row[$key] ?? 0);
}

$user_id = (int) $_SESSION['user_id'];
$user    = fetch_user($conn, $user_id);

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$errors       = [];
$message      = '';
$message_type = 'success';

// --- Handle form submission -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

    if (!$token_ok) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $firstname        = trim($_POST['firstname'] ?? '');
        $lastname         = trim($_POST['lastname'] ?? '');
        $phone            = trim($_POST['phone'] ?? '');
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password     = (string) ($_POST['new_password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        $wants_password_change = $new_password !== '' || $confirm_password !== '';

        // --- Validation ---
        if ($firstname === '' || $lastname === '') {
            $errors[] = 'First name and last name are required.';
        }
        if (mb_strlen($firstname) > MAX_NAME_LENGTH || mb_strlen($lastname) > MAX_NAME_LENGTH) {
            $errors[] = 'First and last name must be under ' . MAX_NAME_LENGTH . ' characters.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,' . MAX_PHONE_LENGTH . '}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }

        if ($wants_password_change) {
            if ($current_password === '') {
                $errors[] = 'Enter your current password to set a new one.';
            } else {
                $pw_stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
                $pw_stmt->bind_param('i', $user_id);
                $pw_stmt->execute();
                $pw_row = $pw_stmt->get_result()->fetch_assoc();
                $pw_stmt->close();

                if (!$pw_row || !password_verify($current_password, $pw_row['password'])) {
                    $errors[] = 'Current password is incorrect.';
                } elseif (strlen($new_password) < MIN_PASSWORD_LENGTH) {
                    $errors[] = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = 'Password confirmation does not match.';
                } elseif (password_verify($new_password, $pw_row['password'])) {
                    $errors[] = 'New password must be different from your current password.';
                }
            }
        }

        // --- Persist ---
        if (empty($errors)) {
            if ($wants_password_change) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare('UPDATE users SET firstname = ?, lastname = ?, contact = ?, password = ? WHERE id = ?');
                $update->bind_param('ssssi', $firstname, $lastname, $phone, $hashed, $user_id);
            } else {
                $update = $conn->prepare('UPDATE users SET firstname = ?, lastname = ?, contact = ? WHERE id = ?');
                $update->bind_param('sssi', $firstname, $lastname, $phone, $user_id);
            }

            if ($update && $update->execute()) {
                $_SESSION['user_name']    = $firstname . ' ' . $lastname;
                $_SESSION['user_contact'] = $phone;
                $_SESSION['csrf_token']   = bin2hex(random_bytes(32)); // rotate after successful state change

                $message = $wants_password_change
                    ? 'Settings updated and password changed successfully.'
                    : 'Settings updated successfully.';

                $user['firstname'] = $firstname;
                $user['lastname']  = $lastname;
                $user['contact']   = $phone;
            } else {
                $errors[] = 'Failed to update settings. Please try again.';
            }
            $update?->close();
        }
    }

    if (!empty($errors)) {
        $message      = implode(' ', $errors);
        $message_type = 'danger';
    }
}

// --- Role-based homepage + admin stats (only queried when needed) ---------
$role = in_array($user['role'] ?? '', VALID_ROLES, true) ? $user['role'] : 'passenger';

$role_home_map = [
    'admin'   => 'admin.php',
    'driver'  => 'drivers_dashboard.php',
];
$role_home = $role_home_map[$role] ?? 'homepage.php';

$active_users     = 0;
$available_trips  = 0;
$today_revenue    = 0.0;

if ($role === 'admin') {
    $active_users    = safe_count($conn, "SELECT COUNT(*) AS total FROM users WHERE status = 'active'", 'total');
    $available_trips = safe_count($conn, "SELECT COUNT(*) AS total FROM trips WHERE status = 'available'", 'total');
    $revenue_result   = $conn->query("SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p WHERE DATE(p.created_at) = CURDATE()");
    $today_revenue    = $revenue_result !== false ? (float) ($revenue_result->fetch_assoc()['total'] ?? 0) : 0.0;
}

$full_name  = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
$role_label = ucfirst($role);
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Settings - SwiftPass</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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

    .nav-container { padding: 1rem 0; }

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
      flex-wrap: wrap;
      gap: 1rem;
      backdrop-filter: blur(10px);
    }

    .header h2 { margin: 0; color: var(--primary); font-weight: 700; font-size: 1.8rem; }
    .header p { margin: 0.5rem 0 0 0; color: #6c757d; font-size: 1rem; }

    .user-info { display: flex; align-items: center; gap: 1rem; }

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

    .card {
      background: rgba(255, 255, 255, 0.95);
      border: none;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(10px);
      color: #333;
    }

    .card h4 { color: var(--primary); font-weight: 700; }

    .section-divider {
      border: none;
      border-top: 1px solid #e9ecef;
      margin: 1.75rem 0 1.25rem;
    }

    .section-subtitle {
      color: var(--primary);
      font-weight: 600;
      font-size: 1rem;
      margin-bottom: 0.25rem;
    }

    .section-hint {
      color: #6c757d;
      font-size: 0.85rem;
      margin-bottom: 1rem;
    }

    .form-control, .form-select {
      border-radius: 10px;
      border: 2px solid #e9ecef;
      padding: 0.75rem 1rem;
      transition: all 0.3s ease;
      color: #333;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--secondary);
      box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
      color: #333;
    }

    .form-control:disabled { background-color: #f1f3f5; }

    .form-control.is-invalid { border-color: var(--danger); }
    .form-control.is-valid { border-color: var(--success); }

    .password-field { position: relative; }
    .password-toggle {
      position: absolute;
      right: 0.9rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #6c757d;
      cursor: pointer;
      padding: 0;
      font-size: 0.95rem;
    }
    .password-toggle:hover { color: var(--secondary); }

    .btn-primary {
      background: linear-gradient(135deg, var(--secondary), var(--primary));
      border: none;
      border-radius: 10px;
      padding: 0.75rem 2rem;
      font-weight: 600;
      transition: all 0.3s ease;
      color: white;
    }

    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4); color: white; }
    .btn-primary:disabled { opacity: 0.7; transform: none; box-shadow: none; }

    .btn-outline-primary {
      border: 2px solid var(--secondary);
      color: var(--secondary);
      background: transparent;
      border-radius: 10px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-outline-primary:hover { background: var(--secondary); color: white; transform: translateY(-2px); }

    .snapshot-row {
      display: flex;
      justify-content: space-between;
      padding: 0.6rem 0;
      border-bottom: 1px solid #eef0f2;
    }
    .snapshot-row:last-child { border-bottom: none; }
    .snapshot-label { color: #6c757d; font-weight: 500; }
    .snapshot-value { color: #333; font-weight: 600; }

    .stat-mini { background: #f8f9fb; border-radius: 12px; padding: 1rem; text-align: center; }
    .stat-mini .stat-number { font-size: 1.6rem; font-weight: 700; color: var(--primary); }
    .stat-mini .stat-label { font-size: 0.85rem; color: #6c757d; }

    @media (max-width: 768px) {
      .sidebar { width: 80px; }
      .sidebar-brand h4 span, .sidebar .nav-link span { display: none; }
      .sidebar .nav-link i { margin-right: 0; font-size: 1.3rem; }
      .sidebar .nav-link { padding: 1rem; justify-content: center; }
      .main-content { margin-left: 80px; padding: 1rem; }
      .header { padding: 1rem; }
    }

    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.1); }
    ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--secondary), var(--primary)); border-radius: 3px; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-brand">
      <h4><i class="fas fa-bus"></i> <span>SwiftPass</span></h4>
    </div>
    <div class="nav-container">
      <a href="<?php echo h($role_home); ?>" class="nav-link">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a href="settings.php" class="nav-link active">
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
        <h2>System Settings</h2>
        <p>Manage your account details and view current system status.</p>
      </div>
      <div class="user-info">
        <div class="text-end">
          <div class="fw-bold text-dark"><?php echo h($full_name); ?></div>
          <small class="text-muted"><?php echo h($role_label); ?></small>
        </div>
        <div class="user-avatar"><?php echo h(mb_substr($full_name, 0, 1) ?: 'U'); ?></div>
      </div>
    </div>

    <?php if ($message !== ''): ?>
      <div class="alert alert-<?php echo h($message_type); ?> alert-dismissible fade show" role="alert" aria-live="polite">
        <?php if (!empty($errors)): ?>
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
              <li><?php echo h($error); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <?php echo h($message); ?>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card p-4">
          <h4 class="mb-3">My Account</h4>
          <form method="POST" id="settingsForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

            <div class="row g-3">
              <div class="col-md-6">
                <label for="firstname" class="form-label">First Name</label>
                <input type="text" id="firstname" name="firstname" class="form-control"
                       value="<?php echo h($user['firstname']); ?>" maxlength="<?php echo MAX_NAME_LENGTH; ?>" required>
              </div>
              <div class="col-md-6">
                <label for="lastname" class="form-label">Last Name</label>
                <input type="text" id="lastname" name="lastname" class="form-control"
                       value="<?php echo h($user['lastname']); ?>" maxlength="<?php echo MAX_NAME_LENGTH; ?>" required>
              </div>
              <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" class="form-control" value="<?php echo h($user['email']); ?>" disabled>
              </div>
              <div class="col-md-6">
                <label for="phone" class="form-label">Contact</label>
                <input type="tel" id="phone" name="phone" class="form-control"
                       value="<?php echo h($user['contact'] ?? ''); ?>"
                       pattern="^[0-9+\-\s()]{6,20}$" maxlength="<?php echo MAX_PHONE_LENGTH; ?>"
                       placeholder="e.g. +250 7xx xxx xxx" autocomplete="tel">
              </div>
            </div>

            <hr class="section-divider">
            <div class="section-subtitle">Change Password</div>
            <p class="section-hint">Leave these fields blank to keep your current password.</p>

            <div class="row g-3">
              <div class="col-md-12">
                <label for="current_password" class="form-label">Current Password</label>
                <div class="password-field">
                  <input type="password" id="current_password" name="current_password" class="form-control"
                         autocomplete="current-password">
                  <button type="button" class="password-toggle" data-target="current_password" aria-label="Show current password">
                    <i class="far fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-6">
                <label for="new_password" class="form-label">New Password</label>
                <div class="password-field">
                  <input type="password" id="new_password" name="new_password" class="form-control"
                         minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" autocomplete="new-password">
                  <button type="button" class="password-toggle" data-target="new_password" aria-label="Show new password">
                    <i class="far fa-eye"></i>
                  </button>
                </div>
                <div class="form-text">At least <?php echo MIN_PASSWORD_LENGTH; ?> characters.</div>
              </div>
              <div class="col-md-6">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="password-field">
                  <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                         minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" autocomplete="new-password">
                  <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show confirm password">
                    <i class="far fa-eye"></i>
                  </button>
                </div>
                <div class="invalid-feedback" id="confirmFeedback">Passwords do not match.</div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4" id="saveBtn">
              <i class="fas fa-save me-2"></i><span id="saveBtnText">Save Settings</span>
            </button>
          </form>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card p-4">
          <h4 class="mb-3">System Snapshot</h4>
          <div class="snapshot-row">
            <span class="snapshot-label">Role</span>
            <span class="snapshot-value"><?php echo h($role_label); ?></span>
          </div>
          <div class="snapshot-row">
            <span class="snapshot-label">Status</span>
            <span class="snapshot-value"><?php echo h(ucfirst($user['status'] ?? 'unknown')); ?></span>
          </div>
          <div class="snapshot-row">
            <span class="snapshot-label">Member Since</span>
            <span class="snapshot-value">
              <?php
                $created_at = $user['created_at'] ?? null;
                $created_ts = $created_at ? strtotime($created_at) : false;
                echo h($created_ts ? date('M j, Y', $created_ts) : 'N/A');
              ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <?php if ($role === 'admin'): ?>
      <div class="row g-4 mt-1">
        <div class="col-lg-6">
          <div class="card p-4">
            <h4 class="mb-3">Admin Controls</h4>
            <p class="mb-3 text-muted">Use these shortcuts to manage the live system quickly.</p>
            <div class="d-grid gap-2">
              <a href="admin.php?section=dashboard" class="btn btn-outline-primary">Dashboard Overview</a>
              <a href="admin.php?section=manage-users" class="btn btn-outline-primary">Manage Users</a>
              <a href="admin.php?section=manage-drivers" class="btn btn-outline-primary">Manage Drivers</a>
              <a href="admin.php?section=manage-trips" class="btn btn-outline-primary">Manage Trips</a>
              <a href="admin.php?section=manage-bookings" class="btn btn-outline-primary">Manage Bookings</a>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card p-4">
            <h4 class="mb-3">Admin Summary</h4>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <div class="stat-mini">
                  <div class="stat-number"><?php echo $active_users; ?></div>
                  <div class="stat-label">Active Users</div>
                </div>
              </div>
              <div class="col-6">
                <div class="stat-mini">
                  <div class="stat-number"><?php echo $available_trips; ?></div>
                  <div class="stat-label">Available Trips</div>
                </div>
              </div>
            </div>
            <div class="snapshot-row">
              <span class="snapshot-label">Today's Revenue</span>
              <span class="snapshot-value"><?php echo number_format($today_revenue, 2); ?> FRW</span>
            </div>
            <div class="snapshot-row">
              <span class="snapshot-label">System Mode</span>
              <span class="snapshot-value text-success">Operational</span>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function () {
      // Password visibility toggles
      document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const input = document.getElementById(btn.dataset.target);
          const icon = btn.querySelector('i');
          const showing = input.type === 'text';
          input.type = showing ? 'password' : 'text';
          icon.classList.toggle('fa-eye', showing);
          icon.classList.toggle('fa-eye-slash', !showing);
        });
      });

      const form = document.getElementById('settingsForm');
      const saveBtn = document.getElementById('saveBtn');
      const saveBtnText = document.getElementById('saveBtnText');
      const newPassword = document.getElementById('new_password');
      const confirmPassword = document.getElementById('confirm_password');
      const currentPassword = document.getElementById('current_password');

      function validateConfirmMatch() {
        if (newPassword.value !== '' && newPassword.value !== confirmPassword.value) {
          confirmPassword.classList.add('is-invalid');
          confirmPassword.setCustomValidity('Passwords do not match.');
        } else {
          confirmPassword.classList.remove('is-invalid');
          confirmPassword.setCustomValidity('');
        }
      }

      newPassword.addEventListener('input', validateConfirmMatch);
      confirmPassword.addEventListener('input', validateConfirmMatch);

      form.addEventListener('submit', function (e) {
        validateConfirmMatch();

        if (newPassword.value !== '' && currentPassword.value === '') {
          currentPassword.classList.add('is-invalid');
          currentPassword.setCustomValidity('Enter your current password to set a new one.');
        } else {
          currentPassword.classList.remove('is-invalid');
          currentPassword.setCustomValidity('');
        }

        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
          form.classList.add('was-validated');
          return;
        }

        // Prevent double submission
        saveBtn.disabled = true;
        saveBtnText.textContent = 'Saving...';
      });
    })();
  </script>
</body>
</html>
<?php $conn->close(); ?>