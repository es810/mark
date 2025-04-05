<?php
session_start(); // Start session

// Check if the admin is logged in, otherwise redirect to login page
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin-login.html?error=notloggedin");
    exit;
}
// No database needed here usually, just display the form. DB interaction happens in the handler.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upload Property | NEWMARK Admin</title>
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
          <li><a href="admin-upload.php" class="active">Upload Property</a></li>
          <li><a href="admin_logout.php">Logout (<?php echo htmlspecialchars($_SESSION["admin_username"]); ?>)</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Upload Form Section -->
  <section class="admin-upload-section">
    <div class="container">
      <h2>Upload New Property</h2>
      <p>Fill in the details below and upload property images.</p>

      <!-- Add message area for success/error feedback -->
      <div id="uploadMessage" style="display: none; padding: 15px; margin-bottom: 20px; border-radius: 5px;"></div>


      <form class="upload-form" id="uploadPropertyForm" action="admin_upload_handler.php" method="POST" enctype="multipart/form-data">
        
        <div class="form-row">
          <div class="form-group">
            <label for="title">Property Title *</label>
            <input type="text" id="title" name="title" placeholder="e.g., Luxurious Villa with Sea View" required>
          </div>
          <div class="form-group">
            <label for="category">Category *</label>
            <select id="category" name="category" required>
              <option value="">Select Category</option>
              <option value="primary">Primary</option>
              <option value="resale">Resale</option>
              <option value="rent">Rent</option>
            </select>
          </div>
        </div>

        <div class="form-row">
           <div class="form-group">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" placeholder="e.g., New Cairo, Zamalek">
          </div>
           <div class="form-group">
             <label for="status">Status</label>
             <select id="status" name="status">
               <option value="available" selected>Available</option>
               <option value="under_contract">Under Contract</option>
               <option value="sold">Sold</option>
               <option value="rented">Rented</option>
             </select>
           </div>
        </div>


        <div class="form-row price-row">
          <div class="form-group">
            <label for="price">Price (EGP) *</label>
            <input type="number" id="price" name="price" placeholder="e.g., 5000000" step="1000" required>
          </div>
          <div class="form-group">
            <label for="price_suffix">Price Suffix (Optional)</label>
            <input type="text" id="price_suffix" name="price_suffix" placeholder="e.g., /month, /sqm">
          </div>
        </div>

        <div class="form-row feature-row">
          <div class="form-group">
            <label for="beds">Bedrooms</label>
            <input type="number" id="beds" name="beds" min="0" placeholder="e.g., 4">
          </div>
          <div class="form-group">
            <label for="baths">Bathrooms</label>
            <input type="number" id="baths" name="baths" min="0" placeholder="e.g., 3">
          </div>
          <div class="form-group">
            <label for="size">Size (sq ft/sq m)</label>
            <input type="number" id="size" name="size" min="0" placeholder="e.g., 300">
          </div>
           <div class="form-group">
            <label for="yearBuilt">Year Built</label>
            <input type="number" id="yearBuilt" name="yearBuilt" min="1800" max="<?php echo date('Y'); ?>" placeholder="e.g., 2020">
          </div>
        </div>
        
         <div class="form-group">
            <label for="tag">Tag (Optional)</label>
            <input type="text" id="tag" name="tag" placeholder="e.g., Featured, New Listing, Hot Deal">
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="6" placeholder="Detailed description of the property..."></textarea>
        </div>

        <div class="form-group">
          <label for="amenities">Amenities (Optional, comma-separated)</label>
          <input type="text" id="amenities" name="amenities" placeholder="e.g., Swimming Pool, Garden, Gym, Security">
        </div>
        
         <div class="form-row contact-row">
            <div class="form-group">
                <label for="contactNumber">Contact Number (Optional)</label>
                <input type="tel" id="contactNumber" name="contactNumber" placeholder="e.g., +20 100 123 4567">
            </div>
            <div class="form-group">
                <label for="whatsappNumber">WhatsApp Number (Optional)</label>
                <input type="tel" id="whatsappNumber" name="whatsappNumber" placeholder="e.g., +20 100 123 4567">
            </div>
        </div>

        <!-- Add Developer Logo Upload -->
        <div class="form-group">
             <label for="developer_logo">Developer Logo (Optional)</label>
             <input type="file" id="developer_logo" name="developer_logo" accept="image/jpeg, image/png, image/gif, image/webp">
             <small>Upload the developer's logo (JPG, PNG, GIF, WEBP). Max 2MB.</small>
        </div>
        <!-- End Add Developer Logo Upload -->
        
        <!-- Add Master Plan Upload -->
        <div class="form-group">
             <label for="master_plan_file">Master Plan (Optional)</label>
             <input type="file" id="master_plan_file" name="master_plan_file" accept="image/jpeg, image/png, image/gif, image/webp, application/pdf">
             <small>Upload an image or PDF of the master plan. Max 5MB.</small>
        </div>
        <!-- End Add Master Plan Upload -->

        <div class="form-group">
          <label for="images">Property Images *</label>
          <input type="file" id="images" name="images[]" multiple accept="image/jpeg, image/png, image/gif, image/webp" required>
          <small>You can select multiple images. Max file size: 5MB each. Allowed types: JPG, PNG, GIF, WEBP.</small>
        </div>

        <div class="form-actions">
          <button type="submit" class="submit-button">Upload Property</button>
          <button type="reset" class="reset-button">Clear Form</button>
        </div>

      </form>
    </div>
  </section>

  <!-- Admin Footer -->
  <footer class="admin-footer">
    <div class="container">
      <p>&copy; 2025 NEWMARK Admin Panel</p>
    </div>
  </footer>
  
  <script>
    // Basic client-side validation or message handling if needed
    const urlParams = new URLSearchParams(window.location.search);
    const uploadStatus = urlParams.get('status');
    const messageDiv = document.getElementById('uploadMessage');

    if (uploadStatus && messageDiv) {
        let message = '';
        let bgColor = '';
        if (uploadStatus === 'success') {
            message = 'Property uploaded successfully!';
            bgColor = '#d4edda'; // Greenish background
            messageDiv.style.color = '#155724'; // Dark green text
        } else if (uploadStatus === 'error') {
            message = 'Error uploading property. Please check the details and try again.';
             bgColor = '#f8d7da'; // Reddish background
             messageDiv.style.color = '#721c24'; // Dark red text
        } else if (uploadStatus === 'file_error') {
             message = 'Error processing uploaded images. Check file size, type, or upload errors.';
             bgColor = '#f8d7da'; 
             messageDiv.style.color = '#721c24'; 
        } else if (uploadStatus === 'db_error') {
             message = 'Database error occurred while saving the property.';
              bgColor = '#f8d7da'; 
             messageDiv.style.color = '#721c24'; 
        }
        
        if(message){
             messageDiv.textContent = message;
             messageDiv.style.backgroundColor = bgColor;
             messageDiv.style.display = 'block';
             // Clear the URL parameter after displaying
             window.history.replaceState({}, document.title, window.location.pathname); 
        }
    }
    
    // Optional: Add more JS for file preview, etc.
  </script>

</body>
</html> 