<?php
session_start();
include 'includes/header.php';

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // If using Composer
// If not using Composer, uncomment the following lines:
// require 'path/to/PHPMailer/src/Exception.php';
// require 'path/to/PHPMailer/src/PHPMailer.php';
// require 'path/to/PHPMailer/src/SMTP.php';

// Simulated database of users (in a real app, this would be a database)
$users = isset($_SESSION['users']) ? $_SESSION['users'] : [];
$verification_codes = isset($_SESSION['verification_codes']) ? $_SESSION['verification_codes'] : [];

// Handle login and email verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (isset($users[$email]) && password_verify($password, $users[$email]['password'])) {
        // Generate verification code
        $code = rand(100000, 999999);
        $verification_codes[$email] = $code;
        $_SESSION['verification_codes'] = $verification_codes;
        $_SESSION['pending_email'] = $email;

        // Send the verification code via email
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Gmail SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'kaushalnepal12@gmail.com'; // Your Gmail address
            $mail->Password = 'oazp glwi wmpo zvno'; // Your Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('kaushalnepal12@gmail.com', 'CleckBasket');
            $mail->addAddress($email); // Send to the user's email

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your Verification Code for CleckBasket';
            $mail->Body    = "<h2 style='color: #D97706;'>CleckBasket Verification Code</h2>
                              <p>Hello " . $users[$email]['first_name'] . ",</p>
                              <p>Your verification code is: <strong>$code</strong></p>
                              <p>Please enter this code to complete your login.</p>
                              <p>If you did not request this, please ignore this email.</p>
                              <p>Thank you,<br>The CleckBasket Team</p>";
            $mail->AltBody = "CleckBasket Verification Code\n\nHello " . $users[$email]['first_name'] . ",\nYour verification code is: $code\nPlease enter this code to complete your login.\nIf you did not request this, please ignore this email.\n\nThank you,\nThe CleckBasket Team";

            $mail->send();
            $_SESSION['message'] = "A verification code has been sent to your email.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to send verification code. Mailer Error: {$mail->ErrorInfo}";
            // Allow the user to proceed to the verification form even if the email fails to send
            // They can cancel and try a different account if needed
        }
    } else {
        $_SESSION['error'] = "Invalid email or password.";
    }
}

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $email = $_SESSION['pending_email'];
    $code = trim($_POST['code']);

    if (isset($verification_codes[$email]) && $verification_codes[$email] == $code) {
        $_SESSION['user'] = $users[$email];
        $_SESSION['user']['email'] = $email;
        unset($verification_codes[$email]);
        unset($_SESSION['pending_email']);
        $_SESSION['message'] = "Login successful!";
        header("Location: profile.php");
        exit();
    } else {
        $_SESSION['error'] = "Invalid verification code.";
    }
}

// Handle canceling verification (e.g., user wants to try a different account)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_verification'])) {
    $email = $_SESSION['pending_email'];
    unset($_SESSION['pending_email']);
    unset($verification_codes[$email]);
    $_SESSION['message'] = "Verification canceled. Please log in with a different account if needed.";
}
?>

<div class="container mx-auto p-4 min-h-screen bg-gradient-to-b  to-white">
    <h1 class="text-4xl font-bold text-center mb-6 text-yellow-800">Login</h1>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <p class="text-center text-yellow-600 mb-4"><?php echo $_SESSION['message'];
                                                    unset($_SESSION['message']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <p class="text-center text-red-600 mb-4"><?php echo $_SESSION['error'];
                                                    unset($_SESSION['error']); ?></p>
    <?php endif; ?>

    <?php if (isset($_SESSION['pending_email'])): ?>
        <!-- Verification Form -->
        <div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-lg border border-yellow-200">
            <h2 class="text-2xl font-semibold text-yellow-800 mb-4">Verify Your Email</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700">Verification Code</label>
                    <input type="text" name="code" placeholder="Enter the 6-digit code" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
                </div>
                <button type="submit" name="verify" class="w-full bg-yellow-600 text-white p-2 rounded hover:bg-yellow-700 transition duration-300">Verify</button>
                <button type="submit" name="cancel_verification" class="w-full bg-red-600 text-white p-2 rounded hover:bg-red-700 transition duration-300 mt-2">Cancel</button>
            </form>
        </div>
    <?php else: ?>
        <!-- Role Selection -->
        <div class="flex justify-center mb-6">
            <button onclick="showForm('customer')" class="border-b-2 border-yellow-600 p-2 text-yellow-800 font-semibold">CUSTOMER</button>
            <button onclick="showForm('trader')" class="p-2 text-gray-600 font-semibold">TRADER</button>
        </div>

        <!-- Login Form -->
        <div id="login-form" class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-lg border border-yellow-200">
            <h2 class="text-2xl font-semibold text-yellow-800 mb-4">Login</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700">Email</label>
                    <input type="email" name="email" placeholder="Enter your email" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
                </div>
                <div>
                    <label class="block text-gray-700">Password</label>
                    <input type="password" name="password" placeholder="Enter your password" class="w-full border rounded p-2 focus:border-yellow-400 focus:ring focus:ring-yellow-200">
                </div>
                <p class="text-blue-500 mb-4">Forgot password? <a href="forgot_password.php" class="hover:underline">Click here</a></p>
                <button type="submit" name="login" class="w-full bg-yellow-600 text-white p-2 rounded hover:bg-yellow-700 transition duration-300">LOGIN</button>
                <p class="text-center mt-4">Don't have an account? <a href="register.php" class="text-blue-500 hover:underline">Sign up</a></p>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    function showForm(role) {
        document.querySelectorAll('.flex button').forEach(btn => {
            btn.classList.remove('border-b-2', 'border-yellow-600', 'text-yellow-800');
            btn.classList.add('text-gray-600');
        });
        document.querySelector(`button[onclick="showForm('${role}')"]`).classList.add('border-b-2', 'border-yellow-600', 'text-yellow-800');
    }
</script>

<?php include 'includes/footer.php'; ?>