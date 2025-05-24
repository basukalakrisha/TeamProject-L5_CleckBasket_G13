<?php
session_start();
require_once 'php_logic/connect.php';
require_once 'vendor/autoload.php';
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Security & Encoding
ini_set('default_charset', 'UTF-8');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Get email from query parameter
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// Check for error or success message
$error_message = $_SESSION['resend_error'] ?? null;
$success_message = $_SESSION['resend_success'] ?? null;
unset($_SESSION['resend_error'], $_SESSION['resend_success']);

// Process resend request
if (!empty($email)) {
    // Check if email exists and is not verified
    $query = "SELECT user_id, first_name, email_verified, email_verification_token FROM USERS WHERE email = :email";
    $stmt = oci_parse($conn, $query);
    oci_bind_by_name($stmt, ":email", $email);
    oci_execute($stmt);
    $user = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$user) {
        $_SESSION['resend_error'] = "Email not found.";
        header("Location: login.php");
        exit;
    }

    if ($user['EMAIL_VERIFIED'] === 'Y') {
        $_SESSION['resend_error'] = "Your email is already verified. Please login.";
        header("Location: login.php");
        exit;
    }

    // Generate new token if needed
    $token = $user['EMAIL_VERIFICATION_TOKEN'];
    if (empty($token)) {
        $token = bin2hex(random_bytes(32));
        $query = "UPDATE USERS SET email_verification_token = :token WHERE user_id = :user_id";
        $stmt = oci_parse($conn, $query);
        oci_bind_by_name($stmt, ":token", $token);
        oci_bind_by_name($stmt, ":user_id", $user['USER_ID']);
        oci_execute($stmt);
        oci_free_statement($stmt);
    }

    // Send verification email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('noreply@cleckbasket.local', 'CleckBasket Team');
        $mail->addAddress($email, $user['FIRST_NAME']);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your CleckBasket Account';

        // FIXED: Get the directory path dynamically to handle subdirectory deployments
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $base_path = ($script_dir == '/' ? '' : $script_dir);
        
        // Create verification link with correct path
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . $base_path . "/verify_email.php?token=" . urlencode($token);

        $mail->Body = "<p>Hello " . htmlspecialchars($user['FIRST_NAME']) . ",</p>
                    <p>Thank you for registering with CleckBasket. Please click the link below to verify your email address:</p>
                    <p><a href=\"" . htmlspecialchars($verification_link) . "\">Verify Email</a></p>
                    <p>If you did not create this account, please ignore this email.</p>";
        $mail->send();

        $_SESSION['resend_success'] = "Verification email has been resent. Please check your inbox.";
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        $_SESSION['resend_error'] = "Failed to send verification email. Please try again later.";
    }

    header("Location: login.php");
    exit;
}

// If no email provided, redirect to login
if (empty($email)) {
    header("Location: login.php");
    exit;
}
