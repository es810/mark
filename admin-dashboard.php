<?php
session_start(); // Start session

// Check if the admin is logged in, otherwise redirect to login page
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin-login.html?error=notloggedin");
    exit;
}

// Include database connection if needed for dashboard data (e.g., fetching properties)
require_once 'config/database.php'; 

// You can now access logged-in admin data via $_SESSION['admin_username'], $_SESSION['admin_id'] if needed

// Fetch counts for summary cards
$total_properties = 0;
$primary_properties = 0;
$resale_properties = 0;
$rent_properties = 0;

// Use prepared statements for category counts for better practice, though simple COUNT(*) is often safe
$sql_total = "SELECT COUNT(*) as total FROM properties";
$sql_primary = "SELECT COUNT(*) as total FROM properties WHERE category = 'primary'";
$sql_resale = "SELECT COUNT(*) as total FROM properties WHERE category = 'resale'";
$sql_rent = "SELECT COUNT(*) as total FROM properties WHERE category = 'rent'";

if ($result = $conn->query($sql_total)) {
    $total_properties = $result->fetch_assoc()['total'];
    $result->free();
}
if ($result = $conn->query($sql_primary)) {
    $primary_properties = $result->fetch_assoc()['total'];
    $result->free();
}
if ($result = $conn->query($sql_resale)) {
    $resale_properties = $result->fetch_assoc()['total'];
    $result->free();
}
if ($result = $conn->query($sql_rent)) {
    $rent_properties = $result->fetch_assoc()['total'];
    $result->free();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard | NEWMARK</title>
  <link rel="stylesheet" href="styles.css"> <!-- Assuming styles.css is in the root -->
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
          <li><a href="admin-dashboard.php" class="active">Dashboard</a></li>
          <li><a href="admin-upload.php">Upload Property</a></li> 
          <!-- Add link for managing users or settings if applicable -->
          <li><a href="admin_logout.php">Logout (<?php echo htmlspecialchars($_SESSION["admin_username"]); ?>)</a></li> 
        </ul>
      </nav>
    </div>
  </header>

  <!-- Admin Dashboard Content -->
  <section class="admin-dashboard-section">
    <div class="container">
      <h2>Welcome, <?php echo htmlspecialchars($_SESSION["admin_username"]); ?>!</h2>
      <p>Manage your property listings from here.</p>

      <div class="dashboard-summary">
        <div class="summary-card">
          <i class="fas fa-building"></i>
          <h3>Total Properties</h3>
          <p><?php echo $total_properties; ?></p>
          <a href="#propertiesList">View All</a> 
        </div>
        <div class="summary-card">
          <i class="fas fa-home"></i>
          <h3>Primary Properties</h3>
          <p><?php echo $primary_properties; ?></p>
          <a href="primary.html">View Primary</a>
        </div>
        <div class="summary-card">
          <i class="fas fa-sync-alt"></i>
          <h3>Resale Properties</h3>
          <p><?php echo $resale_properties; ?></p>
          <a href="resale.html">View Resale</a>
        </div>
        <div class="summary-card">
          <i class="fas fa-key"></i>
          <h3>Rental Properties</h3>
          <p><?php echo $rent_properties; ?></p>
          <a href="rent.html">View Rent</a>
        </div>
        <div class="summary-card">
           <i class="fas fa-plus-circle"></i>
           <h3>Add New Property</h3>
           <p>Upload details and images for a new listing.</p>
           <a href="admin-upload.php">Upload Now</a>
        </div>
        <!-- Add more summary cards if needed (e.g., messages, users) -->
      </div>

      <!-- Property Listings Table -->
      <div class="admin-table-container" id="propertiesList">
        <h3>Current Property Listings</h3>
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Category</th>
              <th>Location</th>
              <th>Price</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
              // Fetch properties to display in the table
              $list_sql = "SELECT id, title, category, location, price, price_suffix, status FROM properties ORDER BY created_at DESC LIMIT 50"; // Limit for performance
              $list_result = $conn->query($list_sql);

              if ($list_result && $list_result->num_rows > 0) {
                  while($property = $list_result->fetch_assoc()) {
                      // Format price
                      $display_price = 'EGP ' . number_format((float)$property['price'], 0);
                      if (!empty($property['price_suffix'])) {
                           $display_price .= $property['price_suffix'];
                      }

                      echo "<tr>";
                      echo "<td>" . htmlspecialchars($property['id']) . "</td>";
                      echo "<td>" . htmlspecialchars($property['title']) . "</td>";
                      echo "<td>" . htmlspecialchars(ucfirst($property['category'])) . "</td>";
                      echo "<td>" . htmlspecialchars($property['location']) . "</td>";
                      echo "<td>" . htmlspecialchars($display_price) . "</td>";
                      echo "<td><span class='status-" . htmlspecialchars(strtolower($property['status'])) . "'>" . htmlspecialchars(ucfirst($property['status'])) . "</span></td>";
                      echo "<td class='actions'>";
                      echo "<a href='admin-edit.php?id=" . htmlspecialchars($property['id']) . "' class='action-button edit'><i class='fas fa-edit'></i> Edit</a>";
                      // Add a delete button - CAUTION: Needs confirmation and secure handling
                      // echo "<a href='admin_delete_property.php?id=" . htmlspecialchars($property['id']) . "' class='action-button delete' onclick='return confirm(\"Are you sure you want to delete this property?\");'><i class='fas fa-trash'></i> Delete</a>"; // Needs admin_delete_property.php
                      echo "</td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='7'>No properties found.</td></tr>";
              }
              $conn->close(); // Close connection when done with DB operations on this page
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- Admin Footer -->
  <footer class="admin-footer">
    <div class="container">
      <p>&copy; 2025 NEWMARK Admin Panel</p>
    </div>
  </footer>

  <!-- Add any necessary JS for dashboard interactions here -->
  <script>
    // Example: Add styles for status badges maybe
    // Or interactions for delete confirmation if delete button is added
  </script>

</body>
</html> 