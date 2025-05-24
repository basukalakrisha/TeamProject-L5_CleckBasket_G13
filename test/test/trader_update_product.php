<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as trader
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['usertype'] !== 'TRADER') {
    error_log("Access denied in update_product: loggedin=" . ($_SESSION['loggedin'] ?? 'not set') . ", usertype=" . ($_SESSION['usertype'] ?? 'not set'));
    $_SESSION['login_error'] = "Please log in as a trader to access the dashboard.";
    header("Location: login.php");
    exit;
}

// Include Oracle database connection
require_once 'php_logic/connect.php';

// Get trader information
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    error_log("user_id not set in session");
    $_SESSION['login_error'] = "Session error. Please log in again.";
    header("Location: login.php");
    exit;
}

// Get form data
$product_id = $_POST['product_id'] ?? '';
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = $_POST['price'] ?? '';
$stock = $_POST['stock'] ?? '';
$unit = trim($_POST['unit'] ?? '');
$status = $_POST['status'] ?? 'Disable';
$discount_id = $_POST['discount'] ?? 'none';

// Validate inputs
if (empty($product_id) || empty($name) || empty($description) || empty($price) || empty($stock)) {
    $_SESSION['error_message'] = "All required fields (Product Name, Description, Price, Stock) must be filled out.";
    header("Location: trader_edit_product.php?id=$product_id");
    exit;
}
if (!is_numeric($price) || $price < 0) {
    $_SESSION['error_message'] = "Price must be a valid non-negative number.";
    header("Location: trader_edit_product.php?id=$product_id");
    exit;
}
if (!is_numeric($stock) || $stock < 0 || floor($stock) != $stock) {
    $_SESSION['error_message'] = "Stock must be a valid non-negative integer.";
    header("Location: trader_edit_product.php?id=$product_id");
    exit;
}
if (strlen($name) > 30) {
    $_SESSION['error_message'] = "Product name must not exceed 30 characters.";
    header("Location: trader_edit_product.php?id=$product_id");
    exit;
}

// Check if product belongs to trader's shop
$query_check = "SELECT p.product_id 
                FROM PRODUCT p 
                JOIN SHOP s ON p.fk1_shop_id = s.shop_id 
                WHERE p.product_id = :product_id AND s.fk1_user_id = :user_id";
$stmt_check = oci_parse($conn, $query_check);
oci_bind_by_name($stmt_check, ':product_id', $product_id);
oci_bind_by_name($stmt_check, ':user_id', $user_id);
if (!oci_execute($stmt_check)) {
    $e = oci_error($stmt_check);
    error_log("Failed to verify product ownership: " . $e['message']);
    $_SESSION['error_message'] = "Failed to verify product ownership.";
    header("Location: trader_edit_product.php?id=$product_id");
    exit;
}
if (!oci_fetch_assoc($stmt_check)) {
    error_log("Product $product_id not found or not owned by user $user_id");
    $_SESSION['error_message'] = "Product not found or you do not have permission to edit it.";
    header("Location: trader_manage_products.php");
    exit;
}
oci_free_statement($stmt_check);

// Check for duplicate product name (excluding current product)
$query_name_check = "SELECT COUNT(*) AS name_count 
                    FROM PRODUCT 
                    WHERE fk1_shop_id = (SELECT shop_id FROM SHOP WHERE fk1_user_id = :user_id) 
                    AND name = :name 
                    AND product_id != :product_id";
$stmt_name_check = oci_parse($conn, $query_name_check);
oci_bind_by_name($stmt_name_check, ':user_id', $user_id);
oci_bind_by_name($stmt_name_check, ':name', $name);
oci_bind_by_name($stmt_name_check, ':product_id', $product_id);
oci_execute($stmt_name_check);
$row_name_check = oci_fetch_assoc($stmt_name_check);
if ($row_name_check['NAME_COUNT'] > 0) {
    $_SESSION['error_message'] = "Product name already exists in your shop.";
    header("Location: trader_edit_product.php?id=$product_id");
    exit;
}
oci_free_statement($stmt_name_check);

