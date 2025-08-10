<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    
    // Fetch all media files for this post
    $media_query = "SELECT * FROM media_files WHERE post_id = ? ORDER BY created_at ASC";
    $stmt = $conn->prepare($media_query);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $media = [];
    while ($row = $result->fetch_assoc()) {
        if (file_exists($row['file_path'])) {
            $media[] = [
                'id' => $row['id'],
                'type' => $row['file_type'],
                'src' => $row['file_path'],
                'name' => $row['file_name']
            ];
        }
    }
    
    $stmt->close();
    $conn->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'media' => $media
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No post ID provided']);
}
?> 