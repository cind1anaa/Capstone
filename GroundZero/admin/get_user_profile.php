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

if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    // Fetch user details
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count,
              (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as comment_count
              FROM users u 
              WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        $user_data = [
            'id' => $user['id'],
            'first_name' => htmlspecialchars($user['first_name']),
            'last_name' => htmlspecialchars($user['last_name']),
            'email' => htmlspecialchars($user['email']),
            'barangay' => htmlspecialchars($user['barangay']),
            'bio' => htmlspecialchars($user['bio'] ?? ''),
            'avatar' => $user['avatar'],
            'post_count' => $user['post_count'],
            'comment_count' => $user['comment_count'],
            'created_at' => date('M j, Y', strtotime($user['created_at']))
        ];
        
        echo json_encode([
            'success' => true,
            'user' => $user_data
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
}

$conn->close();
?> 