<?php
// app/auth/logic/otp.php
require_once '../data/otps.php';

function sendOtp($user_id, $telegram_id, $bot_token) {
    try {
        date_default_timezone_set('Europe/Rome'); // Set to Rome time zone (CEST on June 29, 2025)
        $otp = sprintf("%06d", rand(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
        if ($hashed_otp === false) {
            throw new Exception("Failed to hash OTP");
        }

        deleteOtp($user_id);
        insertOtp($user_id, $hashed_otp, $expires_at);

        $message = "Your OTP is: $otp";
        $url = "https://api.telegram.org/bot$bot_token/sendMessage";
        $data = ['chat_id' => $telegram_id, 'text' => $message];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response_data = json_decode($response, true);
        if ($response === false || $http_code != 200 || !$response_data['ok']) {
            $error = isset($response_data['description']) ? $response_data['description'] : 'Network or SSL error';
            error_log("Telegram API error for user ID $user_id: $error");
            return ['warning_msg' => ['Failed to send OTP: ' . $error]];
        }

        $_SESSION['otp_attempts'] = 0;
        $_SESSION['otp_resend_attempts'] = 0;

        return [];
    } catch (Exception $e) {
        error_log("Error sending OTP for user ID $user_id: " . $e->getMessage());
        return ['warning_msg' => ['Error: ' . $e->getMessage()]];
    }
}
?>