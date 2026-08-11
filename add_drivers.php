<?php
include 'connection.php';

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $contact   = trim($_POST['contact'] ?? '');
    $license   = trim($_POST['license'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    // =====================================================
    // VALIDATION
    // =====================================================

    if ($firstname === '' || $lastname === '' || $contact === '' || $license === '' || $email === '') {

        $error_message =
            "❌ Error: First name, last name, contact, email and license are required.";

    } else {

        // =====================================================
        // VALIDATE EMAIL FORMAT
        // =====================================================

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error_message =
                "❌ Error: Please enter a valid email address.";

        }

        // =====================================================
        // VALIDATE PHONE NUMBER (10 digits, starts with 078, 072, 073, or 079)
        // =====================================================

        if ($error_message === '') {

            // Remove all non-digit characters
            $clean_contact = preg_replace('/[^0-9]/', '', $contact);

            // Check if it's exactly 10 digits and starts with 078, 072, 073, or 079
            if (!preg_match('/^(078|072|073|079)\d{7}$/', $clean_contact)) {

                $error_message =
                    "❌ Error: Phone number must be 10 digits starting with 078, 072, 073, or 079 (e.g., 0781234567)";
            }
        }


        // =====================================================
        // CHECK IF EMAIL ALREADY EXISTS IN USERS
        // =====================================================

        if ($error_message === '') {

            $stmt = $conn->prepare(
                "SELECT id
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            if (!$stmt) {

                $error_message =
                    "❌ Database error: " . $conn->error;

            } else {

                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {

                    $error_message =
                        "❌ This email address is already registered.";
                }

                $stmt->close();
            }
        }


        // =====================================================
        // CHECK IF CONTACT ALREADY EXISTS IN USERS
        // =====================================================

        if ($error_message === '') {

            $stmt = $conn->prepare(
                "SELECT id
                 FROM users
                 WHERE contact = ?
                 LIMIT 1"
            );

            if (!$stmt) {

                $error_message =
                    "❌ Database error: " . $conn->error;

            } else {

                $stmt->bind_param("s", $contact);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {

                    $error_message =
                        "❌ This contact number already has a user account.";
                }

                $stmt->close();
            }
        }


        // =====================================================
        // CHECK IF DRIVER CONTACT ALREADY EXISTS
        // =====================================================

        if ($error_message === '') {

            $stmt = $conn->prepare(
                "SELECT driver_id
                 FROM drivers
                 WHERE contact = ?
                 LIMIT 1"
            );

            if (!$stmt) {

                $error_message =
                    "❌ Database error: " . $conn->error;

            } else {

                $stmt->bind_param("s", $contact);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {

                    $error_message =
                        "❌ This contact number already belongs to a driver.";
                }

                $stmt->close();
            }
        }


        // =====================================================
        // CHECK IF LICENSE ALREADY EXISTS
        // =====================================================

        if ($error_message === '') {

            $stmt = $conn->prepare(
                "SELECT driver_id
                 FROM drivers
                 WHERE license = ?
                 LIMIT 1"
            );

            if (!$stmt) {

                $error_message =
                    "❌ Database error: " . $conn->error;

            } else {

                $stmt->bind_param("s", $license);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {

                    $error_message =
                        "❌ This license number already belongs to a driver.";
                }

                $stmt->close();
            }
        }


        // =====================================================
        // CREATE USER FIRST, THEN DRIVER
        // =====================================================

        if ($error_message === '') {

            $conn->begin_transaction();

            try {

                // =================================================
                // DEFAULT PASSWORD = 123
                // =================================================

                $default_password = "123";

                $hashed_password = password_hash(
                    $default_password,
                    PASSWORD_DEFAULT
                );


                // =================================================
                // ROLE
                // =================================================

                $role = "driver";


                // =================================================
                // STEP 1: INSERT INTO USERS
                // =================================================

                $userStmt = $conn->prepare(
                    "INSERT INTO users
                    (
                        firstname,
                        lastname,
                        email,
                        contact,
                        password,
                        role
                    )
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                if (!$userStmt) {

                    throw new Exception(
                        "Unable to prepare users query: " .
                        $conn->error
                    );
                }


                $userStmt->bind_param(
                    "ssssss",
                    $firstname,
                    $lastname,
                    $email,
                    $contact,
                    $hashed_password,
                    $role
                );


                if (!$userStmt->execute()) {

                    throw new Exception(
                        "Failed to insert user: " .
                        $userStmt->error
                    );
                }


                // =================================================
                // GET THE NEW USER ID
                // =================================================

                $user_id = $conn->insert_id;


                $userStmt->close();


                // =================================================
                // VERIFY USER ID
                // =================================================

                if (!$user_id || $user_id <= 0) {

                    throw new Exception(
                        "The user was inserted, but a valid user ID was not returned."
                    );
                }


                // =================================================
                // STEP 2: INSERT INTO DRIVERS (WITH user_id)
                // =================================================

                /*
                 * Now recording user_id in drivers table
                 * as a regular data field (no foreign key constraint)
                 */

                $driverStmt = $conn->prepare(
                    "INSERT INTO drivers
                    (
                        name,
                        contact,
                        license,
                        user_id
                    )
                    VALUES (?, ?, ?, ?)"
                );


                if (!$driverStmt) {

                    throw new Exception(
                        "Unable to prepare drivers query: " .
                        $conn->error
                    );
                }


                // Combine firstname and lastname for the name field
                $full_name = $firstname . ' ' . $lastname;

                $driverStmt->bind_param(
                    "sssi",
                    $full_name,
                    $contact,
                    $license,
                    $user_id
                );


                if (!$driverStmt->execute()) {

                    throw new Exception(
                        "Failed to insert driver: " .
                        $driverStmt->error
                    );
                }


                $driverStmt->close();


                // =================================================
                // STEP 3: COMMIT
                // =================================================

                $conn->commit();


                // =================================================
                // SUCCESS MESSAGE
                // =================================================

                $success_message =
                    "✅ Driver added successfully!<br><br>" .

                    "<strong>First Name:</strong> " .
                    htmlspecialchars($firstname) .
                    "<br>" .

                    "<strong>Last Name:</strong> " .
                    htmlspecialchars($lastname) .
                    "<br>" .

                    "<strong>Full Name:</strong> " .
                    htmlspecialchars($full_name) .
                    "<br>" .

                    "<strong>Email:</strong> " .
                    htmlspecialchars($email) .
                    "<br>" .

                    "<strong>Contact:</strong> " .
                    htmlspecialchars($contact) .
                    "<br>" .

                    "<strong>License:</strong> " .
                    htmlspecialchars($license) .
                    "<br>" .

                    "<strong>User ID:</strong> " .
                    htmlspecialchars($user_id) .
                    "<br>" .

                    "<strong>User Account:</strong> Created (Role: Driver)" .
                    "<br>" .

                    "<strong>Driver Record:</strong> Created with user_id: " .
                    htmlspecialchars($user_id) .
                    "<br>" .

                    "<strong>Default Password:</strong> 123";


                // Clear form
                $_POST = [];


            } catch (Exception $e) {

                $conn->rollback();

                $error_message =
                    "❌ Error: " .
                    htmlspecialchars(
                        $e->getMessage()
                    );
            }
        }
    }
}
?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Add New Driver - SwiftPass
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >


    <style>

        :root {

            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;

        }


        body {

            background:
                linear-gradient(
                    135deg,
                    #191e32 0%,
                    #1a151f 100%
                );

            min-height: 100vh;

            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

            padding: 20px 0;

        }


        .card {

            border: none;

            border-radius: 15px;

            box-shadow:
                0 10px 30px
                rgba(0, 0, 0, 0.1);

            background:
                rgba(255, 255, 255, 0.95);

            margin-bottom: 2rem;

        }


        .card-header {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--secondary)
                );

            color: white;

            border-radius:
                15px 15px 0 0 !important;

            padding: 1.5rem;

        }


        .btn-success {

            background:
                linear-gradient(
                    135deg,
                    var(--success),
                    #27ae60
                );

            border: none;

            border-radius: 10px;

            padding:
                0.75rem 2rem;

            font-weight: 600;

            transition:
                all 0.3s ease;

        }


        .btn-success:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 5px 15px
                rgba(46, 204, 113, 0.4);

        }


        .btn-warning {

            background:
                linear-gradient(
                    135deg,
                    var(--warning),
                    #e67e22
                );

            border: none;

            border-radius: 10px;

            padding:
                0.5rem 1.5rem;

            transition:
                all 0.3s ease;

        }


        .btn-warning:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 5px 15px
                rgba(243, 156, 18, 0.4);

        }


        .form-control {

            border-radius: 10px;

            border:
                2px solid #e9ecef;

            padding:
                0.75rem 1rem;

            transition:
                all 0.3s ease;

        }


        .form-control:focus {

            border-color:
                var(--secondary);

            box-shadow:
                0 0 0 0.2rem
                rgba(52, 152, 219, 0.25);

        }


        .back-btn {

            background:
                var(--warning);

            color: white;

            border: none;

            border-radius: 10px;

            padding:
                0.5rem 1.5rem;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            gap: 0.5rem;

            transition:
                all 0.3s ease;

        }


        .back-btn:hover {

            background: #e67e22;

            color: white;

            transform:
                translateY(-1px);

        }


        .input-group-text {

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--secondary)
                );

            color: white;

            border: none;

            border-radius:
                10px 0 0 10px;

        }


        .driver-avatar {

            width: 100px;

            height: 100px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    var(--secondary),
                    var(--primary)
                );

            display: flex;

            align-items: center;

            justify-content: center;

            color: white;

            font-size: 2rem;

            margin:
                0 auto 1rem;

        }


        .required-field::after {

            content: " *";

            color:
                var(--danger);

        }


        .login-info {

            background:
                #e8f5e9;

            border-left:
                4px solid var(--success);

            border-radius: 8px;

            padding:
                12px 15px;

            margin-top: 20px;

            color:
                #1b5e20;

        }


        .warning-info {

            background:
                #e3f2fd;

            border-left:
                4px solid var(--secondary);

            border-radius: 8px;

            padding:
                12px 15px;

            margin-top: 10px;

            color:
                #0d47a1;

        }

    </style>

