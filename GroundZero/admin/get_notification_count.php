<?php
session_start();

// Check if user is admin
if (!isset($_SESSION["admin_id"]) || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['unread_count' => 0]);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['unread_count' => 0]);
    exit();
}

$admin_id = $_SESSION["admin_id"];

// Count unread notifications
$unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_id = ? AND is_read = 0";
$stmt = $conn->prepare($unread_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode(['unread_count' => $data['unread_count']]);

$stmt->close();
$conn->close();
?> 