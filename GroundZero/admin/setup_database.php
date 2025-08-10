<?php
// Database setup script to add updated_at column
$conn = new mysqli("localhost", "root", "", "ground_zero");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Setup</h2>";

// Check if updated_at column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM requests LIKE 'updated_at'");

if ($checkColumn->num_rows == 0) {
    echo "<p>Adding updated_at column to requests table...</p>";
    
    // Add the column
    $sql = "ALTER TABLE requests ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ updated_at column added successfully</p>";
        
        // Update existing records
        $updateSql = "UPDATE requests SET updated_at = request_date WHERE updated_at IS NULL";
        if ($conn->query($updateSql) === TRUE) {
            echo "<p style='color: green;'>✓ Existing records updated with updated_at timestamp</p>";
        } else {
            echo "<p style='color: red;'>✗ Error updating existing records: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ updated_at column already exists</p>";
}

$conn->close();

echo "<p><a href='collection.php'>Go to Collection Requests</a></p>";
echo "<p><a href='Chistory.php'>Go to Collection History</a></p>";
?>
