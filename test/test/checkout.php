<?php
// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kathmandu'); // Set time zone
require_once 'php_logic/connect.php'; // Oracle DB connection

// Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['login_error'] = "Please log in to proceed with checkout.";
    header("Location: login.php");
    exit;
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$buy_now = isset($_SESSION['buy_now']) ? $_SESSION['buy_now'] : null;
$cart_items = [];
$total_amount = 0;
$error_message = null;
$success_message = null;
$discount_percent = 0;

// PayPal configuration
$paypalURL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
$paypalID = 'sb-qhqvr41155629@business.example.com'; // Business Email
$success_url = 'http://localhost/test/success.php';
$cancel_url = 'http://localhost/test/cancel.php';

// Fetch cart items if not a Buy Now transaction
if (!$buy_now) {
    $query_cart = "SELECT cp.cart_id, cp.product_id, cp.quantity, p.name, p.price, p.unit
                   FROM CART_PRODUCT cp
                   JOIN CART c ON cp.cart_id = c.cart_id
                   JOIN PRODUCT p ON cp.product_id = p.product_id
                   WHERE c.fk1_user_id = :user_id AND p.status = 'Enable'";
    $stmt_cart = oci_parse($conn, $query_cart);
    oci_bind_by_name($stmt_cart, ':user_id', $user_id);
    if (oci_execute($stmt_cart)) {
        while ($row = oci_fetch_assoc($stmt_cart)) {
            error_log("Fetched cart item: product_id={$row['PRODUCT_ID']}, quantity={$row['QUANTITY']}");
            $cart_items[] = $row;
            $total_amount += $row['PRICE'] * $row['QUANTITY'];
        }
    } else {
        $error = oci_error($stmt_cart);
        $error_message = "Error fetching cart items: " . htmlspecialchars($error['message']);
        error_log("Error fetching cart items: " . $error['message']);
    }
    oci_free_statement($stmt_cart);

    // Check if cart is empty
    if (empty($cart_items)) {
        $_SESSION['cart_error'] = "Your cart is empty.";
        header("Location: cart.php");
        exit;
    }
} else {
    // Handle Buy Now
    $query_product = "SELECT product_id, name, price, unit
                      FROM PRODUCT
                      WHERE product_id = :product_id AND status = 'Enable'";
    $stmt_product = oci_parse($conn, $query_product);
    oci_bind_by_name($stmt_product, ':product_id', $buy_now['product_id']);
    if (oci_execute($stmt_product)) {
        $product = oci_fetch_assoc($stmt_product);
        if ($product) {
            error_log("Buy Now item: product_id={$product['PRODUCT_ID']}, quantity={$buy_now['quantity']}");
            $cart_items[] = [
                'PRODUCT_ID' => $product['PRODUCT_ID'],
                'NAME' => $product['NAME'],
                'PRICE' => $product['PRICE'],
                'QUANTITY' => $buy_now['quantity'],
                'UNIT' => $product['UNIT']
            ];
            $total_amount = $product['PRICE'] * $buy_now['quantity'];
        } else {
            $error_message = "Product not found or is not active.";
            error_log("Buy Now product not found: product_id={$buy_now['product_id']}");
        }
    } else {
        $error = oci_error($stmt_product);
        $error_message = "Error fetching product details: " . htmlspecialchars($error['message']);
        error_log("Error fetching Buy Now product: " . $error['message']);
    }
    oci_free_statement($stmt_product);
}

// Store original total for discount display
$original_total = $total_amount;

// Generate collection slots (next Wed, Thu, Fri; 3 time windows per day; 24-hour advance)
$slots = [];
$current_date = new DateTime();
$current_date->modify('+24 hours');
$days_allowed = ['Wednesday', 'Thursday', 'Friday'];
$time_windows = [
    ['start' => '10:00', 'end' => '13:00', 'display' => '10:00 AM - 1:00 PM'],
    ['start' => '13:00', 'end' => '16:00', 'display' => '1:00 PM - 4:00 PM'],
    ['start' => '16:00', 'end' => '19:00', 'display' => '4:00 PM - 7:00 PM']
];
$max_days = 7;
$found_days = [];

