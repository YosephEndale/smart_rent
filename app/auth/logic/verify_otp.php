<?php
// app/auth/logic/verify_otp.php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/auth/data/otps.php';

function verifyOtp(string $entered_otp, int $user_id): array {
    date_default_timezone_set('Europe/Rome');

    if (!isset($_SESSION['otp_attempts'])) {
        $_SESSION['otp_attempts'] = 0;
    }

    if ($_SESSION['otp_attempts'] >= 3) {
        deleteOtp($user_id);
        return ['warning_msg' => ['Too many incorrect attempts. Please request a new OTP.']];
    }

    $entered_otp = trim($entered_otp);
    if (empty($entered_otp) || !preg_match('/^[0-9]{6}$/', $entered_otp)) {
        $_SESSION['otp_attempts']++;
        $left = 3 - $_SESSION['otp_attempts'];
        return ['warning_msg' => ["Invalid OTP format. Please enter a 6-digit code. $left attempt(s) remaining."]];
    }

    try {
        $otp_data = getOtpByUserId($user_id);

        if ($otp_data && password_verify($entered_otp, $otp_data['otp_code'])) {
            $_SESSION['user_id'] = $user_id;
            deleteOtp($user_id);
            unset($_SESSION['mfa_user_id'], $_SESSION['mfa_user_email'],
                  $_SESSION['otp_attempts'], $_SESSION['otp_resend_attempts']);
            return ['redirect' => '/index.php'];
        }

        $_SESSION['otp_attempts']++;
        $left = 3 - $_SESSION['otp_attempts'];
        return ['warning_msg' => ["Invalid or expired OTP. $left attempt(s) remaining."]];

    } catch (\PDOException $e) {
        error_log("verifyOtp DB error for user_id=$user_id: " . $e->getMessage());
        return ['warning_msg' => ['Database error. Please try again later.']];
    }
}