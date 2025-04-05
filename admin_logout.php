<?php
session_start(); // Access the session

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to admin login page
header("location: admin-login.html");
exit;
?> 