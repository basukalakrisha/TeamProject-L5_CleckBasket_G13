<?php
require_once 'php_logic/connect.php'; // Connect to Oracle DB

/**
 * This script provides a way to retrieve product images from the database
 * in a format that can be used in application builders or other interfaces
 * that need to display BLOB data as images.
 * 
 * Usage:
 * 1. Direct image display: <img src="get_product_image.php?id=800">
 * 2. Base64 for JSON: Use get_product_image_base64.php instead
 */

// Function to convert BLOB to base64 string
function convertBlobToBase64($blob) {
    if ($blob) {
        $image_data = $blob->load();
        if ($image_data) {
            return base64_encode($image_data);
        }
    }
    return null;
}

// Get product ID from request
$product_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!empty($product_id)) {
    // Query to get product image
    $query = "SELECT image FROM PRODUCT WHERE product_id = :product_id";
    $stmt = oci_parse($conn, $query);
    oci_bind_by_name($stmt, ':product_id', $product_id);
    
    if (oci_execute($stmt)) {
        $row = oci_fetch_assoc($stmt);
        
        if ($row && $row['IMAGE']) {
            // Convert BLOB to base64
            $base64_image = convertBlobToBase64($row['IMAGE']);
            
            if ($base64_image) {
                // Determine image type (assuming JPEG for simplicity)
                $mime_type = 'image/jpeg';
                
                // Output as data URI
                header("Content-Type: $mime_type");
                echo base64_decode($base64_image);
            } else {
                // Serve a placeholder image if conversion failed
                header("Content-Type: image/jpeg");
                readfile('assets/images/placeholder.jpg');
            }
        } else {
            // Serve a placeholder image if no image exists
            header("Content-Type: image/jpeg");
            readfile('assets/images/placeholder.jpg');
        }
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        echo "Database error";
    }
    
    oci_free_statement($stmt);
} else {
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid product ID";
}

oci_close($conn);
?>
