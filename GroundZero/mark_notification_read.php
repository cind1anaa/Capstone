<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST["notification_id"])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$notification_id = $_POST["notification_id"];
$user_id = $_SESSION["user_id"];

// Verify the notification belongs to the current user and mark it as read
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_id = ?");
$stmt->bind_param("ii", $notification_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
}

$stmt->close();
$conn->close();
?> 