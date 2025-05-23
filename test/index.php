<?php
// Ensure session is started at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'php_logic/connect.php'; // Connect to Oracle DB
include_once 'includes/header.php';

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    error_log("index.php - Generated new CSRF token: " . $_SESSION['csrf_token']);
}

// Retrieve and clear login success message from session
$login_success_message = $_SESSION['login_success_message'] ?? null;
if ($login_success_message) unset($_SESSION['login_success_message']);

// Fetch Categories from Database
$categories = [];
$query_categories = "SELECT category_id, name as category_name FROM PRODUCT_CATEGORY ORDER BY name ASC";
$stmt_categories = oci_parse($conn, $query_categories);
if (oci_execute($stmt_categories)) {
    while ($row = oci_fetch_assoc($stmt_categories)) {
        // For categories, try to get a product count
        $count_query = "SELECT COUNT(*) AS PRODUCT_COUNT FROM PRODUCT WHERE fk2_category_id = :cat_id_bv AND stock > 0";
        $stmt_count = oci_parse($conn, $count_query);
        oci_bind_by_name($stmt_count, ":cat_id_bv", $row['CATEGORY_ID']);
        oci_execute($stmt_count);
        $product_count_row = oci_fetch_assoc($stmt_count);
        $row['PRODUCT_COUNT'] = $product_count_row ? $product_count_row['PRODUCT_COUNT'] : 0;
        oci_free_statement($stmt_count);
        $categories[] = $row;
    }
} else {
    $e = oci_error($stmt_categories);
    error_log("OCI Error fetching categories: " . $e['message']);
}
oci_free_statement($stmt_categories);

// Fetch Featured Products from Database (e.g., 4 most recently added active products)
$featured_products = [];
$query_featured_products = "SELECT * FROM (
                                SELECT p.product_id, p.name as product_name, p.price, 'unit' as unit,
                                       'assets/images/products/default.png' as image_path, p.stock as stock_available,
                                       c.name as category_name
                                FROM PRODUCT p
                                JOIN PRODUCT_CATEGORY c ON p.fk2_category_id = c.category_id
                                WHERE p.stock > 0
                                ORDER BY p.product_id DESC
                            ) WHERE ROWNUM <= 4";

$stmt_featured_products = oci_parse($conn, $query_featured_products);
if (oci_execute($stmt_featured_products)) {
    while ($row = oci_fetch_assoc($stmt_featured_products)) {
        $featured_products[] = $row;
    }
} else {
    $e = oci_error($stmt_featured_products);
    error_log("OCI Error fetching featured products: " . $e['message']);
}
oci_free_statement($stmt_featured_products);
?>

