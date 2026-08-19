<?php
include 'connection.php';

$sql = "SELECT notification_id, title, message, type, status, created_at
        FROM admin_notifications
        ORDER BY created_at DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Error fetching notifications: " . mysqli_error($conn));
}

while ($notification = mysqli_fetch_assoc($result)) {
    echo "ID: " . htmlspecialchars($notification['notification_id']) . "<br>";
    echo "Title: " . htmlspecialchars($notification['title']) . "<br>";
    echo "Message: " . htmlspecialchars($notification['message']) . "<br>";
    echo "Type: " . htmlspecialchars($notification['type']) . "<br>";
    echo "Status: " . htmlspecialchars($notification['status']) . "<br>";
    echo "Created: " . htmlspecialchars($notification['created_at']) . "<br>";
    echo "<hr>";
}
?>