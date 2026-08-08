<?php
session_start();
header('Content-Type: application/json');
include('connection.php');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check pending booking session data
if (!isset($_SESSION['pending_booking'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No pending booking data in session']);
    exit;
}

$pending = $_SESSION['pending_booking'];
$userId = $_SESSION['user_id'];  // from `users` table

// Extract data (some from session, some from input)
$trip_id = $input['trip_id'] ?? $pending['trip_id'];
$number_of_seats = $input['number_of_seats'] ?? $pending['number_of_seats'];
$total_amount = $input['amount'] ?? $pending['total_amount'];
$payment_method = strtolower($input['payment_method'] ?? 'momo');
if ($payment_method === 'airtel') {
    $payment_method = 'airtel_money';
} elseif ($payment_method !== 'momo' && $payment_method !== 'airtel_money') {
    $payment_method = 'momo';
}
$payment_status = $input['payment_status'] ?? 'completed';
$transaction_id = $input['transaction_id'] ?? $input['temp_booking_ref'] ?? 'TEMP_' . time();
$temp_ref = $transaction_id;

$passenger_name = $pending['passenger_name'];      // "First Last"
$passenger_phone = $pending['passenger_phone'];
$passenger_email = $pending['passenger_email'];

// 1. Get or create customer record
$customer_id = null;
$conn->begin_transaction();

try {
    // Check if customer already exists by email or contact
    $check = $conn->prepare("SELECT customer_id FROM customers WHERE email = ? OR contact = ?");
    $check->bind_param("ss", $passenger_email, $passenger_phone);
    $check->execute();
    $result = $check->get_result();
    if ($row = $result->fetch_assoc()) {
        $customer_id = $row['customer_id'];
    } else {
        // Insert new customer (split name into first and last)
        $name_parts = explode(' ', $passenger_name, 2);
        $firstname = $name_parts[0];
        $lastname = $name_parts[1] ?? '';
        $insert_customer = $conn->prepare("INSERT INTO customers (firstname, lastname, contact, email) VALUES (?, ?, ?, ?)");
        $insert_customer->bind_param("ssss", $firstname, $lastname, $passenger_phone, $passenger_email);
        if (!$insert_customer->execute()) {
            throw new Exception('Customer insert failed: ' . $insert_customer->error);
        }
        $customer_id = $conn->insert_id;
        $insert_customer->close();
    }
    $check->close();

    // 2. Insert booking (only columns that exist)
    $booking_query = "INSERT INTO bookings (customer_id, trip_id, number_of_seats, booking_date) 
                      VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param("iii", $customer_id, $trip_id, $number_of_seats);
    if (!$stmt->execute()) {
        throw new Exception('Booking insert failed: ' . $stmt->error);
    }
    $booking_id = $conn->insert_id;
    $stmt->close();

    // 3. Generate payment record for the created booking
    $check_payment = $conn->prepare("SELECT payment_id FROM payments WHERE booking_id = ?");
    $check_payment->bind_param("i", $booking_id);
    $check_payment->execute();
    $payment_result = $check_payment->get_result();
    $payment_id = null;

    if ($payment_result->num_rows > 0) {
        $payment_id = $payment_result->fetch_assoc()['payment_id'];
    } else {
        $payment_query = "INSERT INTO payments (booking_id, amount, payment_method, transaction_id, payment_status, time_paid) 
                          VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($payment_query);
        $stmt->bind_param("idsss", $booking_id, $total_amount, $payment_method, $temp_ref, $payment_status);
        if (!$stmt->execute()) {
            throw new Exception('Payment insert failed: ' . $stmt->error);
        }
        $payment_id = $conn->insert_id;
        $stmt->close();
    }
    $check_payment->close();

    // 4. Update available seats in trips
    $update_seats = "UPDATE trips SET available_seats = available_seats - ? WHERE trip_id = ?";
    $stmt = $conn->prepare($update_seats);
    $stmt->bind_param("ii", $number_of_seats, $trip_id);
    if (!$stmt->execute()) {
        throw new Exception('Seats update failed: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    // Clear pending booking from session
    unset($_SESSION['pending_booking']);
    unset($_SESSION['temp_booking_ref']);

    echo json_encode(['success' => true, 'booking_id' => $booking_id, 'payment_id' => $payment_id]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    // Log error to server log; return generic message for security
    error_log('create_booking.php error: ' . $e->getMessage());
    echo json_encode(['error' => 'Booking creation failed. Please contact support.']);
} finally {
    $conn->close();
}
?>
