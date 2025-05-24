<?php
session_start();
require_once 'php_logic/connect.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_id"])) {
    $_SESSION['login_error'] = "Please log in to update your profile.";
    if (isset($conn)) oci_close($conn);
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_details'])) {
        // Handle update of user details
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $contact = trim($_POST['phone_number']); // Form uses 'phone_number', DB uses 'contact'
        $address = trim($_POST['address']);

        // Basic validation
        if (empty($first_name) || empty($last_name)) {
            $_SESSION['profile_error'] = "First name and last name are required.";
        } elseif (!empty($contact) && !preg_match("/^[0-9]{10}$/", $contact)) {
            $_SESSION['profile_error'] = "Contact number must be exactly 10 numeric digits.";
        } elseif (strlen($address) > 30) {
            $_SESSION['profile_error'] = "Address must not exceed 30 characters.";
        } else {
            $query_update_details = "UPDATE Users SET 
                                        first_name = :fname_bv, 
                                        last_name = :lname_bv, 
                                        contact = :contact_bv, 
                                        address = :addr_bv 
                                    WHERE user_id = :user_id_bv";
            $stmt_update_details = oci_parse($conn, $query_update_details);

            oci_bind_by_name($stmt_update_details, ":fname_bv", $first_name);
            oci_bind_by_name($stmt_update_details, ":lname_bv", $last_name);
            oci_bind_by_name($stmt_update_details, ":contact_bv", $contact);
            oci_bind_by_name($stmt_update_details, ":addr_bv", $address);
            oci_bind_by_name($stmt_update_details, ":user_id_bv", $user_id);

            if (oci_execute($stmt_update_details)) {
                $_SESSION['profile_success_message'] = "Your details have been updated successfully.";
                if ($_SESSION['first_name'] !== $first_name) {
                    $_SESSION['first_name'] = $first_name;
                }
            } else {
                $e = oci_error($stmt_update_details);
                $_SESSION['profile_error'] = "Database error updating details: " . htmlentities($e['message']);
                error_log("OCI Error in update_profile.php (update details): " . $e['message']);
            }
            oci_free_statement($stmt_update_details);
        }
    } else {
        $_SESSION['profile_error'] = "Invalid profile update request.";
    }
} else {
    $_SESSION['profile_error'] = "Invalid request method.";
}

if (isset($conn)) oci_close($conn);
header("location: profile.php");
exit;
?>