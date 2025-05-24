<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Check if user is logged in as trader
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['usertype'] !== 'TRADER') {
    $_SESSION['login_error'] = "Please log in as a trader to access the dashboard.";
    header("Location: login.php");
    exit;
}
require_once 'php_logic/connect.php'; // Connect to Oracle DB
// Get trader information
$user_id = $_SESSION['user_id'] ?? null;

// Display any messages
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error']);
unset($_SESSION['success']);

// Get trader status
$trader_status = 'Pending';
$query_trader = "SELECT status FROM TRADER WHERE user_id = :user_id";
$stmt_trader = oci_parse($conn, $query_trader);
oci_bind_by_name($stmt_trader, ':user_id', $user_id);
oci_execute($stmt_trader);
$trader_row = oci_fetch_assoc($stmt_trader);
if ($trader_row) {
    $trader_status = $trader_row['STATUS'];
}
oci_free_statement($stmt_trader);

// Get all shops for this trader
$shops = [];
$active_shop_id = null;
$active_shop_name = "";
$active_shop_action = null;
$active_shop_status = null;

$query_shops = "SELECT shop_id, name, type, approval_status, ACTION FROM SHOP WHERE fk1_user_id = :user_id ORDER BY shop_id";
$stmt_shops = oci_parse($conn, $query_shops);
oci_bind_by_name($stmt_shops, ':user_id', $user_id);
oci_execute($stmt_shops);

while ($shop_row = oci_fetch_assoc($stmt_shops)) {
    $shops[] = $shop_row;
    // Set the first shop as active by default
    if (!$active_shop_id) {
        $active_shop_id = $shop_row['SHOP_ID'];
        $active_shop_name = $shop_row['NAME'];
        $active_shop_action = $shop_row['ACTION'];
        $active_shop_status = $shop_row['APPROVAL_STATUS'];
    }
}
oci_free_statement($stmt_shops);

// If no shops found, redirect to become trader page
if (empty($shops)) {
    header("Location: become_trader.php");
    exit;
}

// Check if a specific shop is selected
if (isset($_GET['shop_id']) && !empty($_GET['shop_id'])) {
    $selected_shop_id = $_GET['shop_id'];
    foreach ($shops as $shop) {
        if ($shop['SHOP_ID'] == $selected_shop_id) {
            $active_shop_id = $shop['SHOP_ID'];
            $active_shop_name = $shop['NAME'];
            $active_shop_action = $shop['ACTION'];
            $active_shop_status = $shop['APPROVAL_STATUS'];
            break;
        }
    }
}

// Get order statistics for the active shop
$unpaid_count = 0;
$pending_count = 0;
$to_review_count = 0;
$query_unpaid = "SELECT COUNT(*) as count FROM ORDERR o
                 JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                 JOIN PRODUCT p ON op.product_id = p.product_id
                 WHERE p.fk1_shop_id = :shop_id AND o.status = 'Unpaid' AND p.status = 'Enable'";
$stmt_unpaid = oci_parse($conn, $query_unpaid);
oci_bind_by_name($stmt_unpaid, ':shop_id', $active_shop_id);
oci_execute($stmt_unpaid);
$row_unpaid = oci_fetch_assoc($stmt_unpaid);
if ($row_unpaid) {
    $unpaid_count = $row_unpaid['COUNT'];
}
oci_free_statement($stmt_unpaid);
$query_pending = "SELECT COUNT(*) as count FROM ORDERR o
                  JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                  JOIN PRODUCT p ON op.product_id = p.product_id
                  WHERE p.fk1_shop_id = :shop_id AND o.status = 'Pending' AND p.status = 'Enable'";
$stmt_pending = oci_parse($conn, $query_pending);
oci_bind_by_name($stmt_pending, ':shop_id', $active_shop_id);
oci_execute($stmt_pending);
$row_pending = oci_fetch_assoc($stmt_pending);
if ($row_pending) {
    $pending_count = $row_pending['COUNT'];
}
oci_free_statement($stmt_pending);
$query_review = "SELECT COUNT(*) as count FROM ORDERR o
                 JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                 JOIN PRODUCT p ON op.product_id = p.product_id
                 WHERE p.fk1_shop_id = :shop_id AND o.status = 'Delivered' AND p.status = 'Enable'";
