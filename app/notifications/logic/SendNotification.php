<?php
namespace App\Notifications\Logic;

use App\Notifications\Data\notification_logs;
use PDO;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/app/notifications/data/notification_logs.php';

class SendNotification {
    private $db;
    private $bot_token;
    private $encryption_key;
    private $client;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        $this->encryption_key = base64_decode($_ENV['ENCRYPTION_KEY'] ?? '');
        $this->client = new Client([
            'timeout' => 10,
            'verify' => false // Disable SSL verification for local development
        ]);
        if (!$this->bot_token) {
            error_log('SendNotification: Missing TELEGRAM_BOT_TOKEN');
            throw new \Exception('Invalid Telegram configuration');
        }
        if (strlen($this->encryption_key) !== 32) {
            error_log('SendNotification: Invalid ENCRYPTION_KEY length');
            throw new \Exception('Invalid encryption key');
        }
    }

    public function sendTelegramNotification($user_id, $message, $property_id = null) {
        error_log("SendNotification: Attempting to send notification to user_id=$user_id, property_id=$property_id, message='$message'");

        if (empty($message)) {
            error_log("SendNotification: Empty message for user_id=$user_id");
            return false;
        }

        $stmt = $this->db->prepare("SELECT telegram_id, encrypted_telegram_id, telegram_notifications, language FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("SendNotification: User not found for user_id=$user_id");
            return false;
        }

        if (!$user['telegram_notifications']) {
            error_log("SendNotification: Telegram notifications disabled for user_id=$user_id");
            return false;
        }

        $telegram_id = false;
        // Try to decrypt encrypted_telegram_id
        if (!empty($user['encrypted_telegram_id'])) {
            // Check if encrypted_telegram_id is a bcrypt hash
            if (preg_match('/^\$2y\$10\$/', $user['encrypted_telegram_id'])) {
                error_log("SendNotification: encrypted_telegram_id is a bcrypt hash for user_id=$user_id, encrypted_telegram_id='{$user['encrypted_telegram_id']}'. Expected AES-256-CBC encrypted value.");
            } else {
                $ciphertext = base64_decode($user['encrypted_telegram_id'], true);
                if ($ciphertext !== false) {
                    $telegram_id = openssl_decrypt($ciphertext, 'AES-256-CBC', $this->encryption_key, 0, substr($this->encryption_key, 0, 16));
                    if ($telegram_id === false) {
                        error_log("SendNotification: Decryption failed for user_id=$user_id: " . openssl_error_string());
                    } else {
                        error_log("SendNotification: Decrypted telegram_id=$telegram_id for user_id=$user_id");
                    }
                } else {
                    error_log("SendNotification: Base64 decode failed for encrypted_telegram_id='{$user['encrypted_telegram_id']}' for user_id=$user_id");
                }
            }
        }

        // Fallback to plaintext telegram_id
        if ($telegram_id === false && !empty($user['telegram_id'])) {
            $telegram_id = $user['telegram_id'];
            error_log("SendNotification: Using plaintext telegram_id=$telegram_id for user_id=$user_id");
        }

        if ($telegram_id === false) {
            error_log("SendNotification: No valid Telegram ID for user_id=$user_id");
            return false;
        }

        // Validate telegram_id is numeric
        if (!is_numeric($telegram_id)) {
            error_log("SendNotification: Invalid Telegram ID format for user_id=$user_id, telegram_id='$telegram_id'");
            return false;
        }

        error_log("SendNotification: Using telegram_id=$telegram_id for user_id=$user_id");

        // Format notification message based on user's language
        $notification_message = ($user['language'] === 'it')
            ? "Hai un nuovo messaggio da <b>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</b>. Controlla la tua casella di posta sulla nostra piattaforma."
            : "You have a new message from <b>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</b>. Check your inbox on our platform.";

        $url = "https://api.telegram.org/bot{$this->bot_token}/sendMessage";
        $data = [
            'chat_id' => $telegram_id,
            'text' => $notification_message,
            'parse_mode' => 'HTML'
        ];

        error_log("SendNotification: Sending Telegram message to telegram_id=$telegram_id: $notification_message");

        try {
            $response = $this->client->post($url, ['form_params' => $data]);
            $response_data = json_decode($response->getBody(), true);
            if ($response->getStatusCode() == 200 && $response_data['ok']) {
                error_log("SendNotification: Telegram notification sent successfully for user_id=$user_id, telegram_id=$telegram_id");
                $logs = new notification_logs($this->db);
                $logs->logNotification($user_id, $property_id, $notification_message);
                return true;
            }
            $error = $response_data['description'] ?? 'Unknown error';
            error_log("SendNotification: Telegram API error for user_id=$user_id: $error");
            return false;
        } catch (RequestException $e) {
            error_log("SendNotification: Telegram sendMessage error for user_id=$user_id: " . $e->getMessage());
            return false;
        }
    }
}