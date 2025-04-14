<?php
session_start();
include 'includes/header.php';

// Simulated database of users (in a real app, this would be a database)
$users = isset($_SESSION['users']) ? $_SESSION['users'] : [];

// Simple admin authentication (for demo purposes)
$admin_password = "admin123"; // In a real app, use a secure method for admin authentication

// Handle admin login
if (!isset($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $password = trim($_POST['password']);
    if ($password === $admin_password) {
        $_SESSION['admin'] = true;
        $_SESSION['message'] = "Admin login successful.";
    } else {
        $_SESSION['error'] = "Invalid admin password.";
    }
}

// Handle user deletion
if (isset($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $email = $_POST['email'];
    if (isset($users[$email])) {
        unset($users[$email]);
        $_SESSION['users'] = $users;
        // If the deleted user is currently logged in, log them out
        if (isset($_SESSION['user']) && $_SESSION['user']['email'] === $email) {
            unset($_SESSION['user']);
        }
        $_SESSION['message'] = "User with email $email has been deleted.";
    } else {
        $_SESSION['error'] = "User not found.";
    }
}

// Handle admin logout
if (isset($_POST['admin_logout'])) {
    unset($_SESSION['admin']);
    $_SESSION['message'] = "Admin logged out successfully.";
}
?>

<div class="container mx-auto p-4 min-h-screen bg-gradient-to-b from-yellow-50 to-white">
    <h1 class="text-4xl font-bold text-center mb-6 text-yellow-800">Admin Panel</h1>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <p class="text-center text-yellow-600 mb-4"><?php echo $_SESSION['message'];
                                                    unset($_SESSION['message']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <p class="text-center text-red-600 mb-4"><?php echo $_SESSION['error'];
                                                    unset($_SESSION['error']); ?></p>
    <?php endif; ?>

    <?php if (!isset($_SESSION['admin'])): ?>
        <!-- Admin Login Form -->
        <div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-lg border border-yellow-200">
            <h2 class="text-2xl font-semibold text-yellow-800 mb-4">Admin Login</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700">Admin Password</label>
                    <input type="password" name="password" placeholder="Enter admin password" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
                </div>
                <button type="submit" name="admin_login" class="w-full bg-yellow-600 text-white p-2 rounded hover:bg-yellow-700 transition duration-300">Login</button>
            </form>
        </div>
    <?php else: ?>
        <!-- Admin Actions -->
        <div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow-lg border border-yellow-200">
            <h2 class="text-2xl font-semibold text-yellow-800 mb-4">Manage Users</h2>
            <?php if (empty($users)): ?>
                <p class="text-gray-600">No users registered.</p>
            <?php else: ?>
                <table class="w-full border-collapse bg-white rounded-lg shadow-lg">
                    <thead>
                        <tr class="bg-yellow-600 text-white">
                            <th class="p-3">Email</th>
                            <th class="p-3">First Name</th>
                            <th class="p-3">Last Name</th>
                            <th class="p-3">Role</th>
                            <th class="p-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $email => $user): ?>
                            <tr class="border-b text-gray-700">
                                <td class="p-3"><?php echo htmlspecialchars($email); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($user['first_name']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($user['last_name']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($user['role']); ?></td>
                                <td class="p-3">
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                        <button type="submit" name="delete_user" class="bg-red-600 text-white p-2 rounded hover:bg-red-700 transition duration-300">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <!-- Admin Logout -->
            <form method="POST" class="mt-6">
                <button type="submit" name="admin_logout" class="w-full bg-red-600 text-white p-2 rounded hover:bg-red-700 transition duration-300">Logout</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>