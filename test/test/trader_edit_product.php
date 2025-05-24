<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as trader
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['usertype'] !== 'TRADER') {
    error_log("Access denied: loggedin=" . ($_SESSION['loggedin'] ?? 'not set') . ", usertype=" . ($_SESSION['usertype'] ?? 'not set'));
    $_SESSION['login_error'] = "Please log in as a trader to access the dashboard.";
    header("Location: login.php");
    exit;
}

require_once 'php_logic/connect.php'; // Connect to Oracle DB

// Get trader information
$user_id = $_SESSION['user_id'] ?? null;

// Check trader status
$query_trader = "SELECT status FROM TRADER WHERE user_id = :user_id";
$stmt_trader = oci_parse($conn, $query_trader);
oci_bind_by_name($stmt_trader, ':user_id', $user_id);
if (!oci_execute($stmt_trader)) {
    $e = oci_error($stmt_trader);
    error_log("Failed to fetch trader status: " . $e['message']);
    header("Location: login.php");
    exit();
}
$trader_row = oci_fetch_assoc($stmt_trader);
if (!$trader_row || $trader_row['STATUS'] !== 'Enabled') {
    error_log("Trader not approved or not found: " . $user_id);
    $_SESSION['error_message'] = "Your trader account is not approved.";
    header("Location: login.php");
    exit();
}
oci_free_statement($stmt_trader);

// Get shop information
$query_shop = "SELECT shop_id, name FROM SHOP WHERE fk1_user_id = :user_id";
$stmt_shop = oci_parse($conn, $query_shop);
oci_bind_by_name($stmt_shop, ':user_id', $user_id);
oci_execute($stmt_shop);
$shop_row = oci_fetch_assoc($stmt_shop);
oci_free_statement($stmt_shop);
if (!$shop_row) {
    header("Location: create_shop.php");
    exit();
}
$shop_id = $shop_row['SHOP_ID'];

// Get product ID from URL
$product_id = isset($_GET['id']) ? $_GET['id'] : 0;
if ($product_id <= 0 || !is_numeric($product_id)) {
    $_SESSION['error_message'] = "Invalid product ID.";
    header("Location: trader_manage_products.php");
    exit();
}

// Fetch product details
$query_product = "SELECT p.product_id, p.name, p.description, p.price, p.stock, p.unit, p.status, p.fk3_discount_id
                 FROM PRODUCT p 
                 JOIN SHOP s ON p.fk1_shop_id = s.shop_id 
                 WHERE p.product_id = :product_id AND s.fk1_user_id = :user_id";
$stmt_product = oci_parse($conn, $query_product);
oci_bind_by_name($stmt_product, ':product_id', $product_id);
oci_bind_by_name($stmt_product, ':user_id', $user_id);
if (!oci_execute($stmt_product)) {
    $e = oci_error($stmt_product);
    error_log("Failed to fetch product: " . $e['message']);
    $_SESSION['error_message'] = "Failed to fetch product.";
    header("Location: trader_manage_products.php");
    exit();
}
$product = oci_fetch_assoc($stmt_product);
if (!$product) {
    $_SESSION['error_message'] = "Product not found or you do not have permission to edit it.";
    header("Location: trader_manage_products.php");
    exit();
}
oci_free_statement($stmt_product);

// Get image path
$image_path = "get_product_image.php?id=" . htmlspecialchars($product['PRODUCT_ID']);

// Get all categories for dropdown (optional, if you want to allow category editing)
$categories = [];
$query_categories = "SELECT category_id, name FROM PRODUCT_CATEGORY";
$stmt_categories = oci_parse($conn, $query_categories);
oci_execute($stmt_categories);
while ($row = oci_fetch_assoc($stmt_categories)) {
    $categories[] = $row;
}
oci_free_statement($stmt_categories);

// Get all non-expired discounts for dropdown
$discounts = [];
$query_discounts = "SELECT discount_id, percent, valid_upto FROM DISCOUNT WHERE valid_upto > SYSDATE ORDER BY percent";
$stmt_discounts = oci_parse($conn, $query_discounts);
oci_execute($stmt_discounts);
while ($row = oci_fetch_assoc($stmt_discounts)) {
    $discounts[] = $row;
}
oci_free_statement($stmt_discounts);

