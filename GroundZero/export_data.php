<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Check if export request was made
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['export_data'])) {
    
    // Database connection
    $conn = new mysqli("localhost", "root", "", "ground_zero");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $user_id = $_SESSION["user_id"];
    
    // Get user's basic information
    $user_query = "SELECT id, first_name, last_name, email, phone, barangay, bio, establishment_type, establishment_name, status, created_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    
    // Get user's posts
    $posts_query = "SELECT id, content, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC";
    $posts_stmt = $conn->prepare($posts_query);
    $posts_stmt->bind_param("i", $user_id);
    $posts_stmt->execute();
    $posts_result = $posts_stmt->get_result();
    $posts = [];
    while ($post = $posts_result->fetch_assoc()) {
        $posts[] = $post;
    }
    
    // Get user's comments
    $comments_query = "SELECT c.id, c.content, c.created_at, p.content as post_content 
                       FROM comments c 
                       INNER JOIN posts p ON c.post_id = p.id 
                       WHERE c.user_id = ? 
                       ORDER BY c.created_at DESC";
    $comments_stmt = $conn->prepare($comments_query);
    $comments_stmt->bind_param("i", $user_id);
    $comments_stmt->execute();
    $comments_result = $comments_stmt->get_result();
    $comments = [];
    while ($comment = $comments_result->fetch_assoc()) {
        $comments[] = $comment;
    }
    
    // Get user's likes
    $likes_query = "SELECT l.id, l.created_at, p.content as post_content 
                    FROM likes l 
                    INNER JOIN posts p ON l.post_id = p.id 
                    WHERE l.user_id = ? 
                    ORDER BY l.created_at DESC";
    $likes_stmt = $conn->prepare($likes_query);
    $likes_stmt->bind_param("i", $user_id);
    $likes_stmt->execute();
    $likes_result = $likes_stmt->get_result();
    $likes = [];
    while ($like = $likes_result->fetch_assoc()) {
        $likes[] = $like;
    }
    
    // Get media files associated with user's posts
    $media_query = "SELECT mf.*, p.content as post_content 
                    FROM media_files mf 
                    INNER JOIN posts p ON mf.post_id = p.id 
                    WHERE p.user_id = ? 
                    ORDER BY mf.created_at DESC";
    $media_stmt = $conn->prepare($media_query);
    $media_stmt->bind_param("i", $user_id);
    $media_stmt->execute();
    $media_result = $media_stmt->get_result();
    $media_files = [];
    while ($media = $media_result->fetch_assoc()) {
        $media_files[] = $media;
    }
    
    // Generate PDF content
    $html_content = generatePDFContent($user_data, $posts, $comments, $likes, $media_files);
    
    // Generate filename
    $filename = 'user_data_' . $user_id . '_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Set headers for HTML download
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    
    // Generate HTML file that can be printed as PDF
    generateHTML($html_content, $filename);
    
    // Close database connections
    $stmt->close();
    $posts_stmt->close();
    $comments_stmt->close();
    $likes_stmt->close();
    $media_stmt->close();
    $conn->close();
    
    exit();
} else {
    // If accessed directly without POST request, redirect to edit profile
    header("Location: Eprofile.php");
    exit();
}

