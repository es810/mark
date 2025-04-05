<?php
session_start(); // Start the session at the very beginning

require_once 'config/database.php'; // Adjust path if needed

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($username) || empty($password)) {
        // Redirect back with error
        header("Location: admin-login.html?error=empty");
        exit;
    }

    // Prepare SQL to find the admin user
    $sql = "SELECT id, username, password FROM admins WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            // Verify the password against the stored hash
            if (password_verify($password, $admin['password'])) {
                // Password is correct!
                // Store admin info in session variables
                $_SESSION['admin_loggedin'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Redirect to the admin dashboard
                header("Location: admin-dashboard.php"); // Note: Changed to .php
                exit;
            } else {
                // Invalid password
                header("Location: admin-login.html?error=invalid");
                exit;
            }
        } else {
            // Invalid username
            header("Location: admin-login.html?error=invalid");
            exit;
        }
        $stmt->close();
    } else {
        // Database query error
        // Log this error properly in a real application
        header("Location: admin-login.html?error=db");
        exit;
    }
    $conn->close();
} else {
    // If not a POST request, redirect to login page
    header("Location: admin-login.html");
    exit;
}
?> 