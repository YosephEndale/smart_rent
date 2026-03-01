<?php
require_once '../../../config/env.php';
require_once '../data/users.php';
require_once '../logic/telegram.php';
require_once '../logic/otp.php';

function handleLogin($email, $pass) {
    global $conn;

    // Sanitize email input
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['warning_msg' => ['Invalid email format!']];
    }

    // Load encryption key
    $ENCRYPTION_KEY = base64_decode(getenv('ENCRYPTION_KEY'));
    if (!$ENCRYPTION_KEY || strlen($ENCRYPTION_KEY) !== 32) {
        error_log('ENCRYPTION_KEY invalid or not set in .env file');
        return ['warning_msg' => ['Invalid or missing encryption key! Please contact support.']];
    }

    try {
        // Check if user exists
        $row = getUserByEmail($email);
        if ($row) {
            if (password_verify($pass, $row['password'])) {
                // Check if telegram_id exists
                if (empty($row['encrypted_telegram_id']) && empty($row['telegram_id'])) {
                    return ['warning_msg' => ['Please update your profile with a Telegram ID for MFA.']];
                }

                // Try to decrypt the Telegram ID
                $telegram_id = false;
                if (!empty($row['encrypted_telegram_id'])) {
                    $ciphertext = base64_decode($row['encrypted_telegram_id']);
                    if ($ciphertext === false) {
                        error_log("Base64 decoding failed for encrypted_telegram_id: {$row['encrypted_telegram_id']}, User ID: {$row['user_id']}");
                        return ['warning_msg' => ['Invalid encrypted Telegram ID format!']];
                    }
                    $telegram_id = openssl_decrypt($ciphertext, 'AES-256-CBC', $ENCRYPTION_KEY, 0, substr($ENCRYPTION_KEY, 0, 16));
                    if ($telegram_id === false) {
                        error_log("Decryption failed for user ID: {$row['user_id']}, Error: " . openssl_error_string());
                        return ['warning_msg' => ['Failed to decrypt Telegram ID! Using plaintext Telegram ID if available.']];
                    }
                    error_log("Decrypted Telegram ID: $telegram_id");
                }

                // Fallback to plaintext telegram_id
                if ($telegram_id === false && !empty($row['telegram_id'])) {
                    $telegram_id = $row['telegram_id'];
                    error_log("Using plaintext Telegram ID: $telegram_id for user ID: {$row['user_id']}");
                }

                if ($telegram_id === false) {
                    return ['warning_msg' => ['No valid Telegram ID available!']];
                }

                // Check Telegram chat and send OTP
                $bot_token = getenv('TELEGRAM_BOT_TOKEN');
                if (!$bot_token || strlen($bot_token) < 30) {
                    error_log('TELEGRAM_BOT_TOKEN is missing or invalid');
                    return ['warning_msg' => ['Telegram bot configuration error. Please contact support.']];
                }

                if (!checkTelegramChat($telegram_id, $bot_token)) {
                    return ['warning_msg' => ['Please send /start to @RentalMFA_Bot first!']];
                }

                // Generate and send OTP
                $result = sendOtp($row['user_id'], $telegram_id, $bot_token);
                if (isset($result['warning_msg'])) {
                    return ['warning_msg' => $result['warning_msg']];
                }

                $_SESSION['mfa_user_id'] = $row['user_id'];
                $_SESSION['otp_resend_attempts'] = 0;
                $_SESSION['otp_attempts'] = 0;
                return ['redirect' => 'enter_otp.php'];
            } else {
                return ['warning_msg' => ['Incorrect password!']];
            }
        } else {
            return ['warning_msg' => ['No account found with that email!']];
        }
    } catch (PDOException $e) {
        error_log("Database error during login: " . $e->getMessage());
        return ['warning_msg' => ['Database error: ' . $e->getMessage()]];
    }
}