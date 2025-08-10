<?php
session_start();

// Check if user is admin
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    
    // Fetch comments with user details
    $query = "SELECT c.*, u.first_name, u.last_name, u.barangay, u.avatar 
              FROM comments c 
              JOIN users u ON c.user_id = u.id 
              WHERE c.post_id = ? 
              ORDER BY c.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'id' => $row['id'],
            'content' => htmlspecialchars($row['content']),
            'author_name' => htmlspecialchars($row['first_name'] . ' ' . $row['last_name']),
            'barangay' => htmlspecialchars($row['barangay']),
            'avatar' => $row['avatar'],
            'created_at' => date('M j, Y g:i A', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Post ID is required']);
}

$conn->close();
?> 