</head>


<body>

<div class="container">

    <div class="row justify-content-center">

        <div class="col-md-8">

            <div class="card">


                <!-- HEADER -->

                <div class="card-header text-center">

                    <h3 class="mb-0">

                        <i
                            class="fas fa-user-tie me-2"
                        ></i>

                        Add New Driver

                    </h3>


                    <p class="mb-0 opacity-75">

                        Register a new driver
                        for SwiftPass

                    </p>

                </div>


                <div class="card-body p-4">


                    <!-- SUCCESS -->

                    <?php if ($success_message !== ''): ?>

                        <div
                            class="alert alert-success alert-dismissible fade show"
                            role="alert"
                        >

                            <i
                                class="fas fa-check-circle me-2"
                            ></i>

                            <?= $success_message ?>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                            ></button>

                        </div>

                    <?php endif; ?>


                    <!-- ERROR -->

                    <?php if ($error_message !== ''): ?>

                        <div
                            class="alert alert-danger alert-dismissible fade show"
                            role="alert"
                        >

                            <i
                                class="fas fa-exclamation-circle me-2"
                            ></i>

                            <?= $error_message ?>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                            ></button>

                        </div>

                    <?php endif; ?>


                    <!-- FORM -->

                    <form
                        method="POST"
                        action=""
                        id="driverForm"
                    >

                        <div class="row g-3">


                            <!-- AVATAR -->

                            <div class="col-12 text-center">

                                <div class="driver-avatar">

                                    <i
                                        class="fas fa-user-tie"
                                    ></i>

                                </div>

                            </div>


                            <!-- FIRST NAME -->

                            <div class="col-md-6">

                                <label
                                    for="firstname"
                                    class="form-label fw-bold required-field"
                                >

                                    First Name

                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >

                                        <i
                                            class="fas fa-user"
                                        ></i>

                                    </span>


                                    <input
                                        type="text"
                                        id="firstname"
                                        name="firstname"
                                        class="form-control"
                                        value="<?= isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : '' ?>"
                                        placeholder="Enter first name"
                                        required
                                        maxlength="50"
                                    >

                                </div>

                            </div>


                            <!-- LAST NAME -->

                            <div class="col-md-6">

                                <label
                                    for="lastname"
                                    class="form-label fw-bold required-field"
                                >

                                    Last Name

                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >

                                        <i
                                            class="fas fa-user"
                                        ></i>

                                    </span>


                                    <input
                                        type="text"
                                        id="lastname"
                                        name="lastname"
                                        class="form-control"
                                        value="<?= isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : '' ?>"
                                        placeholder="Enter last name"
                                        required
                                        maxlength="50"
                                    >

                                </div>

                            </div>


                            <!-- EMAIL -->

                            <div class="col-12">

                                <label
                                    for="email"
                                    class="form-label fw-bold required-field"
                                >

                                    Email Address

                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >

                                        <i
                                            class="fas fa-envelope"
                                        ></i>

                                    </span>


                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        class="form-control"
                                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                        placeholder="Enter driver's email address"
                                        required
                                        maxlength="100"
                                    >

                                </div>

                                <div class="form-text">

                                    This email will be used for
                                    the user account login.

                                </div>

                            </div>


                            <!-- CONTACT -->

                            <div class="col-md-6">

                                <label
                                    for="contact"
                                    class="form-label fw-bold required-field"
                                >

                                    Contact Number

                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >

                                        <i
                                            class="fas fa-phone"
                                        ></i>

                                    </span>


                                    <input
                                        type="tel"
                                        id="contact"
                                        name="contact"
                                        class="form-control"
                                        value="<?= isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : '' ?>"
                                        placeholder="078 123 4567"
                                        required
                                        maxlength="20"
                                    >

                                </div>

                                <div class="form-text">

                                    Must be 10 digits starting with
                                    078, 072, 073, or 079.

                                </div>

                            </div>


                            <!-- LICENSE -->

                            <div class="col-md-6">

                                <label
                                    for="license"
                                    class="form-label fw-bold required-field"
                                >

                                    License Number

                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >

                                        <i
                                            class="fas fa-id-card"
                                        ></i>

                                    </span>


                                    <input
                                        type="text"
                                        id="license"
                                        name="license"
                                        class="form-control"
                                        value="<?= isset($_POST['license']) ? htmlspecialchars($_POST['license']) : '' ?>"
                                        placeholder="Enter license number"
                                        required
                                        maxlength="20"
                                    >

                                </div>

                            </div>


                            <!-- AUTOMATIC ACCOUNT -->

                            <div class="col-12">

                                <div class="login-info">

                                    <i
                                        class="fas fa-lock me-2"
                                    ></i>

                                    <strong>
                                        Automatic User Account
                                    </strong>

                                    <br>

                                    A user account will
                                    automatically be created
                                    for this driver.

                                    <br>

                                    <strong>
                                        Email:
                                    </strong>
                                    As entered above

                                    <br>

                                    <strong>
                                        Role:
                                    </strong>
                                    Driver

                                    <br>

                                    <strong>
                                        Default password:
                                    </strong>
                                    123

                                </div>

                                <div class="warning-info">

                                    <i
                                        class="fas fa-info-circle me-2"
                                    ></i>

                                    <strong>Information:</strong>

                                    The driver record will be linked
                                    to the user account.

                                    <br>

                                    <small>
                                        <strong>user_id</strong> in drivers
                                        table will store the user ID
                                        as a data field (no foreign key constraint).
                                    </small>

                                </div>

                            </div>

                        </div>


                        <!-- BUTTONS -->

                        <div
                            class="mt-4 d-flex justify-content-between flex-wrap gap-2"
                        >

                            <a
                                href="admin.php"
                                class="back-btn"
                            >

                                <i
                                    class="fas fa-arrow-left"
                                ></i>

                                Back to Dashboard

                            </a>


                            <div>

                                <button
                                    type="reset"
                                    class="btn btn-warning me-2"
                                >

                                    <i
                                        class="fas fa-undo me-2"
                                    ></i>

                                    Clear Form

                                </button>


                                <button
                                    type="submit"
                                    class="btn btn-success"
                                >

                                    <i
                                        class="fas fa-user-plus me-2"
                                    ></i>

                                    Add Driver

                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