// Validate discount if selected
$discount_id_to_use = ($discount_id === 'none') ? null : $discount_id;
if ($discount_id_to_use !== null) {
    $query_discount = "SELECT discount_id 
                       FROM DISCOUNT 
                       WHERE discount_id = :discount_id AND valid_upto > SYSDATE";
    $stmt_discount = oci_parse($conn, $query_discount);
    oci_bind_by_name($stmt_discount, ':discount_id', $discount_id_to_use);
    oci_execute($stmt_discount);
    if (!oci_fetch_assoc($stmt_discount)) {
        $_SESSION['error_message'] = "Selected discount is invalid or expired.";
        header("Location: trader_edit_product.php?id=$product_id");
        exit;
    }
    oci_free_statement($stmt_discount);
}

// Handle image upload
$image_updated = false;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $image_data = file_get_contents($_FILES['image']['tmp_name']);
    $query_image = "UPDATE PRODUCT 
                    SET image = EMPTY_BLOB(), action = 'Update' 
                    WHERE product_id = :product_id 
                    RETURNING image INTO :image_blob";
    $stmt_image = oci_parse($conn, $query_image);
    $blob = oci_new_descriptor($conn, OCI_D_LOB);
    oci_bind_by_name($stmt_image, ':product_id', $product_id);
    oci_bind_by_name($stmt_image, ':image_blob', $blob, -1, OCI_B_BLOB);
    if (oci_execute($stmt_image, OCI_DEFAULT)) {
        if ($blob->write($image_data) === false) {
            error_log("Failed to write BLOB for product $product_id");
            $_SESSION['error_message'] = "Failed to upload product image.";
            oci_rollback($conn);
            header("Location: trader_edit_product.php?id=$product_id");
            exit;
        }
        $image_updated = true;
    } else {
        $e = oci_error($stmt_image);
        error_log("Failed to prepare image update: " . $e['message']);
        $_SESSION['error_message'] = "Failed to prepare image upload.";
        oci_rollback($conn);
        header("Location: trader_edit_product.php?id=$product_id");
        exit;
    }
    $blob->free();
    oci_free_statement($stmt_image);
}

// Update product details
$query_update = "UPDATE PRODUCT 
                 SET name = :name, 
                     description = :description, 
                     price = :price, 
                     stock = :stock, 
                     unit = :unit, 
                     status = :status, 
                     fk3_discount_id = :discount_id, 
                     action = 'Update'
                 WHERE product_id = :product_id";
$stmt_update = oci_parse($conn, $query_update);
oci_bind_by_name($stmt_update, ':name', $name);
oci_bind_by_name($stmt_update, ':description', $description);
oci_bind_by_name($stmt_update, ':price', $price);
oci_bind_by_name($stmt_update, ':stock', $stock);
oci_bind_by_name($stmt_update, ':unit', $unit);
oci_bind_by_name($stmt_update, ':status', $status);
oci_bind_by_name($stmt_update, ':discount_id', $discount_id_to_use);
oci_bind_by_name($stmt_update, ':product_id', $product_id);

if (oci_execute($stmt_update, OCI_DEFAULT)) {
    if (oci_commit($conn)) {
        $_SESSION['success_message'] = "Product updated successfully.";
        header("Location: trader_manage_products.php");
        exit;
    } else {
        error_log("Failed to commit transaction for product $product_id");
        $_SESSION['error_message'] = "Failed to save changes. Please try again.";
        oci_rollback($conn);
        header("Location: trader_edit_product.php?id=$product_id");
        exit;
    }
} else {
    $e = oci_error($stmt_update);
    error_log("Failed to update product $product_id: " . $e['message']);
    $_SESSION['error_message'] = "Failed to update product: " . htmlentities($e['message']);
    oci_rollback($conn);
    header("Location: trader_edit_product.php?id=$product_id");
    exit;
}

oci_free_statement($stmt_update);
oci_close($conn);
?>