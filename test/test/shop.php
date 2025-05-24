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
    error_log("shop.php - Generated new CSRF token: " . $_SESSION['csrf_token']);
}

// Fetch all Categories for filters
$filter_categories = [];
$query_filter_categories = "SELECT category_id, name as category_name FROM PRODUCT_CATEGORY ORDER BY name ASC";
$stmt_filter_categories = oci_parse($conn, $query_filter_categories);
if (oci_execute($stmt_filter_categories)) {
    while ($row = oci_fetch_assoc($stmt_filter_categories)) {
        $filter_categories[] = $row;
    }
}
oci_free_statement($stmt_filter_categories);

// Fetch all Traders (Shops) for filters
$filter_traders = [];
$query_filter_traders = "SELECT shop_id as trader_id, name as shop_name FROM SHOP ORDER BY name ASC";
$stmt_filter_traders = oci_parse($conn, $query_filter_traders);
if (oci_execute($stmt_filter_traders)) {
    while ($row = oci_fetch_assoc($stmt_filter_traders)) {
        $filter_traders[] = $row;
    }
}
oci_free_statement($stmt_filter_traders);

// Get filter parameters from GET request
$selected_category_ids = isset($_GET['category']) && is_array($_GET['category']) ? array_map('intval', $_GET['category']) : [];
$selected_trader_ids = isset($_GET['trader']) && is_array($_GET['trader']) ? array_map('intval', $_GET['trader']) : [];
$max_price = isset($_GET['price']) && is_numeric($_GET['price']) ? (float)$_GET['price'] : null;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build Product Query
$base_query_products = "FROM PRODUCT p JOIN PRODUCT_CATEGORY c ON p.fk2_category_id = c.category_id JOIN SHOP t ON p.fk1_shop_id = t.shop_id WHERE p.stock > 0";
$where_clauses = [];
$bind_params = [];

if (!empty($selected_category_ids)) {
    $cat_placeholders = [];
    foreach ($selected_category_ids as $key => $cat_id) {
        $ph = ":cat_id_" . $key;
        $cat_placeholders[] = $ph;
        $bind_params[$ph] = $cat_id;
    }
    $where_clauses[] = "p.fk2_category_id IN (" . implode(',', $cat_placeholders) . ")";
}

if (!empty($selected_trader_ids)) {
    $trader_placeholders = [];
    foreach ($selected_trader_ids as $key => $trader_id) {
        $ph = ":trader_id_" . $key;
        $trader_placeholders[] = $ph;
        $bind_params[$ph] = $trader_id;
    }
    $where_clauses[] = "p.fk1_shop_id IN (" . implode(',', $trader_placeholders) . ")";
}

if ($max_price !== null && $max_price > 0) {
    $where_clauses[] = "p.price <= :max_price_bv";
    $bind_params[':max_price_bv'] = $max_price;
}

if (!empty($search_term)) {
    $where_clauses[] = "(LOWER(p.name) LIKE LOWER(:search_bv) OR LOWER(c.name) LIKE LOWER(:search_bv) OR LOWER(t.name) LIKE LOWER(:search_bv))";
    $bind_params[':search_bv'] = '%' . $search_term . '%';
}

$final_where_clause = "";
if (!empty($where_clauses)) {
    $final_where_clause = " AND " . implode(" AND ", $where_clauses);
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$products_per_page = 9; // Number of products per page
$offset = ($page - 1) * $products_per_page;

// Count total products for pagination
$count_query_string = "SELECT COUNT(*) AS TOTAL_PRODUCTS " . $base_query_products . $final_where_clause;
$stmt_count_products = oci_parse($conn, $count_query_string);
foreach ($bind_params as $key => $val) {
    oci_bind_by_name($stmt_count_products, $key, $bind_params[$key]);
}
oci_execute($stmt_count_products);
$total_products_row = oci_fetch_assoc($stmt_count_products);
$total_products = $total_products_row ? $total_products_row['TOTAL_PRODUCTS'] : 0;
$total_pages = ceil($total_products / $products_per_page);
oci_free_statement($stmt_count_products);

// Fetch Products for the current page
$products = [];
// Use ROWNUM for Oracle 11g compatibility
$query_products_string = "SELECT * FROM (
                            SELECT p.product_id, p.name as product_name, p.price, 'unit' as unit, 
                                   'assets/images/products/default.png' as image_path, p.stock as stock_available, 
                                   'Product description' as description, c.name as category_name, t.name as trader_name
                            " . $base_query_products . $final_where_clause . "
                            ORDER BY p.name ASC
                        )
                        WHERE ROWNUM <= :offset_bv + :limit_bv
                        AND ROWNUM > :offset_bv";

