<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Check if request_id is provided
if (!isset($_POST['request_id']) || empty($_POST['request_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Request ID is required']);
    exit();
}

$request_id = intval($_POST['request_id']);

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Check if the request exists and has status 'collected' or 'rejected'
$check_query = "SELECT id, status FROM requests WHERE id = ? AND status IN ('collected', 'rejected')";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $request_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Request not found or cannot be deleted']);
    $check_stmt->close();
    $conn->close();
    exit();
}

$check_stmt->close();

// Delete the request
$delete_query = "DELETE FROM requests WHERE id = ?";
$delete_stmt = $conn->prepare($delete_query);
$delete_stmt->bind_param("i", $request_id);

if ($delete_stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to delete request']);
}

$delete_stmt->close();
$conn->close();
?>