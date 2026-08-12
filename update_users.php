<?php
include 'connection.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}

$userId = (int) $_GET['id'];


// =====================================================
// GET USER INFORMATION
// =====================================================

$query = "
    SELECT id, firstname, lastname, email, role
    FROM users
    WHERE id = ?
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}

$user = $result->fetch_assoc();

$stmt->close();


// =====================================================
// UPDATE USER
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $role      = trim($_POST['role'] ?? '');

    // Validate fields
    if (
        empty($firstname) ||
        empty($lastname) ||
        empty($email) ||
        empty($role)
    ) {

        $error = "All fields are required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (!in_array(strtolower($role), ['admin', 'driver', 'passenger'], true)) {

        $error = "Invalid role selected.";

    } else {

        // Normalize role to lowercase
        $role = strtolower($role);

        // Update user
        $updateQuery = "
            UPDATE users
            SET firstname = ?,
                lastname = ?,
                email = ?,
                role = ?
            WHERE id = ?
        ";

        $updateStmt = $conn->prepare($updateQuery);

        if (!$updateStmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $updateStmt->bind_param(
                "ssssi",
                $firstname,
                $lastname,
                $email,
                $role,
                $userId
            );

            if ($updateStmt->execute()) {

                $updateStmt->close();

                header(
                    "Location: admin.php?section=manage-users&success=" .
                    urlencode("User updated successfully")
                );

                exit;

            } else {

                $error = "Failed to update user: " . $updateStmt->error;

                $updateStmt->close();
            }
        }
    }

    // Keep entered values if there is an error
    $user['firstname'] = $firstname;
    $user['lastname']  = $lastname;
    $user['email']     = $email;
    $user['role']      = $role;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit User - SwiftPass</title>

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

        /* =====================================================
           BODY
        ===================================================== */

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


        /* =====================================================
           CONTAINER
        ===================================================== */

        .edit-container {

            max-width: 700px;

            margin: 40px auto;
        }


        /* =====================================================
           CARD
        ===================================================== */

        .card {

            border: none;

            border-radius: 15px;

            box-shadow:
                0 10px 30px
                rgba(0, 0, 0, 0.1);

            backdrop-filter: blur(10px);

            background:
                rgba(255, 255, 255, 0.95);

            margin-bottom: 2rem;
        }


        /* =====================================================
           CARD HEADER
        ===================================================== */

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

        .card-header h4 {

            margin: 0;

            font-weight: 600;
        }

        .card-header i {

            margin-right: 8px;
        }


        /* =====================================================
           CARD BODY
        ===================================================== */

        .card-body {

            padding: 30px;
        }


        /* =====================================================
           LABELS
        ===================================================== */

        .form-label {

            font-weight: 600;

            color: #2c3e50;

            margin-bottom: 8px;
        }


        /* =====================================================
           INPUTS
        ===================================================== */

        .form-control {

            border-radius: 10px;

            border: 2px solid #e9ecef;

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


        /* =====================================================
           SELECT
        ===================================================== */

        .form-select {

            border-radius: 10px;

            border: 2px solid #e9ecef;

            padding:
                0.75rem 1rem;

            transition:
                all 0.3s ease;
        }

        .form-select:focus {

            border-color:
                var(--secondary);

            box-shadow:
                0 0 0 0.2rem
                rgba(52, 152, 219, 0.25);
        }


        /* =====================================================
           UPDATE BUTTON
        ===================================================== */

        .btn-primary {

            background:
                linear-gradient(
                    135deg,
                    var(--secondary),
                    var(--primary)
                );

            border: none;

            border-radius: 10px;

            padding:
                0.75rem 2rem;

            font-weight: 600;

            transition:
                all 0.3s ease;
        }

        .btn-primary:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 5px 15px
                rgba(52, 152, 219, 0.4);
        }


        /* =====================================================
           CANCEL BUTTON
        ===================================================== */

        .btn-secondary {

            background: #6c757d;

            border: none;

            border-radius: 10px;

            padding:
                0.75rem 1.5rem;

            font-weight: 600;

            transition:
                all 0.3s ease;
        }

        .btn-secondary:hover {

            background: #5a6268;

            transform:
                translateY(-1px);
        }


        /* =====================================================
           ALL BUTTONS
        ===================================================== */

        .btn {

            transition:
                all 0.3s ease;
        }


        /* =====================================================
           ERROR MESSAGE
        ===================================================== */

        .alert-danger {

            border-radius: 10px;

            border: none;

            background:
                linear-gradient(
                    135deg,
                    #f8d7da,
                    #f5c6cb
                );

            color: #721c24;

            font-weight: 500;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 768px) {

            .edit-container {

                margin:
                    20px 15px;
            }

            .card-body {

                padding: 20px;
            }

            .card-header {

                padding: 1.2rem;
            }

        }

    </style>

