<?php
include 'connection.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}

$userId = (int) $_GET['id'];

$status = $_GET['status'] ?? '';

if (!in_array($status, ['active', 'inactive'], true)) {
    die("Invalid status.");
}

$stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $userId);

if ($stmt->execute()) {
    header("Location: users.php");
    exit;
} else {
    echo "Failed to update user status.";
}

$stmt->close();
$conn->close();
?>