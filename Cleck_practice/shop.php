<?php
session_start();
include 'includes/header.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    $products = [
        ['id' => 1, 'name' => 'Beef Steak', 'category' => 'Butchers', 'shop' => 'Shop 1', 'price' => 20.00, 'stock' => 5],
        ['id' => 2, 'name' => 'Fresh Apples', 'category' => 'Greengrocer', 'shop' => 'Shop 2', 'price' => 5.00, 'stock' => 10],
        ['id' => 3, 'name' => 'Salmon Fillet', 'category' => 'Fishmonger', 'shop' => 'Shop 3', 'price' => 15.00, 'stock' => 8],
        ['id' => 4, 'name' => 'Sourdough Bread', 'category' => 'Bakery', 'shop' => 'Shop 4', 'price' => 4.00, 'stock' => 12],
        ['id' => 5, 'name' => 'Gourmet Cheese', 'category' => 'Delicatessen', 'shop' => 'Shop 5', 'price' => 25.00, 'stock' => 3],
        ['id' => 6, 'name' => 'Chicken Breast', 'category' => 'Butchers', 'shop' => 'Shop 1', 'price' => 10.00, 'stock' => 7],
        ['id' => 7, 'name' => 'Organic Carrots', 'category' => 'Greengrocer', 'shop' => 'Shop 2', 'price' => 3.00, 'stock' => 15],
        ['id' => 8, 'name' => 'Cod Fillet', 'category' => 'Fishmonger', 'shop' => 'Shop 3', 'price' => 12.00, 'stock' => 6],
        ['id' => 9, 'name' => 'Croissant', 'category' => 'Bakery', 'shop' => 'Shop 4', 'price' => 2.50, 'stock' => 20],
    ];

    // Find product
    $product = array_filter($products, fn($p) => $p['id'] == $product_id);
    $product = reset($product); // Get first matching product

    if ($product && $quantity > 0 && $quantity <= $product['stock']) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'stock' => $product['stock']
            ];
        }
        // Force session write
        session_write_close();
        // Redirect to same page with current filters to refresh cart count
        $query_string = http_build_query($_GET);
        header("Location: shop.php" . ($query_string ? "?$query_string" : ""));
        exit;
    }
}
?>

