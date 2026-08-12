<?php
session_start();
header('Content-Type: application/json');

// ===== 1. Authentication =====
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// ===== 2. CSRF token validation =====
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$csrf_token = $input['csrf_token'] ?? '';
if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

// ===== 3. Extract parameters =====
$ticket_id = (int)($input['ticket_id'] ?? 0);
$booking_id = (int)($input['booking_id'] ?? 0);
$hash = $input['hash'] ?? '';

if ($ticket_id <= 0 || $booking_id <= 0 || empty($hash)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

// ===== 4. Verify hash =====
$expected_hash = md5($ticket_id . $booking_id . 'swiftpass_secret');
if ($hash !== $expected_hash) {
    echo json_encode(['success' => false, 'message' => 'Invalid ticket hash.']);
    exit;
}

// ===== 5. Database check & update =====
include('connection.php');

// Optional: company restriction
$user_company_id = $_SESSION['company_id'] ?? null;
if ($user_company_id) {
    $check = $conn->prepare("
        SELECT 1 FROM tickets t
        JOIN bookings b ON t.booking_id = b.booking_id
        JOIN trips tr ON b.trip_id = tr.trip_id
        WHERE t.ticket_id = ? AND t.booking_id = ? AND tr.company_id = ?
    ");
    $check->bind_param("iii", $ticket_id, $booking_id, $user_company_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'You are not authorised to verify this ticket.']);
        exit;
    }
}

// Get current status + trip arrival time
$stmt = $conn->prepare("
    SELECT t.checked, t.checked_at, tr.estimated_arrival
    FROM tickets t
    JOIN bookings b ON t.booking_id = b.booking_id
    JOIN trips tr ON b.trip_id = tr.trip_id
    WHERE t.ticket_id = ? AND t.booking_id = ?
");
$stmt->bind_param("ii", $ticket_id, $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
    exit;
}

$ticket = $result->fetch_assoc();

// ===== Expiry check =====
// Optional grace period after arrival (e.g. allow late scans within N minutes of arrival)
$grace_minutes = 0; // set to e.g. 60 if you want some slack for delayed trips
$arrival = new DateTime($ticket['estimated_arrival']);
$arrival->modify("+{$grace_minutes} minutes");
$now = new DateTime();

if ($now > $arrival) {
    echo json_encode([
        'success' => false,
        'message' => 'This ticket has expired — the trip\'s estimated arrival time has passed.'
    ]);
    exit;
}

if ($ticket['checked'] === 'yes') {
    $time = date('M j, Y g:i A', strtotime($ticket['checked_at']));
    echo json_encode(['success' => false, 'message' => "Ticket already verified on $time."]);
    exit;
}

// Update to verified
$update = $conn->prepare("UPDATE tickets SET checked = 'yes', checked_at = NOW() WHERE ticket_id = ? AND booking_id = ?");
$update->bind_param("ii", $ticket_id, $booking_id);
if ($update->execute() && $update->affected_rows > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Ticket verified successfully!',
        'verified_at' => date('M j, Y g:i A')
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update ticket status.']);
}

$conn->close();
?>