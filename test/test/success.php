<?php
session_start();
require_once 'php_logic/connect.php'; // Oracle DB connection
require_once 'vendor/autoload.php'; // PHPMailer (assuming Composer is used)
require_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Log session data for debugging
error_log("Session - loggedin: " . (isset($_SESSION['loggedin']) ? $_SESSION['loggedin'] : 'not set') .
    ", user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') .
    ", order_id: " . (isset($_SESSION['order_id']) ? $_SESSION['order_id'] : 'not set'));

// Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    $_SESSION['login_error'] = "Please log in to view this page.";
    header("Location: login.php");
    exit;
}

// Get order_id from session or PayPal response
$order_id = null;

// First try to get order_id from PayPal custom parameter
if (isset($_GET['custom'])) {
    $order_id = filter_var($_GET['custom'], FILTER_SANITIZE_NUMBER_INT);
    error_log("PayPal custom order_id: " . $order_id);
} 
// If not in GET params, try to get from session
else if (isset($_SESSION['order_id'])) {
    $order_id = $_SESSION['order_id'];
    error_log("Using session order_id: " . $order_id);
}

// Log PayPal response for debugging
error_log("PayPal response: " . print_r($_GET, true));

// Check if we have a valid order_id
if (!$order_id) {
    $_SESSION['error'] = "Unable to identify your order. Please contact support.";
    error_log("No valid order_id found in GET params or session");
    header("Location: cart.php");
    exit;
}

// We'll assume payment is completed since user was redirected to success page
// This is a simplification - in production, you should implement IPN or PDT for verification
$payment_status = 'completed';
error_log("Assuming payment_status: $payment_status");

// Begin transaction - we'll use OCI_NO_AUTO_COMMIT with each statement instead
// and commit at the end

// Check PAYMENT record exists
$query_check_payment = "SELECT payment_id, payment_status FROM PAYMENT WHERE fk1_order_id = :order_id";
$stmt_check_payment = oci_parse($conn, $query_check_payment);
oci_bind_by_name($stmt_check_payment, ':order_id', $order_id);
oci_execute($stmt_check_payment);
$payment = oci_fetch_assoc($stmt_check_payment);
error_log("PAYMENT check - payment_id: " . ($payment['PAYMENT_ID'] ?? 'not found') .
    ", status: " . ($payment['PAYMENT_STATUS'] ?? 'not found'));
oci_free_statement($stmt_check_payment);
if (!$payment) {
    error_log("No PAYMENT record found for order_id: $order_id");
    $_SESSION['error'] = "Payment record not found.";
    oci_rollback($conn);
    header("Location: cart.php");
    exit;
}

// Update PAYMENT table (removed 'Pending' condition to avoid trigger conflict)
$query_update_payment = "UPDATE PAYMENT SET payment_status = 'Booked', paid_on = SYSDATE WHERE fk1_order_id = :order_id";
$stmt_update_payment = oci_parse($conn, $query_update_payment);
oci_bind_by_name($stmt_update_payment, ':order_id', $order_id);
error_log("Attempting PAYMENT update for order_id: $order_id");
if (!oci_execute($stmt_update_payment, OCI_NO_AUTO_COMMIT)) {
    $error = oci_error($stmt_update_payment);
    error_log("Error updating PAYMENT status: " . $error['message']);
    $_SESSION['error'] = "Error processing payment.";
    oci_rollback($conn);
    oci_free_statement($stmt_update_payment);
    header("Location: cart.php");
    exit;
}
error_log("PAYMENT updated successfully for order_id: $order_id");
oci_free_statement($stmt_update_payment);

// Check ORDERR record exists
$query_check_order = "SELECT order_id, status FROM ORDERR WHERE order_id = :order_id";
$stmt_check_order = oci_parse($conn, $query_check_order);
oci_bind_by_name($stmt_check_order, ':order_id', $order_id);
oci_execute($stmt_check_order);
$order_check = oci_fetch_assoc($stmt_check_order);
error_log("ORDERR check - order_id: " . ($order_check['ORDER_ID'] ?? 'not found') .
    ", status: " . ($order_check['STATUS'] ?? 'not found'));
oci_free_statement($stmt_check_order);
if (!$order_check) {
    error_log("No ORDERR record found for order_id: $order_id");
    $_SESSION['error'] = "Order record not found.";
    oci_rollback($conn);
    header("Location: cart.php");
    exit;
}

// Ensure ORDERR status is 'Booked'
$query_update_order = "UPDATE ORDERR SET status = 'Booked' WHERE order_id = :order_id";
$stmt_update_order = oci_parse($conn, $query_update_order);
oci_bind_by_name($stmt_update_order, ':order_id', $order_id);
error_log("Attempting ORDERR update for order_id: $order_id");
if (!oci_execute($stmt_update_order, OCI_NO_AUTO_COMMIT)) {
    $error = oci_error($stmt_update_order);
    error_log("Error updating ORDERR status: " . $error['message']);
    $_SESSION['error'] = "Error updating order status.";
    oci_rollback($conn);
    oci_free_statement($stmt_update_order);
    header("Location: cart.php");
    exit;
}
error_log("ORDERR updated successfully for order_id: $order_id");
oci_free_statement($stmt_update_order);

