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

$admin_id = $_SESSION["admin_id"];
$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile"])) {
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $bio = trim($_POST["bio"]);
    
    // Validate input
    if (empty($first_name) || empty($last_name)) {
        $error_message = "First name and last name are required.";
    } else {
        $avatar_path = $admin['avatar'] ?? ''; // Keep existing avatar by default
        
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['avatar']['type'];
            $file_size = $_FILES['avatar']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_type, $allowed_types)) {
                $error_message = "Only JPG, PNG, and GIF files are allowed.";
            } elseif ($file_size > $max_size) {
                $error_message = "File size must be less than 5MB.";
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = "../uploads/avatars/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = "admin_avatar_" . $admin_id . "_" . time() . "." . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    // Delete old avatar if it exists
                    if (!empty($admin['avatar']) && file_exists($admin['avatar'])) {
                        unlink($admin['avatar']);
                    }
                    $avatar_path = $upload_path;
                } else {
                    $error_message = "Error uploading file. Please try again.";
                }
            }
        }
        
        if (empty($error_message)) {
            // Update admin profile
            $update_query = "UPDATE admins SET first_name = ?, last_name = ?, bio = ?, avatar = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssi", $first_name, $last_name, $bio, $avatar_path, $admin_id);
            
            if ($stmt->execute()) {
                // Update session data
                $_SESSION["admin_first_name"] = $first_name;
                $_SESSION["admin_last_name"] = $last_name;
                $_SESSION["first_name"] = $first_name;
                $_SESSION["last_name"] = $last_name;
                $_SESSION["avatar"] = $avatar_path;
                
                $success_message = "Profile updated successfully!";
            } else {
                $error_message = "Error updating profile. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Get current admin data
$admin_query = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Admin Profile - Ground Zero</title>
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

    .edit-card {
      background-color: white;
      border-radius: 15px;
      padding: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }

    .form-label {
      font-weight: bold;
      color: #27692A;
      margin-bottom: 8px;
    }

    .form-control {
      border: 2px solid #e9ecef;
      border-radius: 8px;
      padding: 12px 15px;
      font-size: 16px;
      transition: border-color 0.2s;
    }

    .form-control:focus {
      border-color: #27692A;
      box-shadow: 0 0 0 0.2rem rgba(39, 105, 42, 0.25);
    }

    .form-control:disabled {
      background-color: #f8f9fa;
      color: #6c757d;
    }

    .btn-green {
      background-color: #27692A;
      color: white;
      border: none;
      border-radius: 20px;
      padding: 12px 30px;
      font-weight: 500;
      font-size: 16px;
    }

    .btn-green:hover {
      background-color: #1f531f;
      color: white;
    }

    .btn-outline-green {
      background-color: transparent;
      color: #27692A;
      border: 2px solid #27692A;
      border-radius: 20px;
      padding: 12px 30px;
      font-weight: 500;
      font-size: 16px;
    }

    .btn-outline-green:hover {
      background-color: #27692A;
      color: white;
    }

    .alert {
      border-radius: 10px;
      border: none;
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
    }

    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
    }

    .profile-preview {
      background-color: #f8f9fa;
      border-radius: 10px;
      padding: 20px;
      margin-top: 20px;
      border: 2px solid #e9ecef;
    }

    .preview-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background-color: #27692A;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 32px;
      margin: 0 auto 15px;
      overflow: hidden;
    }

    .preview-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .preview-name {
      font-size: 18px;
      font-weight: bold;
      color: #27692A;
      text-align: center;
      margin-bottom: 5px;
    }

    .preview-role {
      color: #666;
      text-align: center;
      font-size: 14px;
    }

    .avatar-upload {
      border: 2px dashed #27692A;
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      background-color: #f8f9fa;
      transition: all 0.3s;
    }

    .avatar-upload:hover {
      background-color: #e8f5e8;
      border-color: #1f531f;
    }

    .avatar-upload input[type="file"] {
      display: none;
    }

    .avatar-upload label {
      cursor: pointer;
      color: #27692A;
      font-weight: 500;
    }

    .avatar-upload label:hover {
      color: #1f531f;
    }

    .current-avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto 15px;
      display: block;
      border: 3px solid #27692A;
    }

    .avatar-placeholder {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background-color: #27692A;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 40px;
      margin: 0 auto 15px;
      border: 3px solid #27692A;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
  <div class="container">
    <a class="navbar-brand" href="dashboard.html">
      <img src="../images/logo.png" alt="Logo" height="40" class="me-2">
      GROUND ZERO
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="dashboard.html">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="feed.php">News Feed</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="admin_profile.php">Profile</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.html">Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="container mt-4">
  <div class="row">
    <div class="col-lg-8 mx-auto">
      
      <!-- Edit Profile Card -->
      <div class="edit-card">
        <h3 class="text-center mb-4" style="color: #27692A;">
          <i class="bi bi-pencil-square me-2"></i>Edit Profile
        </h3>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <!-- Avatar Upload Section -->
          <div class="mb-4">
            <label class="form-label">Profile Picture</label>
            <div class="avatar-upload">
              <?php if (!empty($admin['avatar']) && file_exists($admin['avatar'])): ?>
                <img src="<?= htmlspecialchars($admin['avatar']) ?>" alt="Current Avatar" class="current-avatar" id="currentAvatar">
              <?php else: ?>
                <div class="avatar-placeholder" id="currentAvatar">
                  <i class="bi bi-person-circle"></i>
                </div>
              <?php endif; ?>
              
              <input type="file" id="avatar" name="avatar" accept="image/*" onchange="previewAvatar(this)">
              <label for="avatar" class="btn btn-outline-green">
                <i class="bi bi-camera me-2"></i>Choose New Photo
              </label>
              <div class="form-text mt-2">
                <i class="bi bi-info-circle me-1"></i>Upload a JPG, PNG, or GIF file (max 5MB)
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="first_name" class="form-label">First Name *</label>
                <input type="text" class="form-control" id="first_name" name="first_name" 
                       value="<?= htmlspecialchars($admin['first_name']) ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="last_name" class="form-label">Last Name *</label>
                <input type="text" class="form-control" id="last_name" name="last_name" 
                       value="<?= htmlspecialchars($admin['last_name']) ?>" required>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($admin['email']) ?>" disabled>
            <div class="form-text text-muted">
              <i class="bi bi-info-circle me-1"></i>Email address cannot be changed for security reasons.
            </div>
          </div>

          <div class="mb-3">
            <label for="bio" class="form-label">Bio</label>
            <textarea class="form-control" id="bio" name="bio" rows="4" 
                      placeholder="Tell us about yourself..."><?= htmlspecialchars($admin['bio'] ?? '') ?></textarea>
            <div class="form-text">
              <i class="bi bi-info-circle me-1"></i>Optional: Add a short bio about yourself.
            </div>
          </div>

          <!-- Profile Preview -->
          <div class="profile-preview">
            <h6 class="text-center mb-3" style="color: #27692A;">
              <i class="bi bi-eye me-2"></i>Profile Preview
            </h6>
            <div class="preview-avatar" id="previewAvatar">
              <?php if (!empty($admin['avatar']) && file_exists($admin['avatar'])): ?>
                <img src="<?= htmlspecialchars($admin['avatar']) ?>" alt="Preview Avatar">
              <?php else: ?>
                <i class="bi bi-person-circle"></i>
              <?php endif; ?>
            </div>
            <div class="preview-name" id="previewName">
              <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
            </div>
            <div class="preview-role">Administrator</div>
            <div class="text-center mt-2">
              <small class="text-muted" id="previewBio">
                <?= !empty($admin['bio']) ? htmlspecialchars($admin['bio']) : 'No bio added yet.' ?>
              </small>
            </div>
          </div>

          <div class="text-center mt-4">
            <button type="submit" name="update_profile" class="btn btn-green me-2">
              <i class="bi bi-check-circle me-2"></i>Save Changes
            </button>
            <a href="admin_profile.php" class="btn btn-outline-green">
              <i class="bi bi-arrow-left me-2"></i>Cancel
            </a>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live preview functionality
document.getElementById('first_name').addEventListener('input', updatePreview);
document.getElementById('last_name').addEventListener('input', updatePreview);
document.getElementById('bio').addEventListener('input', updatePreview);

function updatePreview() {
    const firstName = document.getElementById('first_name').value || 'First';
    const lastName = document.getElementById('last_name').value || 'Last';
    const bio = document.getElementById('bio').value;
    
    document.getElementById('previewName').textContent = firstName + ' ' + lastName;
    document.getElementById('previewBio').textContent = bio || 'No bio added yet.';
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Update current avatar preview
            const currentAvatar = document.getElementById('currentAvatar');
            if (currentAvatar.tagName === 'IMG') {
                currentAvatar.src = e.target.result;
            } else {
                currentAvatar.innerHTML = `<img src="${e.target.result}" alt="Avatar Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
            }
            
            // Update preview avatar
            const previewAvatar = document.getElementById('previewAvatar');
            previewAvatar.innerHTML = `<img src="${e.target.result}" alt="Preview Avatar">`;
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html> 