</head>


<body>


<div class="container">

    <div class="edit-container">

        <div class="card shadow">


            <!-- =================================================
                 HEADER
            ================================================= -->

            <div class="card-header">

                <h4>

                    <i class=""></i>

                    Edit User

                </h4>

            </div>


            <!-- =================================================
                 BODY
            ================================================= -->

            <div class="card-body">


                <!-- =================================================
                     ERROR MESSAGE
                ================================================= -->

                <?php if (isset($error)): ?>

                    <div class="alert alert-danger">

                        <i class="fas fa-exclamation-circle me-2"></i>

                        <?= htmlspecialchars($error); ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     FORM
                ================================================= -->

                <form method="POST">


                    <!-- =================================================
                         FIRST NAME + LAST NAME
                    ================================================= -->

                    <div class="row">


                        <!-- First Name -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                First Name

                            </label>

                            <input
                                type="text"
                                name="firstname"
                                class="form-control"
                                value="<?= htmlspecialchars($user['firstname'] ?? ''); ?>"
                                placeholder="Enter first name"
                                required
                            >

                        </div>


                        <!-- Last Name -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Last Name

                            </label>

                            <input
                                type="text"
                                name="lastname"
                                class="form-control"
                                value="<?= htmlspecialchars($user['lastname'] ?? ''); ?>"
                                placeholder="Enter last name"
                                required
                            >

                        </div>


                    </div>


                    <!-- =================================================
                         EMAIL
                    ================================================= -->

                    <div class="mb-3">

                        <label class="form-label">

                            Email

                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($user['email'] ?? ''); ?>"
                            placeholder="Enter email address"
                            required
                        >

                    </div>


                    <!-- =================================================
                         ROLE
                    ================================================= -->

                    <div class="mb-4">

                        <label class="form-label">

                            Role

                        </label>

                        <?php
                        $currentRole =
                            strtolower(
                                trim(
                                    $user['role'] ?? ''
                                )
                            );
                        ?>

                        <select
                            name="role"
                            class="form-select"
                            required
                        >

                            <option
                                value=""
                                disabled
                                <?= empty($currentRole) ? 'selected' : ''; ?>
                            >
                                Select Role
                            </option>


                            <option
                                value="admin"
                                <?= ($currentRole === 'admin') ? 'selected' : ''; ?>
                            >
                                Admin
                            </option>


                            <option
                                value="driver"
                                <?= ($currentRole === 'driver') ? 'selected' : ''; ?>
                            >
                                Driver
                            </option>


                            <option
                                value="passenger"
                                <?= ($currentRole === 'passenger') ? 'selected' : ''; ?>
                            >
                                Passenger
                            </option>

                        </select>

                    </div>


                    <!-- =================================================
                         BUTTONS
                    ================================================= -->

                    <div class="d-flex gap-2">


                        <!-- Update -->

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="fas fa-save me-2"></i>

                            Update User

                        </button>


                        <!-- Cancel -->

                        <a
                            href="admin.php?section=manage-users"
                            class="btn btn-secondary"
                        >

                            <i class="fas fa-arrow-left me-2"></i>

                            Cancel

                        </a>


                    </div>


                </form>


            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     BOOTSTRAP JAVASCRIPT
===================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>