<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Upload Debug</h2>";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "<h3>POST Data:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>FILES Data:</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    if (isset($_FILES["media"])) {
        echo "<h3>File Upload Details:</h3>";
        echo "File name: " . $_FILES["media"]["name"] . "<br>";
        echo "File type: " . $_FILES["media"]["type"] . "<br>";
        echo "File size: " . $_FILES["media"]["size"] . "<br>";
        echo "Error code: " . $_FILES["media"]["error"] . "<br>";
        echo "Temp name: " . $_FILES["media"]["tmp_name"] . "<br>";
        
        if ($_FILES["media"]["error"] == 0) {
            $upload_dir = "uploads/posts/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["media"]["name"], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            echo "Attempting to upload to: $file_path<br>";
            
            if (move_uploaded_file($_FILES["media"]["tmp_name"], $file_path)) {
                echo "<strong style='color: green;'>File uploaded successfully!</strong><br>";
                echo "File saved as: $file_path<br>";
            } else {
                echo "<strong style='color: red;'>Failed to upload file!</strong><br>";
                echo "Error: " . error_get_last()['message'] . "<br>";
            }
        } else {
            echo "<strong style='color: red;'>File upload error: " . $_FILES["media"]["error"] . "</strong><br>";
        }
    } else {
        echo "<strong style='color: red;'>No file uploaded!</strong><br>";
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <h3>Test Upload Form</h3>
    <p>Content: <input type="text" name="content" value="Test post"></p>
    <p>File: <input type="file" name="media" accept="image/*,video/*"></p>
    <p><button type="submit">Test Upload</button></p>
</form> 