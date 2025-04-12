<!DOCTYPE html>
<html lang="en" class="font-sans">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" /> -->
    <title>CleckBasket</title>
</head>

<body>
    <?php
    session_start();
    require __DIR__ . '/header.php';

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

    <div class="bg-white min-h-screen">
        <div class="container mx-auto py-4 px-4">
            <div class="flex flex-wrap items-center justify-between">
                <!-- Left: Shop All Products Button -->
                <div class="flex-shrink-0">
                    <a href="#" class="bg-[#DAA667] hover:bg-[#DAA667]/80 text-black font-inter font-medium text-[14px] py-2 px-5 rounded-md transition-colors flex items-center">
                        <i class="fa-solid fa-grip text-sm mr-2"></i>
                        SHOP ALL PRODUCTS
                    </a>
                </div>

                <!-- Middle: Menu Items -->
                <div class="flex items-center justify-center flex-grow space-x-8 py-2 font-inter font-medium">
                    <a href="#" class="flex items-center text-Black text-[14px] hover:text-[#DAA667] font-medium">
                        <!-- <i class="fa-solid fa-bolt mr-2"></i> -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                        </svg>

                        Deals Today
                    </a>
                    <a href="#" class="flex items-center text-Black text-[14px] hover:text-[#DAA667] font-medium">
                        <!-- <i class="fa-solid fa-tag mr-2"></i> -->
                        Special Prices
                    </a>
                    <a href="#" class="text-Black text-[14px] hover:text-[#DAA667] font-medium">
                        Fresh
                    </a>
                    <a href="#" class="text-Black text-[14px] hover:text-[#DAA667] font-medium">
                        Frozen
                    </a>
                    <a href="#" class="text-Black text-[14px] hover:text-[#DAA667] font-medium">
                        Suppliers
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>
</body>

</html>