<?php
session_start();
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Set timezone to Asia/Manila to fix expiration issue
date_default_timezone_set('Asia/Manila');

$error = '';
$success = '';

// Check if user is coming from login
if (!isset($_SESSION["temp_user_id"])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ground_zero");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

// Clean up old expired codes
$cleanup = "DELETE FROM auth_codes WHERE expires < NOW()";
$conn->query($cleanup);

// Debug: Check table structure and existing codes
if (isset($_SESSION["temp_user_id"])) {
    $userId = $_SESSION["temp_user_id"];
    $stmt = $conn->prepare("SELECT * FROM auth_codes WHERE user_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    error_log("Total codes for user $userId: " . $result->num_rows);
    
    while ($row = $result->fetch_assoc()) {
        error_log("Code: '{$row['code']}', Used: {$row['used']}, Expires: {$row['expires']}");
    }
    
    // Show debug info on screen if debug mode is enabled
    if (isset($_GET['debug'])) {
        echo "<div style='background: lightblue; padding: 10px; margin: 10px; border: 1px solid blue;'>";
        echo "<strong>DEBUG INFO:</strong><br>";
        echo "User ID: $userId<br>";
        echo "Total codes: " . $result->num_rows . "<br>";
        
        $stmt->execute(); // Re-execute to get fresh results
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $status = $row['used'] ? 'USED' : 'ACTIVE';
            $expired = strtotime($row['expires']) < time() ? 'EXPIRED' : 'VALID';
            echo "Code: <strong>{$row['code']}</strong> - Status: $status - $expired<br>";
        }
        echo "</div>";
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["code"]) && !empty($_POST["code"])) {
        $userId = $_SESSION["temp_user_id"];
        $code = trim($_POST["code"]); // Get the code as entered

        error_log("Auth attempt - User ID: $userId, Entered Code: '$code'");

        // Use direct query like the working manual test
        $sql = "SELECT * FROM auth_codes WHERE user_id = $userId AND code = '$code' AND expires > NOW() AND used = 0 ORDER BY id DESC LIMIT 1";
        $result = $conn->query($sql);
        
        error_log("Validation query result rows: " . $result->num_rows);

        if ($result->num_rows === 1) {
            error_log("Code validation successful - proceeding with login");
            
            // Mark code as used using direct query
            $updateSql = "UPDATE auth_codes SET used = 1 WHERE user_id = $userId AND code = '$code' AND used = 0";
            $conn->query($updateSql);

            // Get user data including avatar
            $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION["temp_user_id"]);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            // Set session variables
            $_SESSION["user_id"] = $_SESSION["temp_user_id"];
            $_SESSION["email"] = $_SESSION["temp_email"];
            $_SESSION["first_name"] = $_SESSION["temp_first_name"];
            $_SESSION["last_name"] = $_SESSION["temp_last_name"];
            $_SESSION["avatar"] = $user["avatar"] ?? null;

            // Handle remember me
            if (isset($_SESSION["temp_remember_me"])) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

                $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $_SESSION["user_id"], $token, $expires);
                $stmt->execute();

                setcookie("remember_me", $token, strtotime('+30 days'), "/", "", true, true);
            }

            // Clear temp session
            unset($_SESSION["temp_user_id"]);
            unset($_SESSION["temp_email"]);
            unset($_SESSION["temp_first_name"]);
            unset($_SESSION["temp_last_name"]);
            unset($_SESSION["temp_remember_me"]);

            header("Location: feed.php");
            exit();
        } else {
            error_log("Code validation failed - checking what's wrong");
            
            // Check what's wrong using direct query
            $checkSql = "SELECT * FROM auth_codes WHERE user_id = $userId ORDER BY id DESC LIMIT 1";
            $result = $conn->query($checkSql);
            
            if ($result->num_rows === 0) {
                $error = "No verification code found. Please try logging in again.";
                error_log("No auth codes found for user $userId");
            } else {
                $row = $result->fetch_assoc();
                error_log("Debug - Stored code: '{$row['code']}', Entered code: '$code', Used: {$row['used']}, Expires: {$row['expires']}");
                
                if ($row['used'] == 1) {
                    $error = "Code has already been used. Please try logging in again.";
                } elseif ($row['expires'] <= date('Y-m-d H:i:s')) {
                    $error = "Code has expired. Please try logging in again.";
                } else {
                    $error = "Invalid code. Please check your email and try again.";
                }
            }
        }
    } elseif (isset($_POST["resend"])) {
        $userId = $_SESSION["temp_user_id"];
        
        // Get user info
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // Generate new code
            $code = sprintf("%06d", mt_rand(0, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Store new code using direct query
            $sql = "INSERT INTO auth_codes (user_id, code, expires) VALUES ($userId, '$code', '$expires')";
            $conn->query($sql);

            // Send email
            try {
                $mail = new PHPMailer(true);
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
                    <p>Your new verification code is: <strong>{$code}</strong></p>
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you didn't request this code, please ignore this email.</p>
                    <p>Best regards,<br>Ground Zero Admin Team</p>
                ";

                $mail->send();
                $success = "New verification code sent to your email!";
            } catch (Exception $e) {
                $error = "Failed to send verification code. Please try again.";
            }
        } else {
            $error = "User not found";
        }
    } else {
        $error = "Please enter a verification code";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Two-Factor Authentication - MENRO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body { font-family: "Segoe UI", sans-serif; }
    .custom-navbar { background-color: #27692A; }
    .custom-navbar .nav-link, .custom-navbar .navbar-brand span { color: #fff; }
    .auth-section {
      position: relative;
      background: url('images/city.jpg') no-repeat center center;
      background-size: cover;
      background-attachment: fixed;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      padding: 20px 10px;
    }

    .auth-section::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(39, 105, 42, 0.85) 0%, rgba(39, 105, 42, 0.75) 100%);
      z-index: 1;
    }

    .auth-section .auth-card {
      position: relative;
      z-index: 2;
    }
    .auth-card {
      background: linear-gradient(to bottom right, #5a8d3f, #8dc26f);
      padding: 40px 30px;
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      text-align: center;
      max-width: 420px;
    }
    .auth-card h4 { color: white; font-weight: bold; margin-bottom: 20px; }
    .code-boxes input {
      width: 40px;
      height: 50px;
      margin: 0 5px;
      font-size: 24px;
      text-align: center;
      border-radius: 6px;
      border: 1px solid #ccc;
    }
    .btn-verify {
      background-color: white;
      color: #27692A;
      font-weight: bold;
      border: none;
      padding: 8px 25px;
      margin-top: 20px;
    }
    .resend-text { margin-top: 10px; color: #fff; }
    .resend-text a { color: white; text-decoration: underline; }
    .alert { margin-bottom: 20px; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
  <a class="navbar-brand d-flex align-items-center" href="#">
    <img src="images/logo.png" alt="Logo" height="40" class="me-2" />
    <span>MENRO – Malvar Batangas</span>
  </a>
</nav>

<section class="auth-section">
  <div class="auth-card">
    <h4>TWO-FACTOR<br>AUTHENTICATION</h4>
    
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="authForm">
      <div class="code-boxes d-flex justify-content-center mb-3">
        <input type="text" maxlength="1" class="form-control" name="code[]" required />
        <input type="text" maxlength="1" class="form-control" name="code[]" required />
        <input type="text" maxlength="1" class="form-control" name="code[]" required />
        <input type="text" maxlength="1" class="form-control" name="code[]" required />
        <input type="text" maxlength="1" class="form-control" name="code[]" required />
        <input type="text" maxlength="1" class="form-control" name="code[]" required />
      </div>
      <button type="submit" class="btn btn-verify w-100">Verify</button>
      <p class="resend-text">Didn't receive a code? <a href="#" onclick="resendCode()">Resend</a></p>
    </form>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const inputs = document.querySelectorAll('.code-boxes input');
  inputs.forEach((input, index) => {
    input.addEventListener('input', function() {
      if (this.value.length === 1 && index < inputs.length - 1) {
        inputs[index + 1].focus();
      }
    });
    
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
        inputs[index - 1].focus();
      }
    });
  });

  document.getElementById('authForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const codeInputs = document.querySelectorAll('input[name="code[]"]');
    let code = '';
    let isValid = true;
    
    codeInputs.forEach(input => {
      if (input.value.length === 0) {
        isValid = false;
      }
      code += input.value;
    });
    
    if (!isValid || code.length !== 6) {
      alert('Please enter all 6 digits of the verification code');
      return;
    }
    
    // Create hidden input for the complete code
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'code';
    hiddenInput.value = code;
    this.appendChild(hiddenInput);
    
    this.submit();
  });

  function resendCode() {
    const form = document.createElement('form');
    form.method = 'POST';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'resend';
    input.value = '1';
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }
</script>
</body>
</html>
