<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user details
$profile_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION["user_id"];
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// If user not found, redirect to own profile
if (!$user) {
    header("Location: profile.php");
    exit();
}

// Get user's posts
$posts_query = "SELECT p.*, 
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
                FROM posts p 
                WHERE p.user_id = ? 
                ORDER BY p.created_at DESC";
$posts_stmt = $conn->prepare($posts_query);
$posts_stmt->bind_param("i", $profile_user_id);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();

// Get user's stats
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM posts WHERE user_id = ?) as total_posts,
                (SELECT COUNT(*) FROM comments WHERE user_id = ?) as total_comments,
                (SELECT COUNT(*) FROM likes WHERE user_id = ?) as total_likes_given,
                (SELECT COUNT(*) FROM comments c INNER JOIN posts p ON c.post_id = p.id WHERE p.user_id = ?) as comments_received,
                (SELECT COUNT(*) FROM likes l INNER JOIN posts p ON l.post_id = p.id WHERE p.user_id = ?) as likes_received";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("iiiii", $profile_user_id, $profile_user_id, $profile_user_id, $profile_user_id, $profile_user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Fetch notifications for the current user
$notifications_query = "SELECT n.*, u.first_name, u.last_name 
                       FROM notifications n 
                       INNER JOIN users u ON n.sender_id = u.id 
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Ground Zero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .custom-navbar {
            background-color: #27692A;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        .profile-header {
            background: linear-gradient(135deg, #27692A 0%, #1d4f1f 100%);
            color: white;
            padding: 50px 0;
            margin-bottom: 0;
            position: relative;
        }

        .profile-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 20px;
            background: #f8f9fa;
            border-radius: 20px 20px 0 0;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #27692A;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #27692A;
            margin: 0 auto 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            text-align: center;
        }

        .profile-name {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .profile-details {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .profile-details i {
            margin-right: 8px;
            width: 20px;
        }

        .main-content {
            padding: 40px 0;
        }

        .stats-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .stats-title {
            text-align: center;
            margin-bottom: 30px;
            color: #27692A;
            font-weight: 600;
            font-size: 1.3rem;
        }

        .stat-item {
            text-align: center;
            padding: 20px 15px;
            border-radius: 15px;
            transition: transform 0.2s;
        }

        .stat-item:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #27692A;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 1rem;
            color: #666;
            font-weight: 500;
        }

        .posts-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            color: #27692A;
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .post-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .post-card:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .post-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .post-user-info {
            flex: 1;
        }

        .post-user-name {
            font-weight: 600;
            color: #27692A;
            margin-bottom: 5px;
        }

        .post-user-name a {
            transition: color 0.2s ease;
        }

        .post-user-name a:hover {
            color: #1f531f !important;
            text-decoration: underline !important;
        }

        .post-timestamp {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .post-actions {
            position: relative;
        }

        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
            color: #495057;
        }

        .post-content {
            margin-bottom: 20px;
            line-height: 1.6;
            color: #333;
        }

        .media-gallery {
            margin-bottom: 20px;
        }

        .media-grid {
            display: flex;
            gap: 6px;
            max-width: 450px;
        }

        .media-item {
            position: relative;
            flex: 1;
            height: 180px;
            overflow: hidden;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        .media-item.single {
            max-width: 350px;
        }

        .post-media {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .post-media:hover {
            transform: scale(1.05);
        }

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
            border-radius: 10px;
        }

        .overlay-content {
            text-align: center;
            color: white;
        }

        .overlay-text {
            font-size: 14px;
            font-weight: bold;
            background: rgba(0,0,0,0.8);
            padding: 8px 16px;
            border-radius: 25px;
        }

        .post-stats {
            display: flex;
            gap: 20px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .post-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 80px;
            color: #dee2e6;
            margin-bottom: 25px;
        }

        .empty-state h4 {
            color: #495057;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .btn-green {
            background-color: #27692A;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-green:hover {
            background-color: #1f531f;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-outline-light {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
        }

        .dropdown-menu {
            background-color: #27692A;
            border: none;
            padding: 0.5rem 0;
            min-width: 180px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .dropdown-menu .dropdown-header {
            color: #ffffff;
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dropdown-item {
            color: #ffffff;
            padding: 0.5rem 1rem;
            transition: background-color 0.2s;
        }

        .dropdown-item:hover {
            background-color: #1d4f1f;
        }

        .dropdown-item.text-danger:hover {
            background-color: #dc3545;
        }

        .dropdown-item.active {
            background-color: #27692A !important;
            color: white !important;
        }

        .dropdown-item.active:hover {
            background-color: #1f531f !important;
            color: white !important;
        }

        @media (max-width: 768px) {
            .profile-header {
                padding: 30px 0;
            }
            
            .profile-name {
                font-size: 1.8rem;
            }
            
            .profile-details {
                font-size: 1rem;
            }
            
            .stats-section,
            .posts-section {
                margin: 20px 15px;
                padding: 20px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .media-grid {
                max-width: 100%;
            }
            
            .media-item {
                height: 150px;
            }
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
            transition: background-color 0.2s ease;
            user-select: none;
            position: relative;
            z-index: 10;
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
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
    <a class="navbar-brand d-flex align-items-center" href="feed.php">
        <img src="images/logo.png" alt="Logo" height="40" class="me-2" />
        <span>GROUND ZERO</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-center gap-2">
            <li class="nav-item">
                <a class="nav-link" href="feed.php">COMMUNITY</a>
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
                                data-post-id="<?= $notification['post_id'] ?>">
                                <i class="bi bi-<?= $notification['type'] == 'like' ? 'heart-fill' : 'chat-fill' ?>"></i>
                                <div>
                                    <small><strong><?= htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']) ?></strong> 
                                    <?= htmlspecialchars($notification['content']) ?>
                                    <br><span class="text-muted"><?= date('M j, g:i A', strtotime($notification['created_at'])) ?></span></small>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li><small class="text-muted">No notifications yet</small></li>
                    <?php endif; ?>
                </ul>
            </li>
                         <li class="nav-item dropdown">
                 <a class="nav-link dropdown-toggle d-flex align-items-center active" href="#" role="button" data-bs-toggle="dropdown">
                     <i class="bi bi-person-circle me-1"></i>
                     <?= $_SESSION["first_name"] ?? "User" ?>
                 </a>
                 <ul class="dropdown-menu dropdown-menu-end">
                     <li><h6 class="dropdown-header">
                         <i class="bi bi-person-circle me-2"></i>
                         <?= $_SESSION["first_name"] ?> <?= $_SESSION["last_name"] ?? "" ?>
                     </h6></li>
                    <li><a class="dropdown-item active" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="Eprofile.php"><i class="bi bi-pencil-square me-2"></i>Edit Profile</a></li>
                    <li><a class="dropdown-item" href="logout.html"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<!-- Profile Header -->
<div class="profile-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Profile Picture">
                        <?php else: ?>
                            <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                    </div>
                    <h1 class="profile-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
                    <div class="profile-details">
                        <p class="mb-2">
                            <i class="bi bi-geo-alt-fill"></i>
                            <?= htmlspecialchars($user['barangay'] ?? 'Location not set') ?>
                        </p>
                        <?php if (!empty($user['bio'])): ?>
                            <p class="mb-2">
                                <i class="bi bi-quote"></i>
                                <?= htmlspecialchars($user['bio']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="mb-0">
                            <i class="bi bi-envelope-fill"></i>
                            <?= htmlspecialchars($user['email']) ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="feed.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-arrow-left me-2"></i>Back to Feed
                </a>
                <?php if ($profile_user_id == $_SESSION["user_id"]): ?>
                    <a href="Eprofile.php" class="btn btn-outline-light me-2">
                        <i class="bi bi-pencil me-2"></i>Edit Profile
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="container">
        <!-- Stats Section -->
        <div class="stats-section">
            <h3 class="stats-title">
                <i class="bi bi-graph-up me-2"></i>Activity Overview
            </h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['total_posts'] ?></div>
                        <div class="stat-label">Posts Created</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['comments_received'] ?></div>
                        <div class="stat-label">Comments Received</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['likes_received'] ?></div>
                        <div class="stat-label">Likes Received</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posts Section -->
        <div class="posts-section">
            <h3 class="section-title">
                <i class="bi bi-collection"></i>My Posts
            </h3>

            <?php if ($posts_result->num_rows > 0): ?>
                <?php while ($post = $posts_result->fetch_assoc()): ?>
                    <div class="post-card" data-post-id="<?= $post['id'] ?>">
                        <div class="post-header">
                            <div class="post-user-info">
                                <div class="post-user-name">
                                    <a href="profile.php?user_id=<?= $profile_user_id ?>" class="text-decoration-none text-success d-flex align-items-center" style="cursor: pointer;">
                                        <i class="bi bi-person-circle me-2"></i>
                                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                    </a>
                                </div>
                                <div class="post-timestamp">
                                    <?= date('M j, Y \a\t g:i A', strtotime($post['created_at'])) ?>
                                </div>
                            </div>
                            <?php if ($post['user_id'] == $_SESSION["user_id"]): ?>
                                <div class="post-actions">
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
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($post['content']): ?>
                            <div class="post-content"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
                        <?php endif; ?>

                        <!-- Display Media Files -->
                        <?php
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
                            <div class="media-gallery">
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

                        <!-- Post Stats -->
                        <div class="post-stats">
                            <div class="post-stat">
                                <i class="bi bi-heart"></i>
                                <span><?= $post['like_count'] ?> likes</span>
                            </div>
                            <div class="post-stat">
                                <i class="bi bi-chat"></i>
                                <span><?= $post['comment_count'] ?> comments</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-collection"></i>
                    <h4>No posts yet</h4>
                    <p>Start sharing with your community and make your first post!</p>
                    <a href="feed.php" class="btn btn-green">
                        <i class="bi bi-plus-circle me-2"></i>Create Your First Post
                    </a>
                </div>
            <?php endif; ?>
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
                        <textarea class="form-control" id="editPostContent" name="edit_content" rows="4" required></textarea>
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
                document.body.style.overflow = 'hidden';
            }
        })
        .catch(error => {
            console.error('Error fetching media:', error);
        });
}

function closeGallery() {
    document.getElementById('galleryModal').style.display = 'none';
    document.body.style.overflow = 'auto';
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
            img.alt = 'Gallery image';
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

function editPost(postId, content) {
    document.getElementById('editPostId').value = postId;
    document.getElementById('editPostContent').value = content;
    
    const modal = new bootstrap.Modal(document.getElementById('editPostModal'));
    modal.show();
}

function submitEditPost() {
    const form = document.getElementById('editPostForm');
    const formData = new FormData(form);
    formData.append('edit_post_id', document.getElementById('editPostId').value);
    formData.append('edit_content', document.getElementById('editPostContent').value);
    
    fetch('feed.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
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
        
        fetch('feed.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
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
</script>

<style>
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
</style>

<script>
// Notification functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mark notification as read when clicked and navigate to post
    const notificationItems = document.querySelectorAll('.notification-item');
    
    notificationItems.forEach(function(item) {
        item.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-notification-id');
            const postId = this.getAttribute('data-post-id');
            
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
                    this.classList.remove('unread');
                    
                    // Update notification count
                    updateNotificationCount();
                    
                    // Navigate to the post (redirect to feed.php with post ID)
                    if (postId) {
                        // Close the notification dropdown
                        const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('notifDropdown'));
                        if (dropdown) {
                            dropdown.hide();
                        }
                        
                        // Redirect to feed.php to see the post
                        window.location.href = 'feed.php?scroll_to_post=' + postId;
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        });
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
</script>

</body>
</html> 