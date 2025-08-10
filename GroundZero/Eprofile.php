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

// Add bio column to users table if it doesn't exist
$check_bio_column = "SHOW COLUMNS FROM users LIKE 'bio'";
$bio_result = $conn->query($check_bio_column);
if ($bio_result->num_rows == 0) {
    $add_bio_column = "ALTER TABLE users ADD COLUMN bio TEXT";
    $conn->query($add_bio_column);
}

// Get user details
$user_id = $_SESSION["user_id"];
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

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

// Handle form submissions
$success_message = "";
$error_message = "";

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['firstName']);
    $last_name = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $barangay = trim($_POST['barangay']);
    $bio = trim($_POST['bio']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "First name, last name, and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Check if email already exists for another user
        $email_check = "SELECT id FROM users WHERE email = ? AND id != ?";
        $email_stmt = $conn->prepare($email_check);
        $email_stmt->bind_param("si", $email, $user_id);
        $email_stmt->execute();
        $email_result = $email_stmt->get_result();
        
        if ($email_result->num_rows > 0) {
            $error_message = "This email address is already registered by another user.";
        } else {
            // Handle avatar upload if provided
            $avatar_path = $user['avatar']; // Keep existing avatar by default
            
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error_message = "Only JPEG, PNG, and GIF images are allowed.";
                } elseif ($file['size'] > $max_size) {
                    $error_message = "File size must be less than 5MB.";
                } else {
                    $upload_dir = 'uploads/avatars/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $avatar_path = $filepath;
                    } else {
                        $error_message = "Error uploading profile picture. Please try again.";
                    }
                }
            }
            
            if (empty($error_message)) {
                // Update user profile with avatar and bio
                $update_query = "UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    barangay = ?,
                    bio = ?,
                    avatar = ?
                    WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssssssi", $first_name, $last_name, $email, $phone, $barangay, $bio, $avatar_path, $user_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Profile updated successfully! Avatar path: " . $avatar_path;
                    // Update session data
                    $_SESSION["first_name"] = $first_name;
                    $_SESSION["last_name"] = $last_name;
                    $_SESSION["email"] = $email;
                    $_SESSION["avatar"] = $avatar_path;
                    // Refresh user data
                    $user['first_name'] = $first_name;
                    $user['last_name'] = $last_name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    $user['barangay'] = $barangay;
                    $user['bio'] = $bio;
                    $user['avatar'] = $avatar_path;
                    
                    // Force session write
                    session_write_close();
                    session_start();
                } else {
                    $error_message = "Error updating profile. Please try again.";
                }
                $update_stmt->close();
            }
        }
        $email_stmt->close();
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['currentPassword'];
    $new_password = $_POST['newPassword'];
    $confirm_password = $_POST['confirmPassword'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Verify current password
        $verify_query = "SELECT password FROM users WHERE id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param("i", $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $user_data = $verify_result->fetch_assoc();
        
        if (password_verify($current_password, $user_data['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $password_update = "UPDATE users SET password = ? WHERE id = ?";
            $password_stmt = $conn->prepare($password_update);
            $password_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($password_stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Error changing password. Please try again.";
            }
            $password_stmt->close();
        } else {
            $error_message = "Current password is incorrect.";
        }
        $verify_stmt->close();
    }
}



// Handle account deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_account'])) {
    $confirmation = $_POST['deleteConfirmation'];
    
    if ($confirmation === 'DELETE') {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete user's posts and associated media
            $posts_query = "SELECT id FROM posts WHERE user_id = ?";
            $posts_stmt = $conn->prepare($posts_query);
            $posts_stmt->bind_param("i", $user_id);
            $posts_stmt->execute();
            $posts_result = $posts_stmt->get_result();
            
            while ($post = $posts_result->fetch_assoc()) {
                // Delete media files
                $media_query = "SELECT file_path FROM media_files WHERE post_id = ?";
                $media_stmt = $conn->prepare($media_query);
                $media_stmt->bind_param("i", $post['id']);
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
                $delete_media_stmt->bind_param("i", $post['id']);
                $delete_media_stmt->execute();
            }
            
            // Delete user's data
            $conn->query("DELETE FROM likes WHERE user_id = $user_id");
            $conn->query("DELETE FROM comments WHERE user_id = $user_id");
            $conn->query("DELETE FROM posts WHERE user_id = $user_id");
            $conn->query("DELETE FROM users WHERE id = $user_id");
            
            $conn->commit();
            
            // Destroy session and redirect
            session_destroy();
            header("Location: login.php?deleted=1");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error deleting account. Please try again.";
        }
    } else {
        $error_message = "Please type 'DELETE' to confirm account deletion.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Ground Zero</title>
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

        .page-header {
            background: linear-gradient(135deg, #27692A 0%, #1d4f1f 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .edit-profile-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
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

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #27692A;
            box-shadow: 0 0 0 0.2rem rgba(39, 105, 42, 0.25);
        }

        .btn-green {
            background-color: #27692A;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-green:hover {
            background-color: #1f531f;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-outline-green {
            background-color: transparent;
            color: #27692A;
            border: 2px solid #27692A;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-green:hover {
            background-color: #27692A;
            color: white;
            transform: translateY(-1px);
        }

        .profile-avatar-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid #27692A;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #27692A;
            margin: 0 auto 20px;
            position: relative;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #27692A;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .avatar-upload:hover {
            background: #1f531f;
            transform: scale(1.1);
        }

        .password-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .form-check-input:checked {
            background-color: #27692A;
            border-color: #27692A;
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

        @media (max-width: 768px) {
            .page-header {
                padding: 30px 0;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .edit-profile-section {
                padding: 25px;
                margin: 20px 15px;
            }
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
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item active" href="Eprofile.php"><i class="bi bi-pencil-square me-2"></i>Edit Profile</a></li>
                    <li><a class="dropdown-item" href="logout.html"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="page-title">Edit Profile</h1>
                <p class="page-subtitle">Update your personal information and account settings</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="profile.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-arrow-left me-2"></i>Back to Profile
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">


    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Edit Profile Form -->
    <div class="edit-profile-section">
        <h3 class="section-title">
            <i class="bi bi-person-circle"></i>Personal Information
        </h3>

        <form method="POST" enctype="multipart/form-data">
            <!-- Profile Avatar -->
            <div class="profile-avatar-section">
                <div class="profile-avatar">
                    <?php if (!empty($_SESSION['avatar']) && file_exists($_SESSION['avatar'])): ?>
                        <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Profile Picture">
                    <?php else: ?>
                        <i class="bi bi-person-circle"></i>
                    <?php endif; ?>
                    <div class="avatar-upload" onclick="document.getElementById('avatarInput').click()">
                        <i class="bi bi-camera"></i>
                    </div>
                </div>
                <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display: none;">
                <p class="text-muted mb-0">Click the camera icon to change your profile picture</p>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstName" name="firstName" value="<?= htmlspecialchars($_SESSION['first_name']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastName" name="lastName" value="<?= htmlspecialchars($_SESSION['last_name']) ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_SESSION['email']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="barangay" class="form-label">Barangay</label>
                                    <select class="form-control" id="barangay" name="barangay" required>
                  <option value="">Select Barangay</option>
                  <option value="Bagong Pook" <?= ($user['barangay'] == 'Bagong Pook') ? 'selected' : '' ?>>Bagong Pook</option>
                  <option value="Bilucao" <?= ($user['barangay'] == 'Bilucao') ? 'selected' : '' ?>>Bilucao</option>
                  <option value="Bulihan" <?= ($user['barangay'] == 'Bulihan') ? 'selected' : '' ?>>Bulihan</option>
                  <option value="Luta del Norte" <?= ($user['barangay'] == 'Luta del Norte') ? 'selected' : '' ?>>Luta del Norte</option>
                  <option value="Luta del Sur" <?= ($user['barangay'] == 'Luta del Sur') ? 'selected' : '' ?>>Luta del Sur</option>
                  <option value="Poblacion" <?= ($user['barangay'] == 'Poblacion') ? 'selected' : '' ?>>Poblacion</option>
                  <option value="San Andres" <?= ($user['barangay'] == 'San Andres') ? 'selected' : '' ?>>San Andres</option>
                  <option value="San Fernando" <?= ($user['barangay'] == 'San Fernando') ? 'selected' : '' ?>>San Fernando</option>
                  <option value="San Gregorio" <?= ($user['barangay'] == 'San Gregorio') ? 'selected' : '' ?>>San Gregorio</option>
                  <option value="San Isidro East" <?= ($user['barangay'] == 'San Isidro East') ? 'selected' : '' ?>>San Isidro East</option>
                  <option value="San Isidro West" <?= ($user['barangay'] == 'San Isidro West') ? 'selected' : '' ?>>San Isidro West</option>
                  <option value="San Juan" <?= ($user['barangay'] == 'San Juan') ? 'selected' : '' ?>>San Juan</option>
                  <option value="San Pedro I" <?= ($user['barangay'] == 'San Pedro I') ? 'selected' : '' ?>>San Pedro I</option>
                  <option value="San Pedro II" <?= ($user['barangay'] == 'San Pedro II') ? 'selected' : '' ?>>San Pedro II</option>
                  <option value="San Pioquinto" <?= ($user['barangay'] == 'San Pioquinto') ? 'selected' : '' ?>>San Pioquinto</option>
                </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="bio" class="form-label">Bio</label>
                    <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" name="update_profile" class="btn btn-green">
                    <i class="bi bi-check-circle me-2"></i>Save Changes
                </button>
                <a href="profile.php" class="btn btn-outline-green">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Change Password Section -->
    <div class="edit-profile-section">
        <h3 class="section-title">
            <i class="bi bi-shield-lock"></i>Change Password
        </h3>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="currentPassword" class="form-label">Current Password</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" onclick="togglePassword('currentPassword')" style="z-index: 10; text-decoration: none;">
                            <i class="bi bi-eye" id="currentPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="newPassword" class="form-label">New Password</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" onclick="togglePassword('newPassword')" style="z-index: 10; text-decoration: none;">
                            <i class="bi bi-eye" id="newPasswordIcon"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="confirmPassword" class="form-label">Confirm New Password</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" onclick="togglePassword('confirmPassword')" style="z-index: 10; text-decoration: none;">
                            <i class="bi bi-eye" id="confirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password Requirements</label>
                    <div class="password-requirements">
                        <small class="text-muted">
                            <i class="bi bi-check-circle text-success"></i> At least 8 characters<br>
                            <i class="bi bi-check-circle text-success"></i> Include uppercase letter<br>
                            <i class="bi bi-check-circle text-success"></i> Include lowercase letter<br>
                            <i class="bi bi-check-circle text-success"></i> Include number<br>
                            <i class="bi bi-check-circle text-success"></i> Include special character
                        </small>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" name="change_password" class="btn btn-green">
                    <i class="bi bi-key me-2"></i>Change Password
                </button>
                <button type="button" class="btn btn-outline-green" onclick="clearPasswordForm()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Clear
                </button>
            </div>
        </form>
    </div>

    <!-- Account Actions Section -->
    <div class="edit-profile-section">
        <h3 class="section-title">
            <i class="bi bi-gear"></i>Account Actions
        </h3>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                        </h6>
                        <p class="card-text text-muted">Permanently delete your account and all associated data.</p>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="showDeleteAccountModal()">
                            <i class="bi bi-trash me-2"></i>Delete Account
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-warning">
                            <i class="bi bi-download me-2"></i>Export Data
                        </h6>
                        <p class="card-text text-muted">Download a copy of your personal data.</p>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="exportUserData()">
                            <i class="bi bi-download me-2"></i>Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Delete Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                    <p>Deleting your account will:</p>
                    <ul>
                        <li>Permanently remove all your posts and comments</li>
                        <li>Delete your profile information</li>
                        <li>Remove all your activity history</li>
                        <li>Cancel any pending requests</li>
                    </ul>
                    <div class="mb-3">
                        <label for="deleteConfirmation" class="form-label">Type "DELETE" to confirm</label>
                        <input type="text" class="form-control" id="deleteConfirmation" name="deleteConfirmation" placeholder="DELETE">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-danger" disabled id="confirmDeleteBtn">
                        <i class="bi bi-trash me-2"></i>Delete Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Clear password form
function clearPasswordForm() {
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + 'Icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Handle avatar upload preview
document.getElementById('avatarInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const avatar = document.querySelector('.profile-avatar');
            avatar.innerHTML = `<img src="${e.target.result}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"><div class="avatar-upload" onclick="document.getElementById('avatarInput').click()"><i class="bi bi-camera"></i></div>`;
        };
        reader.readAsDataURL(file);
    }
});

// Show delete account modal
function showDeleteAccountModal() {
    const modal = new bootstrap.Modal(document.getElementById('deleteAccountModal'));
    modal.show();
}

// Handle delete confirmation
document.getElementById('deleteConfirmation').addEventListener('input', function() {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = this.value !== 'DELETE';
});

// Password strength validation
document.getElementById('newPassword').addEventListener('input', function() {
    const password = this.value;
    const requirements = document.querySelector('.password-requirements');
    
    const checks = [
        password.length >= 8,
        /[A-Z]/.test(password),
        /[a-z]/.test(password),
        /\d/.test(password),
        /[!@#$%^&*(),.?":{}|<>]/.test(password)
    ];
    
    const icons = requirements.querySelectorAll('i');
    icons.forEach((icon, index) => {
        if (checks[index]) {
            icon.className = 'bi bi-check-circle text-success';
        } else {
            icon.className = 'bi bi-circle text-muted';
        }
    });
});

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.remove();
        }, 5000);
    });
});

// Export user data
function exportUserData() {
    // Show loading message
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating...';
    button.disabled = true;
    
    // Create a form to submit the export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_data.php';
    
    // Add a hidden input to identify the export request
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'export_data';
    input.value = '1';
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Reset button after a short delay
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    }, 3000);
}

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