<?php
// Start session (already started in including files, but included for completeness)
session_start();

// Calculate total number of items in cart
$cart_count = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CleckBasket</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="font-sans">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md p-4 flex items-center justify-between">
        <div class="flex items-center">
            <img src="assets/logo.png" alt="Logo" class="h-10 w-10 mr-4">
            <a href="index.php" class="text-gray-700 hover:text-yellow-600 mx-2">Home</a>
            <a href="shop.php" class="text-gray-700 hover:text-yellow-600 mx-2">Shop</a>
            <a href="categories.php" class="text-gray-700 hover:text-yellow-600 mx-2">Categories</a>
        </div>
        <div class="flex items-center">
            <input type="text" placeholder="Search" class="border rounded p-1 mr-2">
            <a href="cart.php" class="text-gray-700 hover:text-yellow-600 mx-2 relative">
                <svg class="w-6 h-6 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <?php if ($cart_count > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo $cart_count; ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="profile.php" class="text-gray-700 hover:text-yellow-600 mx-2">Profile</a>
            <?php else: ?>
                <a href="login.php" class="text-gray-700 hover:text-yellow-600 mx-2">Profile</a>
            <?php endif; ?>
        </div>
    </nav>