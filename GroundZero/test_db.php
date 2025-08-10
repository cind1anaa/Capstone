<?php
// Test database connection and table creation
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

$conn = new mysqli("localhost", "root", "", "ground_zero");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<p>✅ Database connection successful</p>";

// Test table creation
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

if ($conn->query($createTable)) {
    echo "<p>✅ Auth_codes table created/verified successfully</p>";
} else {
    echo "<p>❌ Error creating table: " . $conn->error . "</p>";
}

// Test inserting a code
$testCode = "123456";
$testUserId = 18;
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$stmt = $conn->prepare("INSERT INTO auth_codes (user_id, code, expires) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $testUserId, $testCode, $expires);

if ($stmt->execute()) {
    echo "<p>✅ Test code inserted successfully</p>";
    
    // Check if code exists
    $checkStmt = $conn->prepare("SELECT * FROM auth_codes WHERE user_id = ? AND code = ?");
    $checkStmt->bind_param("is", $testUserId, $testCode);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p>✅ Code found in database</p>";
    } else {
        echo "<p>❌ Code not found in database</p>";
    }
} else {
    echo "<p>❌ Error inserting test code: " . $stmt->error . "</p>";
}

// Show all codes in database
echo "<h3>All codes in database:</h3>";
$result = $conn->query("SELECT * FROM auth_codes ORDER BY id DESC");
if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Code</th><th>Expires</th><th>Used</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['code'] . "</td>";
        echo "<td>" . $row['expires'] . "</td>";
        echo "<td>" . $row['used'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No codes found in database</p>";
}

$conn->close();
?> 