<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!password_verify($currentPassword, $user['password'])) {
        $error = "Current password is incorrect";
    } elseif (strlen($newPassword) < 8 ||
              !preg_match('/[A-Z]/', $newPassword) ||
              !preg_match('/[a-z]/', $newPassword) ||
              !preg_match('/[0-9]/', $newPassword)) {
        $error = "New password must be at least 8 characters long and include uppercase, lowercase, and a number";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);

        if ($updateStmt->execute()) {
            $success = "Password successfully updated!";
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reset Password - MENRO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
      font-family: "Segoe UI", sans-serif;
      margin: 0;
      padding: 0;
    }

    .custom-navbar {
      background-color: #27692A;
    }

    .custom-navbar .nav-link,
    .custom-navbar .navbar-brand span {
      color: #ffffff;
    }

    .custom-navbar .nav-link:hover {
      color: #f1f1f1;
    }

    .reset-section {
      background: url('images/bg.png') no-repeat center center;
      background-size: cover;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .reset-card {
      background: linear-gradient(to bottom right, #5a8d3f, #8dc26f);
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
      width: 100%;
      max-width: 400px;
      color: #fff;
    }

    .form-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
      font-size: 1.1rem;
      pointer-events: none;
    }

    .form-control {
      padding-left: 40px;
      border-radius: 6px;
      background-color: #f7f7f7;
    }

    .toggle-password {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #666;
      z-index: 10;
    }

    .toggle-password:hover {
      color: #333;
    }

    .btn-reset {
      background-color: #fff;
      color: #27692A;
      font-weight: bold;
      border: none;
      padding: 8px 25px;
      margin-top: 15px;
    }

    .password-requirements {
      text-align: left;
      font-size: 0.85rem;
    }

    .password-requirements ul {
      padding-left: 20px;
      margin-bottom: 0;
    }

    .alert {
      text-align: left;
      border-radius: 6px;
    }
  </style>
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="images/logo.png" alt="Logo" height="40" class="me-2" />
      <span>MENRO – Malvar Batangas</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="index.html">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="about.html">ABOUT</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.html">CONTACT</a></li>
      </ul>
    </div>
  </nav>

  <!-- Reset Section -->
  <section class="reset-section">
    <div class="reset-card">
        <h3 class="fw-bold mb-4">CHANGE PASSWORD</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success mb-3"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" action="" autocomplete="on">
            <div class="mb-3 position-relative">
                <i class="bi bi-lock-fill form-icon"></i>
                <input type="password" name="currentPassword" id="currentPassword" class="form-control"
                       placeholder="Current password" autocomplete="current-password" required />
                <span class="toggle-password" onclick="togglePassword('currentPassword')">
                    <i class="bi bi-eye"></i>
                </span>
            </div>
            <div class="mb-3 position-relative">
                <i class="bi bi-lock-fill form-icon"></i>
                <input type="password" name="newPassword" id="newPassword" class="form-control"
                       placeholder="New password" autocomplete="new-password" required minlength="8" />
                <span class="toggle-password" onclick="togglePassword('newPassword')">
                    <i class="bi bi-eye"></i>
                </span>
            </div>
            <div class="mb-3 position-relative">
                <i class="bi bi-lock-fill form-icon"></i>
                <input type="password" name="confirmPassword" id="confirmPassword" class="form-control"
                       placeholder="Confirm new password" autocomplete="new-password" required minlength="8" />
                <span class="toggle-password" onclick="togglePassword('confirmPassword')">
                    <i class="bi bi-eye"></i>
                </span>
            </div>
            <div class="password-requirements text-light mb-3">
                Password must:
                <ul>
                    <li>Be at least 8 characters long</li>
                    <li>Include at least one number</li>
                    <li>Include at least one uppercase letter</li>
                    <li>Include at least one lowercase letter</li>
                </ul>
            </div>
            <button type="submit" class="btn btn-reset w-100">Update Password</button>
            <p class="mt-3"><a href="feed.php" class="text-white text-decoration-underline">Back to Feed</a></p>
        </form>
    </div>
  </section>

  <script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    
    const icon = event.currentTarget.querySelector('i');
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
}
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
