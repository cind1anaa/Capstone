<?php
session_start();

// Check if user is admin
if (!isset($_SESSION["admin_id"]) || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Update session with latest admin data from database
$admin_id = $_SESSION["admin_id"];
$admin_query = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();

// Update session data with latest database values
if ($admin) {
    $_SESSION["admin_first_name"] = $admin['first_name'];
    $_SESSION["admin_last_name"] = $admin['last_name'];
    $_SESSION["first_name"] = $admin['first_name'];
    $_SESSION["last_name"] = $admin['last_name'];
    $_SESSION["email"] = $admin['email'];
    $_SESSION["avatar"] = $admin['avatar'];
}

// Handle admin post creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_post'])) {
    $content = trim($_POST['content']);
    
    if (!empty($content)) {
        // Get admin ID from session
        $admin_id = $_SESSION["admin_id"];
        
        // Insert the post with author_type = 'admin'
        $insert_post = "INSERT INTO posts (user_id, author_type, content, created_at) VALUES (?, 'admin', ?, NOW())";
        $post_stmt = $conn->prepare($insert_post);
        $post_stmt->bind_param("is", $admin_id, $content);
        
        if ($post_stmt->execute()) {
            $post_id = $conn->insert_id;
            
            // Handle media uploads if any
            if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0])) {
                $upload_dir = '../uploads/posts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['media']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['media']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = $_FILES['media'];
                        $file_name = $file['name'][$key];
                        $file_type = $file['type'][$key];
                        
                        // Determine if it's image or video
                        $media_type = 'image';
                        if (strpos($file_type, 'video/') === 0) {
                            $media_type = 'video';
                        }
                        
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $new_filename = 'admin_post_' . $post_id . '_' . time() . '_' . $key . '.' . $file_extension;
                        $filepath = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            // Store relative path in database (without ../ prefix)
                            $relative_path = 'uploads/posts/' . $new_filename;
                            
                            // Insert media record
                            $insert_media = "INSERT INTO media_files (post_id, file_path, file_type, file_name, created_at) VALUES (?, ?, ?, ?, NOW())";
                            $media_stmt = $conn->prepare($insert_media);
                            $media_stmt->bind_param("isss", $post_id, $relative_path, $media_type, $new_filename);
                            $media_stmt->execute();
                        }
                    }
                }
            }
            
            $success_message = "Post created successfully!";
        } else {
            $error_message = "Error creating post. Please try again.";
        }
        $post_stmt->close();
    } else {
        $error_message = "Post content cannot be empty.";
    }
}

// Handle post deletion by admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_post'])) {
    $post_id = $_POST['post_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete media files first
        $media_query = "SELECT file_path FROM media_files WHERE post_id = ?";
        $media_stmt = $conn->prepare($media_query);
        $media_stmt->bind_param("i", $post_id);
        $media_stmt->execute();
        $media_result = $media_stmt->get_result();
        
        while ($media = $media_result->fetch_assoc()) {
            if (file_exists($media['file_path'])) {
                unlink($media['file_path']);
            }
        }
        
        // Delete media records
        $delete_media = "DELETE FROM media_files WHERE post_id = ?";
        $delete_media_stmt = $conn->prepare($delete_media);
        $delete_media_stmt->bind_param("i", $post_id);
        $delete_media_stmt->execute();
        
        // Delete likes, comments, and notifications
        $conn->query("DELETE FROM likes WHERE post_id = $post_id");
        $conn->query("DELETE FROM comments WHERE post_id = $post_id");
        $conn->query("DELETE FROM notifications WHERE post_id = $post_id");
        
        // Delete the post
        $delete_post = "DELETE FROM posts WHERE id = ?";
        $delete_post_stmt = $conn->prepare($delete_post);
        $delete_post_stmt->bind_param("i", $post_id);
        $delete_post_stmt->execute();
        
        $conn->commit();
        $success_message = "Post deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error deleting post. Please try again.";
    }
}

// Handle post pinning/unpinning by admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_pin'])) {
    $post_id = $_POST['post_id'];
    $is_pinned = $_POST['is_pinned'];
    
    $update_pin = "UPDATE posts SET is_pinned = ? WHERE id = ?";
    $pin_stmt = $conn->prepare($update_pin);
    $pin_stmt->bind_param("ii", $is_pinned, $post_id);
    
    if ($pin_stmt->execute()) {
        $action = $is_pinned ? "pinned" : "unpinned";
        $success_message = "Post $action successfully!";
    } else {
        $error_message = "Error updating post. Please try again.";
    }
    $pin_stmt->close();
}

