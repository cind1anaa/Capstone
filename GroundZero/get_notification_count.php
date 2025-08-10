<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['unread_count' => 0]);
    exit();
}

$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    echo json_encode(['unread_count' => 0]);
    exit();
}

$user_id = $_SESSION["user_id"];

// Count unread notifications
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode(['unread_count' => (int)$data['unread_count']]);

$stmt->close();
$conn->close();
?> 