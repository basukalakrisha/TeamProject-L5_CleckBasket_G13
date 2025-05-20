<?php
// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
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

// PayPal configuration
$paypalURL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
$paypalID = 'sb-qhqvr41155629@business.example.com'; // Business Email
$success_url = 'http://localhost/cleckbasket_ecommerce/success.php';
$cancel_url = 'http://localhost/cleckbasket_ecommerce/cancel.php';

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
            $cart_items[] = $row;
            $total_amount += $row['PRICE'] * $row['QUANTITY'];
        }
    } else {
        $error_message = "Error fetching cart items.";
    }
    oci_free_statement($stmt_cart);
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
        }
    } else {
        $error_message = "Error fetching product details.";
    }
    oci_free_statement($stmt_product);
}

// Generate three collection slot dates (next Wed, Thu, Fri; 24-hour advance)
$slots = [];
$current_date = new DateTime();
$current_date->modify('+24 hours'); // Enforce 24-hour advance rule
$days_allowed = ['Wednesday', 'Thursday', 'Friday'];
$max_days = 7; // Look ahead 7 days to find the next Wed, Thu, Fri
$found_days = [];

for ($i = 1; $i <= $max_days && count($found_days) < 3; $i++) {
    $date = clone $current_date;
    $date->modify("+$i days");
    $day_name = $date->format('l');

    if (in_array($day_name, $days_allowed) && !in_array($day_name, $found_days)) {
        $scheduled_date = $date->format('Y-m-d');
        // Check if slot exists in database
        $query_check_slot = "SELECT slot_id, scheduled_day, scheduled_date
                             FROM COLLECTION_SLOT
                             WHERE scheduled_day = :scheduled_day AND scheduled_date = TO_DATE(:scheduled_date, 'YYYY-MM-DD')";
        $stmt_check_slot = oci_parse($conn, $query_check_slot);
        oci_bind_by_name($stmt_check_slot, ':scheduled_day', $day_name);
        oci_bind_by_name($stmt_check_slot, ':scheduled_date', $scheduled_date);
        if (oci_execute($stmt_check_slot)) {
            $slot = oci_fetch_assoc($stmt_check_slot);
        } else {
            $error_message = "Error checking collection slot: " . oci_error($stmt_check_slot)['message'];
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
                $error_message = "Error checking slot capacity: " . oci_error($stmt_count)['message'];
            }
            oci_free_statement($stmt_count);
        }

        if ($total_items < 20) {
            $slots[] = [
                'SLOT_ID' => $slot_id,
                'SCHEDULED_DAY' => $day_name,
                'SCHEDULED_DATE' => $scheduled_date
            ];
            $found_days[] = $day_name;
        }
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
        list($selected_date, $selected_day) = explode('|', $selected_slot);
        // Verify slot is valid
        $slot_valid = false;
        $selected_slot_id = null;
        foreach ($slots as $slot) {
            if ($slot['SCHEDULED_DATE'] === $selected_date && $slot['SCHEDULED_DAY'] === $selected_day) {
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
                $query_insert_slot = "INSERT INTO COLLECTION_SLOT (scheduled_day, scheduled_date)
                                      VALUES (:scheduled_day, TO_DATE(:scheduled_date, 'YYYY-MM-DD'))
                                      RETURNING slot_id INTO :slot_id";
                $stmt_insert_slot = oci_parse($conn, $query_insert_slot);
                oci_bind_by_name($stmt_insert_slot, ':scheduled_day', $selected_day);
                oci_bind_by_name($stmt_insert_slot, ':scheduled_date', $selected_date);
                oci_bind_by_name($stmt_insert_slot, ':slot_id', $selected_slot_id, -1, SQLT_CHR);
                if (!oci_execute($stmt_insert_slot)) {
                    $error_message = "Error creating collection slot: " . oci_error($stmt_insert_slot)['message'];
                }
                oci_free_statement($stmt_insert_slot);
            }

            if (!$error_message) {
                // Check slot capacity
                $query_count = "SELECT SUM(op.quantity) AS total_items
                                FROM ORDERR o
                                JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                                WHERE o.fk4_slot_id = :slot_id AND o.status = 'Booked'";
                $stmt_count = oci_parse($conn, $query_count);
                oci_bind_by_name($stmt_count, ':slot_id', $selected_slot_id);
                if (oci_execute($stmt_count)) {
                    $count_row = oci_fetch_assoc($stmt_count);
                    $total_items = $count_row['TOTAL_ITEMS'] ? $count_row['TOTAL_ITEMS'] : 0;
                } else {
                    $error_message = "Error checking slot capacity: " . oci_error($stmt_count)['message'];
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
                 WHERE c.code = :coupon_code 
                 AND (c.expiry_date IS NULL OR c.expiry_date >= SYSDATE)
                 AND d.valid_upto >= SYSDATE";
                        $stmt_coupon = oci_parse($conn, $query_coupon);
                        oci_bind_by_name($stmt_coupon, ':coupon_code', $coupon_code);
                        if (oci_execute($stmt_coupon)) {
                            $coupon = oci_fetch_assoc($stmt_coupon);
                            if ($coupon) {
                                $coupon_id = $coupon['COUPON_ID'];
                                $discount_percent = $coupon['PERCENT'];
                                $total_amount = $total_amount * (1 - $discount_percent / 100);
                            } else {
                                $error_message = "Invalid or expired coupon code.";
                            }
                        } else {
                            $error_message = "Error validating coupon: " . oci_error($stmt_coupon)['message'];
                        }
                        oci_free_statement($stmt_coupon);
                    }

                    if (!$error_message) {
                        // Create order
                        $order_id = null;
                        $query_order = "INSERT INTO ORDERR (total_amount, status, placed_on, fk1_user_id, fk2_coupon_id, fk4_slot_id)
                                        VALUES (:total_amount, 'Pending', SYSDATE, :user_id, :coupon_id, :slot_id)
                                        RETURNING order_id INTO :order_id";
                        $stmt_order = oci_parse($conn, $query_order);
                        oci_bind_by_name($stmt_order, ':total_amount', $total_amount);
                        oci_bind_by_name($stmt_order, ':user_id', $user_id);
                        oci_bind_by_name($stmt_order, ':coupon_id', $coupon_id);
                        oci_bind_by_name($stmt_order, ':slot_id', $selected_slot_id);
                        oci_bind_by_name($stmt_order, ':order_id', $order_id, -1, SQLT_CHR);
                        if (oci_execute($stmt_order)) {
                            // Insert order products
                            foreach ($cart_items as $item) {
                                $query_op = "INSERT INTO ORDER_PRODUCT (order_id, product_id, quantity, price_at_purchase)
                                             VALUES (:order_id, :product_id, :quantity, :price)";
                                $stmt_op = oci_parse($conn, $query_op);
                                oci_bind_by_name($stmt_op, ':order_id', $order_id);
                                oci_bind_by_name($stmt_op, ':product_id', $item['PRODUCT_ID']);
                                oci_bind_by_name($stmt_op, ':quantity', $item['QUANTITY']);
                                oci_bind_by_name($stmt_op, ':price', $item['PRICE']);
                                oci_execute($stmt_op);
                                oci_free_statement($stmt_op);
                            }

                            // Create payment record
                            $payment_id = null;
                            $query_payment = "INSERT INTO PAYMENT (method, payment_status, paid_on, fk1_order_id)
                                              VALUES ('PayPal', 'Pending', SYSDATE, :order_id)
                                              RETURNING payment_id INTO :payment_id";
                            $stmt_payment = oci_parse($conn, $query_payment);
                            oci_bind_by_name($stmt_payment, ':order_id', $order_id);
                            oci_bind_by_name($stmt_payment, ':payment_id', $payment_id, -1, SQLT_CHR);
                            oci_execute($stmt_payment);
                            oci_free_statement($stmt_payment);

                            // Update order with payment_id
                            $query_update_order = "UPDATE ORDERR SET fk3_payment_id = :payment_id WHERE order_id = :order_id";
                            $stmt_update_order = oci_parse($conn, $query_update_order);
                            oci_bind_by_name($stmt_update_order, ':payment_id', $payment_id);
                            oci_bind_by_name($stmt_update_order, ':order_id', $order_id);
                            oci_execute($stmt_update_order);
                            oci_free_statement($stmt_update_order);

                            // Store order_id in session for PayPal
                            $_SESSION['order_id'] = $order_id;

                            // Clear cart or Buy Now session
                            if (!$buy_now) {
                                $query_clear_cart = "DELETE FROM CART_PRODUCT WHERE cart_id IN (SELECT cart_id FROM CART WHERE fk1_user_id = :user_id)";
                                $stmt_clear_cart = oci_parse($conn, $query_clear_cart);
                                oci_bind_by_name($stmt_clear_cart, ':user_id', $user_id);
                                oci_execute($stmt_clear_cart);
                                oci_free_statement($stmt_clear_cart);
                            }
                            unset($_SESSION['buy_now']);

                            $success_message = "Order created. Proceed to PayPal for payment.";
                        } else {
                            $error_message = "Error creating order: " . oci_error($stmt_order)['message'];
                        }
                        oci_free_statement($stmt_order);
                    }
                }
            }
        }
    }
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
                            <div class="flex justify-between font-semibold">
                                <span>Total</span>
                                <span>$<?php echo number_format($total_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collection Slot Selection -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Select Collection Slot</h2>
                    <form id="checkout-form" action="" method="POST" class="space-y-4">
                        <div>
                            <label for="slot_select" class="block text-sm font-medium text-gray-700">Collection Slot</label>
                            <select name="slot_select" id="slot_select" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm" required>
                                <option value="">Select a slot</option>
                                <?php foreach ($slots as $slot): ?>
                                    <option value="<?php echo htmlspecialchars($slot['SCHEDULED_DATE'] . '|' . $slot['SCHEDULED_DAY']); ?>">
                                        <?php echo htmlspecialchars($slot['SCHEDULED_DAY'] . ', ' . date('Y-m-d', strtotime($slot['SCHEDULED_DATE']))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Coupon Code -->
                        <div>
                            <label for="coupon_code" class="block text-sm font-medium text-gray-700">Coupon Code</label>
                            <input type="text" name="coupon_code" id="coupon_code" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm" placeholder="Enter coupon code">
                        </div>

                        <!-- PayPal Payment Form -->
                        <?php if (isset($_SESSION['order_id'])): ?>
                            <form action="<?php echo $paypalURL; ?>" method="post" class="mt-4">
                                <input type="hidden" name="business" value="<?php echo $paypalID; ?>">
                                <input type="hidden" name="cmd" value="_xclick">
                                <input type="hidden" name="item_name" value="CleckBasket Order #<?php echo htmlspecialchars($_SESSION['order_id']); ?>">
                                <input type="hidden" name="item_number" value="<?php echo htmlspecialchars($_SESSION['order_id']); ?>">
                                <input type="hidden" name="amount" value="<?php echo number_format($total_amount, 2); ?>">
                                <input type="hidden" name="currency_code" value="USD">
                                <input type="hidden" name="return" value="<?php echo $success_url; ?>">
                                <input type="hidden" name="cancel_return" value="<?php echo $cancel_url; ?>">
                                <input type="image" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynow_LG.gif" alt="PayPal - The safer, easier way to pay online">
                                <img alt="" border="0" width="1" height="1" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif">
                            </form>
                        <?php else: ?>
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
    <?php include_once 'includes/footer.php'; ?>
    <?php if (isset($conn) && $conn) oci_close($conn); ?>
</body>

</html>