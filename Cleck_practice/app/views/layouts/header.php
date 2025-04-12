<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CleckBasket</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/fonts.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- <link href="https://unpkg.com/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"> -->
</head>

<body>
    <!-- Navbar -->
    <?php
    // Example user data - in a real application, this would come from your database/session
    $customerID = '903489111';
    ?>

    <nav class="bg-white border border-gray-300 drop-shadow-md pl-[60px] pr-[65px] py-[15px] flex items-center justify-between">
        <!-- Logo and Brand Section -->
        <div class="flex items-center">
            <div class="mr-2">
                <img src="<?= BASE_URL ?>/public/assets/images/Logo.png" alt="CleckBasket Logo" class="w-[85px] h-[70px]">
            </div>
            <div>
                <h1 class="text-[20px] text-black font-helvetica-rounded font-bold">CleckBasket</h1>
                <p class="text-gray-600 italic font-light">Freshness in Every Click</p>
            </div>
        </div>

        <!-- Search Section -->
        <div class="container mx-auto py-4 px-4 w-[450px]">
            <!-- Search Bar -->
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" class="w-full max-w-4xl mx-auto">
                <div class="flex rounded-md border border-[#B6B6B6] bg-[#F6F6F6] overflow-hidden shadow-sm">
                    <!-- Categories Button -->
                    <button type="button" class="flex items-center justify-center px-4 py-3 text-[#686868] font-helvetica-rounded font-bold text-[10px] border-r border-gray-300 bg-[#F6F6F6] hover:bg-gray-100 transition-colors whitespace-nowrap">
                        ALL CATEGORIES
                    </button>

                    <!-- Search Input -->
                    <input
                        type="text"
                        name="search_query"
                        placeholder="Enter what you are searching for..."
                        class="w-full px-4 py-3 text-black placeholder-[#9E9E9E] [&::placeholder]:text-[12px] focus:outline-none bg-[#F6F6F6]">

                    <!-- Search Button -->
                    <button type="submit" class="px-4 py-3 flex items-center justify-center bg-[#F6F6F6] hover:bg-gray-50 transition-colors">
                        <svg class="w-5 h-5 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>


        <!-- Customer Section -->
        <div class="flex items-center">
            <div class="text-right mr-6">
                <p class="font-helvetica-rounded font-bold text-[15px] text-black"><?php echo htmlspecialchars($customerID); ?></p>
                <p class="text-[10px] text-amber-500">Customer ID</p>
            </div>

            <!-- User Icon -->
            <a href="<?php echo htmlspecialchars('/account.php'); ?>" class="mx-4">
                <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </a>

            <!-- Wishlist Icon -->
            <a href="<?php echo htmlspecialchars('/wishlist.php'); ?>" class="mx-4">
                <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                </svg>
            </a>

            <!-- Cart Icon -->
            <a href="<?php echo htmlspecialchars('/cart.php'); ?>" class="mx-4">
                <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </a>

            <!-- Export/Share Button -->
            <a href="<?php echo htmlspecialchars('/export.php'); ?>" class="ml-4 bg-[#DAA667] p-3 rounded-lg">
                <svg xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="w-6 h-6 text-black hover:text-red-500 transition-colors">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
            </a>
        </div>
    </nav>