include_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - CleckBasket Trader Center</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .required:after {
            content: "*";
            color: red;
        }
        .sidebar-item {
            transition: all 0.3s;
        }
        .sidebar-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Sidebar (consistent with trader_manage_products.php) -->
        <aside id="sidebar" class="w-full md:w-64 bg-white shadow-md md:min-h-screen hidden md:block">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-3">
                        <img src="assets/images/CLeckBasketLogo.jpg" alt="CleckBasket Logo" class="w-full h-full object-contain rounded-lg">
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">CleckBasket</h1>
                        <h2 class="text-lg text-gray-600">Trader Center</h2>
                    </div>
                </div>
            </div>
            <nav class="mt-6">
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="2" y="3" width="20" height="18" rx="2" stroke-width="2" />
                                <line x1="8" y1="3" x2="8" y2="21" stroke-width="2" />
                            </svg>
                            <span class="text-lg font-medium">Products</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="trader_manage_products.php" class="block py-2 text-orange-500 font-semibold">Manage Products</a>
                        <a href="trader_add_product.php" class="block py-2 text-gray-600 hover:text-orange-500">Add Products</a>
                    </div>
                </div>
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <span class="text-lg font-medium">Orders & Review</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="trader_orders.php" class="block py-2 text-gray-600 hover:text-orange-500">Orders</a>
                    </div>
                </div>
                <div class="px-4 py-2">
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
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="trader_profile.php" class="block py-2 text-gray-600 hover:text-orange-500">Settings</a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="container mx-auto px-6 py-8">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6 text-center">
                    <h2 class="text-3xl font-bold text-gray-800">Edit Product</h2>
                    <p class="text-gray-600 mt-2">Update your product details.</p>
                </div>

                <?php if (!empty($_SESSION['error_message'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <form action="trader_update_product.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>">
                    <div class="mb-6">
                        <label for="name" class="block text-gray-700 text-lg mb-2 required">Product Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['NAME']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                        <p class="text-sm text-gray-500 mt-1">Must be unique for your shop and not exceed 30 characters.</p>
                    </div>
                    <div class="mb-6">
                        <label for="description" class="block text-gray-700 text-lg mb-2 required">Description</label>
                        <textarea id="description" name="description" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" rows="4" required><?php echo htmlspecialchars($product['DESCRIPTION']); ?></textarea>
                    </div>
                    <div class="mb-6">
                        <label for="price" class="block text-gray-700 text-lg mb-2 required">Price</label>
                        <input type="number" id="price" name="price" value="<?php echo $product['PRICE']; ?>" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    <div class="mb-6">
                        <label for="stock" class="block text-gray-700 text-lg mb-2 required">Stock</label>
                        <input type="number" id="stock" name="stock" value="<?php echo $product['STOCK']; ?>" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    <div class="mb-6">
                        <label for="unit" class="block text-gray-700 text-lg mb-2">Unit</label>
                        <input type="text" id="unit" name="unit" value="<?php echo htmlspecialchars($product['UNIT']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="mb-6">
                        <label for="status" class="block text-gray-700 text-lg mb-2">Status</label>
                        <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="Enable" <?php echo $product['STATUS'] === 'Enable' ? 'selected' : ''; ?>>Enable</option>
                            <option value="Disable" <?php echo $product['STATUS'] === 'Disable' ? 'selected' : ''; ?>>Disable</option>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label for="discount" class="block text-gray-700 text-lg mb-2">Discount</label>
                        <select id="discount" name="discount" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="none" <?php echo empty($product['FK3_DISCOUNT_ID']) ? 'selected' : ''; ?>>None</option>
                            <?php foreach ($discounts as $discount): ?>
                                <option value="<?php echo htmlspecialchars($discount['DISCOUNT_ID']); ?>" <?php echo $product['FK3_DISCOUNT_ID'] == $discount['DISCOUNT_ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($discount['PERCENT'] . '% (Valid until ' . $discount['VALID_UPTO'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Only non-expired discounts are shown.</p>
                    </div>
                    <div class="mb-6">
                        <label for="image" class="block text-gray-700 text-lg mb-2">Product Image</label>
                        <input type="file" id="image" name="image" class="w-full px-4 py-2 border border-gray-300 rounded-md" accept="image/*">
                        <?php if (!empty($image_path)): ?>
                            <p class="mt-2">Current Image: <img src="<?php echo $image_path; ?>" alt="Product Image" class="w-32 h-32 object-cover mt-2"></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-6 rounded-lg text-lg">Update Product</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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