<?php
// Include necessary files
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php'; 
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/auth/data/otps.php';

function verifyOtp($entered_otp, $user_id) {
    global $conn;

    // Set time zone to Rome (CEST)
    date_default_timezone_set('Europe/Rome');

    // Initialize session variables
    if (!isset($_SESSION['otp_attempts'])) {
        $_SESSION['otp_attempts'] = 0;
    }

    // Check if the user has exceeded the maximum allowed OTP attempts (3 attempts)
    if ($_SESSION['otp_attempts'] >= 3) {
        deleteOtp($user_id); // Clear OTP to force resend
        return ['warning_msg' => ['Too many incorrect OTP attempts. Please request a new OTP.']];
    }

    // Validate the OTP (check if it's empty or not a 6-digit number)
    $entered_otp = trim($entered_otp); // Remove any whitespace
    if (empty($entered_otp) || !preg_match('/^[0-9]{6}$/', $entered_otp)) {
        $_SESSION['otp_attempts']++;
        return ['warning_msg' => ['Invalid OTP format. Please enter a 6-digit OTP. ' . (3 - $_SESSION['otp_attempts']) . ' attempts remaining.']];
    }

    try {
        // Fetch the hashed OTP from the database
        $otp_data = getOtpByUserId($user_id);

        // If OTP exists and is valid
        if ($otp_data && password_verify($entered_otp, $otp_data['otp_code'])) {
            // OTP is correct, proceed with login
            $_SESSION['user_id'] = $user_id;

            // Delete the used OTP
            deleteOtp($user_id);

            // Clear MFA session variables
            unset($_SESSION['mfa_user_id']);
            unset($_SESSION['otp_attempts']);
            unset($_SESSION['otp_resend_attempts']);

            // Redirect to index.php at the web root
            return ['redirect' => '/index.php'];
        } else {
            // Increment failed attempt count
            $_SESSION['otp_attempts']++;
            $attempts_left = 3 - $_SESSION['otp_attempts'];
            return ['warning_msg' => ['Invalid or expired OTP. ' . $attempts_left . ' attempts remaining.']];
        }
    } catch (PDOException $e) {
        error_log("Database error verifying OTP for user ID $user_id: " . $e->getMessage());
        return ['warning_msg' => ['Database error. Please try again later.']];
    }
}
?>