function generatePDFContent($user_data, $posts, $comments, $likes, $media_files) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>User Data Export</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #27692A; padding-bottom: 20px; }
            .section { margin-bottom: 30px; }
            .section-title { color: #27692A; font-size: 18px; font-weight: bold; margin-bottom: 15px; border-left: 4px solid #27692A; padding-left: 10px; }
            .info-grid { display: table; width: 100%; margin-bottom: 20px; }
            .info-row { display: table-row; }
            .info-label { display: table-cell; font-weight: bold; width: 150px; padding: 5px; }
            .info-value { display: table-cell; padding: 5px; }
            .item { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
            .item-date { color: #666; font-size: 12px; }
            .item-content { margin-top: 5px; }
            .stats { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .stats-grid { display: table; width: 100%; }
            .stats-item { display: table-cell; text-align: center; }
            .stats-number { font-size: 24px; font-weight: bold; color: #27692A; }
            .stats-label { font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Ground Zero - User Data Export</h1>
            <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
        </div>
        
        <div class="section">
            <div class="section-title">Personal Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Name:</div>
                    <div class="info-value">' . htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value">' . htmlspecialchars($user_data['email']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone:</div>
                    <div class="info-value">' . htmlspecialchars($user_data['phone'] ?? 'Not provided') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Barangay:</div>
                    <div class="info-value">' . htmlspecialchars($user_data['barangay'] ?? 'Not set') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Bio:</div>
                    <div class="info-value">' . htmlspecialchars($user_data['bio'] ?? 'No bio provided') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Account Status:</div>
                    <div class="info-value">' . ucfirst(htmlspecialchars($user_data['status'])) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Member Since:</div>
                    <div class="info-value">' . date('F j, Y', strtotime($user_data['created_at'])) . '</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Activity Summary</div>
            <div class="stats">
                <div class="stats-grid">
                    <div class="stats-item">
                        <div class="stats-number">' . count($posts) . '</div>
                        <div class="stats-label">Posts Created</div>
                    </div>
                    <div class="stats-item">
                        <div class="stats-number">' . count($comments) . '</div>
                        <div class="stats-label">Comments Made</div>
                    </div>
                    <div class="stats-item">
                        <div class="stats-number">' . count($likes) . '</div>
                        <div class="stats-label">Likes Given</div>
                    </div>
                    <div class="stats-item">
                        <div class="stats-number">' . count($media_files) . '</div>
                        <div class="stats-label">Media Files</div>
                    </div>
                </div>
            </div>
        </div>';
    
    // Add Posts Section
    if (!empty($posts)) {
        $html .= '
        <div class="section">
            <div class="section-title">Posts (' . count($posts) . ')</div>';
        foreach ($posts as $post) {
            $html .= '
            <div class="item">
                <div class="item-date">' . date('F j, Y \a\t g:i A', strtotime($post['created_at'])) . '</div>
                <div class="item-content">' . nl2br(htmlspecialchars($post['content'])) . '</div>
            </div>';
        }
        $html .= '</div>';
    }
    
    // Add Comments Section
    if (!empty($comments)) {
        $html .= '
        <div class="section">
            <div class="section-title">Comments (' . count($comments) . ')</div>';
        foreach ($comments as $comment) {
            $html .= '
            <div class="item">
                <div class="item-date">' . date('F j, Y \a\t g:i A', strtotime($comment['created_at'])) . '</div>
                <div class="item-content"><strong>Comment:</strong> ' . nl2br(htmlspecialchars($comment['content'])) . '</div>
                <div class="item-content"><strong>On Post:</strong> ' . htmlspecialchars(substr($comment['post_content'], 0, 100)) . (strlen($comment['post_content']) > 100 ? '...' : '') . '</div>
            </div>';
        }
        $html .= '</div>';
    }
    
    // Add Media Files Section
    if (!empty($media_files)) {
        $html .= '
        <div class="section">
            <div class="section-title">Media Files (' . count($media_files) . ')</div>';
        foreach ($media_files as $media) {
            $html .= '
            <div class="item">
                <div class="item-date">' . date('F j, Y \a\t g:i A', strtotime($media['created_at'])) . '</div>
                <div class="item-content"><strong>File:</strong> ' . htmlspecialchars($media['file_name']) . ' (' . $media['file_type'] . ')</div>
                <div class="item-content"><strong>Associated Post:</strong> ' . htmlspecialchars(substr($media['post_content'], 0, 100)) . (strlen($media['post_content']) > 100 ? '...' : '') . '</div>
            </div>';
        }
        $html .= '</div>';
    }
    
    $html .= '
    </body>
    </html>';
    
    return $html;
}

function generateHTML($html_content, $filename) {
    // Add print-friendly CSS and JavaScript for PDF conversion
    $html_with_print = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>User Data Export - Ground Zero</title>
        <style>
            @media print {
                body { margin: 0; padding: 20px; }
                .no-print { display: none !important; }
                .page-break { page-break-before: always; }
            }
            @media screen {
                body { margin: 20px; }
                .print-instructions { 
                    background: #f8f9fa; 
                    padding: 15px; 
                    border-radius: 5px; 
                    margin-bottom: 20px; 
                    border-left: 4px solid #27692A;
                }
            }
        </style>
        ' . $html_content . '
        <div class="print-instructions no-print">
            <h4><i class="bi bi-printer"></i> How to Save as PDF:</h4>
            <ol>
                <li>Press <strong>Ctrl + P</strong> (or Cmd + P on Mac)</li>
                <li>Select "Save as PDF" as the destination</li>
                <li>Click "Save" to download your data as PDF</li>
            </ol>
            <p><em>This HTML file contains all your Ground Zero data in a print-friendly format.</em></p>
        </div>
    </body>
    </html>';
    
    echo $html_with_print;
}
?> 