<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}
error_log("Database connection successful");

// Update session with latest user data from database
$user_id = $_SESSION["user_id"];
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Update session data with latest database values
if ($user) {
    $_SESSION["first_name"] = $user['first_name'];
    $_SESSION["last_name"] = $user['last_name'];
    $_SESSION["email"] = $user['email'];
    $_SESSION["avatar"] = $user['avatar'];
}

// Create posts table if it doesn't exist
$createPostsTable = "CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
)";
$conn->query($createPostsTable);

// Create media_files table if it doesn't exist
$createMediaFilesTable = "CREATE TABLE IF NOT EXISTS media_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type ENUM('image', 'video') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id),
    INDEX idx_file_type (file_type)
)";
$conn->query($createMediaFilesTable);

// Create comments table if it doesn't exist
$createCommentsTable = "CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
)";
$conn->query($createCommentsTable);

// Create likes table if it doesn't exist
$createLikesTable = "CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (post_id, user_id),
    INDEX idx_post_id (post_id),
    INDEX idx_user_id (user_id)
)";
$conn->query($createLikesTable);

// Create notifications table if it doesn't exist
$createNotificationsTable = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    sender_id INT NOT NULL,
    post_id INT,
    comment_id INT,
    type ENUM('like', 'comment') NOT NULL,
    content TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_post_id (post_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
)";
$conn->query($createNotificationsTable);

// Handle post submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_post"])) {
    $content = trim($_POST["content"]);
    $user_id = $_SESSION["user_id"];
    
    // Handle file upload
    $uploaded_files = [];
    
    // Check for multiple image uploads
    if (isset($_FILES["media_image"]) && is_array($_FILES["media_image"]["name"])) {
        for ($i = 0; $i < count($_FILES["media_image"]["name"]); $i++) {
            if ($_FILES["media_image"]["error"][$i] == 0) {
                $file = [
                    "name" => $_FILES["media_image"]["name"][$i],
                    "type" => $_FILES["media_image"]["type"][$i],
                    "tmp_name" => $_FILES["media_image"]["tmp_name"][$i],
                    "error" => $_FILES["media_image"]["error"][$i],
                    "size" => $_FILES["media_image"]["size"][$i]
                ];
                $uploaded_files[] = $file;
            }
        }
    }
    // Check for multiple video uploads
    if (isset($_FILES["media_video"]) && is_array($_FILES["media_video"]["name"])) {
        for ($i = 0; $i < count($_FILES["media_video"]["name"]); $i++) {
            if ($_FILES["media_video"]["error"][$i] == 0) {
                $file = [
                    "name" => $_FILES["media_video"]["name"][$i],
                    "type" => $_FILES["media_video"]["type"][$i],
                    "tmp_name" => $_FILES["media_video"]["tmp_name"][$i],
                    "error" => $_FILES["media_video"]["error"][$i],
                    "size" => $_FILES["media_video"]["size"][$i]
                ];
                $uploaded_files[] = $file;
            }
        }
    }
    
    if (!empty($uploaded_files)) {
        error_log("File upload detected: " . print_r($uploaded_files, true));
        
        // Debug: Show file info on screen
        if (isset($_GET['debug'])) {
            echo "<div style='background: yellow; padding: 10px; margin: 10px;'>";
            echo "<strong>DEBUG - File Upload Info:</strong><br>";
            foreach ($uploaded_files as $file) {
                echo "File name: " . $file["name"] . "<br>";
                echo "File type: " . $file["type"] . "<br>";
                echo "File size: " . $file["size"] . " bytes<br>";
                echo "Error code: " . $file["error"] . "<br>";
                echo "Category: " . ($file["type"] == "image/*" ? "image" : "video") . "<br>";
                echo "Temp name: " . $file["tmp_name"] . "<br>";
                echo "Upload directory: uploads/posts/<br>";
            }
            echo "</div>";
        }
        
        $allowed_image_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $allowed_video_types = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv'];
        
                 $upload_dir = "uploads/posts/";
         if (!is_dir($upload_dir)) {
             mkdir($upload_dir, 0777, true);
             error_log("Created upload directory: $upload_dir");
         }
         
         foreach ($uploaded_files as $file) {
             $file_type = $file["type"];
             $file_size = $file["size"];
             $max_size = 50 * 1024 * 1024; // 50MB max
             
             error_log("File type: $file_type, Size: $file_size");
             
             if ($file_size > $max_size) {
                 $error = "File size too large. Maximum size is 50MB.";
                 error_log("File too large: $file_size bytes");
                 continue; // Skip this file
             }
             
             // Determine file type category based on MIME type
             $file_type_category = "";
             if (in_array($file_type, $allowed_image_types)) {
                 $file_type_category = "image";
                 error_log("Image file detected");
             } elseif (in_array($file_type, $allowed_video_types)) {
                 $file_type_category = "video";
                 error_log("Video file detected");
             } else {
                 $error = "Invalid file type. Only images (JPG, PNG, GIF) and videos (MP4, AVI, MOV, WMV) are allowed.";
                 error_log("Invalid file type: $file_type");
                 continue; // Skip this file
             }
             
             if (!isset($error)) {
                 $file_extension = pathinfo($file["name"], PATHINFO_EXTENSION);
                 $file_name = uniqid() . '_' . time() . '.' . $file_extension;
                 $file_path = $upload_dir . $file_name;
                 
                 error_log("Attempting to upload to: $file_path");
                 
                 // Debug: Show final path
                 if (isset($_GET['debug'])) {
                     echo "Final path: $file_path<br>";
                 }
                 
                 if (move_uploaded_file($file["tmp_name"], $file_path)) {
                     error_log("File uploaded successfully: $file_path");
                     
                     // Verify file exists after upload
                     if (file_exists($file_path)) {
                         error_log("File verified to exist: $file_path");
                         
                         // Store file info for database insertion
                         $uploaded_files_info[] = [
                             'path' => $file_path,
                             'type' => $file_type_category,
                             'name' => $file["name"]
                         ];
                     } else {
                         error_log("ERROR: File does not exist after upload: $file_path");
                     }
                 } else {
                     $error = "Failed to upload file. Please try again.";
                     error_log("Failed to upload file. Error: " . error_get_last()['message']);
                 }
             }
         }
    } else {
        error_log("No file uploaded");
        
        // Debug: Show why no file was uploaded
        if (isset($_GET['debug'])) {
            echo "<div style='background: orange; padding: 10px; margin: 10px;'>";
            echo "<strong>DEBUG - No File Upload:</strong><br>";
            echo "No image or video file was selected<br>";
            if (isset($_FILES["media_image"])) {
                echo "Image file error: " . $_FILES["media_image"]["error"] . "<br>";
            }
            if (isset($_FILES["media_video"])) {
                echo "Video file error: " . $_FILES["media_video"]["error"] . "<br>";
            }
            echo "</div>";
        }
    }
    
    // Insert post into database
    if (!isset($error)) {
        error_log("Inserting post - User ID: $user_id, Content: $content");
        
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $content);
        
                 if ($stmt->execute()) {
             $post_id = $conn->insert_id;
             error_log("Post inserted successfully with ID: " . $post_id);
             
             // Insert media files into media_files table
             if (isset($uploaded_files_info) && !empty($uploaded_files_info)) {
                 foreach ($uploaded_files_info as $file_info) {
                     $stmt_media = $conn->prepare("INSERT INTO media_files (post_id, file_path, file_type, file_name) VALUES (?, ?, ?, ?)");
                     $stmt_media->bind_param("isss", $post_id, $file_info['path'], $file_info['type'], $file_info['name']);
                     
                     if ($stmt_media->execute()) {
                         error_log("Media file inserted successfully: " . $file_info['path']);
                     } else {
                         error_log("Failed to insert media file: " . $stmt_media->error);
                     }
                     $stmt_media->close();
                 }
             }
             $success = "Post shared successfully!";
         } else {
            $error = "Failed to save post. Please try again.";
            error_log("Failed to insert post: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Post not inserted due to error: $error");
    }
}

