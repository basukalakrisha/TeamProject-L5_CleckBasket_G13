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

// Clear previous session messages
unset($_SESSION['login_error'], $_SESSION['info_message'], $_SESSION['form_data']);

// Secret key for token hashing (store in config.php in production)
define('REMEMBER_ME_SECRET', 'your-secure-secret-key-here'); // Replace with a strong, unique key

// Check for remember-me cookie
$remember_token = $_COOKIE['remember_me_token'] ?? null;
if ($remember_token && !isset($_POST['email'])) {
    // Parse the token: format is "user_id:expires:random:hash"
    $token_parts = explode(':', $remember_token);
    if (count($token_parts) === 4) {
        list($user_id, $expires, $random, $stored_hash) = $token_parts;
        if (is_numeric($user_id) && is_numeric($expires) && $expires > time()) {
            $sql = "SELECT user_id, first_name, email, usertype, email_verified 
                    FROM USERS WHERE user_id = :user_id";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ":user_id", $user_id);
            if (oci_execute($stmt)) {
                $user = oci_fetch_assoc($stmt);
                if ($user && $user['EMAIL_VERIFIED'] === 'Y') {
                    // Regenerate hash to verify
                    $data = $user_id . ':' . $user['EMAIL'] . ':' . $expires . ':' . $random;
                    $computed_hash = hash_hmac('sha256', $data, REMEMBER_ME_SECRET);
                    if (hash_equals($stored_hash, $computed_hash)) {
                        if ($user['USERTYPE'] === 'TRADER') {
                            $sqlShop = "SELECT approval_status FROM SHOP WHERE fk1_user_id = :user_id";
                            $stmtShop = oci_parse($conn, $sqlShop);
                            oci_bind_by_name($stmtShop, ":user_id", $user['USER_ID']);
                            oci_execute($stmtShop);
                            $shop = oci_fetch_assoc($stmtShop);
                            oci_free_statement($stmtShop);

                            if ($shop && $shop['APPROVAL_STATUS'] === 'Approved') {
                                $_SESSION['user_id'] = $user['USER_ID'];
                                $_SESSION['first_name'] = $user['FIRST_NAME'];
                                $_SESSION['usertype'] = $user['USERTYPE'];
                                oci_free_statement($stmt);
                                oci_close($conn);
                                header("Location: dashboard.php"); // Adjust to your dashboard page
                                exit;
                            }
                        } else {
                            $_SESSION['user_id'] = $user['USER_ID'];
                            $_SESSION['first_name'] = $user['FIRST_NAME'];
                            $_SESSION['usertype'] = $user['USERTYPE'];
                            oci_free_statement($stmt);
                            oci_close($conn);
                            header("Location: dashboard.php"); // Adjust to your dashboard page
                            exit;
                        }
                    }
                }
            }
            oci_free_statement($stmt);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'], $_POST['password'])) {
    // CSRF token check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['login_error'] = "Invalid CSRF token.";
        error_log("login_process.php - CSRF token mismatch");
        header("Location: login.php");
        exit;
    }

    $email = trim($_POST['email']);
    $passwordInput = trim($_POST['password']);
    $remember_me = isset($_POST['remember_me']);

    $_SESSION['form_data'] = ['email' => $email];

    // Input validation
    if (empty($email) || empty($passwordInput)) {
        $_SESSION['login_error'] = "Email and password are required.";
        header("Location: login.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['login_error'] = "Invalid email format.";
        header("Location: login.php");
        exit;
    }

    // Oracle DB check
    if (!$conn || !is_resource($conn)) {
        $_SESSION['login_error'] = "Database connection failed.";
        header("Location: login.php");
        exit;
    }

    // Fetch user
    $sql = "SELECT user_id, first_name, password, email_verified, usertype FROM USERS WHERE email = :email_bv";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":email_bv", $email);
    error_log("Attempting login with email: " . $email);

    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("OCI Execute Error: " . json_encode($e));
        $_SESSION['login_error'] = "Database error during login: Execute failure.";
        oci_free_statement($stmt);
        oci_close($conn);
        header("Location: login.php");
        exit;
    }

    $user = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if ($user) {
        // Password verification based on user type
        $passwordValid = false;
        if ($user['USERTYPE'] === 'TRADER') {
            $passwordValid = ($passwordInput === $user['PASSWORD']);
            error_log("Trader login attempt - using plain text comparison");
        } else {
            $passwordValid = password_verify($passwordInput, $user['PASSWORD']);
            error_log("Customer login attempt - using password_verify");
        }

        if ($passwordValid) {
            if ($user['EMAIL_VERIFIED'] === 'Y') {
                // If trader, check approval
                if ($user['USERTYPE'] === 'TRADER') {
                    $sqlShop = "SELECT approval_status FROM SHOP WHERE fk1_user_id = :user_id";
                    $stmtShop = oci_parse($conn, $sqlShop);
                    oci_bind_by_name($stmtShop, ":user_id", $user['USER_ID']);
                    oci_execute($stmtShop);
                    $shop = oci_fetch_assoc($stmtShop);
                    oci_free_statement($stmtShop);

                    if (!$shop || $shop['APPROVAL_STATUS'] !== 'Approved') {
                        error_log("Trader approval status check failed. Status: " . ($shop ? $shop['APPROVAL_STATUS'] : 'No shop record'));
                        $_SESSION['login_error'] = "Your trader account is pending approval.";
                        oci_close($conn);
                        header("Location: login.php");
                        exit;
                    }
                    error_log("Trader approval status check passed. Status: " . $shop['APPROVAL_STATUS']);
                }

                // Handle remember-me functionality
                if ($remember_me) {
                    $expires = time() + 30 * 24 * 60 * 60; // 30 days
                    $random = bin2hex(random_bytes(16));
                    $data = $user['USER_ID'] . ':' . $email . ':' . $expires . ':' . $random;
                    $token_hash = hash_hmac('sha256', $data, REMEMBER_ME_SECRET);
                    $token = $user['USER_ID'] . ':' . $expires . ':' . $random . ':' . $token_hash;

                    setcookie('remember_me_token', $token, [
                        'expires' => $expires,
                        'path' => '/',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }

                // Generate 2FA code if not using remember-me
                if (!$remember_me) {
                    $code = rand(100000, 999999);
                    $_SESSION['login_verification_code'] = $code;
                    $_SESSION['login_code_timestamp'] = time();
                    $_SESSION['login_user_id_attempt'] = $user['USER_ID'];
                    $_SESSION['login_email_attempt'] = $email;
                    $_SESSION['login_user_type_attempt'] = $user['USERTYPE'];
                    $_SESSION['login_first_name_attempt'] = $user['FIRST_NAME'];

                    // Send email
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
                        $mail->Subject = 'Your CleckBasket Login Verification Code';
                        $mail->Body = "<p>Hello " . htmlspecialchars($user['FIRST_NAME']) . ",</p>
                                       <p>Your login code is: <strong>$code</strong></p>
                                       <p>Valid for 5 minutes. If not you, ignore this.</p>";
                        $mail->send();

                        $_SESSION['info_message'] = "A verification code has been sent to your email.";
                    } catch (Exception $e) {
                        error_log("Mail error: " . $mail->ErrorInfo);
                        $_SESSION['info_message'] = "Verification email failed. For testing, code is: $code";
                    }

                    unset($_SESSION['form_data']);
                    oci_close($conn);
                    header("Location: verify_login_code.php");
                    exit;
                } else {
                    // Skip OTP and log in directly
                    $_SESSION['user_id'] = $user['USER_ID'];
                    $_SESSION['first_name'] = $user['FIRST_NAME'];
                    $_SESSION['usertype'] = $user['USERTYPE'];
                    unset($_SESSION['form_data']);
                    oci_close($conn);
                    header("Location: trader_manage_products.php"); // Adjust to your dashboard page
                    exit;
                }
            } else {
                $_SESSION['login_error'] = "Your email is not verified. <a href='resent_varification.php?email=" . urlencode($email) . "'>Resend email?</a>";
            }
        } else {
            $_SESSION['login_error'] = "Invalid email or password.";
        }
    } else {
        $_SESSION['login_error'] = "No account found with that email.";
    }

    oci_close($conn);
    header("Location: login.php");
    exit;
}

if (isset($conn) && is_resource($conn)) {
    oci_close($conn);
}
header("Location: login.php");
exit;