<div class="container mx-auto px-6 py-8">
    <!-- Display success/error messages -->
    <?php if ($login_success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?php echo htmlspecialchars($login_success_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['cart_success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6">
            <p><?php echo htmlspecialchars($_SESSION['cart_success']); ?></p>
        </div>
        <?php unset($_SESSION['cart_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['cart_error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
            <p><?php echo htmlspecialchars($_SESSION['cart_error']); ?></p>
            <?php if ($_SESSION['cart_error'] === "Cannot add product: Cart is limited to 20 items."): ?>
                <p class="mt-2">Please remove some items to add new ones.</p>
            <?php endif; ?>
        </div>
        <?php unset($_SESSION['cart_error']); ?>
    <?php endif; ?>

<!-- Hero Section -->
<section class="bg-gray-800 rounded-lg shadow-lg p-8 md:p-12 text-center mb-12 relative" style="background-image: url('assets/images/Banner.jpg'); background-size: cover; background-position: center;">
    <!-- Overlay for text readability -->
    <div class="absolute inset-0 bg-black opacity-50 rounded-lg"></div>
    <!-- Content -->
    <div class="relative z-10">
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Welcome to CleckBasket</h1>
        <p class="text-lg text-gray-200 mb-8">Discover a wide range of fresh groceries from local shops. Explore our categories and start shopping today!</p>
        <a href="shop.php" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 px-8 rounded-lg text-lg transition duration-300">Shop All Products</a>
    </div>
</section>

    <!-- Categories Section -->
    <?php if (!empty($categories)): ?>
    <section class="mb-12">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Shop by Category</h2>
        <p class="text-center text-gray-600 mb-8">Click on a category to explore the products available.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
            <?php foreach ($categories as $category): ?>
            <?php
                // Dynamically set background image based on category name
                $background_image = 'assets/images/default_background.jpg'; // Default image
                if ($category['CATEGORY_NAME'] === 'Bakery') {
                    $background_image = 'assets/images/Bakery.jpg';
                } elseif ($category['CATEGORY_NAME'] === 'Butchers') {
                    $background_image = 'assets/images/Butchers.jpg';
                } elseif ($category['CATEGORY_NAME'] === 'Fishmonger') {
                    $background_image = 'assets/images/Fishmonger.jpg';
                }elseif ($category['CATEGORY_NAME'] === 'Greengrocer') {
                    $background_image = 'assets/images/Greengrocer.jpg';
                }elseif ($category['CATEGORY_NAME'] === 'Delicatessen') {
                    $background_image = 'assets/images/Delicatessen.jpg';
                }
            ?>
            <div class="bg-white rounded-lg shadow-md p-6 text-center hover:shadow-xl transition duration-300 relative" style="background-image: url('<?php echo $background_image; ?>'); background-size: cover; background-position: center;">
                <!-- Overlay to ensure text readability -->
                <div class="absolute inset-0 bg-black opacity-30 rounded-lg"></div>
                <!-- Content -->
                <div class="relative z-10">
                    <h3 class="text-xl font-semibold text-white mb-2"><?php echo htmlspecialchars($category['CATEGORY_NAME']); ?></h3>
                    <p class="text-sm text-gray-200 mb-4"><?php echo htmlspecialchars($category['PRODUCT_COUNT']); ?> products</p>
                    <a href="shop.php?category[]=<?php echo urlencode($category['CATEGORY_ID']); ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-md text-sm transition duration-300">Explore</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <section class="mb-12">
        <p class="text-center text-gray-600">No categories available at the moment. Please check back later.</p>
    </section>
    <?php endif; ?>

    <!-- Featured Products Section -->
    <?php if (!empty($featured_products)): ?>
    <section>
        <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center">Featured Products</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <?php foreach ($featured_products as $product): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition duration-300 flex flex-col">
                <a href="product_detail.php?id=<?php echo $product['PRODUCT_ID']; ?>" class="block">
                    <div class="h-48 bg-gray-200 flex items-center justify-center text-gray-500">
                        <img src="get_product_image.php?id=<?php echo $product['PRODUCT_ID']; ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>" class="h-full w-full object-cover">
                    </div>
                </a>
                <div class="p-6 flex flex-col flex-grow">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2"><a href="product_detail.php?id=<?php echo $product['PRODUCT_ID']; ?>" class="hover:text-orange-600"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></a></h3>
                    <p class="text-sm text-gray-500 mb-1">Category: <?php echo htmlspecialchars($product['CATEGORY_NAME']); ?></p>
                    <p class="text-orange-500 font-bold text-lg mb-1">$<?php echo htmlspecialchars(number_format($product['PRICE'], 2)); ?> <?php if ($product['UNIT']) echo "/ " . htmlspecialchars($product['UNIT']); ?></p>
                    <p class="text-sm text-gray-600 mb-3">In Stock: <?php echo htmlspecialchars($product['STOCK_AVAILABLE'] > 0 ? $product['STOCK_AVAILABLE'] : 'Out of Stock'); ?></p>
                    <form action="add_to_cart.php" method="POST" class="mt-auto">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="product_id" value="<?php echo $product['PRODUCT_ID']; ?>">
                        <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>">
                        <input type="hidden" name="price" value="<?php echo htmlspecialchars($product['PRICE']); ?>">
                        <?php if ($product['STOCK_AVAILABLE'] > 0): ?>
                        <div class="flex items-center mb-4">
                            <label for="quantity_<?php echo $product['PRODUCT_ID']; ?>" class="sr-only">Quantity</label>
                            <input type="number" id="quantity_<?php echo $product['PRODUCT_ID']; ?>" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['STOCK_AVAILABLE']); ?>" class="w-16 text-center border border-gray-300 rounded-md py-1 px-2 mr-2">
                        </div>
                        <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-md transition duration-300">Add to Cart</button>
                        <?php else: ?>
                        <p class="text-red-500 font-semibold">Out of Stock</p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-10">
            <a href="shop.php" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 px-8 rounded-lg text-lg transition duration-300">See More Products</a>
        </div>
    </section>
    <?php else: ?>
    <section>
        <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center">Featured Products</h2>
        <p class="text-center text-gray-600">No featured products available at the moment. Please check back later.</p>
    </section>
    <?php endif; ?>
</div>

<?php
if (isset($conn)) oci_close($conn);
include_once 'includes/footer.php';
?>