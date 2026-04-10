<?php
// app/auth/logic/resend.php

require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/app/auth/data/users.php';
require_once ROOT_DIR . '/app/auth/data/otps.php';
require_once ROOT_DIR . '/app/auth/logic/otp.php';

/**
 * Resend the OTP to the user's email address.
 * Max 3 resend attempts per session.
 */
function resendOtp(int $user_id): array {
    if (isset($_SESSION['otp_resend_attempts']) && $_SESSION['otp_resend_attempts'] >= 3) {
        return ['warning_msg' => ['Maximum resend attempts reached. Please log in again.']];
    }

    $user = getUserById($user_id);
    if (!$user) {
        return ['warning_msg' => ['User not found or session expired.']];
    }

    $toEmail  = $user['email']    ?? '';
    $language = $user['language'] ?? 'en';

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("resendOtp: Invalid email for user_id=$user_id");
        return ['warning_msg' => ['No valid email address on this account.']];
    }

    $result = sendOtp($user_id, $toEmail, $language);

    if (isset($result['warning_msg'])) {
        return $result;
    }

    $_SESSION['otp_resend_attempts'] = ($_SESSION['otp_resend_attempts'] ?? 0) + 1;

    return [
        'success_msg' => ['OTP resent! Please check your email.'],
        'redirect'    => 'enter_otp.php',
    ];
}