<?php
session_start();

// Set timezone to Asia/Manila to fix timezone issues
date_default_timezone_set('Asia/Manila');

$conn = new mysqli("localhost", "root", "", "ground_zero");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

$error = '';
$success = '';

// Verify token from email link
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Debug logging
    error_log("Reset token received: " . $token);
    
    $stmt = $conn->prepare("SELECT pr.*, u.email, u.first_name 
                           FROM password_resets pr 
                           JOIN users u ON pr.user_id = u.id 
                           WHERE pr.token = ? AND pr.expires > NOW() AND pr.used = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("Token validation result rows: " . $result->num_rows);
    
    if ($result->num_rows === 0) {
        // Check what's wrong with the token
        $checkStmt = $conn->prepare("SELECT pr.*, u.email, u.first_name 
                                    FROM password_resets pr 
                                    JOIN users u ON pr.user_id = u.id 
                                    WHERE pr.token = ?");
        $checkStmt->bind_param("s", $token);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $error = "Invalid reset link. Please request a new password reset.";
        } else {
            $reset = $checkResult->fetch_assoc();
            if ($reset['used'] == 1) {
                $error = "This reset link has already been used. Please request a new password reset.";
            } elseif ($reset['expires'] <= date('Y-m-d H:i:s')) {
                $error = "This reset link has expired. Please request a new password reset.";
            } else {
                $error = "Invalid reset link. Please request a new password reset.";
            }
        }
    } else {
        $reset = $result->fetch_assoc();
        $_SESSION['reset_user_id'] = $reset['user_id'];
        $_SESSION['reset_token'] = $token;
        $_SESSION['reset_email'] = $reset['email'];
        $_SESSION['reset_name'] = $reset['first_name'];
        error_log("Token validated successfully for user: " . $reset['email']);
    }
}

// Handle password reset form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['reset_user_id'])) {
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    
    if (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $_SESSION['reset_user_id']);
        
        if ($stmt->execute()) {
            // Mark token as used
            $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->bind_param("s", $_SESSION['reset_token']);
            $stmt->execute();
            
            $success = "Password successfully reset! Redirecting to login...";
            session_destroy();
            header("Refresh:2; url=login.php");
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Ground Zero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Copy the same styles from your forgot.php */
        .reset-section {
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

        .reset-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(39, 105, 42, 0.85) 0%, rgba(39, 105, 42, 0.75) 100%);
            z-index: 1;
        }

        .reset-section .reset-card {
            position: relative;
            z-index: 2;
        }

        .reset-card {
            background: #68a751;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            color: #fff;
            text-align: center;
        }

        .form-control {
            padding-left: 35px;
            border-radius: 5px;
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
    </style>
</head>
<body>
    <section class="reset-section">
        <div class="reset-card">
            <h4 class="fw-bold mb-4">RESET PASSWORD</h4>
            <?php if ($error): ?>
                <div class="alert alert-danger mb-3">
                    <?php echo htmlspecialchars($error); ?>
                    <br><br>
                    <a href="forgot.php" class="btn btn-outline-light btn-sm">Request New Reset Link</a>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success mb-3"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!$error && !$success): ?>
            <form method="POST" action="">
                <div class="mb-3 position-relative">
                    <i class="bi bi-lock-fill form-icon"></i>
                    <input type="password" name="newPassword" id="newPassword" class="form-control" 
                           placeholder="New password" required minlength="8" />
                    <button type="button" class="btn btn-sm position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #27692A;" onclick="togglePassword('newPassword')">
                        <i class="bi bi-eye" id="newPasswordIcon"></i>
                    </button>
                </div>
                <div class="mb-3 position-relative">
                    <i class="bi bi-lock-fill form-icon"></i>
                    <input type="password" name="confirmPassword" id="confirmPassword" class="form-control" 
                           placeholder="Confirm password" required minlength="8" />
                    <button type="button" class="btn btn-sm position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #27692A;" onclick="togglePassword('confirmPassword')">
                        <i class="bi bi-eye" id="confirmPasswordIcon"></i>
                    </button>
                </div>
                <button type="submit" class="btn btn-login w-100">Reset Password</button>
            </form>
            <?php endif; ?>
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