<?php

define('DB_HOST', 'localhost'); // Usually localhost for XAMPP/WAMP/MAMP
define('DB_USER', 'root');      // Default user for XAMPP/WAMP/MAMP (change if needed)
define('DB_PASS', '');          // Default password for XAMPP/WAMP/MAMP is often empty (change if needed)
define('DB_NAME', 'newmark_db'); // The database name you created

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // In a real app, log this error properly, don't just die
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    die(json_encode(['success' => false, 'message' => 'Database Connection Failed: ' . $conn->connect_error]));
}

// Set character set to utf8mb4 for broader compatibility
if (!$conn->set_charset("utf8mb4")) {
    // Log error if needed
    // printf("Error loading character set utf8mb4: %s\n", $conn->error);
}

// Note: It's generally better practice to wrap database interactions in functions or classes,
// but for simplicity here, we'll include this file where needed.
// Remember to close the connection when done if not using persistent connections:
// $conn->close(); 

?> 