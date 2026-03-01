<?php
namespace App\Subscription\Data;
use PDO;
use PDOException;

class SubscriptionData {
    private $conn;

    public function __construct() {
        require_once ROOT_DIR . '/components/connect.php';
        $this->conn = get_db_connection();
    }

    public function getUserSubscriptionStatus($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ? $user['is_premium'] : false;
        } catch (PDOException $e) {
            error_log("Error fetching subscription status in SubscriptionData.php: " . $e->getMessage());
            return false;
        }
    }

    public function hasPostedProperties($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM property WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Error checking posted properties in SubscriptionData.php: " . $e->getMessage());
            return false;
        }
    }

    public function upgradeToPremium($user_id) {
        try {
            $stmt = $this->conn->prepare("UPDATE users SET is_premium = 1 WHERE user_id = ?");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Error upgrading to premium in SubscriptionData.php: " . $e->getMessage());
            return false;
        }
    }

    public function downgradeToFree($user_id) {
        try {
            $stmt = $this->conn->prepare("UPDATE users SET is_premium = 0 WHERE user_id = ?");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Error downgrading to free in SubscriptionData.php: " . $e->getMessage());
            return false;
        }
    }
}