// Handle comment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_comment"])) {
    $comment_content = trim($_POST["comment_content"]);
    $post_id = $_POST["post_id"];
    $user_id = $_SESSION["user_id"];
    
    // Debug logging
    error_log("Comment submission attempt - Content: $comment_content, Post ID: $post_id, User ID: $user_id");
    
    if (!empty($comment_content)) {
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $post_id, $user_id, $comment_content);
        
        if ($stmt->execute()) {
            $comment_id = $conn->insert_id;
            error_log("Comment inserted successfully with ID: $comment_id");
            
            // Create notification for the post owner
            $get_post_owner = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
            $get_post_owner->bind_param("i", $post_id);
            $get_post_owner->execute();
            $post_owner_result = $get_post_owner->get_result();
            
            if ($post_owner_result->num_rows > 0) {
                $post_owner = $post_owner_result->fetch_assoc();
                $post_owner_id = $post_owner['user_id'];
                
                // Don't create notification if user is commenting on their own post
                if ($post_owner_id != $user_id) {
                    $sender_name = $_SESSION["first_name"] . " " . $_SESSION["last_name"];
                    $notification_content = "$sender_name commented on your post";
                    
                    $notification_stmt = $conn->prepare("INSERT INTO notifications (recipient_id, sender_id, post_id, comment_id, type, content) VALUES (?, ?, ?, ?, 'comment', ?)");
                    $notification_stmt->bind_param("iiiss", $post_owner_id, $user_id, $post_id, $comment_id, $notification_content);
                    $notification_stmt->execute();
                    $notification_stmt->close();
                }
            }
            $get_post_owner->close();
            
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'comment' => [
                    'id' => $comment_id,
                    'content' => $comment_content,
                    'user_id' => $user_id,
                    'first_name' => $_SESSION["first_name"],
                    'last_name' => $_SESSION["last_name"],
                    'avatar' => $user['avatar'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            exit();
        } else {
            error_log("Failed to insert comment: " . $stmt->error);
            $stmt->close();
            // Return error JSON response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to add comment. Please try again.'
            ]);
            exit();
        }
    } else {
        error_log("Comment content is empty");
        // Return error JSON response for empty content
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Comment cannot be empty.'
        ]);
        exit();
    }
}

// Handle comment edit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_comment_id"])) {
    $comment_id = $_POST["edit_comment_id"];
    $new_content = trim($_POST["edit_comment_content"]);
    $user_id = $_SESSION["user_id"];
    
    // Verify user owns this comment
    $check_ownership = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
    $check_ownership->bind_param("i", $comment_id);
    $check_ownership->execute();
    $ownership_result = $check_ownership->get_result();
    
    if ($ownership_result->num_rows > 0) {
        $comment_data = $ownership_result->fetch_assoc();
        if ($comment_data['user_id'] == $user_id) {
            $update_stmt = $conn->prepare("UPDATE comments SET content = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_content, $comment_id);
            
            if ($update_stmt->execute()) {
                $comment_edit_success = "Comment updated successfully!";
            } else {
                $comment_edit_error = "Failed to update comment. Please try again.";
            }
            $update_stmt->close();
        } else {
            $comment_edit_error = "You can only edit your own comments.";
        }
    } else {
        $comment_edit_error = "Comment not found.";
    }
}

// Handle comment delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_comment_id"])) {
    $comment_id = $_POST["delete_comment_id"];
    $user_id = $_SESSION["user_id"];
    
    // Verify user owns this comment
    $check_ownership = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
    $check_ownership->bind_param("i", $comment_id);
    $check_ownership->execute();
    $ownership_result = $check_ownership->get_result();
    
    if ($ownership_result->num_rows > 0) {
        $comment_data = $ownership_result->fetch_assoc();
        if ($comment_data['user_id'] == $user_id) {
            $delete_stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
            $delete_stmt->bind_param("i", $comment_id);
            
            if ($delete_stmt->execute()) {
                $delete_stmt->close();
                // Return JSON response for AJAX
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            } else {
                $delete_stmt->close();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to delete comment']);
                exit();
            }
        } else {
            $comment_delete_error = "You can only delete your own comments.";
        }
    } else {
        $comment_delete_error = "Comment not found.";
    }
}

// Handle like/unlike
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["toggle_like"])) {
    $post_id = $_POST["post_id"];
    $user_id = $_SESSION["user_id"];
    
    // Check if user already liked this post
    $check_like = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
    $check_like->bind_param("ii", $post_id, $user_id);
    $check_like->execute();
    $like_result = $check_like->get_result();
    
    if ($like_result->num_rows > 0) {
        // Unlike
        $unlike_stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
        $unlike_stmt->bind_param("ii", $post_id, $user_id);
        $unlike_stmt->execute();
        $like_response = "unliked";
    } else {
        // Like
        $like_stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
        $like_stmt->bind_param("ii", $post_id, $user_id);
        $like_stmt->execute();
        $like_response = "liked";
        
        // Create notification for the post owner
        $get_post_owner = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $get_post_owner->bind_param("i", $post_id);
        $get_post_owner->execute();
        $post_owner_result = $get_post_owner->get_result();
        
        if ($post_owner_result->num_rows > 0) {
            $post_owner = $post_owner_result->fetch_assoc();
            $post_owner_id = $post_owner['user_id'];
            
            // Don't create notification if user is liking their own post
            if ($post_owner_id != $user_id) {
                $sender_name = $_SESSION["first_name"] . " " . $_SESSION["last_name"];
                $notification_content = "$sender_name liked your post";
                
                $notification_stmt = $conn->prepare("INSERT INTO notifications (recipient_id, sender_id, post_id, type, content) VALUES (?, ?, ?, 'like', ?)");
                $notification_stmt->bind_param("iiis", $post_owner_id, $user_id, $post_id, $notification_content);
                $notification_stmt->execute();
                $notification_stmt->close();
            }
        }
        $get_post_owner->close();
    }
    
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode(['status' => $like_response]);
    exit();
}

// Handle post edit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_post_id"])) {
    $post_id = $_POST["edit_post_id"];
    $new_content = trim($_POST["edit_content"]);
    $user_id = $_SESSION["user_id"];
    
    // Verify user owns this post
    $check_ownership = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $check_ownership->bind_param("i", $post_id);
    $check_ownership->execute();
    $ownership_result = $check_ownership->get_result();
    
    if ($ownership_result->num_rows > 0) {
        $post_data = $ownership_result->fetch_assoc();
        if ($post_data['user_id'] == $user_id) {
            $update_stmt = $conn->prepare("UPDATE posts SET content = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_content, $post_id);
            
            if ($update_stmt->execute()) {
                $edit_success = "Post updated successfully!";
            } else {
                $edit_error = "Failed to update post. Please try again.";
            }
            $update_stmt->close();
        } else {
            $edit_error = "You can only edit your own posts.";
        }
    } else {
        $edit_error = "Post not found.";
    }
}

// Handle post delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_post_id"])) {
    $post_id = $_POST["delete_post_id"];
    $user_id = $_SESSION["user_id"];
    
    error_log("Delete request received for post ID: $post_id by user ID: $user_id");
    
    // Verify user owns this post
    $check_ownership = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $check_ownership->bind_param("i", $post_id);
    $check_ownership->execute();
    $ownership_result = $check_ownership->get_result();
    
    if ($ownership_result->num_rows > 0) {
        $post_data = $ownership_result->fetch_assoc();
        if ($post_data['user_id'] == $user_id) {
            // Delete associated media files if they exist
            $media_files_stmt = $conn->prepare("SELECT file_path FROM media_files WHERE post_id = ?");
            $media_files_stmt->bind_param("i", $post_id);
            $media_files_stmt->execute();
            $media_files_result = $media_files_stmt->get_result();
            
            while ($media_file = $media_files_result->fetch_assoc()) {
                if (file_exists($media_file['file_path'])) {
                    unlink($media_file['file_path']);
                    error_log("Deleted media file: " . $media_file['file_path']);
                }
            }
            $media_files_stmt->close();
            
            // Delete the post (cascade will handle comments and likes)
            $delete_stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
            $delete_stmt->bind_param("i", $post_id);
            
            if ($delete_stmt->execute()) {
                error_log("Post deleted successfully. Rows affected: " . $delete_stmt->affected_rows);
                $delete_stmt->close();
                
                // Return JSON response for AJAX
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            } else {
                error_log("Failed to delete post: " . $delete_stmt->error);
                $delete_stmt->close();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to delete post']);
                exit();
            }
        } else {
            error_log("Unauthorized delete attempt: User $user_id tried to delete post $post_id owned by " . $post_data['user_id']);
            $delete_error = "You can only delete your own posts.";
        }
    } else {
        error_log("Post not found for deletion: $post_id");
        $delete_error = "Post not found.";
    }
}

