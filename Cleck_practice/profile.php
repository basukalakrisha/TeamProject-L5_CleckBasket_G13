<?php
session_start();
include 'includes/header.php';

// Simulated database of users (in a real app, this would be a database)
$users = isset($_SESSION['users']) ? $_SESSION['users'] : [];

// Redirect to login if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    session_start();
    $_SESSION['message'] = "Logged out successfully.";
    header("Location: login.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = $_SESSION['user']['email'];
    $users[$email]['first_name'] = trim($_POST['first_name']);
    $users[$email]['last_name'] = trim($_POST['last_name']);
    $users[$email]['contact'] = trim($_POST['contact']);
    $users[$email]['address'] = trim($_POST['address']);
    $_SESSION['users'] = $users;
    $_SESSION['user'] = $users[$email];
    $_SESSION['user']['email'] = $email;
    $_SESSION['message'] = "Profile updated successfully!";
}
?>

<div class="container mx-auto p-4 min-h-screen bg-gradient-to-b  to-white">
    <h1 class="text-4xl font-bold text-center mb-6 text-yellow-800">Profile</h1>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <p class="text-center text-yellow-600 mb-4"><?php echo $_SESSION['message'];
                                                    unset($_SESSION['message']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <p class="text-center text-red-600 mb-4"><?php echo $_SESSION['error'];
                                                    unset($_SESSION['error']); ?></p>
    <?php endif; ?>

    <!-- Profile Section -->
    <div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-lg mb-6 border border-yellow-200">
        <h2 class="text-2xl font-semibold text-yellow-800 mb-4">Welcome, <?php echo $_SESSION['user']['first_name']; ?>!</h2>
        <p class="text-gray-700 mb-2"><strong>Role:</strong> <?php echo ucfirst($_SESSION['user']['role']); ?></p>
        <p class="text-gray-700 mb-2"><strong>Email:</strong> <?php echo $_SESSION['user']['email']; ?></p>
        <p class="text-gray-700 mb-2"><strong>Contact:</strong> <?php echo $_SESSION['user']['contact']; ?></p>
        <p class="text-gray-700 mb-4"><strong>Address:</strong> <?php echo $_SESSION['user']['address']; ?></p>

        <!-- Update Profile Form -->
        <h3 class="text-xl font-semibold text-yellow-800 mb-3">Update Profile</h3>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-700">First Name</label>
                <input type="text" name="first_name" value="<?php echo $_SESSION['user']['first_name']; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <div>
                <label class="block text-gray-700">Last Name</label>
                <input type="text" name="last_name" value="<?php echo $_SESSION['user']['last_name']; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <div>
                <label class="block text-gray-700">Contact</label>
                <input type="text" name="contact" value="<?php echo $_SESSION['user']['contact']; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <div>
                <label class="block text-gray-700">Address</label>
                <input type="text" name="address" value="<?php echo $_SESSION['user']['address']; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <button type="submit" name="update_profile" class="w-full bg-yellow-600 text-white p-2 rounded hover:bg-yellow-700 transition duration-300">Update Profile</button>
        </form>

        <!-- Logout Button -->
        <form method="POST" class="mt-4">
            <button type="submit" name="logout" class="w-full bg-red-600 text-white p-2 rounded hover:bg-red-700 transition duration-300">Logout</button>
        </form>

        <!-- Trader-specific Actions -->
        <?php if ($_SESSION['user']['role'] === 'trader'): ?>
            <div class="mt-6">
                <h3 class="text-xl font-semibold text-yellow-800 mb-3">Trader Actions</h3>
                <a href="add_product.php" class="block text-center bg-yellow-600 text-white p-2 rounded mb-2 hover:bg-yellow-700 transition duration-300">Add New Product</a>
                <a href="manage_products.php" class="block text-center bg-yellow-600 text-white p-2 rounded hover:bg-yellow-700 transition duration-300">Manage Products</a>
            </div>
        <?php endif; ?>

        <!-- Customer-specific Actions -->
        <?php if ($_SESSION['user']['role'] === 'customer'): ?>
            <div class="mt-6">
                <h3 class="text-xl font-semibold text-yellow-800 mb-3">Customer Actions</h3>
                <a href="cart.php" class="block text-center bg-yellow-600 text-white p-2 rounded mb-2 hover:bg-yellow-700 transition duration-300">View Cart</a>
                <a href="orders.php" class="block text-center bg-yellow-600 text-white p-2 rounded hover:bg-yellow-700 transition duration-300">View Orders</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>