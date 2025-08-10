<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION["admin_id"]) || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    http_response_code(403);
    exit('Access denied');
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed');
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="feed_data_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, array('Post ID', 'Author Name', 'Author Location', 'Content', 'Likes', 'Comments', 'Created At', 'Comments Details'));

// Get all posts with comments
$query = "SELECT p.*, 
          COALESCE(u.first_name, a.first_name) as author_name,
          COALESCE(u.last_name, a.last_name) as author_last_name,
          COALESCE(u.barangay, a.barangay) as location,
          COALESCE(u.bio, a.bio) as bio
          FROM posts p
          LEFT JOIN users u ON p.user_id = u.id AND p.user_id IS NOT NULL
          LEFT JOIN admins a ON p.user_id = a.id AND p.user_id IS NOT NULL
          ORDER BY p.created_at DESC";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($post = $result->fetch_assoc()) {
        // Get comments for this post
        $comments_query = "SELECT c.*, 
                          COALESCE(u.first_name, a.first_name) as comment_author_name,
                          COALESCE(u.last_name, a.last_name) as comment_author_last_name,
                          COALESCE(u.barangay, a.barangay) as comment_location
                          FROM comments c
                          LEFT JOIN users u ON c.user_id = u.id AND c.user_id IS NOT NULL
                          LEFT JOIN admins a ON c.user_id = a.id AND c.user_id IS NOT NULL
                          WHERE c.post_id = ?
                          ORDER BY c.created_at ASC";
        
        $comments_stmt = $conn->prepare($comments_query);
        $comments_stmt->bind_param("i", $post['id']);
        $comments_stmt->execute();
        $comments_result = $comments_stmt->get_result();
        
        $comments_text = "";
        if ($comments_result->num_rows > 0) {
            while ($comment = $comments_result->fetch_assoc()) {
                $comments_text .= $comment['comment_author_name'] . " " . $comment['comment_author_last_name'] . 
                                 " (" . $comment['comment_location'] . "): " . $comment['content'] . " | ";
            }
        }
        
        // Prepare row data
        $row = array(
            $post['id'],
            $post['author_name'] . " " . $post['author_last_name'],
            $post['location'],
            str_replace(array("\r", "\n"), ' ', $post['content']), // Remove line breaks
            $post['like_count'],
            $post['comment_count'],
            $post['created_at'],
            $comments_text
        );
        
        fputcsv($output, $row);
        $comments_stmt->close();
    }
}

$conn->close();
fclose($output);
?> 