// Commit transaction
error_log("Attempting transaction commit for order_id: $order_id");
if (!oci_commit($conn)) {
    $error = oci_error($conn);
    error_log("Error committing transaction: " . $error['message']);
    $_SESSION['error'] = "Error finalizing order.";
    oci_rollback($conn);
    header("Location: cart.php");
    exit;
}
error_log("Transaction committed successfully for order_id: $order_id");

// Fetch user email
$query_user = "SELECT email FROM USERS WHERE user_id = :user_id";
$stmt_user = oci_parse($conn, $query_user);
oci_bind_by_name($stmt_user, ':user_id', $_SESSION['user_id']);
oci_execute($stmt_user);
$user = oci_fetch_assoc($stmt_user);
$user_email = $user['EMAIL'] ?? null;
oci_free_statement($stmt_user);

if (!$user_email) {
    error_log("No email found for user_id: " . $_SESSION['user_id']);
    $_SESSION['error'] = "Unable to send confirmation email. Please contact support.";
}

// Fetch order details
$query_order = "SELECT o.order_id, o.total_amount, o.status, cs.scheduled_day, cs.scheduled_date, cs.scheduled_time
                FROM ORDERR o
                JOIN COLLECTION_SLOT cs ON o.fk4_slot_id = cs.slot_id
                WHERE o.order_id = :order_id AND o.fk1_user_id = :user_id";
$stmt_order = oci_parse($conn, $query_order);
oci_bind_by_name($stmt_order, ':order_id', $order_id);
oci_bind_by_name($stmt_order, ':user_id', $_SESSION['user_id']);
oci_execute($stmt_order);
$order = oci_fetch_assoc($stmt_order);
oci_free_statement($stmt_order);

// Send order confirmation email
if ($user_email && $order) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME; 
        $mail->Password = SMTP_PASSWORD; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('no-reply@cleckbasket.com', 'CleckBasket');
        $mail->addAddress($user_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Order Confirmation - CleckBasket';
        $mail->Body = '
            <h2 style="color: #f97316;">Order Confirmation</h2>
            <p>Thank you for your order! Your payment has been successfully processed.</p>
            <h3 style="color: #333;">Order Details</h3>
            <p><strong>Order ID:</strong> ' . htmlspecialchars($order['ORDER_ID']) . '</p>
            <p><strong>Total Amount:</strong> $' . number_format($order['TOTAL_AMOUNT'], 2) . '</p>
            <p><strong>Status:</strong> ' . htmlspecialchars($order['STATUS']) . '</p>
            <p><strong>Collection Slot:</strong> ' . htmlspecialchars($order['SCHEDULED_DAY'] . ', ' . date('d M Y', strtotime($order['SCHEDULED_DATE'])) . ', ' . $order['SCHEDULED_TIME']) . '</p>
            <p style="margin-top: 20px;">We look forward to serving you!</p>
            <p><a href="http://localhost/test/shop.php" style="background-color: #f97316; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Continue Shopping</a></p>
        ';
        $mail->AltBody = "Order Confirmation\n\nThank you for your order! Your payment has been successfully processed.\n\nOrder Details:\nOrder ID: " . htmlspecialchars($order['ORDER_ID']) . "\nTotal Amount: $" . number_format($order['TOTAL_AMOUNT'], 2) . "\nStatus: " . htmlspecialchars($order['STATUS']) . "\nCollection Slot: " . htmlspecialchars($order['SCHEDULED_DAY'] . ', ' . date('d M Y', strtotime($order['SCHEDULED_DATE'])) . ', ' . $order['SCHEDULED_TIME']) . "\n\nWe look forward to serving you!";

        $mail->send();
        error_log("Order confirmation email sent to: $user_email");
    } catch (Exception $e) {
        error_log("Failed to send email: {$mail->ErrorInfo}");
        $_SESSION['error'] = "Order processed, but failed to send confirmation email. Please contact support.";
    }
}

// Clear session data
unset($_SESSION['order_id']);
unset($_SESSION['order_placed']);
unset($_SESSION['buy_now']);

// Close database connection
if (isset($conn) && $conn) {
    oci_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - CleckBasket</title>
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

        .success {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
        }
    </style>
</head>

<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container mx-auto px-6 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Order Confirmation</h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
                <p><?php echo htmlspecialchars($_SESSION['error']); ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php elseif ($order): ?>
            <div class="success p-4 rounded-md mb-6">
                <p>Thank you for your order! Your payment has been successfully processed.</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-8 heritage-bg">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Details</h2>
                <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order['ORDER_ID']); ?></p>
                <p><strong>Total Amount:</strong> $<?php echo number_format($order['TOTAL_AMOUNT'], 2); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($order['STATUS']); ?></p>
                <p><strong>Collection Slot:</strong> <?php echo htmlspecialchars($order['SCHEDULED_DAY'] . ', ' . date('d M Y', strtotime($order['SCHEDULED_DATE'])) . ', ' . $order['SCHEDULED_TIME']); ?></p>
                <a href="shop.php" class="btn-primary font-semibold py-2 px-4 rounded-md mt-4 inline-block">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 heritage-bg text-center">
                <p class="text-gray-700">Error retrieving order details.</p>
                <a href="shop.php" class="text-orange-500 hover:text-orange-700 underline mt-4 inline-block">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </div>
    <?php include_once 'includes/footer.php'; ?>
</body>

</html>