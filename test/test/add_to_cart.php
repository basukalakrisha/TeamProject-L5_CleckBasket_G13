<?php
session_start();
require_once 'php_logic/connect.php'; // Connect to Oracle DB

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    $_SESSION['login_error'] = "Please log in to add items to your cart.";
    header('Location: login.php');
    exit;
}

// CSRF token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['cart_error'] = "Invalid CSRF token.";
    error_log("add_to_cart.php - CSRF token mismatch");
    header('Location: shop.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = filter_var($_POST['product_id'] ?? null, FILTER_SANITIZE_STRING);
    $product_name = filter_var($_POST['product_name'] ?? 'Unknown Product', FILTER_SANITIZE_STRING);
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.00;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    // Validate inputs
    if (!$product_id || $quantity <= 0 || $price < 0) {
        $_SESSION['cart_error'] = "Invalid product or quantity.";
        header('Location: shop.php');
        exit;
    }

    // Validate product and stock
    $query_product = "SELECT stock, status FROM PRODUCT WHERE product_id = :product_id";
    $stmt_product = oci_parse($conn, $query_product);
    oci_bind_by_name($stmt_product, ':product_id', $product_id);
    if (!oci_execute($stmt_product)) {
        $error = oci_error($stmt_product);
        error_log("Error validating product: " . $error['message']);
        $_SESSION['cart_error'] = "Error validating product.";
        oci_free_statement($stmt_product);
        header('Location: shop.php');
        exit;
    }
    $product = oci_fetch_assoc($stmt_product);
    oci_free_statement($stmt_product);

    if (!$product || $product['STATUS'] !== 'Enable') {
        $_SESSION['cart_error'] = "Product not found or not available.";
        header('Location: shop.php');
        exit;
    }
    if ($quantity > $product['STOCK']) {
        $_SESSION['cart_error'] = "Requested quantity exceeds available stock.";
        header('Location: product_detail.php?id=' . urlencode($product_id));
        exit;
    }

    // Get or create cart
    $cart_id = null;
    $query_get_cart = "SELECT cart_id FROM CART WHERE fk1_user_id = :user_id";
    $stmt_get_cart = oci_parse($conn, $query_get_cart);
    oci_bind_by_name($stmt_get_cart, ':user_id', $user_id);
    if (!oci_execute($stmt_get_cart)) {
        $error = oci_error($stmt_get_cart);
        error_log("Error fetching cart: " . $error['message']);
        $_SESSION['cart_error'] = "Error accessing cart.";
        oci_free_statement($stmt_get_cart);
        header('Location: shop.php');
        exit;
    }
    $cart_row = oci_fetch_assoc($stmt_get_cart);
    oci_free_statement($stmt_get_cart);

    if ($cart_row) {
        $cart_id = $cart_row['CART_ID'];
    } else {
        $query_create_cart = "INSERT INTO CART (fk1_user_id) VALUES (:user_id) RETURNING cart_id INTO :cart_id";
        $stmt_create_cart = oci_parse($conn, $query_create_cart);
        oci_bind_by_name($stmt_create_cart, ':user_id', $user_id);
        oci_bind_by_name($stmt_create_cart, ':cart_id', $cart_id, -1, SQLT_INT);
        if (oci_execute($stmt_create_cart, OCI_NO_AUTO_COMMIT)) {
            oci_commit($conn);
        } else {
            $error = oci_error($stmt_create_cart);
            error_log("Error creating cart: " . $error['message']);
            $_SESSION['cart_error'] = "Unable to create cart.";
            oci_free_statement($stmt_create_cart);
            header('Location: shop.php');
            exit;
        }
        oci_free_statement($stmt_create_cart);
    }

    if (!$cart_id) {
        $_SESSION['cart_error'] = "Failed to create or retrieve cart.";
        header('Location: shop.php');
        exit;
    }

    // Check if product is already in cart
    $query_check_cart = "SELECT quantity FROM CART_PRODUCT WHERE cart_id = :cart_id AND product_id = :product_id";
    $stmt_check_cart = oci_parse($conn, $query_check_cart);
    oci_bind_by_name($stmt_check_cart, ':cart_id', $cart_id);
    oci_bind_by_name($stmt_check_cart, ':product_id', $product_id);
    oci_execute($stmt_check_cart);
    $cart_item = oci_fetch_assoc($stmt_check_cart);
    oci_free_statement($stmt_check_cart);

    // Begin transaction
    $success = false;
    if ($cart_item) {
        // Update quantity
        $new_quantity = $cart_item['QUANTITY'] + $quantity;
        if ($new_quantity > $product['STOCK']) {
            $_SESSION['cart_error'] = "Total quantity exceeds available stock.";
            header('Location: product_detail.php?id=' . urlencode($product_id));
            exit;
        }
        $query_update = "UPDATE CART_PRODUCT SET quantity = :quantity WHERE cart_id = :cart_id AND product_id = :product_id";
        $stmt_update = oci_parse($conn, $query_update);
        oci_bind_by_name($stmt_update, ':quantity', $new_quantity);
        oci_bind_by_name($stmt_update, ':cart_id', $cart_id);
        oci_bind_by_name($stmt_update, ':product_id', $product_id);
        if (oci_execute($stmt_update, OCI_NO_AUTO_COMMIT)) {
            $success = true;
            oci_commit($conn);
        } else {
            $error = oci_error($stmt_update);
            error_log("Error updating cart: " . $error['message']);
            if ($error['code'] == 20001) {
                $_SESSION['cart_error'] = "Cannot add product: Cart is limited to 20 items.";
            } else {
                $_SESSION['cart_error'] = "Error updating cart.";
            }
            oci_rollback($conn);
        }
        oci_free_statement($stmt_update);
    } else {
        // Insert new cart item
        $query_insert = "INSERT INTO CART_PRODUCT (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :quantity)";
        $stmt_insert = oci_parse($conn, $query_insert);
        oci_bind_by_name($stmt_insert, ':cart_id', $cart_id);
        oci_bind_by_name($stmt_insert, ':product_id', $product_id);
        oci_bind_by_name($stmt_insert, ':quantity', $quantity);
        if (oci_execute($stmt_insert, OCI_NO_AUTO_COMMIT)) {
            $success = true;
            oci_commit($conn);
        } else {
            $error = oci_error($stmt_insert);
            error_log("Error adding to cart: " . $error['message']);
            if ($error['code'] == 20001) {
                $_SESSION['cart_error'] = "Cannot add product: Cart is limited to 20 items.";
            } else {
                $_SESSION['cart_error'] = "Error adding product to cart.";
            }
            oci_rollback($conn);
        }
        oci_free_statement($stmt_insert);
    }

    // Update session cart for counter consistency
    if ($success) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product_name,
                'price' => $price,
                'quantity' => $quantity
            ];
        }
        $_SESSION['cart_success'] = "Product added to cart successfully!";
        header('Location: cart.php');
    } else {
        header('Location: product_detail.php?id=' . urlencode($product_id));
    }
}

if (isset($conn) && $conn) {
    oci_close($conn);
}
exit;