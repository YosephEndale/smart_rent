<?php
namespace App\Notifications\Logic;

use App\Notifications\Data\notification_logs;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/app/notifications/data/notification_logs.php';

class SendNotification {
    private PDO $db;
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUser;
    private string $smtpPass;
    private string $fromEmail;
    private string $fromName;

    public function __construct(PDO $db) {
        $this->db = $db;

        $this->smtpHost  = $_ENV['MAIL_HOST']      ?? '';
        $this->smtpPort  = (int)($_ENV['MAIL_PORT'] ?? 587);
        $this->smtpUser  = $_ENV['MAIL_USERNAME']   ?? '';
        $this->smtpPass  = $_ENV['MAIL_PASSWORD']   ?? '';
        $this->fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? $this->smtpUser;
        $this->fromName  = $_ENV['MAIL_FROM_NAME']    ?? 'Smart Rent';

        if (!$this->smtpHost || !$this->smtpUser || !$this->smtpPass) {
            error_log('SendNotification: Missing SMTP configuration in .env');
            throw new \Exception('Invalid mail configuration');
        }
    }

    /**
     * Send an email notification to a user.
     *
     * @param int|string $user_id
     * @param string     $message   The sender name / short context shown in the email body
     * @param int|null   $property_id
     * @return bool
     */
    public function sendEmailNotification($user_id, string $message, $property_id = null): bool {
        error_log("SendNotification: Attempting email to user_id=$user_id, property_id=$property_id");

        if (empty($message)) {
            error_log("SendNotification: Empty message for user_id=$user_id");
            return false;
        }

        // --- fetch user -------------------------------------------------
        $stmt = $this->db->prepare(
            "SELECT email, email_notifications, language FROM users WHERE user_id = ?"
        );
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("SendNotification: User not found for user_id=$user_id");
            return false;
        }

        if (empty($user['email_notifications'])) {
            error_log("SendNotification: Email notifications disabled for user_id=$user_id");
            return false;
        }

        $toEmail = $user['email'] ?? '';
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("SendNotification: Invalid email address for user_id=$user_id");
            return false;
        }

        // --- rate limit -------------------------------------------------
        $logs = new notification_logs($this->db);
        if (!$logs->checkRateLimit($user_id)) {
            error_log("SendNotification: Rate limit exceeded for user_id=$user_id");
            return false;
        }

        // --- build message ----------------------------------------------
        $isItalian = ($user['language'] === 'it');

        $subject = $isItalian
            ? 'Nuovo messaggio su Smart Rent'
            : 'New message on Smart Rent';

        $bodyText = $isItalian
            ? "Hai un nuovo messaggio da {$message}. Controlla la tua casella di posta sulla nostra piattaforma."
            : "You have a new message from {$message}. Check your inbox on our platform.";

        $bodyHtml = $this->buildHtmlEmail($bodyText, $isItalian);

        // --- send via PHPMailer -----------------------------------------
        $mail = new PHPMailer(true);
        try {
            // Server
            $mail->isSMTP();
            $mail->Host       = $this->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtpUser;
            $mail->Password   = $this->smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->smtpPort;

            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyText;

            $mail->send();

            error_log("SendNotification: Email sent to $toEmail for user_id=$user_id");
            $logs->logNotification($user_id, $property_id, $bodyText);
            return true;

        } catch (MailerException $e) {
            error_log("SendNotification: PHPMailer error for user_id=$user_id: " . $mail->ErrorInfo);
            return false;
        }
    }

    // -----------------------------------------------------------------------
    // Kept for backward-compatibility so callers using the old method name
    // still work without touching every call-site immediately.
    // -----------------------------------------------------------------------
    public function sendTelegramNotification($user_id, $message, $property_id = null): bool {
        return $this->sendEmailNotification($user_id, $message, $property_id);
    }

    // -----------------------------------------------------------------------
    private function buildHtmlEmail(string $bodyText, bool $isItalian): string {
        $platformLabel = $isItalian ? 'Vai alla piattaforma' : 'Go to platform';
        $footerText    = $isItalian
            ? 'Stai ricevendo questa email perché hai attivato le notifiche su Smart Rent.'
            : 'You are receiving this email because you enabled notifications on Smart Rent.';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
    .container { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px;
                 padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    h2 { color: #333; }
    p  { color: #555; line-height: 1.6; }
    .btn { display: inline-block; margin-top: 20px; padding: 12px 24px;
           background: #4f46e5; color: #fff; border-radius: 6px;
           text-decoration: none; font-weight: bold; }
    .footer { margin-top: 32px; font-size: 12px; color: #999; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Smart Rent</h2>
    <p>{$bodyText}</p>
    <a class="btn" href="/">$platformLabel</a>
    <p class="footer">$footerText</p>
  </div>
</body>
</html>
HTML;
    }
}