<?php
session_start();
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set timezone to Asia/Manila to fix timezone issues
date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "ground_zero");
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email = trim($_POST['email']);
    
    // Create password_resets table if it doesn't exist
    $createTable = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_expires (expires),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($createTable);
    
    // Clean up old expired tokens (but keep recent ones for debugging)
    $cleanup = "DELETE FROM password_resets WHERE expires < NOW() AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $conn->query($cleanup);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // After verifying email exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save reset token
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user['id'], $token, $expires);
        
        if ($stmt->execute()) {
            error_log("Token created successfully: $token for user {$user['id']}");
            
            // Verify token was stored
            $checkStmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ?");
            $checkStmt->bind_param("s", $token);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                error_log("Token verified in database");
            } else {
                error_log("Token not found in database after insertion!");
            }
        } else {
            error_log("Failed to create token: " . $stmt->error);
        }

        // Send email
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'groundzer0.use@gmail.com'; // Your Gmail
            $mail->Password   = 'yxsh tpqt havu frle';     // Your App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('groundzer0.use@gmail.com', 'Ground Zero Admin');
            $mail->addAddress($email, $user['first_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - Ground Zero';
            $mail->Body    = "
                <p>Dear {$user['first_name']},</p>
                <p>We received a request to reset your password. Click the link below to reset it:</p>
                <p><a href='http://localhost/GroundZero/reset1.php?token={$token}'>Reset Password</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
                <p>Best regards,<br>Ground Zero Admin Team</p>
            ";

            $mail->send();
            $message = "Password reset instructions have been sent to your email.";
            $alertClass = "alert-success";
        } catch (Exception $e) {
            $message = "Failed to send email. Please try again later.";
            $alertClass = "alert-danger";
        }
    } else {
        $message = "If the email exists in our system, you will receive reset instructions.";
        $alertClass = "alert-info";
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
  <title>Forgot Password - MENRO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
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
      background: #68a751;
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      width: 100%;
      max-width: 400px;
      color: #fff;
      text-align: center;
    }

    .form-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #27692A;
      font-size: 1.2rem;
      pointer-events: none;
    }

    .form-control {
      padding-left: 35px;
      border-radius: 5px;
    }

    .btn-login {
      background-color: #fff;
      color: #27692A;
      font-weight: bold;
      border: none;
      padding: 10px 25px;
      margin-top: 15px;
      border-radius: 5px;
    }

    .forgot-link {
      color: #fff;
      font-size: 0.9rem;
    }

    .back-btn {
      position: absolute;
      top: 20px;
      left: 20px;
      background: white;
      color: #27692A;
      padding: 10px 12px;
      border-radius: 50%;
      font-size: 1.25rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
      text-decoration: none;
      z-index: 10;
      transition: background 0.2s ease;
    }

    .back-btn:hover {
      background: #f1f1f1;
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="images/logo.png" alt="MENRO Logo" height="40" class="me-2" />
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

  <!-- Forgot Password Form -->
  <section class="login-section">
    <div class="login-card">
        <h4 class="fw-bold mb-4">FORGOT PASSWORD</h4>
        <?php if (isset($message)): ?>
            <div class="alert <?php echo $alertClass; ?> mb-3">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="" autocomplete="on">
            <div class="mb-3 position-relative">
                <i class="bi bi-envelope-fill form-icon"></i>
                <input type="email" name="email" class="form-control" placeholder="Enter your email" autocomplete="email" required />
            </div>
            <button type="submit" class="btn btn-login w-100">Send Reset Link</button>
            <p class="mt-3 forgot-link">Remembered your password? <a href="login.php" class="text-white text-decoration-underline">Back to Login</a></p>
        </form>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