// Fetch posts with user information, comment counts, and like counts
$posts_query = "SELECT p.*, 
                CASE 
                    WHEN p.author_type = 'admin' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM admins WHERE id = p.user_id)
                    ELSE (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = p.user_id)
                END as author_name,
                CASE 
                    WHEN p.author_type = 'admin' THEN 'Admin'
                    ELSE (SELECT barangay FROM users WHERE id = p.user_id)
                END as location,
                CASE 
                    WHEN p.author_type = 'admin' THEN (SELECT avatar FROM admins WHERE id = p.user_id)
                    ELSE (SELECT avatar FROM users WHERE id = p.user_id)
                END as avatar,
                CASE 
                    WHEN p.author_type = 'admin' THEN (SELECT bio FROM admins WHERE id = p.user_id)
                    ELSE (SELECT bio FROM users WHERE id = p.user_id)
                END as bio,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked
                FROM posts p 
                WHERE 1=1
                ORDER BY p.is_pinned DESC, p.created_at DESC";

$stmt = $conn->prepare($posts_query);
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$posts_result = $stmt->get_result();

// Fetch notifications for the current user
$notifications_query = "SELECT n.*, 
                       (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = n.sender_id) as sender_name
                       FROM notifications n 
                       WHERE n.recipient_id = ?
                       ORDER BY n.created_at DESC 
                       LIMIT 10";

$notif_stmt = $conn->prepare($notifications_query);
$notif_stmt->bind_param("i", $_SESSION["user_id"]);
$notif_stmt->execute();
$notifications_result = $notif_stmt->get_result();

// Count unread notifications
$unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $_SESSION["user_id"]);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_data = $unread_result->fetch_assoc();
$unread_count = $unread_data['unread_count'];

