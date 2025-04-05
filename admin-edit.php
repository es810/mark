<?php
session_start(); // Start session

// Check if the admin is logged in, otherwise redirect to login page
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin-login.html?error=notloggedin");
    exit;
}

require_once 'config/database.php'; // Include DB connection

$property_id = null;
$property = null;
$error_message = '';

// 1. Get Property ID from URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $property_id = (int)$_GET['id'];
} else {
    $error_message = "Invalid or missing Property ID.";
    // Optional: Redirect if ID is invalid
    // header("location: admin-dashboard.php?error=invalid_id");
    // exit;
}

// 2. Fetch Property Data if ID is valid
if ($property_id && empty($error_message)) {
    $sql = "SELECT * FROM properties WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $property = $result->fetch_assoc();
            // Decode images JSON for potential display/management
            $property['images_array'] = json_decode($property['images'] ?? '[]', true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                 $property['images_array'] = []; // Default on error
            }
        } else {
            $error_message = "Property not found with ID: " . htmlspecialchars($property_id);
        }
        $stmt->close();
    } else {
        $error_message = "Database query failed: " . $conn->error;
    }
}

// $conn->close(); // Close connection only if no further DB interaction on this page (form display)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Property | NEWMARK Admin</title>
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    /* Basic styles for image preview/management */
    .current-images { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
    .current-images h4 { margin-bottom: 10px; }
    .image-preview-container { display: flex; flex-wrap: wrap; gap: 10px; }
    .image-preview-item { position: relative; border: 1px solid #ddd; padding: 5px; border-radius: 4px; }
    .image-preview-item img { max-width: 100px; max-height: 100px; display: block; }
    .image-preview-item .delete-image {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: rgba(220, 53, 69, 0.8); /* Red with transparency */
        color: white;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        line-height: 18px; /* Adjust for vertical centering */
        text-align: center;
        cursor: pointer;
        font-weight: bold;
    }
     .image-preview-item .delete-image:hover {
         background-color: rgba(200, 33, 49, 1);
     }
     .current-logo { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
     .current-logo img { max-height: 60px; border: 1px solid #ddd; padding: 5px; border-radius: 4px; background: #f8f9fa;}
     .current-logo span { margin-left: 10px; color: #6c757d; font-style: italic;}
     .current-file { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
     .current-file img.preview { max-height: 60px; border: 1px solid #ddd; padding: 5px; border-radius: 4px; background: #f8f9fa; vertical-align: middle;}
     .current-file a.file-link { vertical-align: middle; margin-left: 10px; }
     .current-file span.no-file { margin-left: 10px; color: #6c757d; font-style: italic;}
  </style>
</head>
<body>
  <!-- Admin Header -->
  <header class="admin-header">
     <div class="container header-container">
      <div class="logo">
        <img src="images/Logo.png" alt="NEWMARK Admin" class="logo-img">
        <span>Admin Panel</span>
      </div>
      <nav class="admin-nav">
        <ul>
          <li><a href="admin-dashboard.php">Dashboard</a></li>
          <li><a href="admin-upload.php">Upload Property</a></li>
          <li><a href="admin_logout.php">Logout (<?php echo htmlspecialchars($_SESSION["admin_username"]); ?>)</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Edit Form Section -->
  <section class="admin-upload-section"> <!-- Reuse upload section styles -->
    <div class="container">
      <h2>Edit Property Details</h2>

      <?php if (!empty($error_message)): ?>
          <div style="color: red; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
              Error: <?php echo htmlspecialchars($error_message); ?>
          </div>
      <?php endif; ?>

      <!-- Add message area for update success/error -->
      <div id="updateMessage" style="display: none; padding: 15px; margin-bottom: 20px; border-radius: 5px;"></div>

      <?php if ($property && empty($error_message)): // Only show form if property was found ?>
      <form class="upload-form" id="editPropertyForm" action="admin_edit_handler.php" method="POST" enctype="multipart/form-data">
        
        <!-- Important: Include Property ID -->
        <input type="hidden" name="property_id" value="<?php echo htmlspecialchars($property['id']); ?>">

        <!-- Add Display/Upload for Developer Logo -->
        <div class="form-group current-file">
             <label>Current Developer Logo</label>
             <div>
                 <?php if (!empty($property['developer_logo'])): ?>
                    <img src="uploads/logos/<?php echo htmlspecialchars($property['developer_logo']); ?>" alt="Current Logo" class="preview">
                    <input type="hidden" name="existing_developer_logo" value="<?php echo htmlspecialchars($property['developer_logo']); ?>"> <!-- Keep track of old logo -->
                 <?php else: ?>
                     <span class="no-file">No logo currently uploaded.</span>
                 <?php endif; ?>
             </div>
        </div>
         <div class="form-group">
            <label for="developer_logo">Upload New Developer Logo (Optional)</label>
            <input type="file" id="developer_logo" name="developer_logo" accept="image/jpeg, image/png, image/gif, image/webp">
            <small>Upload a new logo to replace the existing one. Max 2MB.</small>
        </div>
        <!-- End Developer Logo -->

        <!-- Master Plan Display/Upload -->
         <div class="form-group current-file">
              <label>Current Master Plan</label>
              <div>
                  <?php if (!empty($property['master_plan_file'])): 
                        $mp_path = 'uploads/masterplans/' . htmlspecialchars($property['master_plan_file']);
                        $mp_ext = strtolower(pathinfo($property['master_plan_file'], PATHINFO_EXTENSION));
                  ?>
                     <?php if (in_array($mp_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                         <img src="<?php echo $mp_path; ?>" alt="Master Plan Preview" class="preview">
                     <?php endif; ?>
                     <a href="<?php echo $mp_path; ?>" target="_blank" class="file-link"><?php echo htmlspecialchars($property['master_plan_file']); ?></a>
                     <input type="hidden" name="existing_master_plan_file" value="<?php echo htmlspecialchars($property['master_plan_file']); ?>"> 
                     <label style="margin-left:20px;"><input type="checkbox" name="delete_master_plan" value="1"> Mark for deletion</label> <!-- Added Delete Checkbox -->
                  <?php else: ?>
                      <span class="no-file">No master plan currently uploaded.</span>
                  <?php endif; ?>
              </div>
         </div>
          <div class="form-group">
             <label for="master_plan_file">Upload New Master Plan (Optional)</label>
             <input type="file" id="master_plan_file" name="master_plan_file" accept="image/jpeg, image/png, image/gif, image/webp, application/pdf">
             <small>Upload a new file (Image or PDF) to replace the existing one. Max 5MB.</small>
         </div>
        <!-- End Master Plan -->

        <div class="form-row">
          <div class="form-group">
            <label for="title">Property Title *</label>
            <input type="text" id="title" name="title" placeholder="e.g., Luxurious Villa with Sea View" required value="<?php echo htmlspecialchars($property['title'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label for="category">Category *</label>
            <select id="category" name="category" required>
              <option value="">Select Category</option>
              <option value="primary" <?php echo ($property['category'] ?? '') === 'primary' ? 'selected' : ''; ?>>Primary</option>
              <option value="resale" <?php echo ($property['category'] ?? '') === 'resale' ? 'selected' : ''; ?>>Resale</option>
              <option value="rent" <?php echo ($property['category'] ?? '') === 'rent' ? 'selected' : ''; ?>>Rent</option>
            </select>
          </div>
        </div>

        <div class="form-row">
           <div class="form-group">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" placeholder="e.g., New Cairo, Zamalek" value="<?php echo htmlspecialchars($property['location'] ?? ''); ?>">
          </div>
           <div class="form-group">
             <label for="status">Status</label>
             <select id="status" name="status">
               <option value="available" <?php echo ($property['status'] ?? '') === 'available' ? 'selected' : ''; ?>>Available</option>
               <option value="under_contract" <?php echo ($property['status'] ?? '') === 'under_contract' ? 'selected' : ''; ?>>Under Contract</option>
               <option value="sold" <?php echo ($property['status'] ?? '') === 'sold' ? 'selected' : ''; ?>>Sold</option>
               <option value="rented" <?php echo ($property['status'] ?? '') === 'rented' ? 'selected' : ''; ?>>Rented</option>
             </select>
           </div>
        </div>

        <div class="form-row price-row">
          <div class="form-group">
            <label for="price">Price (EGP) *</label>
            <input type="number" id="price" name="price" placeholder="e.g., 5000000" step="1000" required value="<?php echo htmlspecialchars($property['price'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label for="price_suffix">Price Suffix (Optional)</label>
            <input type="text" id="price_suffix" name="price_suffix" placeholder="e.g., /month, /sqm" value="<?php echo htmlspecialchars($property['price_suffix'] ?? ''); ?>">
          </div>
        </div>

        <div class="form-row feature-row">
          <div class="form-group">
            <label for="beds">Bedrooms</label>
            <input type="number" id="beds" name="beds" min="0" placeholder="e.g., 4" value="<?php echo htmlspecialchars($property['beds'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label for="baths">Bathrooms</label>
            <input type="number" id="baths" name="baths" min="0" placeholder="e.g., 3" value="<?php echo htmlspecialchars($property['baths'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label for="size">Size (sq ft/sq m)</label>
            <input type="number" id="size" name="size" min="0" placeholder="e.g., 300" value="<?php echo htmlspecialchars($property['size'] ?? ''); ?>">
          </div>
           <div class="form-group">
            <label for="yearBuilt">Year Built</label>
            <input type="number" id="yearBuilt" name="yearBuilt" min="1800" max="<?php echo date('Y'); ?>" placeholder="e.g., 2020" value="<?php echo htmlspecialchars($property['yearBuilt'] ?? ''); ?>">
          </div>
        </div>

         <div class="form-group">
            <label for="tag">Tag (Optional)</label>
            <input type="text" id="tag" name="tag" placeholder="e.g., Featured, New Listing, Hot Deal" value="<?php echo htmlspecialchars($property['tag'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="6" placeholder="Detailed description of the property..."><?php echo htmlspecialchars($property['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
          <label for="amenities">Amenities (Optional, comma-separated)</label>
          <input type="text" id="amenities" name="amenities" placeholder="e.g., Swimming Pool, Garden, Gym, Security" value="<?php echo htmlspecialchars($property['amenities'] ?? ''); ?>">
        </div>

         <div class="form-row contact-row">
            <div class="form-group">
                <label for="contactNumber">Contact Number (Optional)</label>
                <input type="tel" id="contactNumber" name="contactNumber" placeholder="e.g., +20 100 123 4567" value="<?php echo htmlspecialchars($property['contactNumber'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="whatsappNumber">WhatsApp Number (Optional)</label>
                <input type="tel" id="whatsappNumber" name="whatsappNumber" placeholder="e.g., +20 100 123 4567" value="<?php echo htmlspecialchars($property['whatsappNumber'] ?? ''); ?>">
            </div>
        </div>

        <!-- Image Management -->
        <div class="form-group current-images">
            <h4>Current Images</h4>
            <?php if (!empty($property['images_array'])): ?>
                <div class="image-preview-container" id="imagePreviewContainer">
                    <?php foreach ($property['images_array'] as $index => $image_filename): ?>
                        <div class="image-preview-item" data-filename="<?php echo htmlspecialchars($image_filename); ?>">
                            <img src="uploads/<?php echo htmlspecialchars($image_filename); ?>" alt="Property Image <?php echo $index + 1; ?>">
                            <button type="button" class="delete-image" title="Delete Image">&times;</button>
                            <!-- Hidden input to track images marked for deletion -->
                            <input type="hidden" name="delete_images[]" value="<?php echo htmlspecialchars($image_filename); ?>" disabled> 
                        </div>
                    <?php endforeach; ?>
                </div>
                <small>Click the red 'X' to mark an image for deletion upon saving changes.</small>
            <?php else: ?>
                <p>No current images uploaded.</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="new_images">Upload New Images (Optional)</label>
          <input type="file" id="new_images" name="new_images[]" multiple accept="image/jpeg, image/png, image/gif, image/webp">
          <small>Add new images here. Existing images marked with 'X' will be deleted. Max file size: 5MB each.</small>
        </div>

        <div class="form-actions">
          <button type="submit" class="submit-button">Save Changes</button>
          <a href="admin-dashboard.php" class="reset-button" style="text-decoration: none; text-align: center; line-height: initial; padding: 12px 25px;">Cancel</a> <!-- Changed reset to cancel link -->
        </div>

      </form>
      <?php endif; // End check for valid property ?>
    </div>
  </section>

  <!-- Admin Footer -->
  <footer class="admin-footer">
    <div class="container">
      <p>&copy; 2025 NEWMARK Admin Panel</p>
    </div>
  </footer>

  <script>
    // Handle displaying update messages passed via URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const updateStatus = urlParams.get('status');
    const messageDiv = document.getElementById('updateMessage');

    if (updateStatus && messageDiv) {
        let message = '';
        let bgColor = '';
        if (updateStatus === 'success') {
            message = 'Property updated successfully!';
            bgColor = '#d4edda'; 
            messageDiv.style.color = '#155724'; 
        } else if (updateStatus === 'error') {
             const msgParam = urlParams.get('msg'); // Get specific error message if provided
             message = `Error updating property. ${msgParam ? '(' + msgParam + ')' : 'Please check details and try again.'}`;
             bgColor = '#f8d7da';
             messageDiv.style.color = '#721c24';
        } else if (updateStatus === 'file_error') {
             message = 'Error processing uploaded images during update. Check file size, type, or upload errors.';
             bgColor = '#f8d7da'; 
             messageDiv.style.color = '#721c24'; 
        } else if (updateStatus === 'db_error') {
             message = 'Database error occurred while saving updates.';
              bgColor = '#f8d7da'; 
             messageDiv.style.color = '#721c24'; 
        }
        
        if(message){
             messageDiv.textContent = message;
             messageDiv.style.backgroundColor = bgColor;
             messageDiv.style.display = 'block';
             // Optionally clear the URL parameters after displaying
             // window.history.replaceState({}, document.title, `${window.location.pathname}?id=<?php echo $property_id ?? ''; ?>`); 
             // Be careful with clearing, might lose context if user refreshes.
        }
    }

    // Handle image deletion marking
    const imageContainer = document.getElementById('imagePreviewContainer');
    if (imageContainer) {
        imageContainer.addEventListener('click', function(event) {
            if (event.target.classList.contains('delete-image')) {
                const previewItem = event.target.closest('.image-preview-item');
                if (previewItem) {
                    const isMarked = previewItem.style.opacity === '0.5';
                    const hiddenInput = previewItem.querySelector('input[name="delete_images[]"]');
                    
                    if (isMarked) {
                        // Unmark for deletion
                        previewItem.style.opacity = '1';
                        event.target.style.backgroundColor = 'rgba(220, 53, 69, 0.8)'; // Restore original color
                        if (hiddenInput) hiddenInput.disabled = true; // Disable input
                    } else {
                        // Mark for deletion
                        previewItem.style.opacity = '0.5'; 
                        event.target.style.backgroundColor = 'rgba(150, 0, 0, 1)'; // Darken red
                        if (hiddenInput) hiddenInput.disabled = false; // Enable input so value gets submitted
                    }
                }
            }
        });
    }

  </script>

</body>
</html>
