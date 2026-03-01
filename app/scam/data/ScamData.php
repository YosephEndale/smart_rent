<?php
namespace App\Scam\Data;

use Exception;
use PDO;
use PDOException;

class ScamData {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Executes a PDO query with improved error handling.
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return PDOStatement Executed statement
     * @throws Exception on query failure
     */
    public function runQuery($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: $sql, params=" . json_encode($params) . ", error=" . $e->getMessage());
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Fetches property data by ID.
     * @param int $propId Property ID
     * @return array|null Property data or null if not found
     */
    public function getPropertyData($propId) {
        $sql = "SELECT user_id, property_name, address, location_place_id, price, carpet, bhk, description, image_01, image_02, image_03, image_04, image_05, bedroom, total_floors, date, location_lat, location_lng, type, offer, status, furnished, deposite, bathroom, balcony, age, room_floor, loan FROM property WHERE id = :propId";
        try {
            $stmt = $this->runQuery($sql, ['propId' => $propId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                error_log("getPropertyData: No data found for propId=$propId");
            } else {
                error_log("getPropertyData: Fetched data for propId=$propId");
            }
            return $data;
        } catch (Exception $e) {
            error_log("getPropertyData failed for propId=$propId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetches user report data for a property.
     * @param int $propId Property ID
     * @return array Report data
     */
    public function getUserReports($propId) {
        $sql = "SELECT COUNT(*) AS report_count, MAX(created_at) AS last_report FROM property_reports WHERE property_id = :propId";
        try {
            $stmt = $this->runQuery($sql, ['propId' => $propId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $result = $data ?: ['report_count' => 0, 'last_report' => null];
            error_log("getUserReports for propId=$propId: report_count={$result['report_count']}");
            return $result;
        } catch (Exception $e) {
            error_log("getUserReports failed for propId=$propId: " . $e->getMessage());
            return ['report_count' => 0, 'last_report' => null];
        }
    }

    /**
     * Checks for duplicate listings with relaxed margins.
     * @param array $data Property data
     * @param int $propId Property ID
     * @return bool True if duplicates exist
     */
    public function checkDuplicates($data, $propId) {
        $carpet = filter_var($data['carpet'], FILTER_VALIDATE_FLOAT);
        $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
        if ($carpet === false || $price === false) {
            error_log("checkDuplicates: Invalid carpet or price for propId=$propId");
            return false;
        }
        $sql = "SELECT id FROM property WHERE id != :propId AND user_id != :userId AND location_place_id = :placeId AND bhk = :bhk AND ABS(CAST(carpet AS DECIMAL(10,2)) - :carpet) <= :carpetMargin AND ABS(CAST(price AS DECIMAL(10,2)) - :price) <= :priceMargin AND ABS(location_lat - :lat) <= 0.001 AND ABS(location_lng - :lng) <= 0.001";
        $params = [
            'propId' => $propId,
            'userId' => $data['user_id'],
            'placeId' => $data['location_place_id'],
            'bhk' => $data['bhk'],
            'carpet' => $carpet,
            'carpetMargin' => $carpet * 0.1, // Relaxed to 10%
            'price' => $price,
            'priceMargin' => $price * 0.1, // Relaxed to 10%
            'lat' => $data['location_lat'],
            'lng' => $data['location_lng']
        ];
        try {
            $stmt = $this->runQuery($sql, $params);
            $hasDuplicates = $stmt->rowCount() > 0;
            error_log("checkDuplicates for propId=$propId: " . ($hasDuplicates ? "Duplicates found" : "No duplicates"));
            return $hasDuplicates;
        } catch (Exception $e) {
            error_log("checkDuplicates failed for propId=$propId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches area price statistics with fallback.
     * @param string $placeId Location place ID
     * @return float|null Average price per sqft
     */
    public function getAreaPriceStats($placeId) {
        $sql = "SELECT avg_price_per_sqft FROM area_price_stats WHERE location_place_id = :placeId";
        try {
            $stmt = $this->runQuery($sql, ['placeId' => $placeId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            $avgPrice = (float)($stats['avg_price_per_sqft'] ?? 0);
            if ($avgPrice == 0) {
                // Fallback: Use average price per sqft from all properties in the same location
                $sql = "SELECT AVG(CAST(price AS DECIMAL(10,2)) / CAST(carpet AS DECIMAL(10,2))) AS avg_price_per_sqft FROM property WHERE location_place_id = :placeId AND carpet > 0";
                $stmt = $this->runQuery($sql, ['placeId' => $placeId]);
                $fallback = $stmt->fetch(PDO::FETCH_ASSOC);
                $avgPrice = (float)($fallback['avg_price_per_sqft'] ?? 0);
                error_log("getAreaPriceStats for placeId=$placeId: Used fallback, avgPrice=$avgPrice");
            } else {
                error_log("getAreaPriceStats for placeId=$placeId: avgPrice=$avgPrice");
            }
            return $avgPrice;
        } catch (Exception $e) {
            error_log("getAreaPriceStats failed for placeId=$placeId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetches user behavior data.
     * @param int $userId User ID
     * @return array User listing and suspicion data
     */
    public function getUserBehavior($userId) {
        $sql = "SELECT COUNT(*) AS user_listings, SUM(CASE WHEN ps.is_suspicious = 1 THEN 1 ELSE 0 END) AS suspicious_count FROM property p LEFT JOIN property_suspicion ps ON p.id = ps.property_id WHERE p.user_id = :userId AND p.date > NOW() - INTERVAL 30 DAY";
        try {
            $stmt = $this->runQuery($sql, ['userId' => $userId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $result = $data ?: ['user_listings' => 0, 'suspicious_count' => 0];
            error_log("getUserBehavior for userId=$userId: listings={$result['user_listings']}, suspicious={$result['suspicious_count']}");
            return $result;
        } catch (Exception $e) {
            error_log("getUserBehavior failed for userId=$userId: " . $e->getMessage());
            return ['user_listings' => 0, 'suspicious_count' => 0];
        }
    }

    /**
     * Fetches user reputation data.
     * @param int $userId User ID
     * @return array Reputation data
     */
    public function getReputation($userId) {
        $sql = "SELECT AVG(reputation_score) AS avg_score, COUNT(*) AS review_count FROM landlord_reviews WHERE user_id = :userId AND review_text IS NOT NULL";
        try {
            $stmt = $this->runQuery($sql, ['userId' => $userId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $result = $data ?: ['avg_score' => 0, 'review_count' => 0];
            error_log("getReputation for userId=$userId: avg_score={$result['avg_score']}, review_count={$result['review_count']}");
            return $result;
        } catch (Exception $e) {
            error_log("getReputation failed for userId=$userId: " . $e->getMessage());
            return ['avg_score' => 0, 'review_count' => 0];
        }
    }

    /**
     * Saves scam detection results.
     * @param int $propId Property ID
     * @param int $score Suspicion score
     * @param int $isSuspicious Suspicious flag
     */
    public function saveDetectionResult($propId, $score, $isSuspicious) {
        $sql = "INSERT INTO property_suspicion (property_id, suspicion_score, is_suspicious, flagged_at, admin_review_status) VALUES (:propId, :score, :isSuspicious, NOW(), 'pending') ON DUPLICATE KEY UPDATE suspicion_score = :score, is_suspicious = :isSuspicious, flagged_at = NOW(), admin_review_status = 'pending'";
        try {
            $this->runQuery($sql, [
                'propId' => $propId,
                'score' => $score,
                'isSuspicious' => $isSuspicious
            ]);
            error_log("saveDetectionResult: Saved for propId=$propId, score=$score, isSuspicious=$isSuspicious");
        } catch (Exception $e) {
            error_log("saveDetectionResult failed for propId=$propId: " . $e->getMessage());
        }
    }

    /**
     * Gets admin review status for a property.
     * @param int $propId Property ID
     * @return string Admin review status
     */
    public function getAdminReviewStatus($propId) {
        $sql = "SELECT admin_review_status FROM property_suspicion WHERE property_id = :propId";
        try {
            $stmt = $this->runQuery($sql, ['propId' => $propId]);
            $status = $stmt->fetchColumn() ?: 'pending';
            error_log("getAdminReviewStatus for propId=$propId: status=$status");
            return $status;
        } catch (Exception $e) {
            error_log("getAdminReviewStatus failed for propId=$propId: " . $e->getMessage());
            return 'pending';
        }
    }

    /**
     * Sends notification to admin.
     * @param array $notificationData Notification data
     */
    public function sendAdminNotification($notificationData) {
        $sql = "INSERT INTO messages (id, name, email, number, message) VALUES (:id, :name, :email, :number, :message)";
        try {
            $this->runQuery($sql, $notificationData);
            error_log("sendAdminNotification: Sent notification for id={$notificationData['id']}, name={$notificationData['name']}");
        } catch (Exception $e) {
            error_log("sendAdminNotification failed: " . $e->getMessage() . ", data=" . json_encode($notificationData));
        }
    }

    /**
     * Fetches all property IDs for batch processing with limit.
     * @param int $limit Maximum number of properties to fetch
     * @return array List of property IDs
     */
    public function getAllPropertyIds($limit = 100) {
        $sql = "SELECT id FROM property LIMIT :limit";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getAllPropertyIds: Found " . count($ids) . " properties");
            return $ids;
        } catch (Exception $e) {
            error_log("getAllPropertyIds failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetches properties older than 30 days that are not expired.
     * @return array List of old properties
     */
    public function getOldProperties() {
        $sql = "SELECT id, user_id, property_name, address, price, date, status FROM property WHERE date < NOW() - INTERVAL 30 DAY AND status NOT IN ('expired', 'pending_review')";
        try {
            $stmt = $this->runQuery($sql);
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getOldProperties: Found " . count($properties) . " properties older than 30 days");
            return $properties;
        } catch (Exception $e) {
            error_log("getOldProperties failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Marks a property as expired.
     * @param int $propId Property ID
     */
    public function markAsExpired($propId) {
        $sql = "UPDATE property SET status = 'expired' WHERE id = :propId AND status != 'pending_review'";
        try {
            $this->runQuery($sql, ['propId' => $propId]);
            error_log("markAsExpired: Marked propId=$propId as expired");
        } catch (Exception $e) {
            error_log("markAsExpired failed for propId=$propId: " . $e->getMessage());
        }
    }

    /**
     * Updates the status of a property.
     * @param int $propId Property ID
     * @param string $status New status
     * @return bool Success
     * @throws Exception on database error
     */
    public function updatePropertyStatus($propId, $status) {
        try {
            $sql = "UPDATE property SET status = :status WHERE id = :propId";
            $this->runQuery($sql, ['status' => $status, 'propId' => $propId]);
            error_log("updatePropertyStatus: Updated propId=$propId to status=$status");
            return true;
        } catch (Exception $e) {
            error_log("updatePropertyStatus failed for propId=$propId, status=$status: " . $e->getMessage());
            throw new Exception("Failed to update property status: " . $e->getMessage());
        }
    }
}