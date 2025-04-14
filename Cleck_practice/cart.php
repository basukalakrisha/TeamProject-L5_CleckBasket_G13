<?php
session_start();
include 'includes/header.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart updates (quantity change, remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        $product_id = $_POST['product_id'];
        $action = $_POST['action'];
        if (isset($_SESSION['cart'][$product_id])) {
            $current_quantity = $_SESSION['cart'][$product_id]['quantity'];
            $stock = $_SESSION['cart'][$product_id]['stock'];

            if ($action === 'increase' && $current_quantity < $stock) {
                $_SESSION['cart'][$product_id]['quantity']++;
                session_write_close(); // Ensure session is saved
                header("Location: cart.php"); // Redirect to refresh page
                exit;
            } elseif ($action === 'decrease' && $current_quantity > 1) {
                $_SESSION['cart'][$product_id]['quantity']--;
                session_write_close(); // Ensure session is saved
                header("Location: cart.php"); // Redirect to refresh page
                exit;
            }
        }
    } elseif (isset($_POST['remove_item'])) {
        $product_id = $_POST['product_id'];
        unset($_SESSION['cart'][$product_id]);
        session_write_close(); // Ensure session is saved
        header("Location: cart.php"); // Redirect to refresh page
        exit;
    } elseif (isset($_POST['buy_all'])) {
        echo "<p class='text-brown-600'>Proceeding to checkout (placeholder)...</p>";
        // No redirect here, as this is a placeholder for checkout
    }
}
?>

<div class="container mx-auto p-6 bg-stone-100 min-h-screen">
    <h1 class="text-3xl font-bold text-brown-800 mb-6">Your Cart</h1>
    <?php if (empty($_SESSION['cart'])): ?>
        <p class="text-brown-600">Your cart is empty. <a href="shop.php" class="text-yellow-600 hover:underline">Start shopping now!</a></p>
    <?php else: ?>
        <table class="w-full border-collapse bg-white rounded-lg shadow-lg">
            <thead>
                <tr class="bg-yellow-600 text-brown-800">
                    <th class="p-3">Image</th>
                    <th class="p-3">Product</th>
                    <th class="p-3">Quantity</th>
                    <th class="p-3">Delivery Charge</th>
                    <th class="p-3">Price</th>
                    <th class="p-3">Sub Total</th>
                    <th class="p-3">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total = 0;
                foreach ($_SESSION['cart'] as $product_id => $item) {
                    $delivery_charge = 10.00;
                    $subtotal = ($item['price'] * $item['quantity']) + $delivery_charge;
                    $total += $subtotal;

                    echo '
                    <tr class="border-b text-brown-600">
                        <td class="p-3"><div class="h-16 w-16 bg-stone-200 rounded-lg flex items-center justify-center">Image</div></td>
                        <td class="p-3">' . htmlspecialchars($item['name']) . '</td>
                        <td class="p-3">
                            <div class="flex items-center">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="product_id" value="' . $product_id . '">
                                    <input type="hidden" name="action" value="decrease">
                                    <button type="submit" name="update_cart" class="p-1 bg-yellow-600 text-brown-800 rounded-l hover:bg-yellow-700">-</button>
                                </form>
                                <input type="number" value="' . $item['quantity'] . '" min="1" max="' . $item['stock'] . '" class="w-12 text-center border-t border-b border-yellow-600 text-brown-600" readonly>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="product_id" value="' . $product_id . '">
                                    <input type="hidden" name="action" value="increase">
                                    <button type="submit" name="update_cart" class="p-1 bg-yellow-600 text-brown-800 rounded-r hover:bg-yellow-700">+</button>
                                </form>
                            </div>
                        </td>
                        <td class="p-3">$' . number_format($delivery_charge, 2) . '</td>
                        <td class="p-3">$' . number_format($item['price'], 2) . '</td>
                        <td class="p-3">$' . number_format($subtotal, 2) . '</td>
                        <td class="p-3">
                            <form method="POST" class="flex space-x-2">
                                <input type="hidden" name="product_id" value="' . $product_id . '">
                                <button type="submit" name="remove_item" class="bg-red-600 text-white p-2 rounded-lg hover:bg-red-700 transition duration-300">Remove</button>
                                <button type="submit" name="buy_item" class="bg-yellow-600 text-brown-800 p-2 rounded-lg hover:bg-yellow-700 transition duration-300">Buy</button>
                            </form>
                        </td>
                    </tr>';
                }
                ?>
            </tbody>
        </table>
        <div class="text-right mt-6">
            <p class="text-xl font-bold text-brown-800">Total: $<?php echo number_format($total, 2); ?></p>
            <form method="POST" class="mt-4">
                <button type="submit" name="buy_all" class="bg-yellow-600 text-brown-800 p-3 rounded-lg hover:bg-yellow-700 transition duration-300">Buy All</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>