// Get admin user ID from session
$admin_id = $_SESSION["admin_id"];

// Fetch all posts with user details and counts
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
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                FROM posts p 
                ORDER BY p.is_pinned DESC, p.created_at DESC";
$posts_result = $conn->query($posts_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin News Feed - Ground Zero</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .custom-navbar {
      background-color: #27692A;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .navbar-brand {
      font-weight: bold;
      font-size: 18px;
    }

    .nav-link {
      font-weight: 500;
      font-size: 14px;
    }

    .nav-link.active {
      color: #fff !important;
      background-color: rgba(255,255,255,0.1);
      border-radius: 5px;
    }

    .post-box, .featured-box, .user-profile, .feed-post {
      background-color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .featured-box {
      background: linear-gradient(135deg, #27692A, #1f531f);
      color: white;
      position: relative;
      overflow: hidden;
    }

    .featured-box::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
              background: #f8f9fa;
      opacity: 0.1;
    }

    .featured-box .tag {
      background-color: rgba(255,255,255,0.2);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
      display: inline-block;
      margin-bottom: 10px;
    }

    .user-profile {
      background-color: #d8e9d3;
      text-align: center;
      border: 2px solid #27692A;
    }

    .user-profile .icon {
      font-size: 60px;
      color: #27692A;
    }

    .btn-green {
      background-color: #27692A;
      color: white;
      border: none;
      border-radius: 20px;
      padding: 8px 20px;
      font-weight: 500;
    }

    .btn-green:hover {
      background-color: #1f531f;
      color: white;
    }

    .post-header {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
    }

    .post-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      margin-right: 15px;
      background-color: #27692A;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
      border: 2px solid #27692A;
    }

    .post-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .post-info {
      flex: 1;
    }

    .post-author {
      font-weight: bold;
      color: #27692A;
      margin-bottom: 2px;
      font-size: 16px;
    }

    .post-meta {
      font-size: 13px;
      color: #666;
    }

    .post-content {
      margin-bottom: 15px;
      font-size: 15px;
      line-height: 1.6;
    }

    .post-media {
      margin-bottom: 15px;
    }

    .post-media img, .post-media video {
      max-width: 100%;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .post-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #eee;
    }

    .post-stats {
      font-size: 14px;
      color: #666;
    }

    .admin-actions {
      display: flex;
      gap: 10px;
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
      margin: 2px 0;
    }

    .comment-time {
      font-size: 12px;
      color: #999;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #666;
    }

    .empty-state i {
      font-size: 48px;
      color: #ccc;
      margin-bottom: 15px;
    }

    .comments-section {
      background-color: #f8f9fa;
      border-radius: 8px;
      padding: 15px;
      margin-top: 15px;
      border: 1px solid #e9ecef;
    }

    .comment-item {
      background-color: white;
      border: 1px solid #e9ecef !important;
    }

    .comment-avatar img {
      object-fit: cover;
    }

    .spin {
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .highlight {
      animation: highlightPost 2s ease-in-out;
    }

    @keyframes highlightPost {
      0%, 100% { background-color: transparent; }
      50% { background-color: #fff3cd; }
    }

    .modal-lg .modal-body {
      padding: 30px;
    }

    .green-textbox {
      border-color: #27692A;
    }

    .green-textbox:focus {
      border-color: #27692A;
      box-shadow: 0 0 0 0.2rem rgba(39, 105, 42, 0.25);
    }

    /* Sidebar Styles */
    .sidebar {
      position: fixed;
      left: -250px;
      top: 0;
      height: 100%;
      width: 250px;
      background-color: #cfe2c3;
      padding: 20px;
      transition: left 0.3s ease;
      z-index: 1050;
    }

    .sidebar.active {
      left: 0;
    }

    .sidebar .logo img {
      height: 50px;
      margin-bottom: 15px;
    }

    .sidebar h4 {
      color: #27692A;
      margin-top: 10px;
    }

    .sidebar ul {
      list-style: none;
      padding: 0;
      margin-top: 20px;
    }

    .sidebar ul li {
      margin: 15px 0;
      font-size: 16px;
    }

    .sidebar ul li i {
      margin-right: 10px;
    }

    .sidebar ul li a {
      color: #000;
      text-decoration: none;
      display: flex;
      align-items: center;
    }

    .sidebar ul li a.active {
      background-color: #27692A;
      color: white;
      border-radius: 8px;
      padding: 8px 10px;
    }

    .sidebar ul li a.active i {
      color: white;
    }

    .logout {
      position: absolute;
      bottom: 10px;
      width: 50%;
      text-align: center;
    }

    .logout a {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 5px 0;
      color: #000;
      text-decoration: none;
      font-weight: bold;
    }

    .logout a:hover {
      background-color: #b4d7a9;
      border-radius: 4px;
    }

    .logout i {
      margin-right: 4px;
    }

    .topbar {
      background-color: #27692A;
      color: #fff;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1040;
    }

    .toggle-btn {
      font-size: 24px;
      cursor: pointer;
      color: #fff;
    }

    .profile-icon {
      font-size: 24px;
    }

    /* Notification Dropdown Styles */
    .dropdown-menu.notifications {
      background-color: #ffffff;
      border: 1px solid #ddd;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      max-height: 400px;
      overflow-y: auto;
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

    .dropdown-menu .dropdown-header {
      font-weight: bold;
      font-size: 15px;
      color: #27692A;
      border-bottom: 1px solid #ccc;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
      background-color: rgba(0, 0, 0, 0.4);
      z-index: 1000;
      display: none;
    }

    .overlay.active {
      display: block;
    }

    .dashboard-container {
      padding: 30px 15px;
      transition: margin-left 0.3s ease;
    }

    .sidebar.active ~ .dashboard-container {
      margin-left: 250px;
    }

    .pinned-post {
      background-color: #f8fff8;
      border-left: 4px solid #27692A !important;
    }

    .pinned-post:hover {
      background-color: #f0fff0;
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="logo text-center">
    <img src="../images/logo.png" alt="Logo" />
    <h4>Ground Zero</h4>
  </div>
  <ul>
    <li><a href="dashboard.html"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
    <li><a href="feed.php" class="active"><i class="bi bi-envelope-fill"></i> News Feed</a></li>
    <li><a href="user.php"><i class="bi bi-people-fill"></i> User Management</a></li>
    <li><a href="monitoring.html"><i class="bi bi-recycle"></i> Waste Monitoring</a></li>
    <li><a href="createSched.html"><i class="bi bi-truck"></i> Collection Schedule</a></li>
    <li><a href="announcement.html"><i class="bi bi-megaphone"></i> Announcements</a></li>
    <li><a href="waste.html"><i class="bi bi-bar-chart-fill"></i> Waste Reports</a></li>
    <li><a href="collection.html"><i class="bi bi-trash3-fill"></i> Collection Request</a></li>
  </ul>
  <div class="logout">
    <a href="logout.html"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
</div>

<!-- Overlay -->
<div class="overlay" id="overlay"></div>

<!-- Topbar -->
<div class="topbar">
  <div class="d-flex align-items-center gap-3">
    <span class="toggle-btn" id="toggleBtn"><i class="bi bi-list"></i></span>
    <h5 class="m-0">Admin News Feed</h5>
  </div>
  <div class="d-flex align-items-center gap-3">
    <!-- Notification Dropdown -->
    <div class="dropdown">
      <a href="#" class="position-relative" id="notifDropdown" role="button" data-bs-toggle="dropdown" style="color: white; text-decoration: none;">
        <i class="bi bi-bell-fill" style="font-size: 20px;"></i>
        <?php
        // Count unread notifications for admin
        $unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_id = ? AND is_read = 0";
        $unread_stmt = $conn->prepare($unread_query);
        $unread_stmt->bind_param("i", $_SESSION["admin_id"]);
        $unread_stmt->execute();
        $unread_result = $unread_stmt->get_result();
        $unread_data = $unread_result->fetch_assoc();
        $unread_count = $unread_data['unread_count'];
        ?>
        <?php if ($unread_count > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 10px;">
          <?= $unread_count ?>
        </span>
        <?php endif; ?>
      </a>
      <ul class="dropdown-menu dropdown-menu-end notifications" style="min-width: 300px; max-height: 400px; overflow-y: auto;">
        <li class="dropdown-header">
          <i class="bi bi-bell-fill me-2"></i>Notifications
        </li>
        <li><hr class="dropdown-divider"></li>
        <?php
        // Fetch notifications for admin
        $notifications_query = "SELECT n.*, 
                               (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = n.sender_id) as sender_name
                               FROM notifications n 
                               WHERE n.recipient_id = ?
                               ORDER BY n.created_at DESC 
                               LIMIT 10";
        $notif_stmt = $conn->prepare($notifications_query);
        $notif_stmt->bind_param("i", $_SESSION["admin_id"]);
        $notif_stmt->execute();
        $notifications_result = $notif_stmt->get_result();
        
        if ($notifications_result->num_rows > 0):
          while ($notification = $notifications_result->fetch_assoc()):
        ?>
          <li class="notification-item <?= $notification['is_read'] == 0 ? 'unread' : '' ?>" 
              data-notification-id="<?= $notification['id'] ?>"
              data-post-id="<?= $notification['post_id'] ?>"
              title="Click to view the post">
            <div class="d-flex align-items-start">
              <i class="bi bi-<?= $notification['type'] == 'like' ? 'heart-fill' : 'chat-fill' ?> me-2" style="color: #27692A; margin-top: 2px;"></i>
              <div class="flex-grow-1">
                <small><strong><?= htmlspecialchars($notification['sender_name']) ?></strong> 
                <?= htmlspecialchars($notification['content']) ?>
                <br><span class="text-muted"><?= date('M j, g:i A', strtotime($notification['created_at'])) ?></span></small>
              </div>
              <i class="bi bi-arrow-right text-muted" style="font-size: 12px; opacity: 0.6; margin-top: 2px;"></i>
            </div>
          </li>
        <?php 
          endwhile;
        else:
        ?>
          <li><small class="text-muted px-3">No notifications yet</small></li>
        <?php endif; ?>
      </ul>
    </div>
    
    <!-- Profile Dropdown -->
    <div class="dropdown">
      <a href="#" class="d-flex align-items-center" role="button" data-bs-toggle="dropdown" style="color: white; text-decoration: none;">
        <?php if (!empty($_SESSION["avatar"]) && file_exists($_SESSION["avatar"])): ?>
          <img src="<?= htmlspecialchars($_SESSION["avatar"]) ?>" alt="Admin Avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
        <?php else: ?>
          <i class="bi bi-person-circle profile-icon" style="font-size: 24px;"></i>
        <?php endif; ?>
        <span class="ms-2"><?= $_SESSION["admin_first_name"] ?? $_SESSION["first_name"] ?? "Admin" ?></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header">
          <?php if (!empty($_SESSION["avatar"]) && file_exists($_SESSION["avatar"])): ?>
            <img src="<?= htmlspecialchars($_SESSION["avatar"]) ?>" alt="Admin Avatar" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; margin-right: 8px;">
          <?php else: ?>
            <i class="bi bi-person-circle me-2"></i>
          <?php endif; ?>
          <?= $_SESSION["admin_first_name"] ?? $_SESSION["first_name"] ?? "Admin" ?> <?= $_SESSION["admin_last_name"] ?? $_SESSION["last_name"] ?? "" ?>
        </h6></li>
        <li><a class="dropdown-item" href="admin_profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
        <li><a class="dropdown-item" href="admin_edit_profile.php"><i class="bi bi-pencil-square me-2"></i>Edit Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="logout.html"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="dashboard-container" id="dashboardContainer">
  <div class="container-fluid">
  <div class="row g-4">
    
                   <!-- Left: Featured Post -->
      <div class="col-lg-4">
        <div class="featured-box">
          <p class="tag">ADMIN ANNOUNCEMENT</p>
          <h6 class="fw-bold">ADMIN NEWS FEED<br>MONITORING USER POSTS</h6>
          <p class="mb-1"><i class="bi bi-calendar-event me-2"></i><strong><?= date('F j, Y') ?></strong></p>
          <p style="font-size: 14px;">Monitor and manage all user posts, comments, and community interactions from this admin panel.</p>
          <ul class="mb-2" style="font-size: 13px;">
            <li>View all user posts</li>
            <li>Monitor comments and likes</li>
            <li>Delete inappropriate content</li>
            <li>Create admin announcements</li>
            <li>Pin important posts</li>
          </ul>
          <p class="text-muted mb-0" style="font-size: 12px;">#AdminPanel #CommunityManagement</p>
        </div>
        
        <!-- Pinned Posts Section -->
        <?php
        // Fetch pinned posts
        $pinned_query = "SELECT p.*, 
                        CASE 
                            WHEN p.author_type = 'admin' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM admins WHERE id = p.user_id)
                            ELSE (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = p.user_id)
                        END as author_name,
                        CASE 
                            WHEN p.author_type = 'admin' THEN 'Admin'
                            ELSE (SELECT barangay FROM users WHERE id = p.user_id)
                        END as location
                        FROM posts p 
                        WHERE p.is_pinned = 1 
                        ORDER BY p.created_at DESC 
                        LIMIT 3";
        $pinned_result = $conn->query($pinned_query);
        
        if ($pinned_result->num_rows > 0):
        ?>
        <div class="post-box mt-4">
          <h6 class="text-success mb-3">
            <i class="bi bi-pin-angle-fill me-2"></i>Pinned Posts
          </h6>
          <?php while ($pinned_post = $pinned_result->fetch_assoc()): ?>
            <div class="pinned-post mb-3 p-3 border-start border-success border-4 rounded" style="cursor: pointer;" onclick="scrollToPost(<?= $pinned_post['id'] ?>)" data-post-id="<?= $pinned_post['id'] ?>" title="Click to view the full post">
              <div class="d-flex align-items-start">
                <div class="me-2">
                  <i class="bi bi-pin-angle-fill text-success"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start mb-1">
                    <strong class="text-success"><?= htmlspecialchars($pinned_post['author_name']) ?></strong>
                    <small class="text-muted"><?= date('M j', strtotime($pinned_post['created_at'])) ?></small>
                  </div>
                  <p class="mb-1" style="font-size: 14px;"><?= htmlspecialchars(substr($pinned_post['content'], 0, 100)) ?><?= strlen($pinned_post['content']) > 100 ? '...' : '' ?></p>
                  <small class="text-muted"><?= htmlspecialchars($pinned_post['location']) ?></small>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
        <?php endif; ?>
      </div>

     <!-- Center: Post Input + Feed -->
     <div class="col-lg-8">
      <!-- Success/Error Messages -->
      <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= htmlspecialchars($success_message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= htmlspecialchars($error_message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Admin Post Creation Form -->
      <div class="post-box">
        <h6 class="text-success mb-3">
          <i class="bi bi-plus-circle me-2"></i>Create Admin Post
        </h6>
        <form method="POST" enctype="multipart/form-data">
          <div class="mb-3">
            <textarea class="form-control green-textbox" name="content" rows="3" placeholder="Share an announcement or update with the community..." required></textarea>
          </div>
          <div class="mb-3">
            <label for="media" class="form-label">Add Media (Optional)</label>
            <input type="file" class="form-control" name="media[]" id="media" accept="image/*,video/*" multiple>
            <small class="text-muted">You can select multiple images or videos</small>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
              <i class="bi bi-shield-check me-1"></i>This will be posted as an admin announcement
            </small>
            <button type="submit" name="create_post" class="btn btn-green btn-sm">
              <i class="bi bi-send me-1"></i>Post as Admin
            </button>
          </div>
        </form>
      </div>

      <!-- Feed Posts -->
      <div class="feed-post">
        <h6 class="text-success mb-3">
          <i class="bi bi-envelope-fill me-2"></i>All User Posts
        </h6>
        
        <?php if ($posts_result->num_rows > 0): ?>
          <?php while ($post = $posts_result->fetch_assoc()): ?>
            <div class="post-box mb-4" data-post-id="<?= $post['id'] ?>">
              <div class="post-header">
                <div class="post-avatar">
                  <?php if (!empty($post['avatar'])): ?>
                    <?php 
                    $avatar_path = $post['avatar'];
                    // Handle different avatar path formats
                    if (strpos($avatar_path, 'uploads/') === 0) {
                        $avatar_path = '../' . $avatar_path;
                    } elseif (strpos($avatar_path, '../uploads/') !== 0 && strpos($avatar_path, 'uploads/') !== 0) {
                        $avatar_path = '../uploads/avatars/' . $avatar_path;
                    }
                    ?>
                    <img src="<?= htmlspecialchars($avatar_path) ?>" alt="User Avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <i class="bi bi-person-circle" style="display: none;"></i>
                  <?php else: ?>
                    <i class="bi bi-person-circle"></i>
                  <?php endif; ?>
                </div>
                <div class="post-info">
                  <div class="post-author">
                    <a href="#" onclick="viewUserProfile(<?= $post['user_id'] ?>, '<?= htmlspecialchars($post['author_name']) ?>'); return false;" class="text-decoration-none text-dark fw-bold">
                      <?= htmlspecialchars($post['author_name']) ?>
                    </a>
                    <?php if ($post['user_id'] == $admin_id): ?>
                      <span class="badge bg-success ms-2">ADMIN</span>
                    <?php endif; ?>
                  </div>
                  <div class="post-meta">
                    <?= htmlspecialchars($post['location']) ?> • 
                    <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                  </div>
                </div>
              </div>
              
              <div class="post-content">
                <?= nl2br(htmlspecialchars($post['content'])) ?>
              </div>

              <?php if (!empty($post['bio'])): ?>
                <div class="mt-2">
                  <small class="text-muted">
                    <i class="bi bi-quote me-1"></i><?= htmlspecialchars($post['bio']) ?>
                  </small>
                </div>
              <?php endif; ?>

              <?php
              // Fetch media files for this post
              $media_query = "SELECT * FROM media_files WHERE post_id = ? ORDER BY created_at ASC";
              $media_stmt = $conn->prepare($media_query);
              $media_stmt->bind_param("i", $post['id']);
              $media_stmt->execute();
              $media_result = $media_stmt->get_result();
              
              if ($media_result->num_rows > 0):
              ?>
                <div class="post-media">
                  <?php while ($media = $media_result->fetch_assoc()): ?>
                    <?php 
                    $media_path = $media['file_path'];
                    // Handle different media path formats
                    if (strpos($media_path, 'uploads/') === 0) {
                        $media_path = '../' . $media_path;
                    } elseif (strpos($media_path, '../uploads/') !== 0 && strpos($media_path, 'uploads/') !== 0) {
                        $media_path = '../uploads/posts/' . $media_path;
                    }
                    ?>
                    <?php if ($media['file_type'] == 'image'): ?>
                      <img src="<?= htmlspecialchars($media_path) ?>" 
                           alt="Post Image" class="img-fluid rounded mb-2" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                      <div class="text-muted text-center p-3 border rounded" style="display: none;">
                        <i class="bi bi-image"></i> Image not available
                      </div>
                    <?php elseif ($media['file_type'] == 'video'): ?>
                      <video controls class="img-fluid rounded mb-2" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <source src="<?= htmlspecialchars($media_path) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                      </video>
                      <div class="text-muted text-center p-3 border rounded" style="display: none;">
                        <i class="bi bi-camera-video"></i> Video not available
                      </div>
                    <?php endif; ?>
                  <?php endwhile; ?>
                </div>
              <?php endif; ?>

              <div class="post-actions">
                <div class="post-stats">
                  <i class="bi bi-heart-fill text-danger me-1"></i><?= $post['like_count'] ?> likes
                  <a href="#" onclick="toggleComments(<?= $post['id'] ?>); return false;" class="text-decoration-none text-primary ms-3">
                    <i class="bi bi-chat-fill me-1"></i><?= $post['comment_count'] ?> comments
                  </a>
                </div>
                <div class="admin-actions">
                  <button class="btn btn-outline-warning btn-sm me-2" 
                          onclick="togglePin(<?= $post['id'] ?>, <?= $post['is_pinned'] ? 0 : 1 ?>)">
                    <i class="bi bi-pin-angle<?= $post['is_pinned'] ? '-fill' : '' ?>"></i> 
                    <?= $post['is_pinned'] ? 'Unpin' : 'Pin' ?>
                  </button>
                  <button class="btn btn-outline-danger btn-sm" 
                          onclick="deletePost(<?= $post['id'] ?>, '<?= htmlspecialchars($post['author_name']) ?>')">
                    <i class="bi bi-trash"></i> Delete
                  </button>
                </div>
              </div>
              
              <!-- Comments Section (Hidden by default) -->
              <div id="comments-<?= $post['id'] ?>" class="comments-section" style="display: none;">
                <hr>
                <h6 class="text-success mb-3">
                  <i class="bi bi-chat-dots me-2"></i>Comments
                </h6>
                <div id="comments-content-<?= $post['id'] ?>">
                  <!-- Comments will be loaded here via AJAX -->
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="bi bi-envelope"></i>
            <h5>No Posts Yet</h5>
            <p>Users haven't created any posts yet.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>



  </div>
</div>

<!-- Delete Post Modal -->
<div class="modal fade" id="deletePostModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger">
          <i class="bi bi-exclamation-triangle me-2"></i>Delete Post
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="deletePostForm">
        <div class="modal-body">
          <p>Are you sure you want to delete this post by <strong id="postAuthor"></strong>?</p>
          <p class="text-danger"><small>This action cannot be undone.</small></p>
          <input type="hidden" name="post_id" id="postId">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" onclick="confirmDelete()" class="btn btn-danger">
            <i class="bi bi-trash me-2"></i>Delete Post
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Sidebar functionality
  const toggleBtn = document.getElementById('toggleBtn');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const dashboardContainer = document.querySelector('.dashboard-container');

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    dashboardContainer.classList.toggle('active');
  });

  overlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    dashboardContainer.classList.remove('active');
  });

  // Delete post function
  function deletePost(postId, authorName) {
    document.getElementById('postId').value = postId;
    document.getElementById('postAuthor').textContent = authorName;
    const modal = new bootstrap.Modal(document.getElementById('deletePostModal'));
    modal.show();
  }
  
  // Handle delete confirmation
  function confirmDelete() {
    const form = document.getElementById('deletePostForm');
    const submitBtn = form.querySelector('button[onclick="confirmDelete()"]');
    
    // Add the delete_post hidden input
    const deletePostInput = document.createElement('input');
    deletePostInput.type = 'hidden';
    deletePostInput.name = 'delete_post';
    deletePostInput.value = '1';
    form.appendChild(deletePostInput);
    
    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Deleting...';
    submitBtn.disabled = true;
    
    // Submit form and reload page after a short delay
    form.submit();
    setTimeout(() => {
      window.location.reload();
    }, 500);
  }

  // Toggle pin function
  function togglePin(postId, isPinned) {
    // Show loading state
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Processing...';
    button.disabled = true;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const postIdInput = document.createElement('input');
    postIdInput.type = 'hidden';
    postIdInput.name = 'post_id';
    postIdInput.value = postId;
    
    const isPinnedInput = document.createElement('input');
    isPinnedInput.type = 'hidden';
    isPinnedInput.name = 'is_pinned';
    isPinnedInput.value = isPinned;
    
    const togglePinInput = document.createElement('input');
    togglePinInput.type = 'hidden';
    togglePinInput.name = 'toggle_pin';
    togglePinInput.value = '1';
    
    form.appendChild(postIdInput);
    form.appendChild(isPinnedInput);
    form.appendChild(togglePinInput);
    document.body.appendChild(form);
    

    
    // Submit form and reload page after a short delay
    form.submit();
    setTimeout(() => {
      window.location.reload();
    }, 500);
  }

  // Toggle comments function
  function toggleComments(postId) {
    const commentsSection = document.getElementById(`comments-${postId}`);
    const commentsContent = document.getElementById(`comments-content-${postId}`);
    
    
    
    if (commentsSection.style.display === 'none' || commentsSection.style.display === '') {
      // Show comments and load them
      commentsSection.style.display = 'block';
      loadComments(postId, commentsContent);
    } else {
      // Hide comments
      commentsSection.style.display = 'none';
    }
    
    // Prevent default link behavior
    return false;
  }

  // Load comments via AJAX
  function loadComments(postId, container) {
    container.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading comments...</div>';
    
    fetch(`get_comments.php?post_id=${postId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (data.comments.length > 0) {
            let html = '';
            data.comments.forEach(comment => {
              html += `
                <div class="comment-item mb-3 p-3 border rounded">
                  <div class="d-flex align-items-start">
                    <div class="comment-avatar me-3">
                      ${comment.avatar ? `<img src="../${comment.avatar}" alt="Avatar" class="rounded-circle" width="32" height="32">` : 
                        '<i class="bi bi-person-circle text-muted" style="font-size: 32px;"></i>'}
                    </div>
                    <div class="flex-grow-1">
                      <div class="d-flex justify-content-between align-items-start">
                        <div>
                          <strong class="text-success">${comment.author_name}</strong>
                          <small class="text-muted ms-2">${comment.barangay}</small>
                        </div>
                        <small class="text-muted">${comment.created_at}</small>
                      </div>
                      <p class="mb-0 mt-1">${comment.content}</p>
                    </div>
                  </div>
                </div>
              `;
            });
            container.innerHTML = html;
          } else {
            container.innerHTML = '<p class="text-muted text-center">No comments yet.</p>';
          }
        } else {
          container.innerHTML = '<p class="text-danger text-center">Error loading comments.</p>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<p class="text-danger text-center">Error loading comments.</p>';
      });
  }

  // View user profile function
  function viewUserProfile(userId, userName) {
    // Create modal for user profile
    const modalHtml = `
      <div class="modal fade" id="userProfileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="bi bi-person-circle me-2"></i>User Profile
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userProfileContent">
              <div class="text-center">
                <i class="bi bi-arrow-clockwise spin"></i> Loading profile...
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('userProfileModal');
    if (existingModal) {
      existingModal.remove();
    }
    
    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('userProfileModal'));
    modal.show();
    
    // Load user profile data
    loadUserProfile(userId);
    
    // Prevent default link behavior
    return false;
  }

  // Load user profile via AJAX
  function loadUserProfile(userId) {
    const container = document.getElementById('userProfileContent');
    
    fetch(`get_user_profile.php?user_id=${userId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const user = data.user;
          container.innerHTML = `
            <div class="row">
              <div class="col-md-4 text-center">
                <div class="mb-3">
                  ${user.avatar ? `<img src="../${user.avatar}" alt="Profile Picture" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">` : 
                    '<i class="bi bi-person-circle text-muted" style="font-size: 150px;"></i>'}
                </div>
                <h5 class="text-success">${user.first_name} ${user.last_name}</h5>
                <p class="text-muted">${user.barangay}</p>
              </div>
              <div class="col-md-8">
                <div class="row">
                  <div class="col-6">
                    <h6 class="text-success">Email</h6>
                    <p>${user.email}</p>
                  </div>
                  <div class="col-6">
                    <h6 class="text-success">Barangay</h6>
                    <p>${user.barangay}</p>
                  </div>
                </div>
                ${user.bio ? `
                <div class="row">
                  <div class="col-12">
                    <h6 class="text-success">Bio</h6>
                    <p>${user.bio}</p>
                  </div>
                </div>
                ` : ''}
                <div class="row">
                  <div class="col-4">
                    <h6 class="text-success">Posts</h6>
                    <p class="h4 text-primary">${user.post_count}</p>
                  </div>
                  <div class="col-4">
                    <h6 class="text-success">Comments</h6>
                    <p class="h4 text-success">${user.comment_count}</p>
                  </div>
                  <div class="col-4">
                    <h6 class="text-success">Joined</h6>
                    <p class="text-muted">${user.created_at}</p>
                  </div>
                </div>
              </div>
            </div>
          `;
        } else {
          container.innerHTML = '<p class="text-danger text-center">Error loading user profile.</p>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<p class="text-danger text-center">Error loading user profile.</p>';
      });
  }

  // Refresh feed function
  function refreshFeed() {
    location.reload();
  }

  // Export feed data function
  function exportFeedData() {
    // Create a CSV of all posts and comments
    fetch('export_feed_data.php')
      .then(response => response.blob())
      .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'feed_data_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
      })
      .catch(error => {
        console.error('Error exporting data:', error);
        alert('Error exporting data. Please try again.');
      });
  }

  // Auto-dismiss alerts after 5 seconds
  document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
      setTimeout(() => {
        alert.remove();
      }, 5000);
    });

    // Notification functionality
    
    
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
      
      // Debug: Check if target post exists
      const targetPost = document.querySelector(`[data-post-id="${postId}"]`);
      
      
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
          
          // Navigate to the post if available
          if (postId) {
            // Close the notification dropdown
            const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('notifDropdown'));
            if (dropdown) {
              dropdown.hide();
            }
            
            // Scroll to the specific post using the same function as pinned posts
            scrollToPost(postId);
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
          newBadge.style.fontSize = '10px';
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
    } else {
      console.error('Target post not found:', postId);
    }
  }
</script>
</body>
</html> 