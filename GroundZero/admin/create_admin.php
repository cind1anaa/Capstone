<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h3>Setting up Admin Account...</h3>";

// First, add is_admin column if it doesn't exist
$check_column = "SHOW COLUMNS FROM users LIKE 'is_admin'";
$result = $conn->query($check_column);

if ($result->num_rows == 0) {
    echo "✅ Adding 'is_admin' column to users table...<br>";
    $add_column = "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0";
    if ($conn->query($add_column)) {
        echo "✅ 'is_admin' column added successfully!<br><br>";
    } else {
        echo "❌ Error adding is_admin column: " . $conn->error . "<br><br>";
    }
} else {
    echo "✅ 'is_admin' column already exists!<br><br>";
}

// Check if admin account already exists
$check_admin = "SELECT id FROM users WHERE email = 'groundzer0.use@gmail.com'";
$result = $conn->query($check_admin);

if ($result->num_rows > 0) {
    echo "✅ Admin account already exists!<br>";
    echo "Email: groundzer0.use@gmail.com<br>";
    echo "Password: GROUNDZERO2K25<br>";
    echo "<a href='login.php'>Go to Admin Login</a>";
} else {
    // Create admin account
    $admin_password = password_hash('GROUNDZERO2K25', PASSWORD_DEFAULT);
    
    $create_admin = "INSERT INTO users (first_name, last_name, email, password, barangay, is_admin, created_at) 
                     VALUES ('Admin', 'User', 'groundzer0.use@gmail.com', ?, 'Poblacion', 1, NOW())";
    
    $stmt = $conn->prepare($create_admin);
    $stmt->bind_param("s", $admin_password);
    
    if ($stmt->execute()) {
        echo "✅ Admin account created successfully!<br><br>";
        echo "<strong>Admin Login Details:</strong><br>";
        echo "Email: groundzer0.use@gmail.com<br>";
        echo "Password: GROUNDZERO2K25<br><br>";
        echo "<a href='login.php' class='btn btn-primary'>Go to Admin Login</a>";
    } else {
        echo "❌ Error creating admin account: " . $conn->error;
    }
    
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account - Ground Zero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
            padding: 50px 0;
        }
        .setup-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .success-icon {
            color: #28a745;
            font-size: 48px;
            margin-bottom: 20px;
        }
        .btn-primary {
            background-color: #27692A;
            border-color: #27692A;
        }
        .btn-primary:hover {
            background-color: #1f531f;
            border-color: #1f531f;
        }
        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #27692A;
            text-decoration: none;
            font-size: 18px;
        }
        .back-link:hover {
            color: #1f531f;
        }
    </style>
</head>
<body>
    <a href="../index.php" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Home
    </a>
    
    <div class="container">
        <div class="setup-container text-center">
            <div class="success-icon">🔧</div>
            <h3>Admin Account Setup</h3>
            <hr>
            <div id="result">
                <!-- PHP output will be displayed here -->
            </div>
        </div>
    </div>
</body>
</html> 