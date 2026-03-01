<?php
namespace App\Chat\Data;
use PDO;
use PDOException;

require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';

class chat_messages {
    private $db;

    public function __construct() {
        try {
            $this->db = get_db_connection();
        } catch (PDOException $e) {
            error_log("Database connection failed in chat_messages: " . $e->getMessage());
            throw new \Exception("Database connection error");
        }
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
            error_log("Error fetching conversations: " . $e->getMessage());
            return [];
        }
    }

    public function getMessages($user_id, $other_user_id, $property_id) {
        try {
            $stmt = $this->db->prepare("SELECT cm.*, u.name as sender_name
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.user_id
                WHERE cm.property_id = ?
                AND ((cm.sender_id = ? AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = ?))
                ORDER BY cm.timestamp ASC");
            $stmt->execute([$property_id, $user_id, $other_user_id, $other_user_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching messages: " . $e->getMessage());
            return [];
        }
    }

    public function insertMessage($sender_id, $receiver_id, $property_id, $message, $original_language, $translated_message, $translated_language) {
        try {
            $stmt = $this->db->prepare("INSERT INTO chat_messages 
                (sender_id, receiver_id, property_id, message, original_language, translated_message, translated_language, timestamp)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$sender_id, $receiver_id, $property_id, $message, $original_language, $translated_message, $translated_language]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error inserting message: " . $e->getMessage());
            return false;
        }
    }

    public function markAsRead($user_id, $property_id) {
        try {
            $stmt = $this->db->prepare("UPDATE chat_messages SET is_read = 1 WHERE receiver_id = ? AND property_id = ? AND is_read = 0");
            $stmt->execute([$user_id, $property_id]);
            return true;
        } catch (PDOException $e) {
            error_log("Error marking messages as read: " . $e->getMessage());
            return false;
        }
    }
}