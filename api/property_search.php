<?php
header('Content-Type: application/json');
require_once '../config/database.php'; // Adjust path as needed

$params = []; // Parameters for prepared statement
$types = "";  // Type string for bind_param
$where_clauses = []; // Array to hold individual WHERE conditions

// --- Collect and Validate Search Parameters ---

// Category (Required for basic filtering)
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category = $_GET['category'];
    // Basic validation - could expand this
    $allowed_categories = ['primary', 'resale', 'rent'];
    if (in_array($category, $allowed_categories)) {
        $where_clauses[] = "category = ?";
        $params[] = $category;
        $types .= "s";
    } else {
        // Invalid category provided in search, maybe return error or ignore?
        // For now, we'll ignore it, but a required category might be better.
    }
} else {
     // If no category is specified in search, maybe default or return error?
     // For now, let's allow searching across all categories if none is specified.
     // If category MUST be specified, uncomment the error below:
     // http_response_code(400);
     // echo json_encode(['success' => false, 'message' => 'Category is required for search.']);
     // exit;
}

// Location (Exact match for simplicity, could use LIKE for partial)
if (isset($_GET['location']) && !empty($_GET['location'])) {
    $where_clauses[] = "location = ?";
    $params[] = $_GET['location'];
    $types .= "s";
}

// Property Type (Exact match for simplicity)
if (isset($_GET['propertyType']) && !empty($_GET['propertyType'])) {
    // Assuming 'propertyType' corresponds to a column, e.g., 'type' or similar
    // If not, adjust the column name. Let's assume it maps to 'title' or needs adjustment.
    // For now, let's pretend there's a 'type' column for the example.
    // $where_clauses[] = "type = ?"; // Replace 'type' with actual column if needed
    // $params[] = $_GET['propertyType'];
    // $types .= "s";
    // Since there is no 'type' column in the schema, we will ignore this for now.
    // A search on title might be:
    // $where_clauses[] = "title LIKE ?";
    // $params[] = '%' . $_GET['propertyType'] . '%';
    // $types .= "s";
}


// Price Range
if (isset($_GET['minPrice']) && is_numeric($_GET['minPrice'])) {
    $where_clauses[] = "price >= ?";
    $params[] = (float)$_GET['minPrice'];
    $types .= "d"; // Use 'd' for decimal/double
}
if (isset($_GET['maxPrice']) && is_numeric($_GET['maxPrice'])) {
    $where_clauses[] = "price <= ?";
    $params[] = (float)$_GET['maxPrice'];
    $types .= "d";
}

// Bedrooms (Minimum)
if (isset($_GET['bedrooms']) && is_numeric($_GET['bedrooms']) && $_GET['bedrooms'] > 0) {
    $where_clauses[] = "beds >= ?";
    $params[] = (int)$_GET['bedrooms'];
    $types .= "i";
}

// Size Range
if (isset($_GET['minSize']) && is_numeric($_GET['minSize'])) {
    $where_clauses[] = "size >= ?";
    $params[] = (int)$_GET['minSize'];
    $types .= "i";
}
if (isset($_GET['maxSize']) && is_numeric($_GET['maxSize'])) {
    $where_clauses[] = "size <= ?";
    $params[] = (int)$_GET['maxSize'];
    $types .= "i";
}

// Amenities (Assuming comma-separated string in GET and stored as TEXT/JSON in DB)
// This requires a more complex check, e.g., FIND_IN_SET or JSON_CONTAINS if applicable,
// or multiple LIKE clauses if amenities are stored comma-separated in the DB.
// Simple Example: Check if the DB amenities field contains ALL requested amenities.
if (isset($_GET['amenities']) && !empty($_GET['amenities'])) {
    $requested_amenities = explode(',', $_GET['amenities']);
    foreach ($requested_amenities as $amenity) {
        $trimmed_amenity = trim($amenity);
        if (!empty($trimmed_amenity)) {
            // Use LIKE for basic check assuming amenities are stored like "Pool, Garden, Gym"
            // This is not very robust. Storing amenities relationally or as JSON is better.
            $where_clauses[] = "amenities LIKE ?";
            $params[] = '%' . $trimmed_amenity . '%';
            $types .= "s";
        }
    }
}

// --- Build and Execute SQL Query ---

$properties = [];
$base_sql = "SELECT
                id, title, location, price, price_suffix, beds, baths, size, tag, description, images, yearBuilt, status, amenities, contactNumber, whatsappNumber
             FROM
                properties";

// Add public status filter
$where_clauses[] = "(status = 'available' OR status = 'under_contract')";

// Construct WHERE part
if (!empty($where_clauses)) {
    $sql = $base_sql . " WHERE " . implode(" AND ", $where_clauses);
} else {
    $sql = $base_sql; // Should not happen if category or status is always applied
}

// Add ordering
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Bind parameters if any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params); // Use splat operator (...)
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format price (same as in get_properties_by_category.php)
            $display_price = 'EGP ' . number_format((float)$row['price'], 0);
            if (!empty($row['price_suffix'])) {
                $display_price .= $row['price_suffix'];
            }
            $row['price'] = $display_price;
            unset($row['price_suffix']);

            // Decode images JSON (assuming it's stored as JSON array)
            // We also need to select just the first image for the card display in primary.html JS
            $decoded_images = json_decode($row['images'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) {
                 // Use only the first image for the list view card
                 $row['image'] = 'uploads/' . $decoded_images[0]; // Adjust path if 'uploads/' is not correct
                 // Keep the full array if needed for detail view? Or remove it?
                 // unset($row['images']); // Remove original JSON string if not needed
            } else {
                 $row['image'] = 'images/default_property.jpg'; // Provide a default image path
                 // unset($row['images']);
            }
            // The JS in primary.html expects property.image (singular)

            $properties[] = $row;
        }
    }
    $stmt->close();
} else {
    // SQL Prepare Error
    http_response_code(500);
    // Provide more specific error in development, generic in production
    $error_message = 'Database query error.';
    // If you want to see MySQL errors during development: $error_message = 'Database query error: ' . $conn->error;
    echo json_encode(['success' => false, 'message' => $error_message]);
    $conn->close();
    exit;
}

$conn->close();

echo json_encode($properties); // Return properties array (even if empty)

?> 