// Don't close connection here as it's needed for comments later
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Ground Community</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f2f5ef;
      font-family: 'Segoe UI', sans-serif;
    }

    .custom-navbar {
      background-color: #27692A;
    }

    .custom-navbar .nav-link,
    .custom-navbar .navbar-brand span {
      color: #ffffff;
    }

    .custom-navbar .nav-link.active,
    .custom-navbar .nav-link:hover {
      color: #f1f1f1;
    }

    .custom-navbar .nav-link.active {
      background-color: white;
      color: #27692A !important;
      border-radius: 5px;
      padding: 6px 12px;
    }

    .navbar-brand span {
      font-weight: bold;
    }

    .dropdown-menu {
      background-color: #27692A;
      border: none;
      padding: 0.5rem 0;
      min-width: 180px;
    }

    .dropdown-menu .dropdown-header {
      color: #ffffff;
      font-weight: bold;
      padding: 0.5rem 1rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .dropdown-menu .dropdown-item {
      color: #ffffff;
      padding: 0.5rem 1rem;
    }

    .dropdown-menu .dropdown-item:hover {
      background-color: #1d4f1f;
    }

    .dropdown-menu .dropdown-item.active {
      background-color: #27692A !important;
      color: white !important;
    }

    .dropdown-menu .dropdown-item.active:hover {
      background-color: #1f531f !important;
      color: white !important;
    }

    .profile-icon {
      width: 24px;
      height: 24px;
      object-fit: cover;
    }

    .featured-box {
      background-color: white;
      border-left: 6px solid #27692A;
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 20px;
    }

    .featured-title {
      font-size: 14px;
      font-weight: bold;
      color: #27692A;
    }

    .post-box {
      background-color: white;
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 20px;
    }

    .user-profile {
      background-color: #d8e9d3;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
    }

    .user-profile .icon {
      background-color: white;
      border-radius: 50%;
      padding: 10px;
      font-size: 24px;
      color: #27692A;
    }

    .feed-post img,
    .feed-post video {
      max-width: 100%;
      max-height: 300px;
      border-radius: 10px;
      margin-top: 10px;
      object-fit: cover;
    }

    .feed-post video {
      max-height: 300px;
      object-fit: cover;
    }

    .btn-green {
      background-color: #27692A;
      color: white;
      border: none;
    }

    .btn-green:hover {
      background-color: #1f531f;
    }

    .tag {
      font-size: 12px;
      color: gray;
    }

    /* Clickable post author names */
    .feed-post a[href*="profile.php"] {
      transition: color 0.2s ease;
    }

    .feed-post a[href*="profile.php"]:hover {
      color: #1f531f !important;
      text-decoration: underline !important;
    }

    .pinned-post {
      border-left: 4px solid #27692A;
      background-color: #f8fff8;
    }

    .pinned-indicator {
      background-color: #e8f5e8;
      padding: 5px 10px;
      border-radius: 5px;
      border-left: 3px solid #27692A;
    }

    .featured-box {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .featured-box:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .highlight {
      animation: highlightPost 2s ease-in-out;
    }

    @keyframes highlightPost {
      0%, 100% { background-color: transparent; }
      50% { background-color: #fff3cd; }
    }


    
    /* Green textbox style */
    .green-textbox {
      background-color: #eaf7ea;
      border: 1.5px solid #27692A;
      color: #27692A;
      border-radius: 6px;
      font-size: 15px;
      padding: 10px 12px;
      transition: border-color 0.2s;
    }
    .green-textbox:focus {
      border-color: #1d4f1f;
      outline: none;
      background-color: #f4fbf4;
    }
    
    /* File upload styling */
    .file-upload {
      position: relative;
      display: inline-block;
      cursor: pointer;
    }

    .file-upload input[type=file] {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }

    .media-preview {
      max-width: 300px;
      max-height: 300px;
      border-radius: 8px;
      margin-top: 10px;
      display: none;
      object-fit: cover;
    }

    .remove-media {
      position: absolute;
      top: 5px;
      right: 5px;
      background: rgba(255, 0, 0, 0.8);
      color: white;
      border: none;
      border-radius: 50%;
      width: 25px;
      height: 25px;
      font-size: 12px;
      cursor: pointer;
    }

    .media-container {
      position: relative;
      display: inline-block;
    }

    /* Notification Dropdown Fix */
    .dropdown-menu.notifications {
      background-color: #ffffff;
      border: 1px solid #ddd;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      max-height: 400px;
      overflow-y: auto;
      z-index: 1050;
      position: relative;
    }

    .notification-item {
      padding: 8px 12px;
      border-bottom: 1px solid #f0f0f0;
      cursor: pointer;
      transition: all 0.2s ease;
      user-select: none;
      position: relative;
      z-index: 1000;
      border-radius: 4px;
      margin: 2px 4px;
      pointer-events: auto;
    }

    .notification-item:hover {
      background-color: #f8f9fa;
    }

    .notification-item.unread {
      background-color: #e3f2fd;
      border-left: 3px solid #2196f3;
    }

    .notification-item.unread:hover {
      background-color: #bbdefb;
    }

    .notification-item:active {
      background-color: #e8f5e8;
    }

    .notification-item i {
      color: #27692A;
      margin-right: 8px;
      margin-top: 2px;
    }

    /* Post highlight animation */
    .feed-post.highlight {
      animation: highlightPost 2s ease-out;
    }

    @keyframes highlightPost {
      0% { background-color: #e3f2fd; }
      50% { background-color: #bbdefb; }
      100% { background-color: transparent; }
    }

    .dropdown-menu.notifications {
      color: #333;
      border-radius: 10px;
      width: 300px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .dropdown-menu.notifications .dropdown-header {
      font-weight: bold;
      font-size: 15px;
      color: #27692A;
      border-bottom: 1px solid #ccc;
    }

    .dropdown-menu.notifications li {
      padding: 8px 12px;
      font-size: 13px;
      color: #333;
    }

    .dropdown-menu.notifications i {
      color: #27692A;
      margin-right: 8px;
    }

    .dropdown-menu.notifications small {
      font-size: 13px;
    }

    .dropdown-menu.notifications li:hover {
      background-color: #f4f4f4;
      cursor: pointer;
    }

    /* Post actions styling */
    .post-actions {
      display: flex;
      gap: 15px;
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #eee;
    }

    .post-action-btn {
      display: flex;
      align-items: center;
      gap: 5px;
      background: none;
      border: none;
      color: #666;
      font-size: 14px;
      cursor: pointer;
      padding: 5px 10px;
      border-radius: 5px;
      transition: all 0.2s;
    }

    .post-action-btn:hover {
      background-color: #f0f0f0;
      color: #27692A;
    }

    .post-action-btn i {
      font-size: 16px;
    }

    .like-btn:hover i {
      transform: scale(1.1);
      transition: transform 0.2s;
    }

    .like-btn .text-danger {
      color: #dc3545 !important;
    }

    /* Edit/Delete dropdown styling */
    .post-actions .dropdown-menu {
      background-color: white;
      border: 1px solid #ddd;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      min-width: 150px;
    }

    .post-actions .dropdown-menu .dropdown-item {
      color: #333;
      padding: 8px 15px;
      font-size: 14px;
    }

    .post-actions .dropdown-menu .dropdown-item:hover {
      background-color: #f8f9fa;
    }

    .post-actions .dropdown-menu .dropdown-item.text-danger:hover {
      background-color: #f8d7da;
    }

    .comment-section {
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #eee;
    }

    .comment-input {
      display: flex;
      gap: 10px;
      margin-bottom: 10px;
    }

    .comment-input input {
      flex: 1;
      border: 1px solid #ddd;
      border-radius: 20px;
      padding: 8px 15px;
      font-size: 14px;
    }

    .comment-input button {
      background-color: #27692A;
      color: white;
      border: none;
      border-radius: 20px;
      padding: 8px 15px;
      font-size: 14px;
      cursor: pointer;
    }

    .comment-input button:hover {
      background-color: #1f531f;
    }

    .comments-list {
      max-height: 200px;
      overflow-y: auto;
    }

    .comment-item {
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .comment-item:last-child {
      border-bottom: none;
    }

    .comment-author {
      font-weight: bold;
      font-size: 13px;
      color: #27692A;
    }

    .comment-content {
      font-size: 14px;
      margin-top: 2px;
    }

    .comment-time {
      font-size: 12px;
      color: #999;
      margin-top: 2px;
    }

    /* Media Gallery Styling */
    .media-gallery {
      margin-top: 15px;
    }

    .media-grid {
      display: flex;
      gap: 4px;
      max-width: 400px;
    }

    .media-item {
      position: relative;
      flex: 1;
      height: 200px;
      overflow: hidden;
      border-radius: 8px;
      border: 1px solid #ddd;
    }

    .media-item.single {
      max-width: 300px;
    }

    .post-media {
      width: 100%;
      height: 100%;
      object-fit: cover;
      cursor: pointer;
      transition: transform 0.2s;
    }

    .post-media:hover {
      transform: scale(1.05);
    }

    /* Overlay for additional images */
    .media-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7));
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border-radius: 8px;
    }

    .overlay-content {
      text-align: center;
      color: white;
    }

    .overlay-text {
      font-size: 14px;
      font-weight: bold;
      background: rgba(0,0,0,0.8);
      padding: 6px 12px;
      border-radius: 20px;
    }

    /* Gallery Modal */
    .gallery-modal {
      display: none;
      position: fixed;
      z-index: 9999;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.9);
    }

    .gallery-content {
      position: relative;
      margin: auto;
      padding: 20px;
      width: 90%;
      height: 90%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .gallery-slide {
      display: none;
      text-align: center;
    }

    .gallery-slide.active {
      display: block;
    }

    .gallery-slide img {
      max-width: 100%;
      max-height: 80vh;
      object-fit: contain;
    }

    .gallery-slide video {
      max-width: 100%;
      max-height: 80vh;
    }

    .gallery-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255,255,255,0.2);
      color: white;
      border: none;
      padding: 15px 10px;
      cursor: pointer;
      font-size: 18px;
      border-radius: 5px;
    }

    .gallery-nav:hover {
      background: rgba(255,255,255,0.3);
    }

    .gallery-prev {
      left: 20px;
    }

    .gallery-next {
      right: 20px;
    }

    .gallery-close {
      position: absolute;
      top: 20px;
      right: 30px;
      color: white;
      font-size: 35px;
      font-weight: bold;
      cursor: pointer;
    }

    .gallery-counter {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      color: white;
      background: rgba(0,0,0,0.7);
      padding: 10px 20px;
      border-radius: 20px;
      font-size: 14px;
    }

    .media-preview {
      max-width: 150px;
      max-height: 150px;
      border-radius: 8px;
      object-fit: cover;
      margin: 5px;
    }

    .preview-container {
      position: relative;
      display: inline-block;
      margin: 5px;
    }

    .remove-preview {
      position: absolute;
      top: 5px;
      right: 5px;
      background: rgba(255, 0, 0, 0.8);
      color: white;
      border: none;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 10px;
      cursor: pointer;
      z-index: 10;
    }

    @media (max-width: 991px) {
      .user-profile {
        margin-top: 20px;
      }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
  <a class="navbar-brand d-flex align-items-center" href="#">
    <img src="images/logo.png" alt="Logo" height="40" class="me-2" />
    <span>GROUND ZERO</span>
  </a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
    <ul class="navbar-nav align-items-center gap-2">
      <li class="nav-item">
        <a class="nav-link active" href="#">COMMUNITY</a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="Rinstructions.php">REQUEST</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
          TRACKER
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="food.php">Food Tracker</a></li>
          <li><a class="dropdown-item" href="garbage.php">Garbage Tracker</a></li>
        </ul>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown">
          <i class="bi bi-bell-fill"></i>
          <?php if ($unread_count > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $unread_count ?>
          </span>
          <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end notifications">
          <li class="dropdown-header"><i class="bi bi-bell-fill"></i> Notifications</li>
          <li><hr class="dropdown-divider"></li>
          <?php if ($notifications_result->num_rows > 0): ?>
            <?php while ($notification = $notifications_result->fetch_assoc()): ?>
              <li class="d-flex align-items-start notification-item <?= $notification['is_read'] == 0 ? 'unread' : '' ?>" 
                  data-notification-id="<?= $notification['id'] ?>"
                  data-post-id="<?= $notification['post_id'] ?>"
                  title="Click to view the post">
                <i class="bi bi-<?= $notification['type'] == 'like' ? 'heart-fill' : 'chat-fill' ?>"></i>
                <div class="flex-grow-1">
                  <small><strong><?= htmlspecialchars($notification['sender_name']) ?></strong> 
                  <?= htmlspecialchars($notification['content']) ?>
                  <br><span class="text-muted"><?= date('M j, g:i A', strtotime($notification['created_at'])) ?></span></small>
                </div>
                <i class="bi bi-arrow-right text-muted" style="font-size: 12px; opacity: 0.6;"></i>
              </li>
            <?php endwhile; ?>
          <?php else: ?>
            <li><small class="text-muted">No notifications yet</small></li>
          <?php endif; ?>
        </ul>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle me-1"></i>
          <?= $_SESSION["first_name"] ?? "User" ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><h6 class="dropdown-header">
            <i class="bi bi-person-circle me-2"></i>
            <?= $_SESSION["first_name"] ?> <?= $_SESSION["last_name"] ?? "" ?>
          </h6></li>
          <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
          <li><a class="dropdown-item" href="Eprofile.php"><i class="bi bi-pencil-square me-2"></i>Edit Profile</a></li>
          <li><a class="dropdown-item" href="logout.html"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>

<!-- Content Layout -->
<div class="container-fluid mt-4 px-4">
  <div class="row g-4">
    
    <!-- Left: Featured Post -->
    <div class="col-lg-3">
      <?php
      // Fetch pinned admin posts for featured section
      $featured_query = "SELECT p.*, 
                        CASE 
                            WHEN p.author_type = 'admin' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM admins WHERE id = p.user_id)
                            ELSE (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = p.user_id)
                        END as author_name
                        FROM posts p 
                        WHERE p.is_pinned = 1
                        ORDER BY p.created_at DESC 
                        LIMIT 1";
      $featured_result = $conn->query($featured_query);
      
      if ($featured_result->num_rows > 0):
        $featured_post = $featured_result->fetch_assoc();

      ?>
        <div class="featured-box" style="cursor: pointer;" onclick="scrollToPinnedPost(<?= $featured_post['id'] ?>)" data-post-id="<?= $featured_post['id'] ?>" title="Click to view the full post">
          <p class="tag">FEATURED POST by <?= htmlspecialchars($featured_post['author_name']) ?></p>
          <h6 class="fw-bold text-success"><?= htmlspecialchars(strtoupper(substr($featured_post['content'], 0, 50))) ?><?= strlen($featured_post['content']) > 50 ? '...' : '' ?></h6>
          <p class="mb-1"><i class="bi bi-calendar-event me-2"></i><strong><?= date('F j, Y', strtotime($featured_post['created_at'])) ?></strong></p>
          <p style="font-size: 14px;"><?= htmlspecialchars(substr($featured_post['content'], 0, 150)) ?><?= strlen($featured_post['content']) > 150 ? '...' : '' ?></p>
          <div class="mb-2" style="font-size: 13px;">
            <i class="bi bi-pin-angle-fill text-success me-1"></i>
            <small class="text-success">PINNED ANNOUNCEMENT</small>
          </div>
          <p class="text-muted mb-0" style="font-size: 12px;">#AdminAnnouncement #CommunityUpdate</p>
          <div class="mt-2">
            <small class="text-white-50"><i class="bi bi-arrow-down me-1"></i>Click to view full post</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Center: Post Input + Feed -->
    <div class="col-lg-6">



      <!-- Success/Error Messages -->
      <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= $success ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= $error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($comment_success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= $comment_success ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($comment_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= $comment_error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($comment_edit_success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= $comment_edit_success ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($comment_edit_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= $comment_edit_error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($comment_delete_success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= $comment_delete_success ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($comment_delete_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= $comment_delete_error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($edit_success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= $edit_success ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($edit_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= $edit_error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($delete_success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= $delete_success ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($delete_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= $delete_error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          Post deleted successfully!
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($_GET['debug'])): ?>
        <div class="alert alert-info">
          <strong>Debug Info:</strong><br>
          Session User ID: <?= $_SESSION["user_id"] ?? "Not set" ?><br>
          Request Method: <?= $_SERVER["REQUEST_METHOD"] ?><br>
          POST Data: <?= print_r($_POST, true) ?><br>
        </div>
      <?php endif; ?>

      <!-- Post Creation Form -->
      <div class="post-box">
        <form method="POST" enctype="multipart/form-data">
          <div class="d-flex align-items-center mb-2">
            <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
              <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #27692A;" class="me-2">
            <?php else: ?>
              <i class="bi bi-person-circle fs-3 me-2 text-success"></i>
            <?php endif; ?>
            <textarea name="content" class="form-control green-textbox" placeholder="Share something to your community!" rows="3" required></textarea>
          </div>
          
          <!-- File upload info -->
          <div class="mb-2">
            <small class="text-muted">
              <i class="bi bi-info-circle"></i> 
              You can select multiple images and videos. Supported formats: JPG, PNG, GIF (images) and MP4, AVI, MOV, WMV (videos). Max size: 50MB per file
            </small>
          </div>
          
          <!-- Media Preview -->
          <div id="mediaPreview" class="text-center" style="display: none;">
            <div id="mediaContainer" class="d-flex flex-wrap gap-2 justify-content-center">
              <!-- Media previews will be added here -->
            </div>
          </div>
          
          <div class="d-flex justify-content-between mt-2">
            <div class="d-flex gap-2">
              <label class="btn btn-outline-success btn-sm file-upload">
                <i class="bi bi-image"></i> Images
                <input type="file" name="media_image[]" accept="image/*" multiple onchange="previewMultipleMedia(this, 'image')">
              </label>
              <label class="btn btn-outline-success btn-sm file-upload">
                <i class="bi bi-camera-video"></i> Videos
                <input type="file" name="media_video[]" accept="video/*" multiple onchange="previewMultipleMedia(this, 'video')">
              </label>
            </div>
            <button type="submit" name="submit_post" class="btn btn-green btn-sm">Post</button>
          </div>
        </form>
      </div>

      <!-- Community Feed Posts -->
      <?php if ($posts_result && $posts_result->num_rows > 0): ?>
        <?php while ($post = $posts_result->fetch_assoc()): ?>
          <div class="post-box feed-post <?= $post['is_pinned'] ? 'pinned-post' : '' ?>" data-post-id="<?= $post['id'] ?>">
            <?php if ($post['is_pinned']): ?>
              <div class="pinned-indicator mb-2">
                <i class="bi bi-pin-angle-fill text-success me-1"></i>
                <small class="text-success">PINNED ANNOUNCEMENT</small>
              </div>
            <?php endif; ?>
            <div class="d-flex align-items-center mb-2">
              <?php if (!empty($post['avatar']) && file_exists($post['avatar'])): ?>
                <img src="<?= htmlspecialchars($post['avatar']) ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #27692A;" class="me-2">
              <?php else: ?>
                <i class="bi bi-person-circle fs-4 me-2 text-success"></i>
              <?php endif; ?>
              <div class="flex-grow-1">
                <?php if ($post['author_type'] == 'admin'): ?>
                  <strong class="text-success"><?= htmlspecialchars($post['author_name']) ?> <span class="badge bg-success">ADMIN</span></strong>
                <?php else: ?>
                  <a href="profile.php?user_id=<?= $post['user_id'] ?>" class="text-decoration-none text-success" style="cursor: pointer;">
                    <strong><?= htmlspecialchars($post['author_name']) ?></strong>
                  </a>
                <?php endif; ?>
                <div class="text-muted" style="font-size: 13px;">
                  <?= date('M j, Y \a\t g:i A', strtotime($post['created_at'])) ?>
                  <?php if ($post['location']): ?>
                    at <?= htmlspecialchars($post['location']) ?>
                  <?php endif; ?>
                </div>
                <?php if (!empty($post['bio'])): ?>
                  <div class="text-muted mt-1" style="font-size: 12px; font-style: italic;">
                    <i class="bi bi-quote me-1"></i><?= htmlspecialchars($post['bio']) ?>
                  </div>
                <?php endif; ?>
              </div>
              
              <!-- Edit/Delete buttons (only for post owner) -->
              <?php if ($post['user_id'] == $_SESSION["user_id"]): ?>
                <div class="dropdown">
                  <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="editPost(<?= $post['id'] ?>, '<?= htmlspecialchars(addslashes($post['content'])) ?>')">
                      <i class="bi bi-pencil me-2"></i>Edit Post
                    </a></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="deletePost(<?= $post['id'] ?>)">
                      <i class="bi bi-trash me-2"></i>Delete Post
                    </a></li>
                  </ul>
                </div>
              <?php endif; ?>
            </div>
            
            <?php if ($post['content']): ?>
              <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            <?php endif; ?>
            
            <!-- Debug info (remove in production) -->
            <?php if (isset($_GET['debug'])): ?>
              <div class="alert alert-info">
                <small>
                  <strong>Debug Info:</strong><br>
                  Post ID: <?= htmlspecialchars($post['id']) ?><br>
                </small>
              </div>
            <?php endif; ?>
            
            <!-- Display Media Files -->
            <?php
            // Fetch media files for this post
            $media_query = "SELECT * FROM media_files WHERE post_id = ? ORDER BY created_at ASC";
            $media_stmt = $conn->prepare($media_query);
            $media_stmt->bind_param("i", $post['id']);
            $media_stmt->execute();
            $media_result = $media_stmt->get_result();
            
            if ($media_result->num_rows > 0):
                $media_files = [];
                while ($media = $media_result->fetch_assoc()) {
                    if (file_exists($media['file_path'])) {
                        $media_files[] = $media;
                    }
                }
                
                if (!empty($media_files)):
            ?>
              <div class="media-gallery mt-2">
                <div class="media-grid">
                  <?php if (count($media_files) == 1): ?>
                    <!-- Single image -->
                    <div class="media-item single">
                      <?php if ($media_files[0]['file_type'] == 'image'): ?>
                        <img src="<?= htmlspecialchars($media_files[0]['file_path']) ?>" alt="Post image" class="post-media" onclick="openGallery(<?= $post['id'] ?>, 0)" />
                      <?php elseif ($media_files[0]['file_type'] == 'video'): ?>
                        <video controls class="post-media">
                          <source src="<?= htmlspecialchars($media_files[0]['file_path']) ?>" type="video/mp4">
                          Your browser does not support the video tag.
                        </video>
                      <?php endif; ?>
                    </div>
                  <?php elseif (count($media_files) == 2): ?>
                    <!-- Two images side by side -->
                    <?php foreach ($media_files as $index => $media): ?>
                      <div class="media-item">
                        <?php if ($media['file_type'] == 'image'): ?>
                          <img src="<?= htmlspecialchars($media['file_path']) ?>" alt="Post image" class="post-media" onclick="openGallery(<?= $post['id'] ?>, <?= $index ?>)" />
                        <?php elseif ($media['file_type'] == 'video'): ?>
                          <video controls class="post-media">
                            <source src="<?= htmlspecialchars($media['file_path']) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                          </video>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <!-- First two images with overlay on second -->
                    <div class="media-item">
                      <?php if ($media_files[0]['file_type'] == 'image'): ?>
                        <img src="<?= htmlspecialchars($media_files[0]['file_path']) ?>" alt="Post image" class="post-media" onclick="openGallery(<?= $post['id'] ?>, 0)" />
                      <?php elseif ($media_files[0]['file_type'] == 'video'): ?>
                        <video controls class="post-media">
                          <source src="<?= htmlspecialchars($media_files[0]['file_path']) ?>" type="video/mp4">
                          Your browser does not support the video tag.
                        </video>
                      <?php endif; ?>
                    </div>
                    <div class="media-item">
                      <?php if ($media_files[1]['file_type'] == 'image'): ?>
                        <img src="<?= htmlspecialchars($media_files[1]['file_path']) ?>" alt="Post image" class="post-media" onclick="openGallery(<?= $post['id'] ?>, 1)" />
                      <?php elseif ($media_files[1]['file_type'] == 'video'): ?>
                        <video controls class="post-media">
                          <source src="<?= htmlspecialchars($media_files[1]['file_path']) ?>" type="video/mp4">
                          Your browser does not support the video tag.
                        </video>
                      <?php endif; ?>
                      <!-- Overlay for additional images -->
                      <div class="media-overlay" onclick="openGallery(<?= $post['id'] ?>, 1)">
                        <div class="overlay-content">
                          <span class="overlay-text">+<?= count($media_files) - 2 ?> more</span>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php $media_stmt->close(); ?>
            
            <!-- Post Actions -->
            <div class="post-actions">
              <button class="post-action-btn like-btn" onclick="toggleLike(<?= $post['id'] ?>, this)" data-post-id="<?= $post['id'] ?>">
                <i class="bi bi-heart<?= $post['user_liked'] > 0 ? '-fill text-danger' : '' ?>"></i>
                <span class="like-count"><?= $post['like_count'] ?></span>
              </button>
              <button class="post-action-btn comment-count" onclick="toggleComments(<?= $post['id'] ?>)" data-post-id="<?= $post['id'] ?>">
                <i class="bi bi-chat-dots"></i>
                Comment (<?= $post['comment_count'] ?>)
              </button>
              <button class="post-action-btn" onclick="sharePost(<?= $post['id'] ?>)">
                <i class="bi bi-share"></i>
                Share
              </button>
              

            </div>
            
            <!-- Comments Section -->
            <div id="comments-<?= $post['id'] ?>" class="comment-section" style="display: none;">
              <!-- Comment Input -->
              <form class="comment-input">
                <input type="text" name="comment_content" placeholder="Write a comment..." required>
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <button type="submit" name="submit_comment">Comment</button>
              </form>
              
                             <!-- Comments List -->
               <div class="comments-list">
                 <?php
                 // Reconnect to database for comments
                 $conn = new mysqli("localhost", "root", "", "ground_zero");
                 if ($conn->connect_error) {
                     die("Connection failed: " . $conn->connect_error);
                 }
                 
                 // Fetch comments for this post
                 $comments_query = "SELECT c.*, u.first_name, u.last_name, u.avatar 
                                  FROM comments c 
                                  INNER JOIN users u ON c.user_id = u.id 
                                  WHERE c.post_id = ? 
                                  ORDER BY c.created_at ASC";
                 $stmt = $conn->prepare($comments_query);
                 $stmt->bind_param("i", $post['id']);
                 $stmt->execute();
                 $comments_result = $stmt->get_result();
                
                if ($comments_result->num_rows > 0):
                  $comment_counter = 0;
                  while ($comment = $comments_result->fetch_assoc()):
                    $comment_counter++;
                ?>
                  <div class="comment-item" data-comment-id="<?= $comment['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                          <?php 
                          // Get the user's avatar for this comment
                          $comment_avatar_query = "SELECT avatar FROM users WHERE id = ?";
                          $comment_avatar_stmt = $conn->prepare($comment_avatar_query);
                          $comment_avatar_stmt->bind_param("i", $comment['user_id']);
                          $comment_avatar_stmt->execute();
                          $comment_avatar_result = $comment_avatar_stmt->get_result();
                          $comment_avatar_data = $comment_avatar_result->fetch_assoc();
                          ?>
                          <?php if (!empty($comment_avatar_data['avatar']) && file_exists($comment_avatar_data['avatar'])): ?>
                            <img src="<?= htmlspecialchars($comment_avatar_data['avatar']) ?>" alt="Profile Picture" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid #27692A;" class="me-2">
                          <?php else: ?>
                            <i class="bi bi-person-circle me-2 text-success"></i>
                          <?php endif; ?>
                          <div class="comment-author"><?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?></div>
                        </div>
                        <div class="comment-content" id="comment-content-<?= $comment['id'] ?>"><?= htmlspecialchars($comment['content']) ?></div>
                        <div class="comment-time"><?= date('M j, Y \a\t g:i A', strtotime($comment['created_at'])) ?></div>
                      </div>
                      
                      <!-- Edit/Delete buttons for comment owner -->
                      <?php if ($comment['user_id'] == $_SESSION["user_id"]): ?>
                        <div class="dropdown">
                          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots"></i>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="editComment(<?= $comment['id'] ?>, '<?= htmlspecialchars(addslashes($comment['content'])) ?>')">
                              <i class="bi bi-pencil me-2"></i>Edit Comment
                            </a></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteComment(<?= $comment['id'] ?>)">
                              <i class="bi bi-trash me-2"></i>Delete Comment
                            </a></li>
                          </ul>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php 
                  endwhile;
                else:
                ?>
                  <div class="text-muted" style="font-size: 13px; padding: 10px 0;">No comments yet. Be the first to comment!</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="post-box text-center text-muted">
          <i class="bi bi-chat-dots fs-1"></i>
          <p>No posts yet. Be the first to share something!</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right: User Profile -->
    <div class="col-lg-3">
      <div class="user-profile">
        <div class="icon mb-2">
          <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Profile Picture" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #27692A;">
          <?php else: ?>
            <i class="bi bi-person-circle"></i>
          <?php endif; ?>
        </div>
        <h6 class="mb-1"><?= $_SESSION["first_name"] ?> <?= $_SESSION["last_name"] ?? "" ?></h6>
        <small class="text-muted">@<?= strtolower($_SESSION["first_name"]) ?></small>
        <p class="mt-2 mb-1"><i class="bi bi-geo-alt-fill me-1"></i>Lives in <strong><?= htmlspecialchars($user['barangay'] ?? 'Malvar, Batangas') ?></strong></p>
        <?php if (!empty($user['bio'])): ?>
            <p class="mt-1 mb-1" style="font-size: 12px; font-style: italic; color: #6c757d;">
                <i class="bi bi-quote me-1"></i><?= htmlspecialchars($user['bio']) ?>
            </p>
        <?php endif; ?>
        <a href="profile.php" class="btn btn-green btn-sm mt-2">My Profile</a>
      </div>
    </div>

  </div>
</div>

<!-- Edit Post Modal -->
<div class="modal fade" id="editPostModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Post</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editPostForm" method="POST">
          <input type="hidden" name="edit_post_id" id="editPostId">
          <div class="mb-3">
            <label for="editPostContent" class="form-label">Post Content</label>
            <textarea class="form-control green-textbox" id="editPostContent" name="edit_content" rows="4" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-green" onclick="submitEditPost()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Comment Modal -->
<div class="modal fade" id="editCommentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Comment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editCommentForm" method="POST">
          <input type="hidden" name="edit_comment_id" id="editCommentId">
          <div class="mb-3">
            <label for="editCommentContent" class="form-label">Comment Content</label>
            <textarea class="form-control green-textbox" id="editCommentContent" name="edit_comment_content" rows="3" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-green" onclick="submitEditComment()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Gallery Modal -->
<div id="galleryModal" class="gallery-modal">
  <div class="gallery-content">
    <span class="gallery-close" onclick="closeGallery()">&times;</span>
    <button class="gallery-nav gallery-prev" onclick="changeSlide(-1)">&#10094;</button>
    <button class="gallery-nav gallery-next" onclick="changeSlide(1)">&#10095;</button>
    <div id="gallerySlides"></div>
    <div class="gallery-counter">
      <span id="currentSlide">1</span> / <span id="totalSlides">1</span>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function previewMultipleMedia(input, type) {
    const files = input.files;
    const container = document.getElementById('mediaContainer');
    const preview = document.getElementById('mediaPreview');
    
    
    
    if (files.length > 0) {
        // Clear existing previews
        container.innerHTML = '';
        
        // Create a 2-image layout for previews
        container.style.display = 'flex';
        container.style.gap = '4px';
        container.style.maxWidth = '300px';
        container.style.margin = '0 auto';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewContainer = document.createElement('div');
                previewContainer.className = 'preview-container';
                previewContainer.dataset.index = i;
                previewContainer.style.position = 'relative';
                previewContainer.style.flex = '1';
                previewContainer.style.height = '120px';
                previewContainer.style.overflow = 'hidden';
                previewContainer.style.borderRadius = '6px';
                previewContainer.style.border = '1px solid #ddd';
                
                if (type === 'image') {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    img.alt = 'Preview';
                    previewContainer.appendChild(img);
                } else if (type === 'video') {
                    const video = document.createElement('video');
                    video.src = e.target.result;
                    video.style.width = '100%';
                    video.style.height = '100%';
                    video.style.objectFit = 'cover';
                    video.controls = true;
                    previewContainer.appendChild(video);
                }
                
                // Add remove button
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-preview';
                removeBtn.innerHTML = '×';
                removeBtn.style.position = 'absolute';
                removeBtn.style.top = '4px';
                removeBtn.style.right = '4px';
                removeBtn.style.background = 'rgba(255, 0, 0, 0.9)';
                removeBtn.style.color = 'white';
                removeBtn.style.border = 'none';
                removeBtn.style.borderRadius = '50%';
                removeBtn.style.width = '18px';
                removeBtn.style.height = '18px';
                removeBtn.style.fontSize = '12px';
                removeBtn.style.cursor = 'pointer';
                removeBtn.style.zIndex = '10';
                removeBtn.onclick = function() {
                    removePreviewItem(i, input);
                };
                previewContainer.appendChild(removeBtn);
                
                container.appendChild(previewContainer);
            };
            
            reader.readAsDataURL(file);
        }
        
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

function removePreviewItem(index, input) {
    // Create a new FileList without the removed file
    const dt = new DataTransfer();
    const files = input.files;
    
    for (let i = 0; i < files.length; i++) {
        if (i !== index) {
            dt.items.add(files[i]);
        }
    }
    
    input.files = dt.files;
    
    // Remove the preview element
    const container = document.getElementById('mediaContainer');
    const previewContainer = container.querySelector(`[data-index="${index}"]`);
    if (previewContainer) {
        previewContainer.remove();
    }
    
    // If no files left, hide preview
    if (input.files.length === 0) {
        document.getElementById('mediaPreview').style.display = 'none';
        container.innerHTML = '';
        container.style.display = 'flex';
    }
}

function removeMedia() {
    const preview = document.getElementById('mediaPreview');
    const container = document.getElementById('mediaContainer');
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    preview.style.display = 'none';
    container.innerHTML = '';
    
    // Clear file inputs
    fileInputs.forEach(input => {
        input.value = '';
    });
}

function toggleComments(postId) {
    const commentsSection = document.getElementById('comments-' + postId);
    if (commentsSection.style.display === 'none') {
        commentsSection.style.display = 'block';
    } else {
        commentsSection.style.display = 'none';
    }
}

function toggleLike(postId, button) {
    const formData = new FormData();
    formData.append('toggle_like', '1');
    formData.append('post_id', postId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const heartIcon = button.querySelector('i');
        const likeCount = button.querySelector('.like-count');
        const currentCount = parseInt(likeCount.textContent);
        
        if (data.status === 'liked') {
            heartIcon.className = 'bi bi-heart-fill text-danger';
            likeCount.textContent = currentCount + 1;
        } else {
            heartIcon.className = 'bi bi-heart';
            likeCount.textContent = currentCount - 1;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating like. Please try again.');
    });
}

function sharePost(postId) {
    // Get the current URL and add the post ID
    const currentUrl = window.location.href;
    const shareUrl = currentUrl + '?post=' + postId;
    
    // Copy to clipboard
    navigator.clipboard.writeText(shareUrl).then(function() {
        alert('Post link copied to clipboard!');
    }).catch(function(err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = shareUrl;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Post link copied to clipboard!');
    });
}

function editPost(postId, content) {
    // Set the form values
    document.getElementById('editPostId').value = postId;
    document.getElementById('editPostContent').value = content;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('editPostModal'));
    modal.show();
}

function submitEditPost() {
    const form = document.getElementById('editPostForm');
    const formData = new FormData(form);
    formData.append('edit_post_id', document.getElementById('editPostId').value);
    formData.append('edit_content', document.getElementById('editPostContent').value);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            // Reload the page to show updated content
            window.location.reload();
        } else {
            alert('Error updating post. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating post. Please try again.');
    });
}

function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('delete_post_id', postId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Remove the post element from the page
            const postElement = document.querySelector(`[data-post-id="${postId}"]`);
            if (postElement) {
                postElement.remove();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting post. Please try again.');
        });
    }
}

function editComment(commentId, content) {
    // Set the form values
    document.getElementById('editCommentId').value = commentId;
    document.getElementById('editCommentContent').value = content;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('editCommentModal'));
    modal.show();
}

function submitEditComment() {
    const form = document.getElementById('editCommentForm');
    const formData = new FormData(form);
    formData.append('edit_comment_id', document.getElementById('editCommentId').value);
    formData.append('edit_comment_content', document.getElementById('editCommentContent').value);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            // Reload the page to show updated content
            window.location.reload();
        } else {
            alert('Error updating comment. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating comment. Please try again.');
    });
}

// Handle comment form submission
document.addEventListener('DOMContentLoaded', function() {
    const commentForms = document.querySelectorAll('.comment-input');
    commentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const formData = new FormData(form);
            formData.append('submit_comment', '1'); // Add the submit_comment parameter
            
            const postId = formData.get('post_id');
            const commentContent = formData.get('comment_content');
            
            
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
        
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
        
                if (data.success) {
                    // Clear the input
                    form.querySelector('input[name="comment_content"]').value = '';
                    
                    // Add the new comment dynamically
                    const commentSection = form.closest('.comment-section');
                    const commentsList = commentSection.querySelector('.comments-list');
                    if (commentsList) {
                        const newComment = createCommentElement(data.comment);
                        commentsList.appendChild(newComment);
                        
                        // Update comment count
                        const postElement = form.closest('.feed-post');
                        const commentCountElement = postElement.querySelector('.comment-count');
                        if (commentCountElement) {
                            const currentCount = parseInt(commentCountElement.textContent.match(/\d+/)[0]);
                            const newCount = currentCount + 1;
                            commentCountElement.textContent = commentCountElement.textContent.replace(/\d+/, newCount);
                        }
                        
                        // Show the comment section if it's hidden
                        if (commentSection.style.display === 'none') {
                            commentSection.style.display = 'block';
                        }
                    }
                } else {
                    throw new Error(data.error || 'Comment submission failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack
                });
                
                // Try to get more detailed error information
                if (error.message.includes('JSON')) {
                    alert('Error parsing server response. Please try again.');
                } else {
                    alert('Error adding comment. Please try again.');
                }
            });
        });
    });
});

