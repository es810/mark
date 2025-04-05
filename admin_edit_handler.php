<?php
session_start(); // Start session

// Check if the admin is logged in
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    // Not logged in - handle appropriately (e.g., redirect)
    // Since this is processing a form, check if it's a POST request first
     if ($_SERVER["REQUEST_METHOD"] != "POST") {
         header("location: admin-login.html?error=notloggedin");
         exit;
     } else {
         // Handle POST attempt without session - redirect back to edit page with error
         $property_id = $_POST['property_id'] ?? 0; // Try to get ID for redirect
         header("Location: admin-edit.php?id={$property_id}&status=error&msg=auth");
         exit;
     }
}

require_once 'config/database.php'; // Include DB connection

// --- Configuration (same as upload handler) ---
$upload_dir = 'uploads/';
$logo_upload_dir = 'uploads/logos/'; // Logo subdirectory
$masterplan_upload_dir = 'uploads/masterplans/'; // Directory for master plans
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_file_size = 5 * 1024 * 1024; // 5 MB
$max_logo_size = 2 * 1024 * 1024; // 2 MB for logos
$max_masterplan_size = 5 * 1024 * 1024; // 5 MB for master plan

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Retrieve Form Data (Including Property ID) ---
    $property_id = $_POST['property_id'] ?? null;
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
    $amenities = $_POST['amenities'] ?? null;
    $contactNumber = $_POST['contactNumber'] ?? null;
    $whatsappNumber = $_POST['whatsappNumber'] ?? null;
    $images_to_delete = $_POST['delete_images'] ?? []; // Array of filenames marked for deletion
    $existing_developer_logo = $_POST['existing_developer_logo'] ?? null; // Get existing logo filename
    $existing_master_plan_file = $_POST['existing_master_plan_file'] ?? null; // Get existing master plan
    $delete_master_plan = isset($_POST['delete_master_plan']) ? true : false; // Check if delete checkbox is ticked

    // --- Basic Validation ---
    if (empty($property_id) || !is_numeric($property_id)) {
        // Cannot proceed without a valid ID
        header("Location: admin-dashboard.php?status=error&msg=invalid_id_provided");
        exit;
    }
     if (empty($title) || empty($category) || empty($price)) {
        header("Location: admin-edit.php?id={$property_id}&status=error&msg=required_fields");
        exit;
    }
    
    // --- Ensure Upload Directories Exist (redundant check is ok) ---
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
    if (!is_dir($logo_upload_dir)) { mkdir($logo_upload_dir, 0755, true); } 
    if (!is_dir($masterplan_upload_dir)) { mkdir($masterplan_upload_dir, 0755, true); }
    if (!is_writable($upload_dir) || !is_writable($logo_upload_dir) || !is_writable($masterplan_upload_dir)) {
         header("Location: admin-edit.php?id={$property_id}&status=error&msg=upload_dir_permission");
         exit;
    }

    // --- Handle Developer Logo Upload/Update ---
    $developer_logo_filename = $existing_developer_logo; // Start with existing logo
    $logo_file_error = null;
    $new_logo_uploaded = false; 

    if (isset($_FILES['developer_logo']) && $_FILES['developer_logo']['error'] === UPLOAD_ERR_OK) {
        $new_logo_uploaded = true; // Flag that a new logo was submitted
        // ... (validation: type, size using $max_logo_size) ...
         if (!in_array($_FILES['developer_logo']['type'], $allowed_types)) {
             $logo_file_error = "New logo: Invalid type.";
         } elseif ($_FILES['developer_logo']['size'] > $max_logo_size) {
             $logo_file_error = "New logo: Exceeds max size (2MB).";
         } else {
             // Generate unique filename
             $logo_file_extension = pathinfo($_FILES['developer_logo']['name'], PATHINFO_EXTENSION);
             $unique_logo_filename = uniqid('devlogo_', true) . '.' . strtolower($logo_file_extension);
             $logo_destination = $logo_upload_dir . $unique_logo_filename;

             if (move_uploaded_file($_FILES['developer_logo']['tmp_name'], $logo_destination)) {
                 // Successfully uploaded new logo, update filename
                 $developer_logo_filename = $unique_logo_filename; 
                 // Delete the old logo file if it existed
                 if ($existing_developer_logo && file_exists($logo_upload_dir . $existing_developer_logo)) {
                     @unlink($logo_upload_dir . $existing_developer_logo);
                 }
             } else {
                 $logo_file_error = "New logo: Failed to move.";
                 $new_logo_uploaded = false; // Upload failed
             }
         }
    } elseif (isset($_FILES['developer_logo']) && $_FILES['developer_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $logo_file_error = "New logo upload error code: " . $_FILES['developer_logo']['error'];
    }

    // If logo upload failed critically, redirect back
     if ($logo_file_error) {
        $_SESSION['upload_errors'] = [$logo_file_error];
        header("Location: admin-edit.php?id={$property_id}&status=file_error&src=logo");
        exit;
     }

    // --- Handle Master Plan Upload/Update/Delete ---
    $master_plan_filename = $existing_master_plan_file; // Start with existing
    $masterplan_file_error = null;
    $new_masterplan_uploaded = false; 

    // Check for deletion first
    if ($delete_master_plan && $master_plan_filename) {
        if (file_exists($masterplan_upload_dir . $master_plan_filename)) {
            @unlink($masterplan_upload_dir . $master_plan_filename);
        }
        $master_plan_filename = null; // Clear filename if deleted
    }

    // Check for new upload (only if not deleted, or if replacing)
    if (isset($_FILES['master_plan_file']) && $_FILES['master_plan_file']['error'] === UPLOAD_ERR_OK) {
        $new_masterplan_uploaded = true;
        // ... (validation: type using $allowed_types, size using $max_masterplan_size) ...
         if (!in_array($_FILES['master_plan_file']['type'], $allowed_types)) { $masterplan_file_error = "MP: Invalid type."; } 
         elseif ($_FILES['master_plan_file']['size'] > $max_masterplan_size) { $masterplan_file_error = "MP: Exceeds size."; } 
         else {
             $mp_ext = pathinfo($_FILES['master_plan_file']['name'], PATHINFO_EXTENSION);
             $unique_mp_filename = uniqid('mp_', true) . '.' . strtolower($mp_ext);
             $mp_dest = $masterplan_upload_dir . $unique_mp_filename;

             if (move_uploaded_file($_FILES['master_plan_file']['tmp_name'], $mp_dest)) {
                 // Delete old file if replacing
                 if ($existing_master_plan_file && $existing_master_plan_file !== $master_plan_filename) { // Check if different from a previously deleted one
                      if(file_exists($masterplan_upload_dir . $existing_master_plan_file)){
                           @unlink($masterplan_upload_dir . $existing_master_plan_file);
                      }
                 }
                  // Update filename
                 $master_plan_filename = $unique_mp_filename;
             } else {
                 $masterplan_file_error = "MP: Failed move.";
                 $new_masterplan_uploaded = false;
             }
         }
    } elseif (isset($_FILES['master_plan_file']) && $_FILES['master_plan_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $masterplan_file_error = "MP error: " . $_FILES['master_plan_file']['error'];
    }

     // If master plan upload failed critically, cleanup new logo and redirect
    if ($masterplan_file_error) {
        if ($new_logo_uploaded && $developer_logo_filename) { @unlink($logo_upload_dir . $developer_logo_filename); } 
        $_SESSION['upload_errors'] = [$masterplan_file_error];
        header("Location: admin-edit.php?id={$property_id}&status=file_error&src=mp");
        exit;
    }

    // --- Fetch Current Images (Needed for comparison and deletion) ---
    $current_images_json = null;
    $current_images_array = [];
    $stmt_fetch = $conn->prepare("SELECT images FROM properties WHERE id = ?");
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $property_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $current_images_json = $result_fetch->fetch_assoc()['images'];
            $current_images_array = json_decode($current_images_json ?? '[]', true);
             if (json_last_error() !== JSON_ERROR_NONE) { $current_images_array = []; }
        } else {
            // Property not found during update - critical error
            header("Location: admin-dashboard.php?status=error&msg=property_not_found_on_update");
            exit;
        }
        $stmt_fetch->close();
    } else {
         header("Location: admin-edit.php?id={$property_id}&status=db_error&msg=fetch_failed");
         exit;
    }


    // --- Handle Image Deletions ---
    $final_images_array = $current_images_array; // Start with current images
    $files_actually_deleted = [];
    if (!empty($images_to_delete)) {
        foreach ($images_to_delete as $filename_to_delete) {
            $key = array_search($filename_to_delete, $final_images_array);
            if ($key !== false) {
                // Remove from array
                unset($final_images_array[$key]);
                
                // Delete the actual file from server
                $file_path = $upload_dir . $filename_to_delete;
                if (file_exists($file_path)) {
                    if (@unlink($file_path)) { // Attempt to delete, suppress errors slightly
                         $files_actually_deleted[] = $filename_to_delete; // Track success
                    } else {
                        // Log error: Failed to delete file $filename_to_delete
                        // Might want to alert admin but continue DB update
                    }
                }
            }
        }
        // Re-index array after unsetting
        $final_images_array = array_values($final_images_array);
    }


    // --- Handle New File Uploads ---
    $newly_uploaded_filenames = [];
    $file_errors = [];

    if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
        // Check upload dir exists and is writable (might be redundant but safe)
         if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
         if (!is_writable($upload_dir)) {
             header("Location: admin-edit.php?id={$property_id}&status=error&msg=upload_dir_permission");
             exit;
         }
         
        $file_count = count($_FILES['new_images']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $file_name = $_FILES['new_images']['name'][$i];
            $file_tmp_name = $_FILES['new_images']['tmp_name'][$i];
            $file_size = $_FILES['new_images']['size'][$i];
            $file_error = $_FILES['new_images']['error'][$i];
            $file_type = $_FILES['new_images']['type'][$i];

            if ($file_error === UPLOAD_ERR_OK) {
                if (!in_array($file_type, $allowed_types)) {
                    $file_errors[] = "File '$file_name': Invalid type."; continue;
                }
                if ($file_size > $max_file_size) {
                    $file_errors[] = "File '$file_name': Exceeds max size."; continue;
                }
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_filename = uniqid('prop_', true) . '.' . strtolower($file_extension);
                $destination = $upload_dir . $unique_filename;

                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $newly_uploaded_filenames[] = $unique_filename;
                } else {
                    $file_errors[] = "File '$file_name': Failed to move.";
                }
            } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
                 $file_errors[] = "File '$file_name': Upload error code: $file_error.";
            }
        }
    }

    // If there were file upload errors, redirect back
    if (!empty($file_errors)) {
         // Clean up any files that *were* successfully uploaded in this batch
         foreach ($newly_uploaded_filenames as $file) { @unlink($upload_dir . $file); }
         // Redirect back with error
         $_SESSION['upload_errors'] = $file_errors; // Optional: Store specific errors in session
         header("Location: admin-edit.php?id={$property_id}&status=file_error");
         exit;
    }

    // Combine remaining old images with newly uploaded ones
    $final_images_array = array_merge($final_images_array, $newly_uploaded_filenames);
    $final_images_json = json_encode($final_images_array);


    // --- Update Database ---
    $sql_update = "UPDATE properties SET 
                        title = ?, category = ?, location = ?, status = ?, price = ?, price_suffix = ?, 
                        beds = ?, baths = ?, size = ?, yearBuilt = ?, tag = ?, description = ?, amenities = ?, 
                        contactNumber = ?, whatsappNumber = ?, 
                        developer_logo = ?, master_plan_file = ?, images = ? 
                   WHERE id = ?";

    $stmt_update = $conn->prepare($sql_update);

    if ($stmt_update) {
        // Bind parameters (ensure types match INSERT handler: ssssdsiiisssssssi)
        $stmt_update->bind_param(
            "ssssdsiiisssssssi", 
            $title, $category, $location, $status, $price, $price_suffix,
            $beds, $baths, $size, $yearBuilt, $tag, $description, $amenities,
            $contactNumber, $whatsappNumber, 
            $developer_logo_filename, 
            $master_plan_filename, // Bind master plan filename (could be new, existing, or null)
            $final_images_json, // Updated image list
            $property_id // WHERE clause ID
        );

        if ($stmt_update->execute()) {
            // Success! Redirect back to edit page with success message
            header("Location: admin-edit.php?id={$property_id}&status=success");
            exit;
        } else {
            // Database execution error
            // Log $stmt_update->error in development
            // Clean up newly uploaded files since DB update failed
             foreach ($newly_uploaded_filenames as $file) { @unlink($upload_dir . $file); }
             if ($new_logo_uploaded && $developer_logo_filename) { @unlink($logo_upload_dir . $developer_logo_filename); }
             if ($new_masterplan_uploaded && $master_plan_filename) { @unlink($masterplan_upload_dir . $master_plan_filename); }
             header("Location: admin-edit.php?id={$property_id}&status=db_error&msg=execute_failed");
             exit;
        }
        $stmt_update->close();
    } else {
        // Database prepare error
        // Log $conn->error in development
        // Clean up newly uploaded files
        foreach ($newly_uploaded_filenames as $file) { @unlink($upload_dir . $file); }
        if ($new_logo_uploaded && $developer_logo_filename) { @unlink($logo_upload_dir . $developer_logo_filename); }
        if ($new_masterplan_uploaded && $master_plan_filename) { @unlink($masterplan_upload_dir . $master_plan_filename); }
        header("Location: admin-edit.php?id={$property_id}&status=db_error&msg=prepare_failed");
        exit;
    }

    $conn->close();

} else {
    // Not a POST request - redirect away
    header("Location: admin-dashboard.php");
    exit;
}
?> 