for ($i = 1; $i <= $max_days && count($found_days) < 3; $i++) {
    $date = clone $current_date;
    $date->modify("+$i days");
    $day_name = $date->format('l');

    if (in_array($day_name, $days_allowed) && !in_array($day_name, $found_days)) {
        $scheduled_date = $date->format('Y-m-d');

        foreach ($time_windows as $time) {
            $scheduled_time = $time['start'] . '-' . $time['end'];

            // Check if slot exists in database
            $query_check_slot = "SELECT slot_id, scheduled_day, scheduled_date, scheduled_time
                                 FROM COLLECTION_SLOT
                                 WHERE scheduled_day = :scheduled_day 
                                 AND scheduled_date = TO_DATE(:scheduled_date, 'YYYY-MM-DD')
                                 AND scheduled_time = :scheduled_time";
            $stmt_check_slot = oci_parse($conn, $query_check_slot);
            oci_bind_by_name($stmt_check_slot, ':scheduled_day', $day_name);
            oci_bind_by_name($stmt_check_slot, ':scheduled_date', $scheduled_date);
            oci_bind_by_name($stmt_check_slot, ':scheduled_time', $scheduled_time);
            if (oci_execute($stmt_check_slot)) {
                $slot = oci_fetch_assoc($stmt_check_slot);
            } else {
                $error = oci_error($stmt_check_slot);
                $error_message = "Error checking collection slot: " . htmlspecialchars($error['message']);
                error_log("Error checking slot: " . $error['message']);
            }
            oci_free_statement($stmt_check_slot);

            $slot_id = $slot ? $slot['SLOT_ID'] : null;

            // Check slot capacity
            $total_items = 0;
            if ($slot_id) {
                $query_count = "SELECT SUM(op.quantity) AS total_items
                                FROM ORDERR o
                                JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                                WHERE o.fk4_slot_id = :slot_id AND o.status = 'Booked'";
                $stmt_count = oci_parse($conn, $query_count);
                oci_bind_by_name($stmt_count, ':slot_id', $slot_id);
                if (oci_execute($stmt_count)) {
                    $count_row = oci_fetch_assoc($stmt_count);
                    $total_items = $count_row['TOTAL_ITEMS'] ? $count_row['TOTAL_ITEMS'] : 0;
                } else {
                    $error = oci_error($stmt_count);
                    $error_message = "Error checking slot capacity: " . htmlspecialchars($error['message']);
                    error_log("Error checking slot capacity: " . $error['message']);
                }
                oci_free_statement($stmt_count);
            }

            if ($total_items < 20) {
                $slots[] = [
                    'SLOT_ID' => $slot_id,
                    'SCHEDULED_DAY' => $day_name,
                    'SCHEDULED_DATE' => $scheduled_date,
                    'SCHEDULED_TIME' => $scheduled_time,
                    'DISPLAY_TIME' => $time['display']
                ];
            }
        }
        $found_days[] = $day_name;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $selected_slot = isset($_POST['slot_select']) ? filter_var($_POST['slot_select'], FILTER_SANITIZE_STRING) : null;
    $coupon_code = isset($_POST['coupon_code']) ? trim(filter_var($_POST['coupon_code'], FILTER_SANITIZE_STRING)) : null;

    // Validate collection slot
    if (!$selected_slot) {
        $error_message = "Please select a collection slot.";
    } else {
        list($selected_date, $selected_day, $selected_time) = explode('|', $selected_slot);
        error_log("Selected slot: date=$selected_date, day=$selected_day, time=$selected_time");

        $date_obj = DateTime::createFromFormat('Y-m-d', $selected_date);
        if ($date_obj) {
            $selected_day = trim($date_obj->format('l'));
        } else {
            $error_message = "Invalid date format for selected slot.";
        }

        // Verify slot is valid
        $slot_valid = false;
        $selected_slot_id = null;
        foreach ($slots as $slot) {
            if (
                $slot['SCHEDULED_DATE'] === $selected_date &&
                $slot['SCHEDULED_DAY'] === $selected_day &&
                $slot['SCHEDULED_TIME'] === $selected_time
            ) {
                $slot_valid = true;
                $selected_slot_id = $slot['SLOT_ID'];
                break;
            }
        }

        if (!$slot_valid) {
            $error_message = "Invalid collection slot selected.";
        } else {
            // Check or create slot in database
            if (!$selected_slot_id) {
                $query_insert_slot = "INSERT INTO COLLECTION_SLOT (scheduled_day, scheduled_time, scheduled_date)
                                      VALUES (:scheduled_day, :scheduled_time, TO_DATE(:scheduled_date, 'YYYY-MM-DD'))
                                      RETURNING slot_id INTO :slot_id";
                $stmt_insert_slot = oci_parse($conn, $query_insert_slot);
                oci_bind_by_name($stmt_insert_slot, ':scheduled_day', $selected_day);
                oci_bind_by_name($stmt_insert_slot, ':scheduled_time', $selected_time);
                oci_bind_by_name($stmt_insert_slot, ':scheduled_date', $selected_date);
                oci_bind_by_name($stmt_insert_slot, ':slot_id', $selected_slot_id, 32, SQLT_INT);
                if (oci_execute($stmt_insert_slot, OCI_NO_AUTO_COMMIT)) {
                    error_log("Collection slot created: $selected_slot_id");
                } else {
                    $error = oci_error($stmt_insert_slot);
                    $error_message = "Error creating collection slot: " . htmlspecialchars($error['message']);
                    error_log("Error creating slot: " . $error['message']);
                    oci_free_statement($stmt_insert_slot);
                    goto end_submission;
                }
                oci_free_statement($stmt_insert_slot);
            }

            if (!$error_message) {
                // Verify slot_id exists
                error_log("Verifying slot_id: $selected_slot_id");
                $query_verify_slot = "SELECT slot_id FROM COLLECTION_SLOT WHERE slot_id = :slot_id";
                $stmt_verify_slot = oci_parse($conn, $query_verify_slot);
                oci_bind_by_name($stmt_verify_slot, ':slot_id', $selected_slot_id);
                if (oci_execute($stmt_verify_slot, OCI_NO_AUTO_COMMIT)) {
                    $slot_exists = oci_fetch_assoc($stmt_verify_slot);
                    if (!$slot_exists) {
                        $error_message = "Collection slot ID is invalid or does not exist: $selected_slot_id";
                        error_log("Slot verification failed for slot_id: $selected_slot_id");
                        oci_free_statement($stmt_verify_slot);
                        goto end_submission;
                    }
                } else {
                    $error = oci_error($stmt_verify_slot);
                    $error_message = "Error verifying collection slot: " . htmlspecialchars($error['message']);
                    error_log("Error verifying slot: " . $error['message']);
                    oci_free_statement($stmt_verify_slot);
                    goto end_submission;
                }
                oci_free_statement($stmt_verify_slot);

                // Verify user_id exists
                $query_verify_user = "SELECT user_id FROM CUSTOMER WHERE user_id = :user_id";
                $stmt_verify_user = oci_parse($conn, $query_verify_user);
                oci_bind_by_name($stmt_verify_user, ':user_id', $user_id);
                if (oci_execute($stmt_verify_user, OCI_NO_AUTO_COMMIT)) {
                    $user_exists = oci_fetch_assoc($stmt_verify_user);
                    if (!$user_exists) {
                        $error_message = "User ID $user_id not found in CUSTOMER table.";
                        error_log("User verification failed: user_id=$user_id");
                        oci_free_statement($stmt_verify_user);
                        goto end_submission;
                    }
                } else {
                    $error = oci_error($stmt_verify_user);
                    $error_message = "Error verifying user: " . htmlspecialchars($error['message']);
                    error_log("Error verifying user: " . $error['message']);
                    oci_free_statement($stmt_verify_user);
                    goto end_submission;
                }
                oci_free_statement($stmt_verify_user);

                // Check slot capacity
                $query_count = "SELECT SUM(op.quantity) AS total_items
                                FROM ORDERR o
                                JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                                WHERE o.fk4_slot_id = :slot_id AND o.status = 'Booked'";
                $stmt_count = oci_parse($conn, $query_count);
                oci_bind_by_name($stmt_count, ':slot_id', $selected_slot_id);
                if (oci_execute($stmt_count, OCI_NO_AUTO_COMMIT)) {
                    $count_row = oci_fetch_assoc($stmt_count);
                    $total_items = $count_row['TOTAL_ITEMS'] ? $count_row['TOTAL_ITEMS'] : 0;
                } else {
                    $error = oci_error($stmt_count);
                    $error_message = "Error checking slot capacity: " . htmlspecialchars($error['message']);
                    error_log("Error checking slot capacity: " . $error['message']);
                    oci_free_statement($stmt_count);
                    goto end_submission;
                }
                oci_free_statement($stmt_count);

                if ($total_items >= 20) {
                    $error_message = "Selected collection slot is full.";
                } else {
                    // Apply coupon if provided
                    $coupon_id = null;
                    $discount_percent = 0;
                    if ($coupon_code) {
                        $query_coupon = "SELECT c.coupon_id, d.percent
                                         FROM COUPON c
                                         JOIN DISCOUNT d ON c.discount_id = d.discount_id
                                         WHERE UPPER(c.code) = UPPER(:coupon_code) 
                                         AND (c.expiry_date IS NULL OR c.expiry_date >= SYSDATE)
                                         AND d.valid_upto >= SYSDATE";
                        $stmt_coupon = oci_parse($conn, $query_coupon);
                        oci_bind_by_name($stmt_coupon, ':coupon_code', $coupon_code);
                        if (oci_execute($stmt_coupon, OCI_NO_AUTO_COMMIT)) {
                            $coupon = oci_fetch_assoc($stmt_coupon);
                            if ($coupon) {
                                $coupon_id = $coupon['COUPON_ID'];
                                $discount_percent = $coupon['PERCENT'];
                                $total_amount = $total_amount * (1 - $discount_percent / 100);
                            } else {
                                $error_message = "Invalid or expired coupon code.";
                            }
                        } else {
                            $error = oci_error($stmt_coupon);
                            $error_message = "Error validating coupon: " . htmlspecialchars($error['message']);
                            error_log("Error validating coupon: " . $error['message']);
                        }
                        oci_free_statement($stmt_coupon);
                    }

                    if (!$error_message) {
                        // Validate coupon_id if provided
                        if ($coupon_id) {
                            $query_verify_coupon = "SELECT coupon_id FROM COUPON WHERE coupon_id = :coupon_id";
                            $stmt_verify_coupon = oci_parse($conn, $query_verify_coupon);
                            oci_bind_by_name($stmt_verify_coupon, ':coupon_id', $coupon_id);
                            if (oci_execute($stmt_verify_coupon, OCI_NO_AUTO_COMMIT)) {
                                $coupon_exists = oci_fetch_assoc($stmt_verify_coupon);
                                if (!$coupon_exists) {
                                    $error_message = "Coupon ID $coupon_id not found in COUPON table.";
                                    error_log("Coupon verification failed: coupon_id=$coupon_id");
                                    oci_free_statement($stmt_verify_coupon);
                                    goto end_submission;
                                }
                            } else {
                                $error = oci_error($stmt_verify_coupon);
                                $error_message = "Error verifying coupon: " . htmlspecialchars($error['message']);
                                error_log("Error verifying coupon: " . $error['message']);
                                oci_free_statement($stmt_verify_coupon);
                                goto end_submission;
                            }
                            oci_free_statement($stmt_verify_coupon);
                        }

                        // Create order
                        $order_id = null;
                        $query_order = "INSERT INTO ORDERR (total_amount, status, placed_on, fk1_user_id, fk2_coupon_id, fk4_slot_id)
                                        VALUES (:total_amount, 'Booked', SYSDATE, :user_id, :coupon_id, :slot_id)
                                        RETURNING order_id INTO :order_id";
                        $stmt_order = oci_parse($conn, $query_order);
                        oci_bind_by_name($stmt_order, ':total_amount', $total_amount);
                        oci_bind_by_name($stmt_order, ':user_id', $user_id);
                        oci_bind_by_name($stmt_order, ':coupon_id', $coupon_id);
                        oci_bind_by_name($stmt_order, ':slot_id', $selected_slot_id);
                        oci_bind_by_name($stmt_order, ':order_id', $order_id, 32, SQLT_INT);
                        error_log("Attempting ORDERR insert: user_id=$user_id, slot_id=$selected_slot_id, coupon_id=" . ($coupon_id ?: 'NULL'));

                        if (oci_execute($stmt_order, OCI_NO_AUTO_COMMIT)) {
                            $order_id = (int)$order_id;
                            error_log("Order created with ID: $order_id");

                            if (!$order_id) {
                                $error_message = "Failed to retrieve order ID after insert.";
                                error_log($error_message);
                                oci_rollback($conn);
                                oci_free_statement($stmt_order);
                                goto end_submission;
                            }

                            // Verify order exists
                            $query_verify_order = "SELECT order_id, fk1_user_id, fk4_slot_id FROM ORDERR WHERE order_id = :order_id";
                            $stmt_verify_order = oci_parse($conn, $query_verify_order);
                            oci_bind_by_name($stmt_verify_order, ':order_id', $order_id);
                            if (oci_execute($stmt_verify_order, OCI_NO_AUTO_COMMIT)) {
                                $order_row = oci_fetch_assoc($stmt_verify_order);
                                if (!$order_row) {
                                    $error_message = "Order ID $order_id not found in ORDERR table after insert.";
                                    error_log("Verification failed: Order ID $order_id not found.");
                                    oci_rollback($conn);
                                    oci_free_statement($stmt_verify_order);
                                    goto end_submission;
                                }
                                error_log("Order verified: ID=$order_id, user_id={$order_row['FK1_USER_ID']}, slot_id={$order_row['FK4_SLOT_ID']}");
                            } else {
                                $error = oci_error($stmt_verify_order);
                                $error_message = "Error verifying order: " . htmlspecialchars($error['message']);
                                error_log("Verification error: " . $error['message']);
                                oci_rollback($conn);
                                oci_free_statement($stmt_verify_order);
                                goto end_submission;
                            }
                            oci_free_statement($stmt_verify_order);

                            // Insert order products
                            foreach ($cart_items as $item) {
                                $query_op = "INSERT INTO ORDER_PRODUCT (order_id, product_id, quantity, price_at_purchase)
                                             VALUES (:order_id, :product_id, :quantity, :price)";
                                $stmt_op = oci_parse($conn, $query_op);
                                oci_bind_by_name($stmt_op, ':order_id', $order_id);
                                oci_bind_by_name($stmt_op, ':product_id', $item['PRODUCT_ID']);
                                oci_bind_by_name($stmt_op, ':quantity', $item['QUANTITY']);
                                oci_bind_by_name($stmt_op, ':price', $item['PRICE']);
                                error_log("Inserting ORDER_PRODUCT: order_id=$order_id, product_id={$item['PRODUCT_ID']}, quantity={$item['QUANTITY']}");
                                if (!oci_execute($stmt_op, OCI_NO_AUTO_COMMIT)) {
                                    $error = oci_error($stmt_op);
                                    $error_message = "Error inserting order product: " . htmlspecialchars($error['message']);
                                    error_log("ORDER_PRODUCT error: " . $error['message']);
                                    oci_rollback($conn);
                                    oci_free_statement($stmt_op);
                                    goto end_submission;
                                }
                                oci_free_statement($stmt_op);
                            }

                            // Create payment record
                            $payment_id = null;
                            $query_payment = "INSERT INTO PAYMENT (method, payment_status, paid_on, fk1_order_id)
                                              VALUES ('PayPal', 'Pending', SYSDATE, :order_id)
                                              RETURNING payment_id INTO :payment_id";
                            $stmt_payment = oci_parse($conn, $query_payment);
                            oci_bind_by_name($stmt_payment, ':order_id', $order_id);
                            oci_bind_by_name($stmt_payment, ':payment_id', $payment_id, 32, SQLT_INT);
                            if (!oci_execute($stmt_payment, OCI_NO_AUTO_COMMIT)) {
                                $error = oci_error($stmt_payment);
                                $error_message = "Error inserting payment: " . htmlspecialchars($error['message']);
                                error_log("PAYMENT error: " . $error['message']);
                                oci_rollback($conn);
                                oci_free_statement($stmt_payment);
                                goto end_submission;
                            }
                            $payment_id = (int)$payment_id;
                            oci_free_statement($stmt_payment);

                            // Update order with payment_id
                            $query_update_order = "UPDATE ORDERR SET fk3_payment_id = :payment_id WHERE order_id = :order_id";
                            $stmt_update_order = oci_parse($conn, $query_update_order);
                            oci_bind_by_name($stmt_update_order, ':payment_id', $payment_id);
                            oci_bind_by_name($stmt_update_order, ':order_id', $order_id);
                            if (!oci_execute($stmt_update_order, OCI_NO_AUTO_COMMIT)) {
                                $error = oci_error($stmt_update_order);
                                $error_message = "Error updating order with payment ID: " . htmlspecialchars($error['message']);
                                error_log("Update ORDERR error: " . $error['message']);
                                oci_rollback($conn);
                                oci_free_statement($stmt_update_order);
                                goto end_submission;
                            }
                            oci_free_statement($stmt_update_order);

                            // Commit the entire transaction
                            if (!oci_commit($conn)) {
                                $error = oci_error($conn);
                                $error_message = "Error committing transaction: " . htmlspecialchars($error['message']);
                                error_log("Transaction commit error: " . $error['message']);
                                oci_rollback($conn);
                                goto end_submission;
                            }
                            error_log("Transaction committed for order_id: $order_id");

                            // Store order_id in session for PayPal
                            $_SESSION['order_id'] = $order_id;
                            $_SESSION['order_placed'] = true;

                            // Clear cart or Buy Now session
                            if (!$buy_now) {
                                $query_clear_cart = "DELETE FROM CART_PRODUCT WHERE cart_id IN (SELECT cart_id FROM CART WHERE fk1_user_id = :user_id)";
                                $stmt_clear_cart = oci_parse($conn, $query_clear_cart);
                                oci_bind_by_name($stmt_clear_cart, ':user_id', $user_id);
                                if (!oci_execute($stmt_clear_cart, OCI_NO_AUTO_COMMIT)) {
                                    $error = oci_error($stmt_clear_cart);
                                    $error_message = "Error clearing cart: " . htmlspecialchars($error['message']);
                                    error_log("Cart clear error: " . $error['message']);
                                    oci_rollback($conn);
                                    oci_free_statement($stmt_clear_cart);
                                    goto end_submission;
                                }
                                oci_free_statement($stmt_clear_cart);
                                oci_commit($conn);
                            }

                            // Clear buy_now session
                            unset($_SESSION['buy_now']);

                            $success_message = "Order created. Proceed to PayPal for payment.";

                            // Redirect to PayPal
                            $paypal_params = [
                                'cmd' => '_cart',
                                'upload' => '1',
                                'business' => $paypalID,
                                'return' => $success_url,
                                'cancel_return' => $cancel_url,
                                'currency_code' => 'USD',
                                'invoice' => $order_id,
                                'custom' => $order_id
                            ];

                            $i = 1;
                            foreach ($cart_items as $item) {
                                $paypal_params["item_name_{$i}"] = htmlspecialchars($item['NAME']);
                                $paypal_params["amount_{$i}"] = number_format($item['PRICE'], 2, '.', '');
                                $paypal_params["quantity_{$i}"] = $item['QUANTITY'];
                                $i++;
                            }

                            if ($discount_percent > 0) {
                                $discount_amount = $original_total * ($discount_percent / 100);
                                $paypal_params['discount_amount_cart'] = number_format($discount_amount, 2, '.', '');
                            }

                            $paypal_url = $paypalURL . '?' . http_build_query($paypal_params);
                            header("Location: $paypal_url");
                            exit;
                        } else {
                            $error = oci_error($stmt_order);
                            $error_message = "Error creating order: " . htmlspecialchars($error['message']);
                            error_log("ORDERR insert error: " . $error['message']);
                            oci_rollback($conn);
                        }
                        oci_free_statement($stmt_order);
                    }
                }
            }
        }
    }
    end_submission:
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - CleckBasket</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Georgia', serif;
            background-color: #f5f5f5;
        }

        .heritage-bg {
            background-color: #fff7ed;
            border: 1px solid #f97316;
        }

        .btn-primary {
            background-color: #f97316;
            color: white;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #e55e0d;
        }

        .error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
        }

        .success {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
        }
    </style>
