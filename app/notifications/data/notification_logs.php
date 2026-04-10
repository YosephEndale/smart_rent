<?php
namespace App\Notifications\Data;

use PDO;

require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';

class notification_logs {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function logNotification($user_id, $property_id, string $message): bool {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO notification_logs (user_id, property_id, message, sent_at)
                 VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([$user_id, $property_id, $message]);
            $success = $stmt->rowCount() > 0;
            error_log("notification_logs: Logged for user_id=$user_id, success=$success");
            return $success;
        } catch (\PDOException $e) {
            error_log("notification_logs: Failed to log for user_id=$user_id: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Allow max 5 notifications per user per hour.
     */
    public function checkRateLimit($user_id): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM notification_logs
                 WHERE user_id = ? AND sent_at > NOW() - INTERVAL 1 HOUR"
            );
            $stmt->execute([$user_id]);
            $count = $stmt->fetchColumn();
            $within = $count < 5;
            error_log("notification_logs: Rate limit for user_id=$user_id, count=$count, ok=$within");
            return $within;
        } catch (\PDOException $e) {
            error_log("notification_logs: Rate limit check failed for user_id=$user_id: " . $e->getMessage());
            return false;
        }
    }

    public function getNotifications($user_id): array {
        try {
            $stmt = $this->db->prepare(
                "SELECT nl.*, p.property_name
                 FROM notification_logs nl
                 LEFT JOIN property p ON nl.property_id = p.id
                 WHERE nl.user_id = ?
                 ORDER BY nl.sent_at DESC"
            );
            $stmt->execute([$user_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("notification_logs: Retrieved " . count($rows) . " rows for user_id=$user_id");
            return $rows;
        } catch (\PDOException $e) {
            error_log("notification_logs: Failed to retrieve for user_id=$user_id: " . $e->getMessage());
            return [];
        }
    }
}