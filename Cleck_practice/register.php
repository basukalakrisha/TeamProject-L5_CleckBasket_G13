<?php
session_start();
include 'includes/header.php';

// Simulated database of users (in a real app, this would be a database)
$users = isset($_SESSION['users']) ? $_SESSION['users'] : [];

// Function to validate strong password
function isStrongPassword($password)
{
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
    $length = strlen($password) >= 8;
    $uppercase = preg_match('/[A-Z]/', $password);
    $lowercase = preg_match('/[a-z]/', $password);
    $number = preg_match('/[0-9]/', $password);
    $special = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);

    return $length && $uppercase && $lowercase && $number && $special;
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($contact) || empty($address) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
    } elseif (!isStrongPassword($password)) {
        $_SESSION['error'] = "Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character (e.g., !@#$%^&*).";
    } elseif (isset($users[$email])) {
        $_SESSION['error'] = "This email is already registered. Please use a different email or log in.";
    } else {
        // Register the user
        $users[$email] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'contact' => $contact,
            'address' => $address,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => isset($_POST['role']) && $_POST['role'] === 'trader' ? 'trader' : 'customer'
        ];
        $_SESSION['users'] = $users;
        $_SESSION['message'] = "Registration successful! Please log in.";
        header("Location: login.php");
        exit();
    }
}
?>

<div class="container mx-auto p-4 min-h-screen bg-gradient-to-b  to-white">
    <h1 class="text-4xl font-bold text-center mb-6 text-yellow-800">Register</h1>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <p class="text-center text-yellow-600 mb-4"><?php echo $_SESSION['message'];
                                                    unset($_SESSION['message']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <p class="text-center text-red-600 mb-4"><?php echo $_SESSION['error'];
                                                    unset($_SESSION['error']); ?></p>
    <?php endif; ?>

    <!-- Role Selection -->
    <div class="flex justify-center mb-6">
        <button onclick="showForm('customer')" class="border-b-2 border-yellow-600 p-2 text-yellow-800 font-semibold">CUSTOMER</button>
        <button onclick="showForm('trader')" class="p-2 text-gray-600 font-semibold">TRADER</button>
    </div>

    <!-- Registration Form -->
    <div id="register-form" class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-lg border border-yellow-200">
        <h2 class="text-2xl font-semibold text-yellow-800 mb-4">Register</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="role" id="role" value="customer">
            <div>
                <label class="block text-gray-700">First Name</label>
                <input type="text" name="first_name" placeholder="Enter your first name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <div>
                <label class="block text-gray-700">Last Name</label>
                <input type="text" name="last_name" placeholder="Enter your last name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <div>
                <label class="block text-gray-700">Email</label>
                <input type="email" name="email" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <div>
                <label class="block text-gray-700">Contact</label>
                <input type="text" name="contact" placeholder="Enter your contact" value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <div>
                <label class="block text-gray-700">Address</label>
                <input type="text" name="address" placeholder="Enter your address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <div>
                <label class="block text-gray-700">Password</label>
                <input type="password" name="password" placeholder="Enter your password" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
                <p class="text-sm text-gray-500 mt-1">Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character (e.g., !@#$%^&*).</p>
            </div>
            <div>
                <label class="block text-gray-700">Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Re-enter your password" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
            </div>
            <button type="submit" name="register" class="w-full bg-yellow-600 text-white p-2 rounded hover:bg-yellow-700 transition duration-300">REGISTER</button>
            <p class="text-center mt-4">Already have an account? <a href="login.php" class="text-blue-500 hover:underline">Login</a></p>
        </form>
    </div>
</div>

<script>
    function showForm(role) {
        document.getElementById('role').value = role;
        document.querySelectorAll('.flex button').forEach(btn => {
            btn.classList.remove('border-b-2', 'border-yellow-600', 'text-yellow-800');
            btn.classList.add('text-gray-600');
        });
        document.querySelector(`button[onclick="showForm('${role}')"]`).classList.add('border-b-2', 'border-yellow-600', 'text-yellow-800');
    }
</script>

<?php include 'includes/footer.php'; ?>