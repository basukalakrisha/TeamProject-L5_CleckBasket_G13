<?php include 'includes/header.php'; ?>

<!-- Main Content -->
<div class="container mx-auto px-6 py-12 bg-gradient-to-b from-gray-50 to-white">
    <!-- Heading and Subheading -->
    <div class="text-center mb-12">
        <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">Explore Our Categories</h1>
        <p class="text-lg text-gray-600 max-w-2xl mx-auto">Discover a wide range of products across our vibrant categories. Click on a category to explore fresh and quality items available.</p>
    </div>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-10">
        <?php
        // Array of categories (as per the case study)
        $categories = [
            ['name' => 'Butchers', 'product_count' => 10],
            ['name' => 'Greengrocer', 'product_count' => 15],
            ['name' => 'Fishmonger', 'product_count' => 8],
            ['name' => 'Bakery', 'product_count' => 12],
            ['name' => 'Delicatessen', 'product_count' => 20],
            ['name' => 'Dairy', 'product_count' => 5],
            ['name' => 'Beverages', 'product_count' => 7],
            ['name' => 'Snacks', 'product_count' => 9],
            ['name' => 'Organic', 'product_count' => 6],
        ];

        // Loop through categories to display them
        foreach ($categories as $category) {
            echo '
            <div class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 border border-yellow-100 hover:border-yellow-300 transform hover:-translate-y-1 min-h-[200px] w-full">
                <h3 class="text-xl font-bold text-gray-800 mb-3">' . $category['name'] . '</h3>
                <p class="text-gray-500 mb-5">' . $category['product_count'] . ' product' . ($category['product_count'] != 1 ? 's' : '') . ' Available</p>
                <a href="shop.php?category=' . urlencode($category['name']) . '" class="inline-block bg-yellow-400 text-gray-900 font-semibold px-6 py-3 rounded-lg hover:bg-yellow-500 transition-colors duration-200">Explore</a>
            </div>';
        }
        ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>