</head>

<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container mx-auto px-6 py-8">
        <!-- Breadcrumbs -->
        <nav class="mb-6 text-sm text-gray-600">
            <a href="index.php" class="hover:text-orange-500">Home</a> >
            <a href="cart.php" class="hover:text-orange-500">Cart</a> >
            <span class="text-gray-800">Checkout</span>
        </nav>

        <!-- Messages -->
        <?php if ($error_message): ?>
            <div class="error p-4 rounded-md mb-6">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success p-4 rounded-md mb-6">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($cart_items && !$error_message): ?>
            <div class="bg-white rounded-lg shadow-md p-8 heritage-bg">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">Checkout</h1>

                <!-- Order Summary -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Summary</h2>
                    <div class="border rounded-md p-4">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex justify-between mb-2">
                                <span><?php echo htmlspecialchars($item['NAME']); ?> (x<?php echo htmlspecialchars($item['QUANTITY']); ?>) <?php echo $item['UNIT'] ? ' / ' . htmlspecialchars($item['UNIT']) : ''; ?></span>
                                <span>$<?php echo number_format($item['PRICE'] * $item['QUANTITY'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($original_total, 2); ?></span>
                            </div>
                            <?php if ($discount_percent > 0): ?>
                                <div class="flex justify-between text-green-600">
                                    <span>Discount (<?php echo $discount_percent; ?>%)</span>
                                    <span>-$<?php echo number_format($original_total * ($discount_percent / 100), 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between font-bold mt-2">
                                <span>Total</span>
                                <span>$<?php echo number_format($total_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Checkout Form -->
                <div>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <?php if (!isset($_SESSION['order_id']) && !isset($_SESSION['order_placed'])): ?>
                            <!-- Collection Slot Selection -->
                            <div class="mb-6">
                                <h2 class="text-xl font-semibold text-gray-800 mb-4">Select Collection Slot</h2>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <?php foreach ($slots as $slot): ?>
                                        <label class="border rounded-md p-4 cursor-pointer hover:border-orange-500 transition-colors">
                                            <input type="radio" name="slot_select" value="<?php echo htmlspecialchars($slot['SCHEDULED_DATE'] . '|' . $slot['SCHEDULED_DAY'] . '|' . $slot['SCHEDULED_TIME']); ?>" class="mr-2">
                                            <div class="font-semibold"><?php echo htmlspecialchars($slot['SCHEDULED_DAY']); ?></div>
                                            <div><?php echo date('d M Y', strtotime($slot['SCHEDULED_DATE'])); ?></div>
                                            <div><?php echo htmlspecialchars($slot['DISPLAY_TIME']); ?></div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <label for="coupon_code" class="block text-sm font-medium text-gray-700">Coupon Code</label>
                            <input type="text" name="coupon_code" id="coupon_code" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm" placeholder="Enter coupon code">
                        </div>

                        <!-- PayPal Payment Form -->
                        <?php if (isset($_SESSION['order_id'])): ?>
                            <div class="mt-6 bg-blue-50 p-4 rounded-md border border-blue-200">
                                <h3 class="text-lg font-semibold text-blue-800 mb-2">Complete Payment with PayPal</h3>
                                <p class="text-sm text-blue-700 mb-4">Your order #<?php echo htmlspecialchars($_SESSION['order_id']); ?> has been created. Please complete your payment with PayPal.</p>

                                <form action="<?php echo $paypalURL; ?>" method="post">
                                    <input type="hidden" name="cmd" value="_cart">
                                    <input type="hidden" name="upload" value="1">
                                    <input type="hidden" name="business" value="<?php echo $paypalID; ?>">
                                    <input type="hidden" name="invoice" value="<?php echo htmlspecialchars($_SESSION['order_id']); ?>">
                                    <input type="hidden" name="custom" value="<?php echo htmlspecialchars($_SESSION['order_id']); ?>">
                                    <input type="hidden" name="currency_code" value="USD">
                                    <input type="hidden" name="return" value="<?php echo $success_url; ?>">
                                    <input type="hidden" name="cancel_return" value="<?php echo $cancel_url; ?>">

                                    <?php $i = 1;
                                    foreach ($cart_items as $item): ?>
                                        <input type="hidden" name="item_name_<?php echo $i; ?>" value="<?php echo htmlspecialchars($item['NAME']); ?>">
                                        <input type="hidden" name="amount_<?php echo $i; ?>" value="<?php echo number_format($item['PRICE'], 2, '.', ''); ?>">
                                        <input type="hidden" name="quantity_<?php echo $i; ?>" value="<?php echo $item['QUANTITY']; ?>">
                                    <?php $i++;
                                    endforeach; ?>

                                    <?php if ($discount_percent > 0): ?>
                                        <input type="hidden" name="discount_amount_cart" value="<?php echo number_format($original_total * ($discount_percent / 100), 2, '.', ''); ?>">
                                    <?php endif; ?>

                                    <input type="image" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynow_LG.gif" alt="PayPal - The safer, easier way to pay online">
                                    <img alt="" border="0" width="1" height="1" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif">
                                </form>
                            </div>
                        <?php elseif (!isset($_SESSION['order_placed'])): ?>
                            <button type="submit" name="place_order" value="1" class="btn-primary font-semibold py-2 px-4 rounded-md">Place Order</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 heritage-bg text-center">
                <p class="text-gray-700">Your cart is empty or an error occurred.</p>
                <a href="shop.php" class="text-orange-500 hover:text-orange-700 underline mt-4 inline-block">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    if (isset($conn) && $conn) {
        oci_close($conn);
    }
    // Clear session data after successful order
    if (isset($_SESSION['order_placed']) && $_SESSION['order_placed'] === true) {
        unset($_SESSION['order_id']);
        unset($_SESSION['order_placed']);
    }
    ?>
</body>

</html>