function createCommentElement(commentData) {
    const commentDiv = document.createElement('div');
    commentDiv.className = 'comment-item';
    commentDiv.setAttribute('data-comment-id', commentData.id);
    
    const currentUser = <?= json_encode($_SESSION["user_id"]) ?>;
    const isOwner = commentData.user_id == currentUser;
    
    // Get avatar from comment data or current user
    const avatar = commentData.avatar || <?= json_encode(!empty($user['avatar']) && file_exists($user['avatar']) ? $user['avatar'] : '') ?>;
    const avatarHtml = avatar ? 
        `<img src="${avatar}" alt="Profile Picture" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid #27692A;" class="me-2">` :
        `<i class="bi bi-person-circle me-2 text-success"></i>`;
    
    commentDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-1">
                    ${avatarHtml}
                    <div class="comment-author">${commentData.first_name} ${commentData.last_name}</div>
                </div>
                <div class="comment-content" id="comment-content-${commentData.id}">${commentData.content}</div>
                <div class="comment-time">${new Date(commentData.created_at).toLocaleString()}</div>
            </div>
            ${isOwner ? `
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="editComment(${commentData.id}, '${commentData.content.replace(/'/g, "\\'")}')">
                            <i class="bi bi-pencil me-2"></i>Edit Comment
                        </a></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteComment(${commentData.id})">
                            <i class="bi bi-trash me-2"></i>Delete Comment
                        </a></li>
                    </ul>
                </div>
            ` : ''}
        </div>
    `;
    
    return commentDiv;
}

function deleteComment(commentId) {
    if (confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('delete_comment_id', commentId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Remove the comment element from the page
            const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
            if (commentElement) {
                commentElement.remove();
                
                // Update the comment count
                const postElement = commentElement.closest('.feed-post');
                if (postElement) {
                    const commentCountElement = postElement.querySelector('.comment-count');
                    if (commentCountElement) {
                        const currentCount = parseInt(commentCountElement.textContent.match(/\d+/)[0]);
                        const newCount = Math.max(0, currentCount - 1);
                        commentCountElement.textContent = commentCountElement.textContent.replace(/\d+/, newCount);
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting comment. Please try again.');
        });
    }
}

// Gallery functionality
let currentSlideIndex = 0;
let galleryMedia = [];

function openGallery(postId, startIndex = 0) {
    // Fetch all media files for this post via AJAX
    fetch(`get_media.php?post_id=${postId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.media.length > 0) {
                galleryMedia = data.media;
                currentSlideIndex = startIndex;
                showSlide(currentSlideIndex);
                document.getElementById('galleryModal').style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
            }
        })
        .catch(error => {
            console.error('Error fetching media:', error);
            // Fallback to visible media only
            const postElement = document.querySelector(`[data-post-id="${postId}"]`);
            if (!postElement) return;
            
            const mediaItems = postElement.querySelectorAll('.post-media');
            galleryMedia = [];
            
            mediaItems.forEach((item, index) => {
                if (item.tagName === 'IMG') {
                    galleryMedia.push({
                        type: 'image',
                        src: item.src,
                        alt: item.alt
                    });
                } else if (item.tagName === 'VIDEO') {
                    const source = item.querySelector('source');
                    if (source) {
                        galleryMedia.push({
                            type: 'video',
                            src: source.src
                        });
                    }
                }
            });
            
            if (galleryMedia.length === 0) return;
            
            currentSlideIndex = startIndex;
            showSlide(currentSlideIndex);
            document.getElementById('galleryModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
}

function closeGallery() {
    document.getElementById('galleryModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
}

function changeSlide(direction) {
    currentSlideIndex += direction;
    
    if (currentSlideIndex >= galleryMedia.length) {
        currentSlideIndex = 0;
    } else if (currentSlideIndex < 0) {
        currentSlideIndex = galleryMedia.length - 1;
    }
    
    showSlide(currentSlideIndex);
}

function showSlide(index) {
    const slidesContainer = document.getElementById('gallerySlides');
    const currentSlideSpan = document.getElementById('currentSlide');
    const totalSlidesSpan = document.getElementById('totalSlides');
    
    slidesContainer.innerHTML = '';
    
    if (galleryMedia[index]) {
        const slide = document.createElement('div');
        slide.className = 'gallery-slide active';
        
        if (galleryMedia[index].type === 'image') {
            const img = document.createElement('img');
            img.src = galleryMedia[index].src;
            img.alt = galleryMedia[index].alt || 'Gallery image';
            slide.appendChild(img);
        } else if (galleryMedia[index].type === 'video') {
            const video = document.createElement('video');
            video.controls = true;
            video.autoplay = true;
            const source = document.createElement('source');
            source.src = galleryMedia[index].src;
            source.type = 'video/mp4';
            video.appendChild(source);
            slide.appendChild(video);
        }
        
        slidesContainer.appendChild(slide);
    }
    
    currentSlideSpan.textContent = index + 1;
    totalSlidesSpan.textContent = galleryMedia.length;
}

// Close gallery when clicking outside
document.getElementById('galleryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeGallery();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (document.getElementById('galleryModal').style.display === 'block') {
        if (e.key === 'Escape') {
            closeGallery();
        } else if (e.key === 'ArrowLeft') {
            changeSlide(-1);
        } else if (e.key === 'ArrowRight') {
            changeSlide(1);
        }
    }
});

// Auto-dismiss alert messages
document.addEventListener('DOMContentLoaded', function() {
    // Get all alert elements
    const alerts = document.querySelectorAll('.alert');
    
    // Auto-dismiss each alert after 5 seconds
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // Check if the alert is still in the DOM
            if (alert.parentNode) {
                // Use Bootstrap's dismiss method if available
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000); // 5 seconds
    });
});

