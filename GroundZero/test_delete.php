<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if we have a delete request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_post_id"])) {
    $post_id = $_POST["delete_post_id"];
    $user_id = $_SESSION["user_id"] ?? 1; // Default to user 1 for testing
    
    echo "Attempting to delete post ID: $post_id<br>";
    echo "User ID: $user_id<br>";
    
    // Check if post exists
    $check_post = $conn->prepare("SELECT * FROM posts WHERE id = ?");
    $check_post->bind_param("i", $post_id);
    $check_post->execute();
    $post_result = $check_post->get_result();
    
    if ($post_result->num_rows > 0) {
        $post_data = $post_result->fetch_assoc();
        echo "Post found: " . $post_data['content'] . "<br>";
        echo "Post owner: " . $post_data['user_id'] . "<br>";
        
        if ($post_data['user_id'] == $user_id) {
            // Delete the post
            $delete_stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
            $delete_stmt->bind_param("i", $post_id);
            
            if ($delete_stmt->execute()) {
                echo "SUCCESS: Post deleted! Rows affected: " . $delete_stmt->affected_rows . "<br>";
            } else {
                echo "ERROR: Failed to delete post: " . $delete_stmt->error . "<br>";
            }
        } else {
            echo "ERROR: User not authorized to delete this post<br>";
        }
    } else {
        echo "ERROR: Post not found<br>";
    }
}

// Show all posts
echo "<h2>Current Posts:</h2>";
$posts_query = "SELECT * FROM posts ORDER BY created_at DESC";
$posts_result = $conn->query($posts_query);

if ($posts_result->num_rows > 0) {
    while ($post = $posts_result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<strong>Post ID: " . $post['id'] . "</strong><br>";
        echo "Content: " . $post['content'] . "<br>";
        echo "User ID: " . $post['user_id'] . "<br>";
        echo "Created: " . $post['created_at'] . "<br>";
        echo "<form method='POST' style='display: inline;'>";
        echo "<input type='hidden' name='delete_post_id' value='" . $post['id'] . "'>";
        echo "<button type='submit' onclick='return confirm(\"Delete this post?\")'>Delete</button>";
        echo "</form>";
        echo "</div>";
    }
} else {
    echo "No posts found.";
}

$conn->close();
?> 