$stmt_review = oci_parse($conn, $query_review);
oci_bind_by_name($stmt_review, ':shop_id', $active_shop_id);
oci_execute($stmt_review);
$row_review = oci_fetch_assoc($stmt_review);
if ($row_review) {
    $to_review_count = $row_review['COUNT'];
}
oci_free_statement($stmt_review);
include_once 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trader Dashboard - CleckBasket</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="hidden md:block md:w-64 bg-white shadow-md">
            <div class="p-6">
                <div class="flex items-center mb-8">
                    <div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
                        <img src="assets/images/CLeckBasketLogo.jpg" alt="CleckBasket Logo" class="w-full h-full object-contain rounded-lg">
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">CleckBasket</h1>
                </div>
                <nav class="space-y-4">
                    <!-- Shops Section -->
                    <div class="mb-6">
                        <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <span class="text-lg font-medium">My Shops</span>
                            </div>
                            <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="ml-9 mt-2 sidebar-content">
                            <?php foreach ($shops as $shop): ?>
                                <a href="trader_profile.php?shop_id=<?php echo $shop['SHOP_ID']; ?>" 
                                   class="block py-2 <?php echo ($active_shop_id == $shop['SHOP_ID']) ? 'text-orange-500 font-semibold' : 'text-gray-600'; ?>">
                                    <?php echo htmlspecialchars($shop['NAME']); ?>
                                    <?php if ($shop['APPROVAL_STATUS'] == 'Pending'): ?>
                                        <span class="text-xs text-yellow-600 ml-1">(Pending)</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                            
                            <?php if (count($shops) < 2): ?>
                                <a href="add_shop.php" class="block py-2 text-green-600 hover:text-green-800">
                                    <span class="inline-flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Add New Shop
                                    </span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Products Section -->
                    <div class="mb-6">
                        <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <span class="text-lg font-medium">Products</span>
                            </div>
                            <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="ml-9 mt-2 sidebar-content">
                            <a href="trader_add_product.php?shop_id=<?php echo $active_shop_id; ?>" class="block py-2 text-gray-600">Add Product</a>
                            <a href="trader_manage_products.php?shop_id=<?php echo $active_shop_id; ?>" class="block py-2 text-gray-600">Manage Products</a>
                        </div>
                    </div>
                    
                    <!-- Orders Section -->
                    <div class="mb-6">
                        <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                                <span class="text-lg font-medium">Orders</span>
                            </div>
                            <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="ml-9 mt-2 sidebar-content">
                            <a href="trader_orders.php?shop_id=<?php echo $active_shop_id; ?>&status=all" class="block py-2 text-gray-600">All Orders</a>
                            <a href="trader_orders.php?shop_id=<?php echo $active_shop_id; ?>&status=unpaid" class="block py-2 text-gray-600">Unpaid <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full"><?php echo $unpaid_count; ?></span></a>
                            <a href="trader_orders.php?shop_id=<?php echo $active_shop_id; ?>&status=pending" class="block py-2 text-gray-600">Pending <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full"><?php echo $pending_count; ?></span></a>
                            <a href="trader_orders.php?shop_id=<?php echo $active_shop_id; ?>&status=delivered" class="block py-2 text-gray-600">To Review <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full"><?php echo $to_review_count; ?></span></a>
                        </div>
                    </div>
                    
                    <!-- Account Section -->
                    <div class="mb-6">
                        <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span class="text-lg font-medium">My Account</span>
                            </div>
                            <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="ml-9 mt-2 sidebar-content">
                            <a href="trader_profile.php" class="block py-2 text-gray-600">Settings</a>
                        </div>
                    </div>
                </nav>
            </div>
        </aside>
        <!-- Main Content -->
        <main class="flex-1">
            <!-- Mobile Header -->
            <header class="md:hidden bg-white shadow-md p-4 flex justify-between items-center">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
                        <img src="assets/images/CLeckBasketLogo.jpg" alt="CleckBasket Logo" class="w-full h-full object-contain rounded-lg">
                    </div>
                    <h1 class="text-lg font-bold text-gray-800">CleckBasket</h1>
                </div>
                <button id="menu-toggle" class="text-gray-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>
            </header>
            <div class="container mx-auto px-6 py-8">
                <!-- Error/Success Messages -->
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Banner -->
                <div class="bg-white rounded-lg shadow-md p-8 mb-8 text-center">
                    <h2 class="text-3xl font-bold text-gray-800">Welcome to Your Trader Dashboard</h2>
                    <p class="text-gray-600 mt-2">Manage your shops, products, and orders with ease.</p>
                </div>
                
                <!-- Active Shop Info -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($active_shop_name); ?></h3>
                                <div class="flex flex-wrap items-center mt-1">
                                    <p class="text-sm text-gray-600 mr-4">Trader Status: <?php echo htmlspecialchars($trader_status); ?></p>
                                    <p class="text-sm text-gray-600 mr-4">Shop Status: 
                                        <span class="<?php echo $active_shop_status == 'Approved' ? 'text-green-600' : 'text-yellow-600'; ?>">
                                            <?php echo htmlspecialchars($active_shop_status); ?>
                                        </span>
                                    </p>
                                    <?php if (!empty($active_shop_action)): ?>
                                        <p class="text-sm text-gray-600">Last Action: <?php echo htmlspecialchars($active_shop_action); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (count($shops) < 2): ?>
                            <a href="add_shop.php" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md transition duration-300">
                                Add New Shop
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Shop Selection (Mobile Only) -->
                <div class="md:hidden bg-white rounded-lg shadow-md p-6 mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">My Shops</h3>
                    <div class="space-y-2">
                        <?php foreach ($shops as $shop): ?>
                            <a href="trader_profile.php?shop_id=<?php echo $shop['SHOP_ID']; ?>" 
                               class="block p-3 rounded-md <?php echo ($active_shop_id == $shop['SHOP_ID']) ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-700'; ?> hover:bg-orange-50">
                                <?php echo htmlspecialchars($shop['NAME']); ?>
                                <?php if ($shop['APPROVAL_STATUS'] == 'Pending'): ?>
                                    <span class="text-xs text-yellow-600 ml-1">(Pending)</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php if (count($shops) < 2): ?>
                            <a href="add_shop.php" class="block p-3 rounded-md bg-green-100 text-green-700 hover:bg-green-50">
                                <span class="inline-flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Add New Shop
                                </span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Trader Info and Notifications -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Notifications -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Important Notifications</h3>
                        <div class="flex items-start mb-4">
                            <svg class="w-5 h-5 text-gray-600 mr-2 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke-width="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
                            </svg>
                            <div>
                                <p class="text-sm text-gray-600">Reminder: Stock up packaging materials for 9.9 campaign by 12th March</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Health -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Account Health</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-md font-bold text-gray-800 mb-2">Non-Compliance Points (NCP)</h4>
                            <div class="flex items-baseline mb-2">
                                <span class="text-3xl font-bold text-gray-800 mr-2">0</span>
                                <span class="text-gray-600">Need To Improve</span>
                            </div>
                            <p class="text-sm text-gray-600">Status reflects a combination of Non-Compliance Points (NCP) and store operational metric performance.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Campaign Events -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-6">Campaign Events</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-xl transition-shadow">
                                <div class="text-center mb-4">
                                    <h4 class="text-xl font-bold text-gray-800"><?php echo sprintf("%02d:%02d:%02d", rand(0, 9), rand(0, 23), rand(0, 59)); ?></h4>
                                    <p class="text-xs text-gray-600">DAYS:HOURS:MINS</p>
                                </div>
                                <div class="bg-gray-200 h-32 mb-4 rounded-lg flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-600 mb-4">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                                <button class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 rounded-md transition duration-300">Submit Deal</button>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle for mobile
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.getElementById('sidebar');
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });
            // Sidebar item toggle
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            sidebarItems.forEach(item => {
                item.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const arrow = this.querySelector('svg:last-child');
                    content.classList.toggle('hidden');
                    arrow.classList.toggle('rotate-180');
                });
            });
        });
    </script>
</body>
</html>
<?php
if (isset($conn)) oci_close($conn);
include_once 'includes/footer.php';
?>