// Notification functionality
document.addEventListener('DOMContentLoaded', function() {
    // Use event delegation for notification clicks (works with dynamic content)
    document.addEventListener('click', function(e) {
        const notificationItem = e.target.closest('.notification-item');
        if (!notificationItem) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        const notificationId = notificationItem.getAttribute('data-notification-id');
        const postId = notificationItem.getAttribute('data-post-id');
        
        // Debug: Check if postId exists
        if (!postId) {
            console.error('No post ID found in notification');
            return;
        }
        
        // Scroll to the post immediately
        scrollToPost(postId);
        
        // Mark as read via AJAX
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove unread styling
                notificationItem.classList.remove('unread');
                
                // Update notification count
                updateNotificationCount();
                
                // Close the notification dropdown
                const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('notifDropdown'));
                if (dropdown) {
                    dropdown.hide();
                }
            }
        })
        .catch(error => console.error('Error:', error));
    });
});

function updateNotificationCount() {
    fetch('get_notification_count.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.querySelector('#notifDropdown .badge');
        if (data.unread_count > 0) {
            if (badge) {
                badge.textContent = data.unread_count;
            } else {
                // Create badge if it doesn't exist
                const newBadge = document.createElement('span');
                newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                newBadge.textContent = data.unread_count;
                document.querySelector('#notifDropdown').appendChild(newBadge);
            }
        } else {
            // Remove badge if count is 0
            if (badge) {
                badge.remove();
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Function to scroll to a specific post
function scrollToPost(postId) {
    const targetPost = document.querySelector(`[data-post-id="${postId}"]`);
    
    if (targetPost) {
        targetPost.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add a highlight effect to the post
        targetPost.classList.add('highlight');
        setTimeout(() => {
            targetPost.classList.remove('highlight');
        }, 2000);
    }
}

  // Function to scroll to pinned post specifically
  function scrollToPinnedPost(postId) {
    // Use the regular scrollToPost function since pinned posts are now in the main feed
    scrollToPost(postId);
  }
</script>
</body>
</html>
