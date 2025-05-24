<?php
session_start();
require_once 'php_logic/connect.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION['login_error'] = "Please log in to access your profile.";
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$first_name = '';
$last_name = '';
$email = '';
$phone_number = '';
$address = '';

// Fetch user details from the database
$query_user_details = "SELECT first_name, last_name, email, contact, address FROM Users WHERE user_id = :user_id_bv";
$stmt_user_details = oci_parse($conn, $query_user_details);
oci_bind_by_name($stmt_user_details, ":user_id_bv", $user_id);

if (oci_execute($stmt_user_details)) {
    $user = oci_fetch_assoc($stmt_user_details);
    if ($user) {
        $first_name = $user['FIRST_NAME'];
        $last_name = $user['LAST_NAME'];
        $email = $user['EMAIL'];
        $phone_number = $user['CONTACT'];
        $address = $user['ADDRESS'];
    } else {
        // Should not happen if user_id in session is valid
        $_SESSION['profile_error'] = "Could not retrieve user details.";
    }
} else {
    $e = oci_error($stmt_user_details);
    $_SESSION['profile_error'] = "Database error fetching profile: " . htmlentities($e['message']);
    error_log("OCI Error in profile.php (fetch user details): " . $e['message']);
}
oci_free_statement($stmt_user_details);

// Retrieve and clear messages
$profile_success_message = $_SESSION['profile_success_message'] ?? null;
$profile_error_message = $_SESSION['profile_error'] ?? null;
if ($profile_success_message) unset($_SESSION['profile_success_message']);
if ($profile_error_message) unset($_SESSION['profile_error']);

// Pagination for orders
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$orders_per_page = 5;
$offset = ($page - 1) * $orders_per_page;

// Count total orders for pagination
$query_count_orders = "SELECT COUNT(*) as total FROM ORDERR WHERE fk1_user_id = :user_id";
$stmt_count_orders = oci_parse($conn, $query_count_orders);
oci_bind_by_name($stmt_count_orders, ':user_id', $user_id);
oci_execute($stmt_count_orders);
$row_count = oci_fetch_assoc($stmt_count_orders);
$total_orders = $row_count['TOTAL'];
$total_pages = ceil($total_orders / $orders_per_page);
oci_free_statement($stmt_count_orders);

// Fetch user's orders with pagination
$query_orders = "SELECT * FROM (
                    SELECT a.*, rownum rnum FROM (
                        SELECT o.order_id, o.total_amount, o.status, o.placed_on, cs.scheduled_day, cs.scheduled_date, cs.scheduled_time
                        FROM ORDERR o
                        JOIN COLLECTION_SLOT cs ON o.fk4_slot_id = cs.slot_id
                        WHERE o.fk1_user_id = :user_id
                        ORDER BY o.placed_on DESC
                    ) a WHERE rownum <= :max_row
                  ) WHERE rnum > :min_row";

$stmt_orders = oci_parse($conn, $query_orders);
oci_bind_by_name($stmt_orders, ':user_id', $user_id);
$max_row = $offset + $orders_per_page;
$min_row = $offset;
oci_bind_by_name($stmt_orders, ':max_row', $max_row);
oci_bind_by_name($stmt_orders, ':min_row', $min_row);
oci_execute($stmt_orders);

// Fetch orders into array
$orders = [];
while ($order = oci_fetch_assoc($stmt_orders)) {
    $order_id = $order['ORDER_ID'];
    
    // Fetch products for this order
    $query_products = "SELECT op.quantity, p.name, p.price
                      FROM ORDER_PRODUCT op
                      JOIN PRODUCT p ON op.product_id = p.product_id
                      WHERE op.order_id = :order_id";
    $stmt_products = oci_parse($conn, $query_products);
    oci_bind_by_name($stmt_products, ':order_id', $order_id);
    oci_execute($stmt_products);
    
    $products = [];
    while ($product = oci_fetch_assoc($stmt_products)) {
        $products[] = $product;
    }
    oci_free_statement($stmt_products);
    
    $order['products'] = $products;
    $orders[] = $order;
}
oci_free_statement($stmt_orders);
?>

<div class="container mx-auto px-6 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Your Profile</h1>

    <?php if ($profile_success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?php echo htmlspecialchars($profile_success_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($profile_error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo htmlspecialchars($profile_error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-8 mb-8">
        <h2 class="text-2xl font-semibold text-gray-700 mb-6">Account Details</h2>
        <form action="update_profile.php" method="POST">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="first_name" class="block text-gray-700 text-sm font-bold mb-2">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="last_name" class="block text-gray-700 text-sm font-bold mb-2">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
            </div>
            <div class="mb-6">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address (cannot be changed)</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" readonly>
            </div>
            <div class="mb-6">
                <label for="phone_number" class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" pattern="[0-9]{10}" title="Must be exactly 10 digits">
            </div>
            <div class="mb-6">
                <label for="address" class="block text-gray-700 text-sm font-bold mb-2">Address Line</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" maxlength="30">
            </div>
            <div class="flex justify-end">
                <button type="submit" name="update_details" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Details
                </button>
            </div>
        </form>
    </div>

    <!-- Order History Section -->
    <div class="bg-white shadow-md rounded-lg p-8">
        <h2 class="text-2xl font-semibold text-gray-700 mb-6">Order History</h2>
        
        <?php if (empty($orders)): ?>
            <div class="text-center py-8">
                <p class="text-gray-600">You haven't placed any orders yet.</p>
                <a href="shop.php" class="mt-4 inline-block bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 p-4 flex flex-col md:flex-row justify-between items-start md:items-center">
                            <div>
                                <p class="text-sm text-gray-500">Order #<?php echo htmlspecialchars($order['ORDER_ID']); ?></p>
                                <p class="text-sm text-gray-500">Placed on: <?php echo date('F j, Y', strtotime($order['PLACED_ON'])); ?></p>
                            </div>
                            <div class="mt-2 md:mt-0">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($order['STATUS']) {
                                        case 'Booked':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'Pending':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'Delivered':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'Cancelled':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo htmlspecialchars($order['STATUS']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="p-4 border-t border-gray-200">
                            <h3 class="text-lg font-medium text-gray-700 mb-2">Collection Details</h3>
                            <p class="text-sm text-gray-600">
                                <?php 
                                    $date = new DateTime($order['SCHEDULED_DATE']);
                                    echo htmlspecialchars($order['SCHEDULED_DAY']) . ', ' . $date->format('F j, Y'); 
                                ?>
                            </p>
                            <p class="text-sm text-gray-600">Time: <?php echo htmlspecialchars(str_replace('-', ' - ', $order['SCHEDULED_TIME'])); ?></p>
                        </div>
                        
                        <div class="p-4 border-t border-gray-200">
                            <h3 class="text-lg font-medium text-gray-700 mb-2">Order Items</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($order['products'] as $product): ?>
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($product['NAME']); ?>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    $<?php echo number_format($product['PRICE'], 2); ?>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($product['QUANTITY']); ?>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    $<?php echo number_format($product['PRICE'] * $product['QUANTITY'], 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="p-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
                            <span class="text-lg font-semibold text-gray-700">Total:</span>
                            <span class="text-lg font-bold text-gray-900">$<?php echo number_format($order['TOTAL_AMOUNT'], 2); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center mt-8">
                    <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-orange-600 bg-orange-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php 
if (isset($conn)) oci_close($conn);
require_once 'includes/footer.php'; 
?>