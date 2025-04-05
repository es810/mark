<?php
session_start(); // Start session

// Check if the admin is logged in
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    // Send JSON error if accessed directly without session or via non-POST
     if ($_SERVER["REQUEST_METHOD"] != "POST") {
         header("location: admin-login.html?error=notloggedin");
         exit;
     } else {
        // Handle POST attempt without session
         header("Location: admin-upload.php?status=error&msg=auth"); // Redirect back with error
         exit;
     }
}

require_once 'config/database.php'; // Include DB connection

// --- Configuration ---
$upload_dir = 'uploads/'; // Directory to store images (ensure it exists and is writable!)
$logo_upload_dir = 'uploads/logos/'; // Use a subdirectory for logos (optional but cleaner)
$masterplan_upload_dir = 'uploads/masterplans/'; // Directory for master plans
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowed_masterplan_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']; // Allow PDF for master plan
$max_file_size = 5 * 1024 * 1024; // 5 MB
$max_logo_size = 2 * 1024 * 1024; // 2 MB for logos
$max_masterplan_size = 5 * 1024 * 1024; // 5 MB for master plan

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Retrieve Form Data ---
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $location = $_POST['location'] ?? null;
    $status = $_POST['status'] ?? 'available';
    $price = $_POST['price'] ?? 0;
    $price_suffix = $_POST['price_suffix'] ?? null;
    $beds = !empty($_POST['beds']) ? (int)$_POST['beds'] : null;
    $baths = !empty($_POST['baths']) ? (int)$_POST['baths'] : null;
    $size = !empty($_POST['size']) ? (int)$_POST['size'] : null;
    $yearBuilt = !empty($_POST['yearBuilt']) ? (int)$_POST['yearBuilt'] : null;
    $tag = $_POST['tag'] ?? null;
    $description = $_POST['description'] ?? null;
    $amenities = $_POST['amenities'] ?? null; // Store as text/comma-separated
    $contactNumber = $_POST['contactNumber'] ?? null;
    $whatsappNumber = $_POST['whatsappNumber'] ?? null;

    // --- Basic Validation ---
    if (empty($title) || empty($category) || empty($price)) {
        header("Location: admin-upload.php?status=error&msg=required_fields");
        exit;
    }
    
    // --- Ensure Upload Directories Exist ---
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
    if (!is_dir($logo_upload_dir)) { mkdir($logo_upload_dir, 0755, true); } // Create logo subdir
    if (!is_dir($masterplan_upload_dir)) { mkdir($masterplan_upload_dir, 0755, true); } // Create masterplan subdir
    if (!is_writable($upload_dir) || !is_writable($logo_upload_dir) || !is_writable($masterplan_upload_dir)) {
        header("Location: admin-upload.php?status=error&msg=upload_dir_permission");
        exit; 
    }

    // --- Handle Developer Logo Upload ---
    $developer_logo_filename = null; // Default to null
    $logo_file_error = null;
    if (isset($_FILES['developer_logo']) && $_FILES['developer_logo']['error'] === UPLOAD_ERR_OK) {
         $logo_file_name = $_FILES['developer_logo']['name'];
         $logo_file_tmp_name = $_FILES['developer_logo']['tmp_name'];
         $logo_file_size = $_FILES['developer_logo']['size'];
         $logo_file_type = $_FILES['developer_logo']['type'];

         // Validate type & size
         if (!in_array($logo_file_type, $allowed_types)) {
             $logo_file_error = "Logo file '$logo_file_name': Invalid type.";
         } elseif ($logo_file_size > $max_logo_size) {
              $logo_file_error = "Logo file '$logo_file_name': Exceeds max size (2MB).";
         } else {
             // Generate unique filename
             $logo_file_extension = pathinfo($logo_file_name, PATHINFO_EXTENSION);
             $unique_logo_filename = uniqid('devlogo_', true) . '.' . strtolower($logo_file_extension);
             $logo_destination = $logo_upload_dir . $unique_logo_filename;

             if (move_uploaded_file($logo_file_tmp_name, $logo_destination)) {
                 $developer_logo_filename = $unique_logo_filename; // Store only filename
             } else {
                  $logo_file_error = "Logo file '$logo_file_name': Failed to move.";
             }
         }
    } elseif (isset($_FILES['developer_logo']) && $_FILES['developer_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
         // Handle other upload errors for logo
         $logo_file_error = "Logo upload error code: " . $_FILES['developer_logo']['error'];
    }
    
    // If logo upload failed critically, redirect back
    if ($logo_file_error) {
        $_SESSION['upload_errors'] = [$logo_file_error]; // Pass back the error
        header("Location: admin-upload.php?status=file_error");
        exit;
    }

    // --- Handle Master Plan Upload ---
    $master_plan_filename = null; // Default to null
    $masterplan_file_error = null;
    if (isset($_FILES['master_plan_file']) && $_FILES['master_plan_file']['error'] === UPLOAD_ERR_OK) {
         $mp_file_name = $_FILES['master_plan_file']['name'];
         $mp_file_tmp_name = $_FILES['master_plan_file']['tmp_name'];
         $mp_file_size = $_FILES['master_plan_file']['size'];
         $mp_file_type = $_FILES['master_plan_file']['type'];

         // Validate type & size
         if (!in_array($mp_file_type, $allowed_masterplan_types)) {
             $masterplan_file_error = "Master Plan file '$mp_file_name': Invalid type.";
         } elseif ($mp_file_size > $max_masterplan_size) {
              $masterplan_file_error = "Master Plan file '$mp_file_name': Exceeds max size (5MB).";
         } else {
             // Generate unique filename
             $mp_file_extension = pathinfo($mp_file_name, PATHINFO_EXTENSION);
             $unique_mp_filename = uniqid('mp_', true) . '.' . strtolower($mp_file_extension);
             $mp_destination = $masterplan_upload_dir . $unique_mp_filename;

             if (move_uploaded_file($mp_file_tmp_name, $mp_destination)) {
                 $master_plan_filename = $unique_mp_filename; // Store only filename
             } else {
                  $masterplan_file_error = "Master Plan file '$mp_file_name': Failed to move.";
             }
         }
    } elseif (isset($_FILES['master_plan_file']) && $_FILES['master_plan_file']['error'] !== UPLOAD_ERR_NO_FILE) {
         $masterplan_file_error = "Master Plan upload error code: " . $_FILES['master_plan_file']['error'];
    }
    
    // If master plan upload failed critically, cleanup logo and redirect back
    if ($masterplan_file_error) {
        if ($developer_logo_filename) { @unlink($logo_upload_dir . $developer_logo_filename); } // Cleanup logo
        $_SESSION['upload_errors'] = [$masterplan_file_error];
        header("Location: admin-upload.php?status=file_error");
        exit;
    }

    // --- Handle Property Images Upload ---
    $uploaded_filenames = [];
    $prop_img_file_errors = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $file_count = count($_FILES['images']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $file_name = $_FILES['images']['name'][$i];
            $file_tmp_name = $_FILES['images']['tmp_name'][$i];
            $file_size = $_FILES['images']['size'][$i];
            $file_error = $_FILES['images']['error'][$i];
            $file_type = $_FILES['images']['type'][$i];

            if ($file_error === UPLOAD_ERR_OK) {
                // Validate type
                if (!in_array($file_type, $allowed_types)) {
                    $prop_img_file_errors[] = "File '$file_name': Invalid type ($file_type). Allowed: JPG, PNG, GIF, WEBP.";
                    continue; // Skip this file
                }

                // Validate size
                if ($file_size > $max_file_size) {
                    $prop_img_file_errors[] = "File '$file_name': Exceeds max size (5MB).";
                    continue; // Skip this file
                }

                // Generate unique filename to prevent overwrites
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_filename = uniqid('prop_', true) . '.' . strtolower($file_extension);
                $destination = $upload_dir . $unique_filename;

                // Move the file
                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $uploaded_filenames[] = $unique_filename; // Store only the filename
                } else {
                    $prop_img_file_errors[] = "File '$file_name': Failed to move to destination.";
                }

            } elseif ($file_error !== UPLOAD_ERR_NO_FILE) { // Ignore "no file" error if some files are uploaded
                 $prop_img_file_errors[] = "File '$file_name': Upload error code: $file_error.";
            }
        }
    } else {
         // No files uploaded - check if required
         $prop_img_file_errors[] = "At least one image is required.";
    }

    // If there were critical property image errors
    if (!empty($prop_img_file_errors) && empty($uploaded_filenames)) {
         // Clean up logo if it was uploaded
         if ($developer_logo_filename) { @unlink($logo_upload_dir . $developer_logo_filename); }
         if ($master_plan_filename) { @unlink($masterplan_upload_dir . $master_plan_filename); }
         $_SESSION['upload_errors'] = $prop_img_file_errors;
         header("Location: admin-upload.php?status=file_error");
         exit;
    }
    
    // --- Prepare Data for Database ---
    $images_json = json_encode($uploaded_filenames); // Store filenames as JSON array

    // --- Insert into Database ---
    $sql = "INSERT INTO properties (
                title, category, location, status, price, price_suffix, 
                beds, baths, size, yearBuilt, tag, description, amenities, 
                contactNumber, whatsappNumber, developer_logo, master_plan_file, images, created_at 
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind parameters (check types carefully!)
        $stmt->bind_param(
            "ssssdsiiisssssssss", // Adjust types if columns change (s=string, d=double/decimal, i=integer)
            $title,
            $category,
            $location,
            $status,
            $price, // Should be float/double if price has decimals, or string if always whole EGP
            $price_suffix,
            $beds,
            $baths,
            $size,
            $yearBuilt,
            $tag,
            $description,
            $amenities,
            $contactNumber,
            $whatsappNumber,
            $developer_logo_filename,
            $master_plan_filename,
            $images_json // Stored as JSON text
        );

        if ($stmt->execute()) {
            // Success! Redirect back to upload page with success message
            header("Location: admin-upload.php?status=success");
            exit;
        } else {
            // Database execution error
             // Log $stmt->error in development
             // Clean up uploaded files if DB insert fails? Maybe.
             if ($developer_logo_filename) { @unlink($logo_upload_dir . $developer_logo_filename); }
             if ($master_plan_filename) { @unlink($masterplan_upload_dir . $master_plan_filename); }
             foreach($uploaded_filenames as $file) {
                 @unlink($upload_dir . $file); 
             }
             header("Location: admin-upload.php?status=db_error");
             exit;
        }
        $stmt->close();
    } else {
        // Database prepare error
        // Log $conn->error in development
        // Clean up uploaded files
         if ($developer_logo_filename) { @unlink($logo_upload_dir . $developer_logo_filename); }
         if ($master_plan_filename) { @unlink($masterplan_upload_dir . $master_plan_filename); }
         foreach($uploaded_filenames as $file) {
             @unlink($upload_dir . $file); 
         }
        header("Location: admin-upload.php?status=db_error");
        exit;
    }

    $conn->close();

} else {
    // Not a POST request - redirect away or show error
    header("Location: admin-dashboard.php"); // Redirect to dashboard if accessed directly
    exit;
}
?> 