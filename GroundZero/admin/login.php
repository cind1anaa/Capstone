<?php
session_start();

// If already logged in as admin, redirect to admin dashboard
if (isset($_SESSION["admin_id"]) && isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] == 1) {
    header("Location: dashboard.html");
    exit();
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Database connection
    $conn = new mysqli("localhost", "root", "", "ground_zero");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Check if admin exists in admin table
    $query = "SELECT * FROM admins WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        
        if (password_verify($password, $admin['password'])) {
            // Set session variables
            $_SESSION["admin_id"] = $admin['id'];
            $_SESSION["user_id"] = $admin['id']; // For compatibility with existing code
            $_SESSION["admin_first_name"] = $admin['first_name'];
            $_SESSION["admin_last_name"] = $admin['last_name'];
            $_SESSION["first_name"] = $admin['first_name'];
            $_SESSION["last_name"] = $admin['last_name'];
            $_SESSION["email"] = $admin['email'];
            $_SESSION["is_admin"] = 1;
            $_SESSION["avatar"] = $admin['avatar'];
            
            // Redirect to admin dashboard
            header("Location: dashboard.html");
            exit();
        } else {
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "Admin account not found.";
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login - Ground Zero</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
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

    .login-section {
      position: relative;
      background: url('../images/city.jpg') no-repeat center center;
      background-size: cover;
      background-attachment: fixed;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px 10px;
    }

    .login-section::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(39, 105, 42, 0.85) 0%, rgba(39, 105, 42, 0.75) 100%);
      z-index: 1;
    }

    .login-section .login-card {
      position: relative;
      z-index: 2;
    }

    .login-card {
      background: linear-gradient(to bottom right, #5a8d3f, #8dc26f);
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
      width: 100%;
      max-width: 400px;
      color: #fff;
      text-align: center;
    }

    .login-card input {
      border-radius: 6px;
    }

    .login-card .form-control {
      background-color: #f7f7f7;
      padding-left: 40px;
    }

    .btn-login {
      background-color: #fff;
      color: #27692A;
      font-weight: bold;
      border: none;
      padding: 8px 25px;
      margin-top: 15px;
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

    .alert-custom {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
      border-radius: 6px;
      font-size: 0.9rem;
      padding: 10px;
      margin-bottom: 10px;
    }

    .admin-badge {
      background-color: #dc3545;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: bold;
      margin-left: 10px;
    }
  </style>
</head>
<body>

  <!-- Login Form -->
  <section class="login-section">
    <div class="position-absolute top-0 start-0 m-3" style="z-index: 3;">
      <a href="../index.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-left"></i>
      </a>
    </div>
    <div class="login-card">
      <h3 class="fw-bold mb-4">ADMIN LOGIN <span class="admin-badge">ADMIN</span></h3>

      <?php if (!empty($error_message)): ?>
        <div class="alert-custom"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="on">
        <div class="mb-3 position-relative">
          <i class="bi bi-envelope-fill form-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="Enter admin email" 
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required />
        </div>
        <div class="mb-3 position-relative">
          <i class="bi bi-lock-fill form-icon"></i>
          <input type="password" name="password" id="password" class="form-control" placeholder="Enter admin password" autocomplete="current-password" required />
          <button type="button" class="btn btn-sm position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #27692A;" onclick="togglePassword('password')">
            <i class="bi bi-eye" id="passwordIcon"></i>
          </button>
        </div>
        <button type="submit" class="btn btn-login w-100">Login as Admin</button>
        <p class="mt-3 text-white">
          <small><i class="bi bi-shield-check me-1"></i>Admin access only</small>
        </p>
      </form>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(inputId + 'Icon');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
  </script>
</body>
</html> 