<?php
require_once '../../../config/env.php';
require_once '../data/users.php';
require_once '../data/otps.php';
require_once 'telegram.php';
require_once 'otp.php';

function resendOtp($user_id) {
    // Check if the user has exceeded the maximum allowed OTP resend attempts (3)
    if (isset($_SESSION['otp_resend_attempts']) && $_SESSION['otp_resend_attempts'] >= 3) {
        return ['warning_msg' => ['You have reached the maximum number of OTP resend attempts. Please try again later.']];
    }

    // Fetch user details
    $user = getUserById($user_id);
    if (!$user) {
        return ['warning_msg' => ['User not found or session expired.']];
    }

    // Load encryption key
    $ENCRYPTION_KEY = base64_decode(getenv('ENCRYPTION_KEY'));
    if (!$ENCRYPTION_KEY || strlen($ENCRYPTION_KEY) !== 32) {
        error_log('ENCRYPTION_KEY invalid or not set in .env file');
        return ['warning_msg' => ['Invalid or missing encryption key!']];
    }

    // Attempt to decrypt Telegram ID
    $telegram_id = false;
    if (!empty($user['encrypted_telegram_id'])) {
        $ciphertext = base64_decode($user['encrypted_telegram_id']);
        if ($ciphertext === false) {
            error_log("Base64 decoding failed for encrypted_telegram_id: {$user['encrypted_telegram_id']}, User ID: {$user['user_id']}");
            return ['warning_msg' => ['Invalid encrypted Telegram ID format!']];
        }
        $telegram_id = openssl_decrypt($ciphertext, 'AES-256-CBC', $ENCRYPTION_KEY, 0, substr($ENCRYPTION_KEY, 0, 16));
        if ($telegram_id === false) {
            error_log("Decryption failed for user ID: {$user['user_id']}, Error: " . openssl_error_string());
            return ['warning_msg' => ['Failed to decrypt Telegram ID! Using plaintext Telegram ID if available.']];
        }
        error_log("Decrypted Telegram ID: $telegram_id");
    }

    // Fallback to plaintext Telegram ID
    if ($telegram_id === false && !empty($user['telegram_id'])) {
        $telegram_id = $user['telegram_id'];
        error_log("Using plaintext Telegram ID: $telegram_id for user ID: {$user['user_id']}");
    }

    if ($telegram_id === false) {
        return ['warning_msg' => ['No valid Telegram ID available!']];
    }

    // Check Telegram chat
    $bot_token = getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE';
    if (!checkTelegramChat($telegram_id, $bot_token)) {
        return ['warning_msg' => ['Please send /start to @RentalMFA_Bot first!']];
    }

    // Use sendOtp to generate and send the OTP
    $result = sendOtp($user_id, $telegram_id, $bot_token);
    if (isset($result['warning_msg'])) {
        return ['warning_msg' => $result['warning_msg']];
    }

    // Increment resend attempts
    $_SESSION['otp_resend_attempts'] = isset($_SESSION['otp_resend_attempts']) ? $_SESSION['otp_resend_attempts'] + 1 : 1;
    return ['success_msg' => ['OTP resent! Please check your Telegram.'], 'redirect' => 'enter_otp.php'];
}