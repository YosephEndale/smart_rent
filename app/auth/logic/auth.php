<?php
// app/auth/logic/auth.php

require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/app/auth/data/users.php';
require_once ROOT_DIR . '/app/auth/logic/otp.php';

/**
 * Handle login form submission.
 * On success: stores mfa_user_id in session and returns ['redirect' => 'enter_otp.php']
 */
function handleLogin(string $email, string $pass): array {
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['warning_msg' => ['Invalid email format!']];
    }

    try {
        $row = getUserByEmail($email);

        if (!$row) {
            return ['warning_msg' => ['No account found with that email!']];
        }

        if (!password_verify($pass, $row['password'])) {
            return ['warning_msg' => ['Incorrect password!']];
        }

        $toEmail  = $row['email']    ?? '';
        $language = $row['language'] ?? 'en';

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("handleLogin: Invalid or missing email for user_id={$row['user_id']}");
            return ['warning_msg' => ['No valid email address on this account. Please contact support.']];
        }

        // Generate & email OTP
        $result = sendOtp((int)$row['user_id'], $toEmail, $language);

        if (isset($result['warning_msg'])) {
            return $result;
        }

        $_SESSION['mfa_user_id']         = $row['user_id'];
        $_SESSION['mfa_user_email']      = $toEmail;   // shown on enter_otp page
        $_SESSION['language']            = $language;
        $_SESSION['otp_resend_attempts'] = 0;
        $_SESSION['otp_attempts']        = 0;

        return ['redirect' => 'enter_otp.php'];

    } catch (\PDOException $e) {
        error_log('handleLogin DB error: ' . $e->getMessage());
        return ['warning_msg' => ['Database error. Please try again later.']];
    }
}