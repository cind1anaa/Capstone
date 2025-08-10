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

// Get admin data
$admin_id = $_SESSION["admin_id"];
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
  <title>Admin Profile - Ground Zero</title>
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

    .profile-card {
      background-color: white;
      border-radius: 15px;
      padding: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }

    .profile-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .profile-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      margin: 0 auto 20px;
      background-color: #27692A;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 48px;
      border: 4px solid #d8e9d3;
    }

    .profile-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .profile-name {
      font-size: 24px;
      font-weight: bold;
      color: #27692A;
      margin-bottom: 5px;
    }

    .profile-role {
      color: #666;
      font-size: 16px;
    }

    .info-section {
      margin-bottom: 25px;
    }

    .info-label {
      font-weight: bold;
      color: #27692A;
      margin-bottom: 5px;
    }

    .info-value {
      color: #333;
      font-size: 16px;
      padding: 8px 0;
      border-bottom: 1px solid #eee;
    }

    .btn-green {
      background-color: #27692A;
      color: white;
      border: none;
      border-radius: 20px;
      padding: 10px 25px;
      font-weight: 500;
      text-decoration: none;
      display: inline-block;
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
      padding: 10px 25px;
      font-weight: 500;
      text-decoration: none;
      display: inline-block;
    }

    .btn-outline-green:hover {
      background-color: #27692A;
      color: white;
    }

    .stats-card {
      background-color: #d8e9d3;
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      border: 2px solid #27692A;
    }

    .stats-number {
      font-size: 32px;
      font-weight: bold;
      color: #27692A;
    }

    .stats-label {
      color: #666;
      font-size: 14px;
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
      
      <!-- Profile Card -->
      <div class="profile-card">
        <div class="profile-header">
          <div class="profile-avatar">
            <?php if (!empty($admin['avatar']) && file_exists($admin['avatar'])): ?>
              <img src="<?= htmlspecialchars($admin['avatar']) ?>" alt="Admin Avatar">
            <?php else: ?>
              <i class="bi bi-person-circle"></i>
            <?php endif; ?>
          </div>
          <div class="profile-name"><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></div>
          <div class="profile-role">Administrator</div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="info-section">
              <div class="info-label">First Name</div>
              <div class="info-value"><?= htmlspecialchars($admin['first_name']) ?></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-section">
              <div class="info-label">Last Name</div>
              <div class="info-value"><?= htmlspecialchars($admin['last_name']) ?></div>
            </div>
          </div>
        </div>

        <div class="info-section">
          <div class="info-label">Email Address</div>
          <div class="info-value"><?= htmlspecialchars($admin['email']) ?></div>
        </div>

        <?php if (!empty($admin['bio'])): ?>
        <div class="info-section">
          <div class="info-label">Bio</div>
          <div class="info-value"><?= htmlspecialchars($admin['bio']) ?></div>
        </div>
        <?php endif; ?>

        <div class="info-section">
          <div class="info-label">Account Created</div>
          <div class="info-value"><?= date('F j, Y', strtotime($admin['created_at'])) ?></div>
        </div>

        <div class="text-center mt-4">
          <a href="admin_edit_profile.php" class="btn btn-green me-2">
            <i class="bi bi-pencil-square me-2"></i>Edit Profile
          </a>
          <a href="feed.php" class="btn btn-outline-green">
            <i class="bi bi-arrow-left me-2"></i>Back to Feed
          </a>
        </div>
      </div>

      <!-- Admin Statistics -->
      <div class="row mt-4">
        <div class="col-md-4">
          <div class="stats-card">
            <div class="stats-number">
              <?php
              $conn = new mysqli("localhost", "root", "", "ground_zero");
              $posts_query = "SELECT COUNT(*) as count FROM posts WHERE author_type = 'admin' AND user_id = ?";
              $stmt = $conn->prepare($posts_query);
              $stmt->bind_param("i", $admin_id);
              $stmt->execute();
              $result = $stmt->get_result();
              $data = $result->fetch_assoc();
              echo $data['count'];
              ?>
            </div>
            <div class="stats-label">Posts Created</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stats-card">
            <div class="stats-number">
              <?php
              $users_query = "SELECT COUNT(*) as count FROM users";
              $result = $conn->query($users_query);
              $data = $result->fetch_assoc();
              echo $data['count'];
              ?>
            </div>
            <div class="stats-label">Total Users</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stats-card">
            <div class="stats-number">
              <?php
              $posts_query = "SELECT COUNT(*) as count FROM posts";
              $result = $conn->query($posts_query);
              $data = $result->fetch_assoc();
              echo $data['count'];
              ?>
            </div>
            <div class="stats-label">Total Posts</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 