<?php
// Define absolute paths
require dirname(__DIR__) . '/config/routes.php';
define('BASE_PATH', dirname(__DIR__, 2)); // Goes up two levels from public/
define('APP_PATH', BASE_PATH . '/app');

// $request = $_SERVER['REQUEST_URI'];
// if ($request === '/shop') {
//     require __DIR__.'/../views/partials/shop.php';
//     exit();
// }

// Correct require statement
require APP_PATH . '/views/layouts/main.php';
