<?php

session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "ground_zero");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $request_id);
    
    if ($stmt->execute()) {
        // Get user ID for notification
        $query = "SELECT user_id FROM requests WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        
        // Create notification
        $message = $status === 'done' ? 
            "Your waste collection request has been completed" : 
            "Your waste collection request has been " . $status;
            
        $stmt = $conn->prepare("INSERT INTO notifications (recipient_id, type, content) VALUES (?, 'request', ?)");
        $stmt->bind_param("is", $request['user_id'], $message);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}

?>