$stmt_products = oci_parse($conn, $query_products_string);
foreach ($bind_params as $key => $val) {
    oci_bind_by_name($stmt_products, $key, $bind_params[$key]);
}
oci_bind_by_name($stmt_products, ":offset_bv", $offset);
oci_bind_by_name($stmt_products, ":limit_bv", $products_per_page);

if (oci_execute($stmt_products)) {
    while ($row = oci_fetch_assoc($stmt_products)) {
        $products[] = $row;
    }
} else {
    $e = oci_error($stmt_products);
    error_log("OCI Error fetching products for shop page: " . $e['message']);
}
oci_free_statement($stmt_products);

// Display success/error messages
if (isset($_SESSION['cart_success'])) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6">';
    echo '<p>' . htmlspecialchars($_SESSION['cart_success']) . '</p>';
    echo '</div>';
    unset($_SESSION['cart_success']);
}
if (isset($_SESSION['cart_error'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">';
    echo '<p>' . htmlspecialchars($_SESSION['cart_error']) . '</p>';
    if ($_SESSION['cart_error'] === "Cannot add product: Cart is limited to 20 items.") {
        echo '<p class="mt-2">Please remove some items to add new ones.</p>';
    }
    echo '</div>';
    unset($_SESSION['cart_error']);
}
?>

<div class="container mx-auto px-6 py-8">
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Filters Sidebar -->
        <aside class="w-full md:w-1/4 bg-white p-6 rounded-lg shadow-md h-fit">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Filter Products</h3>
            <form action="shop.php" method="GET" id="filterForm">
                <!-- Search -->
                <div class="mb-6">
                    <label for="search" class="text-md font-semibold text-gray-600 mb-2 block">Search Products</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search by name, description..." class="w-full p-2 border border-gray-300 rounded-md">
                </div>

                <!-- Filter by Category -->
                <?php if (!empty($filter_categories)): ?>
                    <div class="mb-6">
                        <h4 class="text-md font-semibold text-gray-600 mb-2">Filter by Category</h4>
                        <div class="space-y-1 max-h-48 overflow-y-auto">
                            <?php foreach ($filter_categories as $category): ?>
                                <div>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="category[]" value="<?php echo $category['CATEGORY_ID']; ?>" class="form-checkbox text-orange-500 h-4 w-4" <?php echo in_array($category['CATEGORY_ID'], $selected_category_ids) ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700"><?php echo htmlspecialchars($category['CATEGORY_NAME']); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filter by Shop (Trader) -->
                <?php if (!empty($filter_traders)): ?>
                    <div class="mb-6">
                        <h4 class="text-md font-semibold text-gray-600 mb-2">Filter by Shop</h4>
                        <div class="space-y-1 max-h-48 overflow-y-auto">
                            <?php foreach ($filter_traders as $trader): ?>
                                <div>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="trader[]" value="<?php echo $trader['TRADER_ID']; ?>" class="form-checkbox text-orange-500 h-4 w-4" <?php echo in_array($trader['TRADER_ID'], $selected_trader_ids) ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700"><?php echo htmlspecialchars($trader['SHOP_NAME']); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filter by Price Range -->
                <div class="mb-6">
                    <label for="price" class="text-md font-semibold text-gray-600 mb-2 block">Max Price: <span id="priceValue"><?php echo $max_price !== null ? '$' . number_format($max_price, 2) : 'Any'; ?></span></label>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">$0</span>
                        <span class="text-sm text-gray-500">$100+</span>
                    </div>
                    <input type="range" name="price" id="priceRange" min="0" max="100" step="1" value="<?php echo $max_price ?? 100; ?>" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-orange-500 mt-1">
                </div>

                <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-md transition duration-300">Apply Filters</button>
                <a href="shop.php" class="mt-2 block text-center text-sm text-orange-500 hover:text-orange-700">Clear Filters</a>
            </form>
        </aside>

        <!-- Products Grid -->
        <main class="w-full md:w-3/4">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">All Products <?php if ($search_term) echo "(matching '" . htmlspecialchars($search_term) . "')"; ?></h1>
            <?php if (!empty($products)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($products as $product): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition duration-300 flex flex-col">
                            <a href="product_detail.php?id=<?php echo $product['PRODUCT_ID']; ?>" class="block">
                                <div class="h-48 bg-gray-200 flex items-center justify-center text-gray-500">
                                    <img src="get_product_image.php?id=<?php echo $product['PRODUCT_ID']; ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>" class="h-full w-full object-cover">
                                </div>
                            </a>
                            <div class="p-6 flex flex-col flex-grow">
                                <h3 class="text-xl font-semibold text-gray-800 mb-2"><a href="product_detail.php?id=<?php echo $product['PRODUCT_ID']; ?>" class="hover:text-orange-600"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></a></h3>
                                <p class="text-sm text-gray-500 mb-1">Category: <?php echo htmlspecialchars($product['CATEGORY_NAME']); ?></p>
                                <p class="text-sm text-gray-500 mb-1">Sold by: <?php echo htmlspecialchars($product['TRADER_NAME']); ?></p>
                                <p class="text-orange-500 font-bold text-lg mb-1">$<?php echo htmlspecialchars(number_format($product['PRICE'], 2)); ?> <?php if ($product['UNIT']) echo "/ " . htmlspecialchars($product['UNIT']); ?></p>
                                <p class="text-sm text-gray-600 mb-3 truncate" title="<?php echo htmlspecialchars($product['DESCRIPTION']); ?>"><?php echo nl2br(htmlspecialchars(substr($product['DESCRIPTION'], 0, 70))) . (strlen($product['DESCRIPTION']) > 70 ? '...' : ''); ?></p>
                                <p class="text-sm text-gray-600 mb-3">In Stock: <?php echo htmlspecialchars($product['STOCK_AVAILABLE'] > 0 ? $product['STOCK_AVAILABLE'] : 'Out of Stock'); ?></p>

                                <form action="add_to_cart.php" method="POST" class="mt-auto">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $product['PRODUCT_ID']; ?>">
                                    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>">
                                    <input type="hidden" name="price" value="<?php echo htmlspecialchars($product['PRICE']); ?>">
                                    <?php if ($product['STOCK_AVAILABLE'] > 0): ?>
                                        <div class="flex items-center mb-4">
                                            <label for="quantity_shop_<?php echo $product['PRODUCT_ID']; ?>" class="sr-only">Quantity</label>
                                            <input type="number" id="quantity_shop_<?php echo $product['PRODUCT_ID']; ?>" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['STOCK_AVAILABLE']); ?>" class="w-16 text-center border border-gray-300 rounded-md py-1 px-2 mr-2">
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

                <!-- Pagination Links -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-8 flex justify-center items-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-orange-500 hover:text-white transition">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-4 py-2 <?php echo $i == $page ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-md hover:bg-orange-500 hover:text-white transition"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-orange-500 hover:text-white transition">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <p class="text-gray-600 text-center py-10">No products found matching your criteria. Try adjusting your filters or search term.</p>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const priceRange = document.getElementById('priceRange');
        const priceValue = document.getElementById('priceValue');
        if (priceRange && priceValue) {
            priceRange.addEventListener('input', function() {
                priceValue.textContent = this.value > 0 ? '$' + parseFloat(this.value).toFixed(2) : 'Any';
            });
        }
    });
</script>
<?php
if (isset($conn)) oci_close($conn);
include_once 'includes/footer.php';
?>