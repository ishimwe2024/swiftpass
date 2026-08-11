
<?php
session_start();
include('connection.php');

// ========== ADMIN AUTH ==========
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$serverError = '';
$serverSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first = trim($_POST['firstName'] ?? '');
    $last  = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['contact'] ?? '');
    $role  = trim($_POST['role'] ?? 'passenger');

    $allowedRoles = ['admin', 'passenger'];

    // ========== VALIDATE ROLE ==========
    if (!in_array($role, $allowedRoles)) {
        $serverError = "❌ Invalid role selected.";
    }

    // ========== VALIDATE FIRST NAME ==========
    if (empty($serverError) && strlen($first) < 2) {
        $serverError = "❌ First name must be at least 2 characters.";
    }

    // ========== VALIDATE LAST NAME ==========
    if (empty($serverError) && strlen($last) < 2) {
        $serverError = "❌ Last name must be at least 2 characters.";
    }

    // ========== VALIDATE EMAIL ==========
    if (empty($serverError) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $serverError = "❌ Please enter a valid email address.";
    }

    // ========== VALIDATE PHONE ==========
    if (empty($serverError)) {
        $cleanPhone = preg_replace('/\s+/', '', $phone);

        if (!preg_match('/^[\+]?[0-9\-\(\)]{10,}$/', $cleanPhone)) {
            $serverError = "❌ Please enter a valid phone number.";
        }
    }

    // ========== CHECK EMAIL ==========
    if (empty($serverError)) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");

        if ($check) {
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $serverError = "❌ This email is already registered.";
            }

            $check->close();
        } else {
            $serverError = "❌ Database error while checking email.";
        }
    }

    // ========== CHECK PHONE ==========
    if (empty($serverError)) {
        $check = $conn->prepare("SELECT id FROM users WHERE contact = ?");

        if ($check) {
            $check->bind_param("s", $phone);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $serverError = "❌ This phone number is already registered.";
            }

            $check->close();
        } else {
            $serverError = "❌ Database error while checking phone number.";
        }
    }

    // ========== CREATE USER ==========
    if (empty($serverError)) {

        // Default password for every newly created user
        $defaultPassword = '123';

        // Hash the default password before saving
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users
            (firstname, lastname, email, contact, password, role)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        if ($stmt) {

            $stmt->bind_param(
                "ssssss",
                $first,
                $last,
                $email,
                $phone,
                $hashedPassword,
                $role
            );

            if ($stmt->execute()) {

                $serverSuccess =
                    "✅ User <strong>" .
                    htmlspecialchars($first . ' ' . $last) .
                    "</strong> created successfully!<br>" .
                    "Default password: <strong>123</strong>";

                $_POST = [];

            } else {
                $serverError = "❌ Error: " . $stmt->error;
            }

            $stmt->close();

        } else {
            $serverError = "❌ Database error.";
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin - Add New User | SwiftPass</title>

    <meta
        name="description"
        content="Create a new user account with a specific role."
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Mono:wght@500;600&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>

        /* ================================
           GLOBAL
        ================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {

            --primary-blue: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-blue: #3b82f6;

            --accent-cyan: #06b6d4;
            --accent-cyan-dark: #0891b2;

            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-light: #94a3b8;

            --surface: #ffffff;
            --background: #f8fafc;

            --border: #e2e8f0;
            --border-focus: #3b82f6;

            --success: #10b981;
            --success-bg: #d1fae5;

            --warning: #f59e0b;

            --error: #ef4444;
            --error-bg: #fee2e2;

            --gradient-primary:
                linear-gradient(
                    135deg,
                    var(--primary-blue) 0%,
                    var(--primary-dark) 100%
                );

            --gradient-secondary:
                linear-gradient(
                    135deg,
                    var(--secondary-blue) 0%,
                    var(--accent-cyan) 100%
                );

            --gradient-bg:
                linear-gradient(
                    135deg,
                    #e0f2fe 0%,
                    #f0f9ff 50%,
                    #e0f2fe 100%
                );

            --shadow-card:
                0 25px 50px -18px rgba(29, 78, 216, 0.35);

            --radius: 20px;
        }

        body {

            font-family:
                'Inter',
                -apple-system,
                BlinkMacSystemFont,
                'Segoe UI',
                sans-serif;

            background: var(--gradient-bg);

            color: var(--text-primary);

            line-height: 1.6;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 2.5rem 1rem;
        }

        /* ================================
           MAIN CONTAINER
        ================================= */

        .pass-shell {

            width: 100%;

            max-width: 900px;
        }

        .pass-card {

            display: flex;

            align-items: stretch;

            background: var(--surface);

            border-radius: var(--radius);

            box-shadow: var(--shadow-card);

            overflow: visible;

            position: relative;
        }

        /* ================================
           LEFT PANEL
        ================================= */

        .stub-panel {

            flex: 0 0 268px;

            background: var(--gradient-primary);

            color: #fff;

            padding: 2.25rem 1.85rem;

            border-radius:
                var(--radius)
                0
                0
                var(--radius);

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            position: relative;

            overflow: hidden;
        }

        .stub-panel::before {

            content: '';

            position: absolute;

            inset: 0;

            background-image:
                repeating-linear-gradient(
                    115deg,
                    rgba(255, 255, 255, 0.06) 0 2px,
                    transparent 2px 26px
                );

            pointer-events: none;
        }

        .stub-brand {

            display: flex;

            align-items: center;

            gap: 0.65rem;

            position: relative;

            z-index: 1;
        }

        .stub-brand .icon {

            width: 38px;

            height: 38px;

            border-radius: 10px;

            background: var(--accent-cyan);

            color: var(--primary-dark);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 1.05rem;

            flex-shrink: 0;
        }

        .stub-brand .name {

            font-family: 'Space Grotesk', sans-serif;

            font-weight: 700;

            font-size: 1.4rem;

            letter-spacing: -0.01em;
        }

        .stub-tagline {

            margin-top: 0.35rem;

            font-size: 0.72rem;

            letter-spacing: 0.14em;

            text-transform: uppercase;

            color: rgba(255, 255, 255, 0.6);

            position: relative;

            z-index: 1;
        }

        .route-strip {

            position: relative;

            z-index: 1;

            display: flex;

            align-items: center;

            gap: 0.6rem;

            margin: 2.1rem 0 2rem;
        }

        .route-point {

            font-family: 'IBM Plex Mono', monospace;

            font-size: 0.62rem;

            letter-spacing: 0.08em;

            color: var(--accent-cyan);

            white-space: nowrap;
        }

        .route-track {

            flex: 1;

            height: 1px;

            background-image:
                linear-gradient(
                    to right,
                    rgba(255,255,255,0.35) 0 6px,
                    transparent 6px 11px
                );

            background-size: 11px 1px;

            background-repeat: repeat-x;

            position: relative;
        }

        .route-bus {

            position: absolute;

            top: 50%;

            left: 0;

            transform: translate(-50%, -50%);

            width: 22px;

            height: 22px;

            border-radius: 50%;

            background: var(--accent-cyan);

            color: var(--primary-dark);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 0.65rem;

            animation: travel 4.5s ease-in-out infinite;
        }

        @keyframes travel {

            0%, 100% {
                left: 4%;
            }

            50% {
                left: 96%;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .route-bus {

                animation: none;

                left: 50%;
            }
        }

        .stub-barcode {

            position: relative;

            z-index: 1;

            height: 34px;

            margin-bottom: 1.75rem;

            background-image:
                repeating-linear-gradient(
                    to right,
                    rgba(255, 255, 255, 0.85) 0px,
                    rgba(255, 255, 255, 0.85) 2px,
                    transparent 2px,
                    transparent 5px,
                    rgba(255, 255, 255, 0.85) 5px,
                    rgba(255, 255, 255, 0.85) 8px,
                    transparent 8px,
                    transparent 10px,
                    rgba(255, 255, 255, 0.85) 10px,
                    rgba(255, 255, 255, 0.85) 13px,
                    transparent 13px,
                    transparent 15px
                );

            opacity: 0.4;
        }

        .stub-meta {

            position: relative;

            z-index: 1;

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 1.1rem;

            font-family: 'IBM Plex Mono', monospace;
        }

        .stub-meta .meta-label {

            display: block;

            font-size: 0.6rem;

            letter-spacing: 0.1em;

            color: rgba(255, 255, 255, 0.55);

            margin-bottom: 0.2rem;
        }

        .stub-meta .meta-value {

            font-size: 0.82rem;

            font-weight: 600;

            color: #fff;
        }

        /* ================================
           PERFORATION
        ================================= */

        .perforation {

            flex: 0 0 26px;

            position: relative;

            background: var(--surface);
        }

        .perforation::before {

            content: '';

            position: absolute;

            top: 0;

            bottom: 0;

            left: 50%;

            width: 0;

            border-left: 2px dashed var(--border);
        }

        .perforation .notch {

            position: absolute;

            left: 50%;

            width: 30px;

            height: 30px;

            background: var(--background);

            border-radius: 50%;

            transform: translateX(-50%);
        }

        .perforation .notch.top {

            top: -15px;
        }

        .perforation .notch.bottom {

            bottom: -15px;
        }

        /* ================================
           FORM PANEL
        ================================= */

        .form-panel {

            flex: 1;

            padding: 2.5rem 2.5rem 2.25rem;

            min-width: 0;
        }

        .form-heading {

            margin-bottom: 1.6rem;
        }

        .form-heading h1 {

            font-family: 'Space Grotesk', sans-serif;

            font-size: 1.55rem;

            font-weight: 700;

            letter-spacing: -0.01em;

            color: var(--text-primary);
        }

        .form-heading p {

            margin-top: 0.35rem;

            font-size: 0.9rem;

            color: var(--text-secondary);
        }

        .form-group {

            margin-bottom: 1.35rem;

            position: relative;
        }

        .form-row {

            display: flex;

            gap: 1rem;
        }

        .form-row .form-group {

            flex: 1;
        }

        .form-label {

            display: block;

            font-size: 0.8rem;

            font-weight: 600;

            color: var(--text-primary);

            margin-bottom: 0.4rem;
        }

        .form-label i {

            color: var(--text-light);

            width: 14px;
        }

        .form-input {

            width: 100%;

            padding: 0.8rem 1rem;

            border: 1.5px solid var(--border);

            border-radius: 10px;

            font-size: 0.95rem;

            font-family: inherit;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;

            background: #fff;

            color: var(--text-primary);
        }

        .form-input::placeholder {

            color: var(--text-light);
        }

        .form-input:focus,
        .form-input:focus-visible {

            outline: none;

            border-color: var(--border-focus);

            box-shadow:
                0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .form-input.error {

            border-color: var(--error);

            animation: shake 0.3s ease-in-out;
        }

        .form-input.success {

            border-color: var(--success);
        }

        @keyframes shake {

            0%, 100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-4px);
            }

            75% {
                transform: translateX(4px);
            }
        }

        .error-message,
        .success-message {

            font-size: 0.78rem;

            margin-top: 0.35rem;

            display: none;

            align-items: center;

            gap: 0.3rem;
        }

        .error-message {

            color: var(--error);
        }

        .success-message {

            color: var(--success);
        }

        .error-message.show,
        .success-message.show {

            display: flex;
        }

        /* ================================
           BUTTON
        ================================= */

        .btn-primary {

            width: 100%;

            padding: 0.95rem;

            border: none;

            border-radius: 10px;

            font-size: 0.98rem;

            font-weight: 700;

            font-family: 'Space Grotesk', sans-serif;

            letter-spacing: 0.01em;

            cursor: pointer;

            background: var(--gradient-primary);

            color: #fff;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 0.55rem;

            transition:
                background 0.2s ease,
                transform 0.15s ease,
                box-shadow 0.2s ease;

            margin-bottom: 1.4rem;

            box-shadow:
                0 10px 20px -8px rgba(37, 99, 235, 0.5);
        }

        .btn-primary:hover {

            background: var(--gradient-secondary);

            transform: translateY(-1px);
        }

        .btn-primary:active {

            transform: translateY(0);
        }

        .btn-primary:focus-visible {

            outline: 3px solid var(--primary-dark);

            outline-offset: 2px;
        }

        .btn-primary.loading {

            pointer-events: none;

            opacity: 0.75;
        }

        .btn-primary.loading::after {

            content: '';

            width: 16px;

            height: 16px;

            border: 2px solid rgba(255,255,255,0.4);

            border-top: 2px solid #fff;

            border-radius: 50%;

            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {

            to {
                transform: rotate(360deg);
            }
        }

        /* ================================
           SERVER MESSAGE
        ================================= */

        .server-message {

            padding: 0.85rem 1rem;

            border-radius: 10px;

            margin-bottom: 1.4rem;

            font-size: 0.87rem;

            display: flex;

            align-items: flex-start;

            gap: 0.55rem;
        }

        .server-message.error {

            background: var(--error-bg);

            color: #b91c1c;

            border-left:
                3px solid var(--error);
        }

        .server-message.success {

            background: var(--success-bg);

            color: #065f46;

            border-left:
                3px solid var(--success);
        }

        .server-message a {

            color: inherit;

            font-weight: 700;
        }

        /* ================================
           BACK LINK
        ================================= */

        .signin-link {

            text-align: center;

            font-size: 0.9rem;

            color: var(--text-secondary);
        }

        .signin-link a {

            color: var(--primary-blue);

            font-weight: 700;

            text-decoration: none;
        }

        .signin-link a:hover {

            text-decoration: underline;
        }

        /* ================================
           RESPONSIVE
        ================================= */

        @media (max-width: 760px) {

            .pass-card {

                flex-direction: column;

                border-radius: var(--radius);
            }

            .stub-panel {

                flex: none;

                border-radius:
                    var(--radius)
                    var(--radius)
                    0
                    0;

                padding:
                    1.9rem
                    1.6rem
                    2.3rem;
            }

            .stub-meta {

                grid-template-columns:
                    1fr 1fr 1fr;
            }

            .perforation {

                flex: none;

                height: 26px;
            }

            .perforation::before {

                left: 0;

                right: 0;

                top: 50%;

                bottom: auto;

                width: auto;

                height: 0;

                border-left: none;

                border-top: 2px dashed var(--border);
            }

            .perforation .notch {

                top: 50%;

                left: -15px;

                transform: translateY(-50%);
            }

            .perforation .notch.bottom {

                left: auto;

                right: -15px;

                bottom: auto;
            }

            .form-panel {

                padding:
                    2rem
                    1.5rem
                    1.75rem;
            }
        }

        @media (max-width: 480px) {

            body {

                padding:
                    1.25rem
                    0.75rem;
            }

            .form-row {

                flex-direction: column;

                gap: 0;
            }

            .stub-brand .name {

                font-size: 1.25rem;
            }
        }

    </style>

</head>

<body>

<div class="pass-shell">

    <div class="pass-card">

        <!-- ================================
             LEFT TICKET PANEL
        ================================= -->

        <div class="stub-panel">

            <div>

                <div class="stub-brand">

                    <span class="icon">
                        <i class="fas fa-bus"></i>
                    </span>

                    <span class="name">
                        SwiftPass
                    </span>

                </div>

                <p class="stub-tagline">
                    Admin User Creation
                </p>

                <div class="route-strip">

                    <span class="route-point">
                        REGISTER
                    </span>

                    <span class="route-track">

                        <span class="route-bus">

                            <i class="fas fa-bus"></i>

                        </span>

                    </span>

                    <span class="route-point">
                        ACTIVE
                    </span>

                </div>

                <div
                    class="stub-barcode"
                    aria-hidden="true"
                ></div>

            </div>

            <div class="stub-meta">

                <div>

                    <span class="meta-label">
                        ROLE
                    </span>

                    <span
                        class="meta-value"
                        id="roleDisplay"
                    >
                        PASSENGER
                    </span>

                </div>

                <div>

                    <span class="meta-label">
                        TICKET NO
                    </span>

                    <span
                        class="meta-value"
                        id="ticketNo"
                    >
                        SP-000000
                    </span>

                </div>

                <div>

                    <span class="meta-label">
                        CLASS
                    </span>

                    <span class="meta-value">
                        STANDARD
                    </span>

                </div>

                <div>

                    <span class="meta-label">
                        STATUS
                    </span>

                    <span class="meta-value">
                        PENDING
                    </span>

                </div>

            </div>

        </div>


        <!-- ================================
             PERFORATION
        ================================= -->

        <div
            class="perforation"
            aria-hidden="true"
        >

            <span class="notch top"></span>

            <span class="notch bottom"></span>

        </div>


        <!-- ================================
             FORM PANEL
        ================================= -->

        <div class="form-panel">

            <div class="form-heading">

                <h1>
                    Add New User
                </h1>

                <p>
                    Create a user account and assign their role.
                </p>

            </div>


            <!-- ERROR MESSAGE -->

            <?php if (!empty($serverError)): ?>

                <div class="server-message error">

                    <i class="fas fa-exclamation-circle"></i>

                    <span>
                        <?= htmlspecialchars($serverError) ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- SUCCESS MESSAGE -->

            <?php if (!empty($serverSuccess)): ?>

                <div class="server-message success">

                    <i class="fas fa-check-circle"></i>

                    <span>
                        <?= $serverSuccess ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- ================================
                 USER FORM
            ================================= -->

            <form
                method="POST"
                action=""
                novalidate
                id="adminUserForm"
            >

                <!-- FIRST + LAST NAME -->

                <div class="form-row">

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="firstName"
                        >

                            <i class="fas fa-user"></i>

                            First Name

                        </label>

                        <input
                            type="text"
                            id="firstName"
                            name="firstName"
                            class="form-input"
                            placeholder="Enter first name"
                            required
                            autocomplete="given-name"
                            value="<?= isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : '' ?>"
                        >

                        <div
                            class="error-message"
                            id="firstNameError"
                        >

                            <i class="fas fa-exclamation-circle"></i>

                            <span>
                                First name must be at least 2 characters
                            </span>

                        </div>

                    </div>


                    <div class="form-group">

                        <label
                            class="form-label"
                            for="lastName"
                        >

                            <i class="fas fa-user"></i>

                            Last Name

                        </label>

                        <input
                            type="text"
                            id="lastName"
                            name="lastName"
                            class="form-input"
                            placeholder="Enter last name"
                            required
                            autocomplete="family-name"
                            value="<?= isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : '' ?>"
                        >

                        <div
                            class="error-message"
                            id="lastNameError"
                        >

                            <i class="fas fa-exclamation-circle"></i>

                            <span>
                                Last name must be at least 2 characters
                            </span>

                        </div>

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label
                        class="form-label"
                        for="email"
                    >

                        <i class="fas fa-envelope"></i>

                        Email Address

                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="Enter email"
                        required
                        autocomplete="email"
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                    >

                    <div
                        class="error-message"
                        id="emailError"
                    >

                        <i class="fas fa-exclamation-circle"></i>

                        <span>
                            Please enter a valid email
                        </span>

                    </div>

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label
                        class="form-label"
                        for="phone"
                    >

                        <i class="fas fa-phone"></i>

                        Phone Number

                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="contact"
                        class="form-input"
                        placeholder="Enter phone number"
                        required
                        autocomplete="tel"
                        value="<?= isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : '' ?>"
                    >

                    <div
                        class="error-message"
                        id="phoneError"
                    >

                        <i class="fas fa-exclamation-circle"></i>

                        <span>
                            Enter at least 10 digits
                        </span>

                    </div>

                </div>


                <!-- ROLE -->

                <div class="form-group">

                    <label
                        class="form-label"
                        for="role"
                    >

                        <i class="fas fa-user-tag"></i>

                        User Role

                    </label>

                    <select
                        id="role"
                        name="role"
                        class="form-input"
                        required
                    >

                        <option
                            value="passenger"
                            <?= (
                                isset($_POST['role']) &&
                                $_POST['role'] === 'passenger'
                            ) ? 'selected' : '' ?>
                        >
                            Passenger
                        </option>

                        <option
                            value="admin"
                            <?= (
                                isset($_POST['role']) &&
                                $_POST['role'] === 'admin'
                            ) ? 'selected' : '' ?>
                        >
                            Admin
                        </option>

                    </select>

                </div>


                <!-- ================================
                     CREATE USER BUTTON
                ================================= -->

                <button
                    type="submit"
                    class="btn-primary"
                    id="createUserBtn"
                >

                    <i class="fas fa-user-plus"></i>

                    Create User

                </button>


                <!-- BACK LINK -->

                <div class="signin-link">

                    <a
                        href="admin.php?section=manage-users"
                    >

                        <i class="fas fa-arrow-left"></i>

                        Back to User Management

                    </a>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

    // ==========================================
    // GENERATE TICKET NUMBER
    // ==========================================

    document.getElementById('ticketNo').textContent =
        'SP-' +
        Math.floor(
            100000 +
            Math.random() * 900000
        );


    // ==========================================
    // ROLE DISPLAY
    // ==========================================

    const roleSelect =
        document.getElementById('role');

    const roleDisplay =
        document.getElementById('roleDisplay');

    roleSelect.addEventListener(
        'change',
        function () {

            roleDisplay.textContent =
                this.options[
                    this.selectedIndex
                ].text.toUpperCase();

        }
    );

    // Initial role display

    roleDisplay.textContent =
        roleSelect.options[
            roleSelect.selectedIndex
        ].text.toUpperCase();


    // ==========================================
    // FORM ELEMENTS
    // ==========================================

    const form =
        document.getElementById('adminUserForm');

    const inputs = {

        firstName:
            document.getElementById('firstName'),

        lastName:
            document.getElementById('lastName'),

        email:
            document.getElementById('email'),

        phone:
            document.getElementById('phone')

    };

    const createUserBtn =
        document.getElementById('createUserBtn');


    // ==========================================
    // VALIDATION FUNCTIONS
    // ==========================================

    function validateEmail(email) {

        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/
            .test(email);

    }


    function validatePhone(contact) {

        return /^[\+]?[0-9\-\(\)]{10,}$/
            .test(
                contact.replace(/\s/g, '')
            );

    }


    function showError(inputId) {

        const input =
            inputs[inputId];

        if (!input) return;

        const errDiv =
            document.getElementById(
                inputId + 'Error'
            );

        const succDiv =
            document.getElementById(
                inputId + 'Success'
            );

        input.classList.add('error');

        input.classList.remove('success');

        if (errDiv) {

            errDiv.classList.add('show');

        }

        if (succDiv) {

            succDiv.classList.remove('show');

        }

    }


    function showSuccess(inputId) {

        const input =
            inputs[inputId];

        if (!input) return;

        const errDiv =
            document.getElementById(
                inputId + 'Error'
            );

        const succDiv =
            document.getElementById(
                inputId + 'Success'
            );

        input.classList.add('success');

        input.classList.remove('error');

        if (errDiv) {

            errDiv.classList.remove('show');

        }

        if (succDiv) {

            succDiv.classList.add('show');

        }

    }


    function clearValidation(inputId) {

        const input =
            inputs[inputId];

        if (!input) return;

        const errDiv =
            document.getElementById(
                inputId + 'Error'
            );

        const succDiv =
            document.getElementById(
                inputId + 'Success'
            );

        input.classList.remove(
            'error',
            'success'
        );

        if (errDiv) {

            errDiv.classList.remove(
                'show'
            );

        }

        if (succDiv) {

            succDiv.classList.remove(
                'show'
            );

        }

    }


    // ==========================================
    // LIVE VALIDATION
    // ==========================================

    inputs.firstName.addEventListener(
        'blur',
        function () {

            if (this.value.trim().length < 2) {

                showError('firstName');

            } else {

                showSuccess('firstName');

            }

        }
    );


    inputs.lastName.addEventListener(
        'blur',
        function () {

            if (this.value.trim().length < 2) {

                showError('lastName');

            } else {

                showSuccess('lastName');

            }

        }
    );


    inputs.email.addEventListener(
        'blur',
        function () {

            if (!validateEmail(this.value)) {

                showError('email');

            } else {

                showSuccess('email');

            }

        }
    );


    inputs.phone.addEventListener(
        'blur',
        function () {

            if (!validatePhone(this.value)) {

                showError('phone');

            } else {

                showSuccess('phone');

            }

        }
    );


    // Clear validation when typing

    Object.keys(inputs).forEach(
        function (key) {

            if (inputs[key]) {

                inputs[key].addEventListener(
                    'input',
                    function () {

                        clearValidation(key);

                    }
                );

            }

        }
    );


    // ==========================================
    // FORM SUBMISSION VALIDATION
    // ==========================================

    form.addEventListener(
        'submit',
        function (e) {

            let isValid = true;


            if (
                inputs.firstName.value
                    .trim()
                    .length < 2
            ) {

                showError('firstName');

                isValid = false;

            }


            if (
                inputs.lastName.value
                    .trim()
                    .length < 2
            ) {

                showError('lastName');

                isValid = false;

            }


            if (
                !validateEmail(
                    inputs.email.value
                )
            ) {

                showError('email');

                isValid = false;

            }


            if (
                !validatePhone(
                    inputs.phone.value
                )
            ) {

                showError('phone');

                isValid = false;

            }


            if (!isValid) {

                e.preventDefault();

                return;

            }


            // Show loading state

            createUserBtn.classList.add(
                'loading'
            );

        }
    );

</script>

</body>

</html>