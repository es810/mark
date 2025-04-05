<?php
header('Content-Type: application/json');
require_once '../config/database.php'; // Adjust path as needed

// Get category from query parameter
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Validate category
$allowed_categories = ['primary', 'resale', 'rent'];
if (!in_array($category, $allowed_categories)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid or missing category specified.']);
    exit;
}

$properties = [];

// Prepare SQL statement to prevent SQL injection
$sql = "SELECT 
            id, title, location, price, price_suffix, beds, baths, size, tag, description, images, yearBuilt, status, amenities, contactNumber, whatsappNumber, category 
        FROM 
            properties 
        WHERE 
            category = ? AND (status = 'available' OR status = 'under_contract') -- Only show available/under contract publicly
        ORDER BY 
            created_at DESC"; // Order by most recent

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Combine price and suffix
            $display_price = 'EGP ' . number_format((float)$row['price'], 0);
            if (!empty($row['price_suffix'])) {
                $display_price .= $row['price_suffix'];
            }
            $row['price'] = $display_price; // Overwrite price with formatted string
            unset($row['price_suffix']); // Don't need suffix anymore

            // Decode JSON fields (images)
            // Store amenities as is (text) for now, frontend doesn't use it in cards
            $decoded_images = json_decode($row['images'] ?? '[]', true); // Decode original 'images' JSON
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) {
                 // Create the singular 'image' key with the path to the first image
                 $row['image'] = 'uploads/' . $decoded_images[0]; 
            } else {
                 // Provide a default image path if no images or JSON error
                 $row['image'] = 'images/default_property.jpg'; 
            }
            
            // Remove the original 'images' field (JSON string/array) as frontend expects 'image'
            unset($row['images']); 

            $properties[] = $row;
        }
    }
    $stmt->close();
} else {
    // SQL Prepare Error
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $conn->error]);
    $conn->close();
    exit;
}

$conn->close();

echo json_encode($properties); // Return properties array (even if empty)

?> 