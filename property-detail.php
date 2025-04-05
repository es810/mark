<?php
// No session needed usually for public view, unless you add user-specific features
require_once 'config/database.php'; // Include DB connection

$property_id = null;
$property = null;
$error_message = '';
$page_title = 'Property Details | NEWMARK'; // Default title

// 1. Get Property ID from URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $property_id = (int)$_GET['id'];
} else {
    $error_message = "Invalid or missing Property ID.";
    // Optional: Redirect if ID is invalid
    // header("location: index.html?error=invalid_id"); // Redirect to home or appropriate page
    // exit;
}

// 2. Fetch Property Data if ID is valid
if ($property_id && empty($error_message)) {
    // Select all relevant columns
    $sql = "SELECT * FROM properties WHERE id = ?"; 
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $property = $result->fetch_assoc();
            // Decode images JSON for gallery
            $property['images_array'] = json_decode($property['images'] ?? '[]', true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                 $property['images_array'] = []; // Default on error
            }
            // Format price for display
            $property['display_price'] = 'EGP ' . number_format((float)($property['price'] ?? 0), 0);
            if (!empty($property['price_suffix'])) {
                $property['display_price'] .= $property['price_suffix'];
            }
            // Set dynamic page title
            $page_title = htmlspecialchars($property['title'] ?? 'Property Details') . ' | NEWMARK';

        } else {
            $error_message = "Property not found with ID: " . htmlspecialchars($property_id);
        }
        $stmt->close();
    } else {
        $error_message = "Database query failed: " . $conn->error;
    }
    $conn->close(); // Close connection after fetching
} else {
     if(empty($error_message)) { // Only close if not already closed by DB query failure
          $conn->close();
     }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $page_title; ?></title> 
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <!-- Add new styles here or link a separate detail page CSS -->
  <style>
    /* --- Styles for New Layout --- */
    .detail-header { display: flex; align-items: center; gap: 30px; margin-bottom: 30px; flex-wrap: wrap; padding-bottom: 20px; border-bottom: 1px solid #eee;}
    .detail-header .developer-logo img { max-width: 80px; max-height: 80px; border-radius: 50%; object-fit: contain; border: 1px solid #eee; }
    .detail-header .title-price-info { flex-grow: 1; }
    .detail-header .title-tag { display: flex; align-items: center; gap: 15px; margin-bottom: 5px; }
    .detail-header h1 { margin: 0; font-size: 1.8em; /* Adjust */ color: #333; }
    .detail-header .compound-tag { background-color: #eee; color: #555; padding: 4px 10px; border-radius: 15px; font-size: 0.85em; font-weight: 600; }
    .detail-header .price-info { display: flex; gap: 40px; flex-wrap: wrap; margin-top: 10px; }
    .detail-header .price-item h4 { margin: 0 0 5px 0; font-size: 0.9em; color: #777; font-weight: normal; }
    .detail-header .price-item p { margin: 0; font-size: 1.4em; font-weight: 700; color: #333; }
    .detail-header .contact-buttons { display: flex; gap: 10px; margin-left: auto; /* Pushes buttons right */ align-self: flex-start; }
    .detail-header .contact-buttons .btn { padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: 1px solid transparent; }
    .detail-header .contact-buttons .btn-call { background-color: #e7f1ff; color: #0056b3; border-color: #cce0ff; }
    .detail-header .contact-buttons .btn-whatsapp { background-color: #25d366; color: white; }
    .detail-header .contact-buttons .btn i { font-size: 1.1em; }

    .detail-actions-section h3 { font-size: 1.3em; margin-bottom: 15px; color: #444; }
    .detail-actions-grid { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 30px; }
    .detail-action-item { flex: 1; min-width: 120px; text-align: center; padding: 15px; border: 1px solid #eee; border-radius: 8px; background-color: #fff; cursor: pointer; transition: box-shadow 0.2s ease, transform 0.2s ease; text-decoration: none; color: inherit;}
    .detail-action-item:hover { box-shadow: 0 2px 5px rgba(0,0,0,0.1); transform: translateY(-2px); }
    .detail-action-item i { font-size: 2em; color: #007bff; margin-bottom: 10px; display: block;}
    .detail-action-item span { font-weight: 600; font-size: 0.95em; color: #555; }
    .detail-action-item .new-badge { position: absolute; top: 5px; right: 5px; background-color: #fd7e14; color: white; font-size: 0.7em; padding: 2px 5px; border-radius: 3px; } /* Style positioning needed */

    .payment-plans-section h3 { font-size: 1.3em; margin-bottom: 15px; color: #444; }
    .payment-plan { background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; /* Add more styling */ }
    
    /* --- Existing Gallery Styles (Keep or adapt) --- */
    .property-gallery { margin-bottom: 30px; }
    .gallery-main-image img { width: 100%; max-height: 500px; object-fit: cover; border-radius: 8px; margin-bottom: 15px; border: 1px solid #eee; }
    .gallery-thumbnails { display: flex; flex-wrap: wrap; gap: 10px; }
    .gallery-thumbnails img { width: 100px; height: 75px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 2px solid transparent; transition: border-color 0.3s ease; }
    .gallery-thumbnails img.active, .gallery-thumbnails img:hover { border-color: #007bff; }
    
     /* --- Existing Info/Desc/Amenity Styles (Keep or adapt) --- */
     .property-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px 25px; margin-bottom: 25px; padding: 20px; background-color: #f8f9fa; border-radius: 8px; }
     .info-item { display: flex; align-items: center; gap: 8px; }
     .info-item i { color: #007bff; width: 20px; text-align: center; }
     .info-item span { font-weight: 600; }
     .property-description-section h3, .property-amenities-section h3, .property-contact-section h3 { margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
     .amenities-list { list-style: none; padding: 0; display: flex; flex-wrap: wrap; gap: 10px; }
     .amenities-list li { background-color: #e9ecef; padding: 5px 12px; border-radius: 15px; font-size: 0.9em; }
      /* Contact section styles can be removed if using buttons in header */
     /* .contact-details p { margin-bottom: 10px; } */
     /* .contact-details i { margin-right: 8px; color: #28a745; } */
     
     /* Responsive adjustments if needed */
     @media (max-width: 768px) {
         .detail-header { gap: 15px; }
         .detail-header .contact-buttons { width: 100%; margin-left: 0; justify-content: center; margin-top: 15px;}
     }

    .detail-action-item.disabled { cursor: not-allowed; opacity: 0.6; pointer-events: none;} /* Style for disabled action items */

  </style>
</head>
<body>
  <!-- Header / Navigation -->
  <header>
    <div class="container header-container">
      <div class="logo">
        <img src="images/Logo.png" alt="NEWMARK" class="logo-img">
      </div>
      <nav>
        <ul class="nav-links">
          <li><a href="index.html">Home</a></li>
          <li><a href="primary.html">Primary</a></li>
          <li><a href="resale.html">Resale</a></li>
          <li><a href="rent.html">Rent</a></li>
          <li><a href="#">About Us</a></li>
          <li><a href="#">Contact</a></li>
          <li><a href="login.html">Login</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Property Detail Content -->
  <section class="property-detail-section" style="padding: 40px 0;">
    <div class="container">

      <?php if (!empty($error_message)): ?>
          <div class="error-message" style="color: red; text-align: center; margin-bottom: 30px;">
              Error: <?php echo htmlspecialchars($error_message); ?> <br>
              <a href="index.html">Return to Home</a>
          </div>
      <?php elseif ($property): // Display content only if property was found ?>

      <!-- NEW: Detail Header Section -->
      <div class="detail-header">
          <div class="developer-logo">
              <!-- Placeholder for developer logo -->
              <img src="images/default-logo.png" alt="Developer Logo"> 
          </div>
          <div class="title-price-info">
              <div class="title-tag">
                  <h1><?php echo htmlspecialchars($property['title'] ?? 'Property Title'); ?></h1>
                   <!-- Assuming 'tag' can be used like 'Compound', adjust if needed -->
                  <?php if (!empty($property['tag'])): ?>
                      <span class="compound-tag"><?php echo htmlspecialchars($property['tag']); ?></span>
                  <?php endif; ?>
              </div>
              <div class="price-info">
                  <div class="price-item">
                      <!-- Placeholder - needs DB field -->
                      <h4>Developer Start Price</h4> 
                      <p>EGP N/A</p> 
                  </div>
                  <div class="price-item">
                      <!-- Use existing price, assume it's Resale Price -->
                      <h4><?php echo !empty($property['price_suffix']) ? 'Rental Price' : 'Resale Start Price'; ?></h4> 
                      <p><?php echo htmlspecialchars($property['display_price'] ?? 'N/A'); ?></p>
                  </div>
              </div>
          </div>
          <div class="contact-buttons">
              <?php if (!empty($property['contactNumber'])): ?>
                <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $property['contactNumber'])); ?>" class="btn btn-call"><i class="fas fa-phone-alt"></i> Call Us</a>
              <?php endif; ?>
              <?php if (!empty($property['whatsappNumber'])): ?>
                <a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/[^\d+]/', '', $property['whatsappNumber'])); ?>" target="_blank" class="btn btn-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp</a>
              <?php endif; ?>
          </div>
      </div>
      
      <!-- NEW: Details Actions Section -->
       <div class="detail-actions-section">
           <h3>Details</h3>
           <div class="detail-actions-grid">
               <a href="#propertyGallerySection" class="detail-action-item"> <!-- Link to gallery section below -->
                   <i class="fas fa-images"></i>
                   <span>Gallery</span>
               </a>
               
               <!-- Master Plan Link/Placeholder -->
               <?php 
                   // Check if master plan filename exists in DB AND the file exists on server
                   $master_plan_exists = false;
                   $master_plan_path = '';
                   if (!empty($property['master_plan_file'])) {
                       $potential_path = 'uploads/masterplans/' . $property['master_plan_file'];
                       if (file_exists($potential_path)) {
                           $master_plan_exists = true;
                           $master_plan_path = htmlspecialchars($potential_path);
                       }
                   }
               ?>
               <?php if ($master_plan_exists): ?>
                   <a href="<?php echo $master_plan_path; ?>" target="_blank" class="detail-action-item">
                       <i class="fas fa-drafting-compass"></i>
                       <span>Master Plan</span>
                   </a>
               <?php else: ?>
                   <div class="detail-action-item disabled"> <!-- Applies disabled style -->
                       <i class="fas fa-drafting-compass"></i>
                       <span>Master Plan</span>
                   </div>
               <?php endif; ?>
               
               <!-- View on Map Placeholder -->
               <div class="detail-action-item disabled"> 
                   <i class="fas fa-map-marked-alt"></i>
                   <span>View on Map</span>
               </div>
           </div>
       </div>
       
      <!-- NEW: Payment Plans Section -->
      <div class="payment-plans-section">
          <h3>Payment Plans</h3>
          <!-- Placeholder - Needs data from DB -->
          <div class="payment-plan">
              <p>Payment plan information not available.</p> 
          </div>
      </div>
      
      <hr style="margin: 40px 0; border: none; border-top: 1px solid #eee;">

      <!-- EXISTING: Image Gallery (Linked from Details Action) -->
      <div class="property-gallery" id="propertyGallerySection">
        <h3>Gallery</h3>
        <div class="gallery-main-image">
          <img id="mainImage" src="<?php echo (!empty($property['images_array'])) ? 'uploads/' . htmlspecialchars($property['images_array'][0]) : 'images/default_property.jpg'; ?>" alt="<?php echo htmlspecialchars($property['title'] ?? ''); ?>">
        </div>
        <?php if (count($property['images_array'] ?? []) > 1): ?>
          <div class="gallery-thumbnails" id="galleryThumbnails">
            <?php foreach ($property['images_array'] as $index => $image_filename): ?>
              <img src="uploads/<?php echo htmlspecialchars($image_filename); ?>" alt="Thumbnail <?php echo $index + 1; ?>" class="<?php echo ($index === 0) ? 'active' : ''; ?>" onclick="changeMainImage('uploads/<?php echo htmlspecialchars($image_filename); ?>', this)">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- EXISTING: Core Info Grid -->
      <div class="property-info-grid">
          <?php if (isset($property['beds'])): ?><div class="info-item"><i class="fas fa-bed"></i> <span><?php echo htmlspecialchars($property['beds']); ?> Beds</span></div><?php endif; ?>
          <?php if (isset($property['baths'])): ?><div class="info-item"><i class="fas fa-bath"></i> <span><?php echo htmlspecialchars($property['baths']); ?> Baths</span></div><?php endif; ?>
          <?php if (isset($property['size'])): ?><div class="info-item"><i class="fas fa-ruler-combined"></i> <span><?php echo htmlspecialchars(number_format((float)$property['size'])) . ' sq ft'; ?></span></div><?php endif; ?>
          <?php if (isset($property['category'])): ?><div class="info-item"><i class="fas fa-tag"></i> <span><?php echo htmlspecialchars(ucfirst($property['category'])); ?></span></div><?php endif; ?>
          <?php if (isset($property['yearBuilt'])): ?><div class="info-item"><i class="fas fa-calendar-alt"></i> <span>Built <?php echo htmlspecialchars($property['yearBuilt']); ?></span></div><?php endif; ?>
          <?php if (isset($property['status'])): ?><div class="info-item"><i class="fas fa-info-circle"></i> <span class='status-badge status-<?php echo htmlspecialchars(strtolower($property['status'])); ?>'><?php echo htmlspecialchars(ucfirst($property['status'])); ?></span></div><?php endif; ?>
           <?php if (isset($property['location'])): ?><div class="info-item"><i class="fas fa-map-marker-alt"></i> <span><?php echo htmlspecialchars($property['location']); ?></span></div><?php endif; ?>
      </div>

      <!-- EXISTING: Description -->
      <?php if (!empty($property['description'])): ?>
      <div class="property-description-section">
          <h3>Description</h3>
          <p><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
      </div>
      <?php endif; ?>

      <!-- EXISTING: Amenities -->
      <?php if (!empty($property['amenities'])): 
          $amenities_array = array_map('trim', explode(',', $property['amenities']));
          $amenities_array = array_filter($amenities_array); 
      ?>
      <?php if (!empty($amenities_array)): ?>
      <div class="property-amenities-section">
          <h3>Amenities</h3>
          <ul class="amenities-list">
              <?php foreach ($amenities_array as $amenity): ?><li><?php echo htmlspecialchars($amenity); ?></li><?php endforeach; ?>
          </ul>
      </div>
      <?php endif; endif; ?>
       
       <!-- NOTE: Contact info is now in buttons at the top -->


      <?php else: ?>
           <p style="text-align: center;">Property details could not be loaded.</p>
      <?php endif; ?>

    </div>
  </section>

  <!-- Footer -->
  <footer>
    <div class="container">
      <p>&copy; 2025 NEWMARK. All rights reserved.</p>
    </div>
  </footer>

  <!-- Simple JS for gallery thumbnail clicks -->
  <script>
      function changeMainImage(newSrc, clickedThumbnail) {
          document.getElementById('mainImage').src = newSrc;
          const thumbnails = document.querySelectorAll('#galleryThumbnails img');
          thumbnails.forEach(thumb => thumb.classList.remove('active'));
          clickedThumbnail.classList.add('active');
      }
  </script>
</body>
</html> 