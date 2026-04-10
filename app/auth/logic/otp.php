<?php
// app/auth/logic/otp.php

require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/app/auth/data/otps.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Generate a 6-digit OTP, store it (hashed), and email it to the user.
 *
 * @param int    $user_id
 * @param string $email      Recipient email address
 * @param string $language   'it' or 'en'
 * @return array  Empty on success, ['warning_msg' => [...]] on failure
 */
function sendOtp(int $user_id, string $email, string $language = 'en'): array {
    try {
        date_default_timezone_set('Europe/Rome');

        // Generate OTP
        $otp        = sprintf('%06d', random_int(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);

        if ($hashed_otp === false) {
            throw new \Exception('Failed to hash OTP');
        }

        // Persist
        deleteOtp($user_id);
        insertOtp($user_id, $hashed_otp, $expires_at);

        // Build message
        $isItalian = ($language === 'it');
        $subject   = $isItalian ? 'Il tuo codice OTP - Smart Rent' : 'Your OTP code - Smart Rent';
        $bodyText  = $isItalian
            ? "Il tuo codice OTP è: $otp\nScade tra 5 minuti. Non condividerlo con nessuno."
            : "Your OTP code is: $otp\nIt expires in 5 minutes. Do not share it with anyone.";
        $bodyHtml  = buildOtpHtmlEmail($otp, $isItalian);

        // Send
        $mail = _buildMailer();
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText;
        $mail->send();

        error_log("sendOtp: OTP emailed to $email for user_id=$user_id");

        $_SESSION['otp_attempts']       = 0;
        $_SESSION['otp_resend_attempts'] = 0;
        return [];

    } catch (MailerException $e) {
        error_log("sendOtp: Mailer error for user_id=$user_id: " . $e->getMessage());
        return ['warning_msg' => ['Failed to send OTP email. Please try again.']];
    } catch (\Exception $e) {
        error_log("sendOtp: Error for user_id=$user_id: " . $e->getMessage());
        return ['warning_msg' => ['Error: ' . $e->getMessage()]];
    }
}

// ---------------------------------------------------------------------------

function _buildMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_HOST']      ?? '';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USERNAME']   ?? '';
    $mail->Password   = $_ENV['MAIL_PASSWORD']   ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
    $mail->setFrom(
        $_ENV['MAIL_FROM_ADDRESS'] ?? $mail->Username,
        $_ENV['MAIL_FROM_NAME']    ?? 'Smart Rent'
    );
    return $mail;
}

function buildOtpHtmlEmail(string $otp, bool $isItalian): string {
    $title  = $isItalian ? 'Il tuo codice OTP' : 'Your OTP Code';
    $intro  = $isItalian
        ? 'Usa il seguente codice per accedere a Smart Rent:'
        : 'Use the following code to log in to Smart Rent:';
    $expiry = $isItalian
        ? 'Il codice scade in <strong>5 minuti</strong>. Non condividerlo con nessuno.'
        : 'This code expires in <strong>5 minutes</strong>. Do not share it with anyone.';

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:0; }
    .wrap { max-width:480px; margin:40px auto; background:#fff; border-radius:8px;
            padding:32px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
    h2   { color:#333; margin-top:0; }
    .otp { font-size:2.4rem; font-weight:bold; letter-spacing:.3rem;
           color:#4f46e5; margin:24px 0; text-align:center; }
    p    { color:#555; line-height:1.6; }
    .ft  { margin-top:28px; font-size:12px; color:#999; }
  </style>
</head>
<body>
  <div class="wrap">
    <h2>Smart Rent</h2>
    <p>$intro</p>
    <div class="otp">$otp</div>
    <p>$expiry</p>
    <p class="ft">Smart Rent &mdash; $title</p>
  </div>
</body>
</html>
HTML;
}