></script>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const form =
            document.getElementById(
                'driverForm'
            );

        const firstnameInput =
            document.getElementById(
                'firstname'
            );

        const lastnameInput =
            document.getElementById(
                'lastname'
            );

        const emailInput =
            document.getElementById(
                'email'
            );

        const contactInput =
            document.getElementById(
                'contact'
            );

        const licenseInput =
            document.getElementById(
                'license'
            );


        // ==========================================
        // FORMAT FIRST NAME
        // ==========================================

        firstnameInput.addEventListener(
            'blur',
            function () {

                this.value =
                    this.value
                        .trim()
                        .charAt(0)
                        .toUpperCase() +
                    this.value
                        .trim()
                        .slice(1)
                        .toLowerCase();

            }
        );


        // ==========================================
        // FORMAT LAST NAME
        // ==========================================

        lastnameInput.addEventListener(
            'blur',
            function () {

                this.value =
                    this.value
                        .trim()
                        .charAt(0)
                        .toUpperCase() +
                    this.value
                        .trim()
                        .slice(1)
                        .toLowerCase();

            }
        );


        // ==========================================
        // EMAIL TO LOWER CASE
        // ==========================================

        emailInput.addEventListener(
            'blur',
            function () {

                this.value =
                    this.value.toLowerCase().trim();

            }
        );


        // ==========================================
        // LICENSE UPPERCASE
        // ==========================================

        licenseInput.addEventListener(
            'input',
            function () {

                this.value =
                    this.value.toUpperCase();

            }
        );


        // ==========================================
        // PHONE NUMBER FORMATTING
        // ==========================================

        contactInput.addEventListener(
            'input',
            function () {

                // Remove all non-digit characters
                let numbers = this.value.replace(/\D/g, '');

                // Limit to 10 digits
                numbers = numbers.substring(0, 10);

                // Format as: 078 123 4567
                let formatted = '';

                if (numbers.length > 0) {
                    if (numbers.length <= 3) {
                        formatted = numbers;
                    } else if (numbers.length <= 6) {
                        formatted = numbers.substring(0, 3) + ' ' + numbers.substring(3);
                    } else {
                        formatted = numbers.substring(0, 3) + ' ' + numbers.substring(3, 6) + ' ' + numbers.substring(6);
                    }
                }

                this.value = formatted;

            }
        );


        // ==========================================
        // FORM VALIDATION
        // ==========================================

        form.addEventListener(
            'submit',
            function (event) {

                const firstname =
                    firstnameInput.value.trim();

                const lastname =
                    lastnameInput.value.trim();

                const email =
                    emailInput.value.trim();

                const contact =
                    contactInput.value.trim();

                const license =
                    licenseInput.value.trim();


                // Check required fields
                if (
                    firstname === '' ||
                    lastname === '' ||
                    email === '' ||
                    contact === '' ||
                    license === ''
                ) {

                    event.preventDefault();

                    alert(
                        'Please fill in all required fields.'
                    );

                    return;
                }


                // Validate email format
                const emailRegex =
                    /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (
                    !emailRegex.test(email)
                ) {

                    event.preventDefault();

                    alert(
                        'Please enter a valid email address.'
                    );

                    emailInput.focus();

                    return;
                }


                // Validate phone number (10 digits, starts with 078, 072, 073, or 079)
                const cleanContact = contact.replace(/\s/g, '');

                const phoneRegex =
                    /^(078|072|073|079)\d{7}$/;

                if (
                    !phoneRegex.test(cleanContact)
                ) {

                    event.preventDefault();

                    alert(
                        'Please enter a valid phone number.\n\n' +
                        'Must be 10 digits starting with 078, 072, 073, or 079.\n' +
                        'Example: 078 123 4567'
                    );

                    contactInput.focus();

                    return;
                }


                if (
                    license.length < 3
                ) {

                    event.preventDefault();

                    alert(
                        'Please enter a valid license number.'
                    );

                    licenseInput.focus();

                    return;
                }

            }
        );


        // ==========================================
        // CLEAR FORM
        // ==========================================

        const resetButton =
            document.querySelector(
                'button[type="reset"]'
            );


        resetButton.addEventListener(
            'click',
            function (event) {

                if (
                    !confirm(
                        'Are you sure you want to clear the form?'
                    )
                ) {

                    event.preventDefault();

                }

            }
        );


        // ==========================================
        // AUTO HIDE ALERTS
        // ==========================================

        setTimeout(
            function () {

                document
                    .querySelectorAll(
                        '.alert'
                    )
                    .forEach(
                        function (element) {

                            const alert =
                                bootstrap.Alert
                                    .getOrCreateInstance(
                                        element
                                    );

                            alert.close();

                        }
                    );

            },
            7000
        );

    }
);

</script>

</body>

</html>


<?php

if (
    isset($conn) &&
    $conn instanceof mysqli
) {

    $conn->close();

}

?>