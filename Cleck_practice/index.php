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
        ['id' => 1, 'name' => 'Beef Steak', 'price' => 20.00, 'stock' => 5],
        ['id' => 2, 'name' => 'Fresh Apples', 'price' => 5.00, 'stock' => 10],
        ['id' => 3, 'name' => 'Salmon Fillet', 'price' => 15.00, 'stock' => 8],
        ['id' => 4, 'name' => 'Sourdough Bread', 'price' => 4.00, 'stock' => 12],
        ['id' => 5, 'name' => 'Organic Carrots', 'price' => 3.00, 'stock' => 15],
        ['id' => 6, 'name' => 'Cod Fillet', 'price' => 12.00, 'stock' => 6],
        ['id' => 7, 'name' => 'Croissant', 'price' => 2.50, 'stock' => 20],
        ['id' => 8, 'name' => 'Gourmet Cheese', 'price' => 25.00, 'stock' => 3],
        ['id' => 9, 'name' => 'Chicken Breast', 'price' => 10.00, 'stock' => 7],
    ];

    $product = array_filter($products, fn($p) => $p['id'] == $product_id)[array_key_first(array_filter($products, fn($p) => $p['id'] == $product_id))];

    if ($product && $quantity <= $product['stock']) {
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
    }
}
?>

<div class="bg-gray-50 min-h-screen">
    <div class="container mx-auto p-6">
        <!-- Hero Section -->
        <div class="flex flex-col items-center text-center py-12">
            <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Welcome to CleckBasket</h1>
            <p class="text-lg md:text-xl text-gray-600 mb-8 max-w-2xl">Discover a wide range of fresh groceries from local shops. Explore our categories and start shopping today!</p>
            <a href="shop.php" class="bg-yellow-600 text-brown-800 px-6 py-3 rounded-lg text-lg font-semibold hover:bg-yellow-700 transition duration-300">Shop Now</a>
        </div>

        <!-- Categories Section -->
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-brown-800 mb-6">Categories</h2>
            <p class="text-brown-600 mb-8">Click on a category to explore the products available.</p>

            <!-- Categories Container with Arrows -->
            <div class="relative flex items-center justify-center">
                <!-- Left Arrow -->
                <button onclick="shiftCategories('left')" class="absolute left-0 transform -translate-x-1/2 bg-yellow-600 text-brown-800 p-2 rounded-full hover:bg-yellow-700 transition duration-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>

                <!-- Categories Display (Fixed 5 Cards) -->
                <div id="categoryContainer" class="flex justify-center space-x-4">
                    <!-- Categories will be populated by JavaScript -->
                </div>

                <!-- Right Arrow -->
                <button onclick="shiftCategories('right')" class="absolute right-0 transform translate-x-1/2 bg-yellow-600 text-brown-800 p-2 rounded-full hover:bg-yellow-700 transition duration-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            <!-- JavaScript for Category Shifting -->
            <script>
                const categories = [{
                        name: 'Butchers',
                        product_count: 10
                    },
                    {
                        name: 'Greengrocer',
                        product_count: 15
                    },
                    {
                        name: 'Fishmonger',
                        product_count: 8
                    },
                    {
                        name: 'Bakery',
                        product_count: 12
                    },
                    {
                        name: 'Delicatessen',
                        product_count: 20
                    },
                    {
                        name: 'Dairy',
                        product_count: 5
                    },
                    {
                        name: 'Beverages',
                        product_count: 7
                    },
                    {
                        name: 'Snacks',
                        product_count: 9
                    },
                    {
                        name: 'Organic',
                        product_count: 6
                    },
                ];

                let currentStartIndex = 0;

                function displayCategories() {
                    const container = document.getElementById('categoryContainer');
                    container.innerHTML = '';

                    for (let i = 0; i < 5; i++) {
                        const index = (currentStartIndex + i) % categories.length;
                        const category = categories[index];
                        container.innerHTML += `
                            <div class="flex-none">
                                <div class="w-32 h-32 md:w-40 md:h-40 bg-white rounded-full shadow-md flex flex-col items-center justify-center p-4 hover:shadow-lg transition duration-300">
                                    <h3 class="text-sm md:text-base font-semibold text-brown-800 text-center">${category.name}</h3>
                                    <p class="text-xs md:text-sm text-brown-600 text-center">${category.product_count} product${category.product_count !== 1 ? 's' : ''}</p>
                                    <a href="shop.php?category=${encodeURIComponent(category.name)}" class="mt-2 text-xs md:text-sm bg-yellow-600 text-brown-800 px-3 py-1 rounded-full hover:bg-yellow-700 transition duration-300">Explore</a>
                                </div>
                            </div>
                        `;
                    }
                }

                function shiftCategories(direction) {
                    if (direction === 'left') {
                        currentStartIndex = (currentStartIndex - 1 + categories.length) % categories.length;
                    } else if (direction === 'right') {
                        currentStartIndex = (currentStartIndex + 1) % categories.length;
                    }
                    displayCategories();
                }

                displayCategories();
            </script>
        </div>

        <!-- Featured Products Section -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-brown-800 mb-6 text-center">Featured Products</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php
                $featured_products = [
                    ['id' => 1, 'name' => 'Beef Steak', 'price' => 20.00, 'stock' => 5],
                    ['id' => 2, 'name' => 'Fresh Apples', 'price' => 5.00, 'stock' => 10],
                    ['id' => 3, 'name' => 'Salmon Fillet', 'price' => 15.00, 'stock' => 8],
                    ['id' => 4, 'name' => 'Sourdough Bread', 'price' => 4.00, 'stock' => 12],
                ];

                foreach ($featured_products as $product) {
                    echo '
                    <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition duration-300">
                        <div class="h-48 bg-stone-200 rounded-lg mb-4 flex items-center justify-center">
                            <span class="text-brown-600">Image Placeholder</span>
                        </div>
                        <h3 class="text-lg font-semibold text-brown-800">' . $product['name'] . '</h3>
                        <p class="text-yellow-600 font-medium">$' . number_format($product['price'], 2) . '</p>
                        <p class="text-brown-600">In Stock: ' . $product['stock'] . '</p>
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="product_id" value="' . $product['id'] . '">
                            <input type="number" name="quantity" value="1" min="1" max="' . $product['stock'] . '" class="w-16 p-1 border rounded text-brown-600">
                            <button type="submit" name="add_to_cart" class="w-full bg-yellow-600 text-brown-800 p-2 rounded-lg hover:bg-yellow-700 transition duration-300">Add to Cart</button>
                        </form>
                    </div>';
                }
                ?>
            </div>
        </div>

        <!-- New Arrivals Section -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-brown-800 mb-6 text-center">New Arrivals</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php
                $new_arrivals = [
                    ['id' => 5, 'name' => 'Organic Carrots', 'price' => 3.00, 'stock' => 15],
                    ['id' => 6, 'name' => 'Cod Fillet', 'price' => 12.00, 'stock' => 6],
                    ['id' => 7, 'name' => 'Croissant', 'price' => 2.50, 'stock' => 20],
                    ['id' => 8, 'name' => 'Gourmet Cheese', 'price' => 25.00, 'stock' => 3],
                ];

                foreach ($new_arrivals as $product) {
                    echo '
                    <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition duration-300">
                        <div class="h-48 bg-stone-200 rounded-lg mb-4 flex items-center justify-center">
                            <span class="text-brown-600">Image Placeholder</span>
                        </div>
                        <h3 class="text-lg font-semibold text-brown-800">' . $product['name'] . '</h3>
                        <p class="text-yellow-600 font-medium">$' . number_format($product['price'], 2) . '</p>
                        <p class="text-brown-600">In Stock: ' . $product['stock'] . '</p>
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="product_id" value="' . $product['id'] . '">
                            <input type="number" name="quantity" value="1" min="1" max="' . $product['stock'] . '" class="w-16 p-1 border rounded text-brown-600">
                            <button type="submit" name="add_to_cart" class="w-full bg-yellow-600 text-brown-800 p-2 rounded-lg hover:bg-yellow-700 transition duration-300">Add to Cart</button>
                        </form>
                    </div>';
                }
                ?>
            </div>
        </div>

        <!-- Popular Products Section -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-brown-800 mb-6 text-center">Popular Products</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php
                $popular_products = [
                    ['id' => 9, 'name' => 'Chicken Breast', 'price' => 10.00, 'stock' => 7],
                    ['id' => 2, 'name' => 'Fresh Apples', 'price' => 5.00, 'stock' => 10],
                    ['id' => 4, 'name' => 'Sourdough Bread', 'price' => 4.00, 'stock' => 12],
                    ['id' => 3, 'name' => 'Salmon Fillet', 'price' => 15.00, 'stock' => 8],
                ];

                foreach ($popular_products as $product) {
                    echo '
                    <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition duration-300">
                        <div class="h-48 bg-stone-200 rounded-lg mb-4 flex items-center justify-center">
                            <span class="text-brown-600">Image Placeholder</span>
                        </div>
                        <h3 class="text-lg font-semibold text-brown-800">' . $product['name'] . '</h3>
                        <p class="text-yellow-600 font-medium">$' . number_format($product['price'], 2) . '</p>
                        <p class="text-brown-600">In Stock: ' . $product['stock'] . '</p>
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="product_id" value="' . $product['id'] . '">
                            <input type="number" name="quantity" value="1" min="1" max="' . $product['stock'] . '" class="w-16 p-1 border rounded text-brown-600">
                            <button type="submit" name="add_to_cart" class="w-full bg-yellow-600 text-brown-800 p-2 rounded-lg hover:bg-yellow-700 transition duration-300">Add to Cart</button>
                        </form>
                    </div>';
                }
                ?>
            </div>
        </div>

        <!-- See More Button -->
        <div class="text-center mb-12">
            <a href="shop.php" class="bg-yellow-600 text-brown-800 px-6 py-3 rounded-lg text-lg font-semibold hover:bg-yellow-700 transition duration-300">See More</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>