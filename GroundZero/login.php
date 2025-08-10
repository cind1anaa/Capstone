<?php
require 'vendor/autoload.php'; // Composer-based loading

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Set timezone to Asia/Manila to fix expiration issue
date_default_timezone_set('Asia/Manila');

session_start();


$loginError = "";

// Check if there's a remember me cookie
if (!isset($_SESSION["user_id"]) && isset($_COOKIE["remember_me"])) {
    $conn = new mysqli("localhost", "root", "", "ground_zero");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Create remember_tokens table if it doesn't exist
    $createRememberTable = "CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_expires (expires),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($createRememberTable);

    $token = $_COOKIE["remember_me"];
    $conn->query("DELETE FROM remember_tokens WHERE expires < NOW()");

    $stmt = $conn->prepare("SELECT users.* FROM users 
                           INNER JOIN remember_tokens ON users.id = remember_tokens.user_id 
                           WHERE remember_tokens.token = ? AND remember_tokens.expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

            if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($user['status'] === 'accepted') {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["first_name"] = $user["first_name"];
                $_SESSION["last_name"] = $user["last_name"];
                $_SESSION["avatar"] = $user["avatar"] ?? null;

            $newToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

            $stmt = $conn->prepare("UPDATE remember_tokens SET token = ?, expires = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $newToken, $expires, $user["id"]);
            $stmt->execute();

            setcookie("remember_me", $newToken, strtotime('+30 days'), "/", "", true, true);

            header("Location: feed.php");
            exit();
        }
    }

    $stmt->close();
    $conn->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "ground_zero");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user['status'] === 'accepted') {
            if (password_verify($password, $user['password'])) {
                $code = sprintf("%06d", mt_rand(0, 999999));
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                error_log("Attempting to create auth_codes table and store code: $code");

                // Create auth_codes table if it doesn't exist
                $createTable = "CREATE TABLE IF NOT EXISTS auth_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    code VARCHAR(6) NOT NULL,
                    expires DATETIME NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_code (user_id, code),
                    INDEX idx_expires (expires)
                )";
                
                if (!$conn->query($createTable)) {
                    error_log("Error creating auth_codes table: " . $conn->error);
                    $loginError = "Database error. Please try again.";
                } else {
                    error_log("Auth_codes table created/verified successfully");
                    
                    // Clean up old expired codes
                    $cleanup = "DELETE FROM auth_codes WHERE expires < NOW()";
                    $conn->query($cleanup);

                    // Store the code in database FIRST
                    $stmt = $conn->prepare("INSERT INTO auth_codes (user_id, code, expires) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $user["id"], $code, $expires);
                    
                    if ($stmt->execute()) {
                        error_log("Code stored successfully: $code for user {$user['id']}");
                        
                        // Set session variables
                        $_SESSION["temp_user_id"] = $user["id"];
                        $_SESSION["temp_email"] = $user["email"];
                        $_SESSION["temp_first_name"] = $user["first_name"];
                        $_SESSION["temp_last_name"] = $user["last_name"];

                        if (isset($_POST["remember_me"])) {
                            $_SESSION["temp_remember_me"] = true;
                        }

                        // Send email
                        $mail = new PHPMailer(true);

                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'groundzer0.use@gmail.com';
                            $mail->Password = 'yxsh tpqt havu frle';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;
                            
                            // Fix SSL certificate verification issues on XAMPP
                            $mail->SMTPOptions = array(
                                'ssl' => array(
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                )
                            );

                            $mail->setFrom('groundzer0.use@gmail.com', 'Ground Zero Admin');
                            $mail->addAddress($user["email"], $user["first_name"]);
                            $mail->isHTML(true);
                            $mail->Subject = 'Your Login Verification Code';
                            $mail->Body = "
                                <p>Dear {$user['first_name']},</p>
                                <p>Your verification code is: <strong>{$code}</strong></p>
                                <p>This code will expire in 15 minutes.</p>
                                <p>If you didn't request this code, please ignore this email.</p>
                                <p>Best regards,<br>Ground Zero Admin Team</p>
                            ";

                            if ($mail->send()) {
                                error_log("Email sent successfully to {$user['email']} with code: $code");
                                $_SESSION['verification_email'] = $user["email"];
                                
                                // For debugging: Show the code on screen
                                if (isset($_GET['debug'])) {
                                    echo "<div style='background: yellow; padding: 10px; margin: 10px;'>";
                                    echo "<strong>DEBUG MODE:</strong> Your verification code is: <strong>$code</strong>";
                                    echo "<br><a href='authentication.php'>Click here to enter the code</a>";
                                    echo "</div>";
                                }
                                
                                header("Location: authentication.php");
                                exit();
                            }
                        } catch (Exception $e) {
                            $loginError = "Failed to send verification code. Please try again.";
                            error_log("Mail error: " . $e->getMessage());
                        }
                    } else {
                        $loginError = "Failed to generate verification code. Please try again.";
                        error_log("Failed to store code in database: " . $stmt->error);
                    }
                }
            } else {
                $loginError = "Invalid password.";
            }
        } elseif ($user['status'] === 'pending') {
            $loginError = "Your account is pending approval.";
        } elseif ($user['status'] === 'rejected') {
            $loginError = "Your account has been rejected.";
        }
    } else {
        $loginError = "No account found with that email.";
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
  <title>Login - Ground Zero</title>
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
      background: url('images/city.jpg') no-repeat center center;
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

    .forgot-link,
    .register-link {
      color: #fff;
      font-size: 0.9rem;
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
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="images/logo.png" alt="Ground Zero Logo" height="40" class="me-2" />
      <span>MENRO – Malvar Batangas</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="index.php">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="about.html">ABOUT</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.html">CONTACT</a></li>
      </ul>
    </div>
  </nav>

  <!-- Login Form -->
  <section class="login-section">
    <div class="position-absolute top-0 start-0 m-3" style="z-index: 3;">
      <a href="index.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-left"></i>
      </a>
    </div>
    <div class="login-card">
      <h3 class="fw-bold mb-4">LOGIN</h3>

      <?php if (!empty($loginError)): ?>
        <div class="alert-custom"><?= $loginError ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="on">
        <div class="mb-3 position-relative">
          <i class="bi bi-envelope-fill form-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="Enter your email" autocomplete="email" required />
        </div>
        <div class="mb-3 position-relative">
          <i class="bi bi-lock-fill form-icon"></i>
          <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" autocomplete="current-password" required />
          <button type="button" class="btn btn-sm position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #27692A;" onclick="togglePassword('password')">
            <i class="bi bi-eye" id="passwordIcon"></i>
          </button>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me" />
            <label class="form-check-label" for="rememberMe">Remember me</label>
          </div>
          <a href="forgot.php" class="forgot-link text-decoration-underline">Forgot Password?</a>
        </div>
        <button type="submit" class="btn btn-login w-100">Login</button>
        <p class="mt-3 register-link">Don't have an account? <a href="register.php" class="text-white text-decoration-underline">Register here</a></p>
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
