<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Delete the remember me cookie if it exists
if (isset($_COOKIE["remember_me"])) {
    setcookie("remember_me", "", time() - 3600, "/");
}

// Redirect to login page
header("Location: login.php");
exit();