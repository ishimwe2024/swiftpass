// verify-ticket.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    echo "Method Not Allowed. Only POST requests are accepted.";
    exit;
}