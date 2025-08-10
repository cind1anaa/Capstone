<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Structure Check</h2>";

// Check posts table
echo "<h3>Posts Table:</h3>";
$result = $conn->query("SHOW CREATE TABLE posts");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<pre>" . $row['Create Table'] . "</pre>";
} else {
    echo "Error getting posts table structure: " . $conn->error;
}

// Check comments table
echo "<h3>Comments Table:</h3>";
$result = $conn->query("SHOW CREATE TABLE comments");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<pre>" . $row['Create Table'] . "</pre>";
} else {
    echo "Error getting comments table structure: " . $conn->error;
}

// Check likes table
echo "<h3>Likes Table:</h3>";
$result = $conn->query("SHOW CREATE TABLE likes");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<pre>" . $row['Create Table'] . "</pre>";
} else {
    echo "Error getting likes table structure: " . $conn->error;
}

// Check current posts
echo "<h3>Current Posts:</h3>";
$result = $conn->query("SELECT id, user_id, content, created_at FROM posts ORDER BY created_at DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | User: " . $row['user_id'] . " | Content: " . substr($row['content'], 0, 50) . "...<br>";
    }
} else {
    echo "No posts found or error: " . $conn->error;
}

$conn->close();
?> 