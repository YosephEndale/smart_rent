<?php
namespace App\Chat\Logic;
use PDO;
use PDOException;

require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/notifications/logic/SendNotification.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Notifications\Logic\SendNotification;

class chat_processor {
    private $db;
    private $client;

    public function __construct() {
        // Set OpenSSL environment variables
        putenv("OPENSSL_CONF=C:/wamp64/bin/php/php8.2.18/extras/ssl/openssl.cnf");
        putenv("TMP=C:/wamp64/tmp");
        putenv("TEMP=C:/wamp64/tmp");

        try {
            $this->db = get_db_connection();
        } catch (PDOException $e) {
            error_log("chat_processor: Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection error");
        }

        $this->client = new Client([
            'base_uri' => 'https://openrouter.ai/api/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['OPENROUTER_API_KEY'],
                'Content-Type' => 'application/json',
            ],
            'verify' => false,
            'timeout' => 30,
        ]);
    }

    public function getConversations($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT DISTINCT cm.property_id, p.property_name, u.name as receiver_name,
                CASE 
                    WHEN cm.sender_id = ? THEN cm.receiver_id 
                    ELSE cm.sender_id 
                END as other_user_id
                FROM chat_messages cm
                JOIN property p ON cm.property_id = p.id
                JOIN users u ON (
                    (cm.sender_id = u.user_id AND cm.receiver_id = ?) OR 
                    (cm.receiver_id = u.user_id AND cm.sender_id = ?)
                )
                WHERE (cm.sender_id = ? OR cm.receiver_id = ?)
                ORDER BY cm.timestamp DESC");
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("chat_processor: Error fetching conversations for user_id=$user_id: " . $e->getMessage());
            return [];
        }
    }

    public function getMessages($user_id, $other_user_id, $property_id) {
        try {
            $property_stmt = $this->db->prepare("SELECT id, user_id, property_name FROM property WHERE id = ?");
            $property_stmt->execute([$property_id]);
            $property = $property_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$property) {
                error_log("chat_processor: Property not found for property_id=$property_id");
                return ['error' => 'Property not found'];
            }
            if ($other_user_id == $user_id) {
                error_log("chat_processor: Self-chat attempted by user_id=$user_id");
                return ['error' => 'You cannot chat with yourself'];
            }

            $stmt = $this->db->prepare("SELECT cm.*, u.name as sender_name
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.user_id
                WHERE cm.property_id = ?
                AND ((cm.sender_id = ? AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = ?))
                ORDER BY cm.timestamp ASC");
            $stmt->execute([$property_id, $user_id, $other_user_id, $other_user_id, $user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $user_lang_stmt = $this->db->prepare("SELECT language FROM users WHERE user_id = ?");
            $user_lang_stmt->execute([$user_id]);
            $user_language = $user_lang_stmt->fetch(PDO::FETCH_ASSOC)['language'] ?? 'en';

            foreach ($messages as &$msg) {
                // Decrypt the original message
                $decoded = json_decode($msg['message'], true);
                $b64 = $msg['sender_id'] == $user_id ? ($decoded['sender'] ?? '') : ($decoded['receiver'] ?? '');
                $encrypted = base64_decode($b64);
                $decrypted_message = "[Error decrypting]";

                $private_path = "C:/secure_keys/{$user_id}_private.pem";
                if (file_exists($private_path)) {
                    $priv_key = file_get_contents($private_path);
                    if (openssl_private_decrypt($encrypted, $output, $priv_key)) {
                        $decrypted_message = $output;
                    } else {
                        error_log("chat_processor: Decryption failed for message ID {$msg['id']}, user_id=$user_id: " . openssl_error_string());
                    }
                } else {
                    error_log("chat_processor: Private key not found for user_id=$user_id at $private_path");
                }

                // Decrypt the translated message
                $decoded_translated = json_decode($msg['translated_message'], true);
                $translated_b64 = $msg['sender_id'] == $user_id ? ($decoded_translated['sender'] ?? '') : ($decoded_translated['receiver'] ?? '');
                $encrypted_translated = base64_decode($translated_b64);
                $decrypted_translated_message = $decrypted_message; // Fallback to decrypted original message

                if ($translated_b64 && $msg['translated_language'] === $user_language) {
                    if (file_exists($private_path)) {
                        $priv_key = file_get_contents($private_path);
                        if (openssl_private_decrypt($encrypted_translated, $translated_output, $priv_key)) {
                            $decrypted_translated_message = $translated_output;
                        } else {
                            error_log("chat_processor: Decryption failed for translated message ID {$msg['id']}, user_id=$user_id: " . openssl_error_string());
                        }
                    } else {
                        error_log("chat_processor: Private key not found for user_id=$user_id at $private_path");
                    }
                }

                // Use decrypted translated_message if the user's language matches translated_language, else use decrypted original message
                $msg['display_message'] = ($msg['translated_language'] === $user_language && $decrypted_translated_message !== "[Error decrypting]")
                    ? $decrypted_translated_message
                    : $decrypted_message;
            }

            return $messages;
        } catch (PDOException $e) {
            error_log("chat_processor: Error in getMessages for user_id=$user_id, property_id=$property_id: " . $e->getMessage());
            return ['error' => 'Database error'];
        }
    }

    public function sendMessage($sender_id, $receiver_id, $property_id, $message) {
        if (empty($message)) {
            error_log("chat_processor: Empty message attempted by sender_id=$sender_id to receiver_id=$receiver_id, property_id=$property_id");
            return ['error' => 'Message cannot be empty'];
        }

        try {
            $sender_stmt = $this->db->prepare("SELECT public_key, language, name FROM users WHERE user_id = ?");
            $sender_stmt->execute([$sender_id]);
            $receiver_stmt = $this->db->prepare("SELECT public_key, language, name FROM users WHERE user_id = ?");
            $receiver_stmt->execute([$receiver_id]);
            $sender = $sender_stmt->fetch(PDO::FETCH_ASSOC);
            $receiver = $receiver_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sender || !$receiver || empty($sender['public_key']) || empty($receiver['public_key'])) {
                error_log("chat_processor: Missing public key or user data for sender_id=$sender_id or receiver_id=$receiver_id");
                return ['error' => 'Missing public key(s) or user data'];
            }

            $sender_key = $sender['public_key'];
            $receiver_key = $receiver['public_key'];
            $sender_language = $sender['language'] ?? 'en';
            $receiver_language = $receiver['language'] ?? 'en';
            $detected_language = $sender_language;

            $language_map = [
                'hi' => ['language' => 'en', 'it' => 'ciao', 'en' => 'hi'],
                'hello' => ['language' => 'en', 'it' => 'salve', 'en' => 'hello'],
                'hey' => ['language' => 'en', 'it' => 'ciao', 'en' => 'hey'],
                'ciao' => ['language' => 'it', 'it' => 'ciao', 'en' => 'hello'],
                'salve' => ['language' => 'it', 'it' => 'salve', 'en' => 'hello'],
                'buonasera' => ['language' => 'it', 'it' => 'buonasera', 'en' => 'good evening'],
                'good evening' => ['language' => 'en', 'it' => 'buonasera', 'en' => 'good evening'],
                'ciao bello' => ['language' => 'it', 'it' => 'ciao bello', 'en' => 'hello guy'],
            ];

            $translated_message = $message;
            $translated_language = $detected_language;

            $lowercase_message = strtolower($message);
            if (isset($language_map[$lowercase_message])) {
                $detected_language = $language_map[$lowercase_message]['language'];
                if ($receiver_language === 'it' && $detected_language === 'en') {
                    $translated_message = $language_map[$lowercase_message]['it'];
                    $translated_language = 'it';
                } elseif ($receiver_language === 'en' && $detected_language === 'it') {
                    $translated_message = $language_map[$lowercase_message]['en'];
                    $translated_language = 'en';
                }
            } elseif ($receiver_language !== $detected_language && strlen($message) > 2) {
                $source_language = $detected_language === 'it' ? 'Italian' : 'English';
                $target_language = $receiver_language === 'it' ? 'Italian' : 'English';

                $translate_prompt = [
                    'model' => 'mistralai/mixtral-8x7b-instruct',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Translate the following text from {$source_language} to {$target_language}. Return only the translated text as a single string, without any explanations, notes, or additional context. Do not include any descriptions or parentheses. If the input is too short or ambiguous, return a single {$target_language} word or phrase that best matches the input.",
                        ],
                        ['role' => 'user', 'content' => $message],
                    ],
                    'max_tokens' => 50,
                ];

                try {
                    $response = $this->client->post('chat/completions', ['json' => $translate_prompt]);
                    $api_data = json_decode($response->getBody(), true);
                    $translated_message = trim(explode("\n", $api_data['choices'][0]['message']['content'] ?? $message)[0]);
                    $translated_message = preg_replace('/\s*\([^)]+\)/', '', $translated_message);
                    $translated_language = $receiver_language;
                } catch (RequestException $e) {
                    error_log("chat_processor: Translation failed for sender_id=$sender_id, message='$message': " . $e->getMessage());
                    $translated_message = $message;
                    $translated_language = $detected_language;
                }
            }

            if (empty($sender['language'])) {
                $update_lang = $this->db->prepare("UPDATE users SET language = ? WHERE user_id = ?");
                $update_lang->execute([$detected_language, $sender_id]);
            }

            while (openssl_error_string()) {}
            // Encrypt original message
            if (
                openssl_public_encrypt($message, $encrypted_sender, $sender_key) &&
                openssl_public_encrypt($message, $encrypted_receiver, $receiver_key)
            ) {
                $encoded = json_encode([
                    'sender' => base64_encode($encrypted_sender),
                    'receiver' => base64_encode($encrypted_receiver),
                ]);
            } else {
                error_log("chat_processor: Encryption failed for original message, sender_id=$sender_id: " . openssl_error_string());
                return ['error' => 'Encryption failed for original message'];
            }

            // Encrypt translated message
            $encoded_translated = $encoded; // Fallback to original message encoding
            if ($translated_message !== $message) { // Only encrypt if translation occurred
                if (
                    openssl_public_encrypt($translated_message, $encrypted_translated_sender, $sender_key) &&
                    openssl_public_encrypt($translated_message, $encrypted_translated_receiver, $receiver_key)
                ) {
                    $encoded_translated = json_encode([
                        'sender' => base64_encode($encrypted_translated_sender),
                        'receiver' => base64_encode($encrypted_translated_receiver),
                    ]);
                } else {
                    error_log("chat_processor: Encryption failed for translated message, sender_id=$sender_id: " . openssl_error_string());
                    return ['error' => 'Encryption failed for translated message'];
                }
            }

            $insert = $this->db->prepare("INSERT INTO chat_messages 
                (sender_id, receiver_id, property_id, message, original_language, translated_message, translated_language, timestamp)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert->execute([$sender_id, $receiver_id, $property_id, $encoded, $detected_language, $encoded_translated, $translated_language]);

            if ($insert->rowCount() > 0) {
                $notificationSender = new SendNotification($this->db);
                error_log("chat_processor: Attempting to send notification to receiver_id=$receiver_id, property_id=$property_id, sender_name='{$sender['name']}'");
                $notification_success = $notificationSender->sendTelegramNotification($receiver_id, $sender['name'], $property_id);
                error_log("chat_processor: Notification attempt for receiver_id=$receiver_id, property_id=$property_id, success=$notification_success");
                return ['success' => 'Message sent'];
            } else {
                error_log("chat_processor: Message insert failed for sender_id=$sender_id, receiver_id=$receiver_id, property_id=$property_id");
                return ['error' => 'Failed to send message'];
            }
        } catch (PDOException $e) {
            error_log("chat_processor: Database error in sendMessage for sender_id=$sender_id, receiver_id=$receiver_id: " . $e->getMessage());
            return ['error' => 'Database error: ' . $e->getMessage()];
        } catch (RequestException $e) {
            error_log("chat_processor: Translation service error in sendMessage for sender_id=$sender_id: " . $e->getMessage());
            return ['error' => 'Translation service error: ' . $e->getMessage()];
        }
    }

    public function markMessagesAsRead($user_id, $property_id) {
        try {
            $stmt = $this->db->prepare("UPDATE chat_messages SET is_read = 1 WHERE receiver_id = ? AND property_id = ? AND is_read = 0");
            $stmt->execute([$user_id, $property_id]);
            error_log("chat_processor: Marked messages as read for user_id=$user_id, property_id=$property_id");
            return true;
        } catch (PDOException $e) {
            error_log("chat_processor: Error marking messages as read for user_id=$user_id, property_id=$property_id: " . $e->getMessage());
            return false;
        }
    }
}