<div class="container mx-auto p-6 bg-stone-100 min-h-screen">
    <div class="flex flex-col md:flex-row gap-6">
        <!-- Filters -->
        <div class="w-full md:w-1/4 p-6 bg-white rounded-lg shadow-lg">
            <h2 class="text-2xl font-bold text-brown-800 mb-6">Filter Products</h2>
            <form method="GET" action="shop.php">
                <!-- Filter by Category -->
                <div class="mb-6">
                    <h3 class="font-semibold text-brown-800 mb-3">Filter by Category</h3>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="categories[]" value="Butchers" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['categories']) && in_array('Butchers', $_GET['categories'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Butchers</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="categories[]" value="Greengrocer" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['categories']) && in_array('Greengrocer', $_GET['categories'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Greengrocer</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="categories[]" value="Fishmonger" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['categories']) && in_array('Fishmonger', $_GET['categories'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Fishmonger</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="categories[]" value="Bakery" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['categories']) && in_array('Bakery', $_GET['categories'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Bakery</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="categories[]" value="Delicatessen" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['categories']) && in_array('Delicatessen', $_GET['categories'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Delicatessen</span>
                        </label>
                    </div>
                </div>

                <!-- Filter by Shop -->
                <div class="mb-6">
                    <h3 class="font-semibold text-brown-800 mb-3">Filter by Shop</h3>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="shops[]" value="Shop 1" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['shops']) && in_array('Shop 1', $_GET['shops'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Shop 1</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="shops[]" value="Shop 2" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['shops']) && in_array('Shop 2', $_GET['shops'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Shop 2</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="shops[]" value="Shop 3" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['shops']) && in_array('Shop 3', $_GET['shops'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Shop 3</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="shops[]" value="Shop 4" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['shops']) && in_array('Shop 4', $_GET['shops'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Shop 4</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="shops[]" value="Shop 5" class="h-4 w-4 text-yellow-600 rounded" <?php echo (isset($_GET['shops']) && in_array('Shop 5', $_GET['shops'])) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-brown-600">Shop 5</span>
                        </label>
                    </div>
                </div>

                <!-- Filter by Price -->
                <div class="mb-6">
                    <h3 class="font-semibold text-brown-800 mb-3">Price Range</h3>
                    <div class="flex items-center space-x-3">
                        <span class="text-brown-600">$0</span>
                        <input type="range" name="price" min="0" max="50" value="<?php echo isset($_GET['price']) ? $_GET['price'] : 50; ?>" class="w-full h-2 bg-yellow-200 rounded-lg appearance-none cursor-pointer accent-yellow-600" oninput="this.nextElementSibling.value = this.value">
                        <output class="text-brown-600"><?php echo isset($_GET['price']) ? $_GET['price'] : 50; ?></output>
                        <span class="text-brown-600">$50</span>
                    </div>
                </div>

                <!-- Apply Button -->
                <button type="submit" class="w-full bg-yellow-600 text-brown-800 p-3 rounded-lg hover:bg-yellow-700 transition duration-300">Apply Filters</button>
            </form>
        </div>

        <!-- Products -->
        <div class="w-full md:w-3/4">
            <?php
            $selected_category = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'All Categories';
            $selected_categories = isset($_GET['categories']) ? $_GET['categories'] : [];
            $selected_shops = isset($_GET['shops']) ? $_GET['shops'] : [];
            $max_price = isset($_GET['price']) ? (float)$_GET['price'] : 50;

            $products = [
                ['id' => 1, 'name' => 'Beef Steak', 'category' => 'Butchers', 'shop' => 'Shop 1', 'price' => 20.00, 'stock' => 5],
                ['id' => 2, 'name' => 'Fresh Apples', 'category' => 'Greengrocer', 'shop' => 'Shop 2', 'price' => 5.00, 'stock' => 10],
                ['id' => 3, 'name' => 'Salmon Fillet', 'category' => 'Fishmonger', 'shop' => 'Shop 3', 'price' => 15.00, 'stock' => 8],
                ['id' => 4, 'name' => 'Sourdough Bread', 'category' => 'Bakery', 'shop' => 'Shop 4', 'price' => 4.00, 'stock' => 12],
                ['id' => 5, 'name' => 'Gourmet Cheese', 'category' => 'Delicatessen', 'shop' => 'Shop 5', 'price' => 25.00, 'stock' => 3],
                ['id' => 6, 'name' => 'Chicken Breast', 'category' => 'Butchers', 'shop' => 'Shop 1', 'price' => 10.00, 'stock' => 7],
                ['id' => 7, 'name' => 'Organic Carrots', 'category' => 'Greengrocer', 'shop' => 'Shop 2', 'price' => 3.00, 'stock' => 15],
                ['id' => 8, 'name' => 'Cod Fillet', 'category' => 'Fishmonger', 'shop' => 'Shop 3', 'price' => 12.00, 'stock' => 6],
                ['id' => 9, 'name' => 'Croissant', 'category' => 'Bakery', 'shop' => 'Shop 4', 'price' => 2.50, 'stock' => 20],
            ];

            $filtered_products = array_filter($products, function ($product) use ($selected_categories, $selected_shops, $max_price, $selected_category) {
                if ($selected_category !== 'All Categories' && $product['category'] !== $selected_category) return false;
                if (!empty($selected_categories) && !in_array($product['category'], $selected_categories)) return false;
                if (!empty($selected_shops) && !in_array($product['shop'], $selected_shops)) return false;
                if ($product['price'] > $max_price) return false;
                return true;
            });

            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $products_per_page = 6;
            $total_products = count($filtered_products);
            $total_pages = max(1, ceil($total_products / $products_per_page));
            $current_page = max(1, min($current_page, $total_pages));
            $start_index = ($current_page - 1) * $products_per_page;
            $paginated_products = array_slice($filtered_products, $start_index, $products_per_page);
            ?>

            <h2 class="text-3xl font-bold text-brown-800 mb-6"><?php echo $selected_category; ?></h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                if (count($paginated_products) > 0) {
                    foreach ($paginated_products as $product) {
                        echo '
                        <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition duration-300">
                            <div class="h-48 bg-stone-200 rounded-lg mb-4 flex items-center justify-center">
                                <span class="text-brown-600">Image Placeholder</span>
                            </div>
                            <h3 class="text-lg font-semibold text-brown-800">' . htmlspecialchars($product['name']) . '</h3>
                            <p class="text-yellow-600 font-medium">$' . number_format($product['price'], 2) . '</p>
                            <p class="text-brown-600">In Stock: ' . $product['stock'] . '</p>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="product_id" value="' . $product['id'] . '">
                                <input type="number" name="quantity" value="1" min="1" max="' . $product['stock'] . '" class="w-16 p-1 border rounded text-brown-600">
                                <button type="submit" name="add_to_cart" class="w-full bg-yellow-600 text-brown-800 p-2 rounded-lg hover:bg-yellow-700 transition duration-300">Add to Cart</button>
                            </form>
                        </div>';
                    }
                } else {
                    echo '<p class="text-brown-600 col-span-full">No products found matching your filters.</p>';
                }
                ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_products > 0): ?>
                <div class="mt-8 flex flex-wrap justify-center gap-2">
                    <a href="shop.php?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" class="px-4 py-2 bg-yellow-600 text-brown-800 rounded-lg hover:bg-yellow-700 transition duration-300 <?php echo $current_page == 1 ? 'opacity-50 pointer-events-none' : ''; ?>">Previous</a>
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="shop.php?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-4 py-2 rounded-lg transition duration-300 <?php echo $i == $current_page ? 'bg-yellow-400 text-brown-800' : 'bg-stone-200 text-brown-600 hover:bg-stone-300'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <a href="shop.php?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" class="px-4 py-2 bg-yellow-600 text-brown-800 rounded-lg hover:bg-yellow-700 transition duration-300 <?php echo $current_page == $total_pages ? 'opacity-50 pointer-events-none' : ''; ?>">Next</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>