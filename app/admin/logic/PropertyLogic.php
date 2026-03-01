<?php
namespace App\Admin\Logic;

use App\Admin\Data\PropertyData;
use App\Admin\Data\UserData;

class PropertyLogic {
    private $propertyData;
    private $userData;
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->propertyData = new PropertyData($conn);
        $this->userData = new UserData($conn);
    }

    public function deleteProperty($property_id) {
        if ($this->propertyData->deleteProperty($property_id)) {
            return ['success' => true, 'message' => 'Listing deleted!'];
        }
        return ['success' => false, 'message' => 'Listing already deleted!'];
    }

    public function reviewSuspiciousProperty($property_id, $review_status, $admin_comment, $delete_property, $notify_user, $admin_id, $bot_token, $encryption_key, $iv = null) {
        $result = ['success' => [], 'errors' => []];
        $this->conn->beginTransaction();

        try {
            if (!in_array($review_status, ['approved', 'rejected', 'pending'])) {
                $result['errors'][] = 'Invalid review status!';
                return $result;
            }

            $is_suspicious = ($review_status === 'approved') ? 0 : 1;
            $this->propertyData->updateSuspicionStatus($property_id, $review_status, $is_suspicious, $admin_id);

            $property = $this->propertyData->getPropertyById($property_id);
            if ($property && ($review_status === 'rejected' && $notify_user)) {
                $user = $this->userData->getUserById($property['user_id']);
                $telegram_id = $this->decryptTelegramId($user, $encryption_key, $iv);
                if ($telegram_id && $this->checkTelegramChat($telegram_id, $bot_token)) {
                    $message = "Your property listing (ID: $property_id, Name: {$property['property_name']}) has been $review_status.";
                    if ($delete_property) {
                        $message .= " The property has been deleted.";
                    }
                    if ($admin_comment) {
                        $message .= " Admin Comment: $admin_comment";
                    }
                    $telegram_result = $this->sendTelegramMessage($telegram_id, $bot_token, $message);
                    if ($telegram_result['success']) {
                        $result['success'][] = 'Telegram notification sent!';
                    } else {
                        $result['errors'][] = 'Failed to send Telegram notification: ' . $telegram_result['error'];
                    }
                } else {
                    $result['errors'][] = 'No valid Telegram ID or chat not started!';
                }
            }

            if ($review_status === 'rejected' && $delete_property) {
                $this->propertyData->deleteProperty($property_id);
                $result['success'][] = 'Property deleted!';
            }

            $result['success'][] = 'Review updated successfully!';
            $this->conn->commit();
        } catch (\Exception $e) {
            $this->conn->rollBack();
            $result['errors'][] = 'Database error: ' . $e->getMessage();
        }

        return $result;
    }

    private function decryptTelegramId($user, $encryption_key, $iv = null) {
        if (empty($user['encrypted_telegram_id'])) {
            return $user['telegram_id'] ?? false;
        }

        $ciphertext = base64_decode($user['encrypted_telegram_id']);
        if ($ciphertext === false) {
            return false;
        }

        // Use a fixed IV if none provided, or ideally retrieve from user data
        $iv = $iv ?: str_repeat("\0", 16); // Temporary fixed IV; replace with stored IV
        $key = base64_decode($encryption_key);
        if ($key === false || strlen($key) !== 32) { // AES-256 requires 32-byte key
            return false;
        }

        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : ($user['telegram_id'] ?? false);
    }

    private function checkTelegramChat($telegram_id, $bot_token) {
        $url = "https://api.telegram.org/bot$bot_token/getChat";
        $data = ['chat_id' => $telegram_id];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $http_code === 200;
    }

    private function sendTelegramMessage($telegram_id, $bot_token, $message) {
        $url = "https://api.telegram.org/bot$bot_token/sendMessage";
        $data = ['chat_id' => $telegram_id, 'text' => $message];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response_data = json_decode($response, true);
        return [
            'success' => ($response !== false && $http_code == 200 && $response_data['ok']),
            'error' => $response_data['description'] ?? 'SSL or network error'
        ];
    }
}