<?php
namespace App\Components;

use PDO;
use PDOException;

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/currency.php';

class Recommendations {
    public static function getRecommendedProperties($user_id, $conn, $rates) {
        try {
            $heading = '✨ Latest Dream Homes Just for You! 🏡💫';
            $properties = [];

            if (!empty($user_id)) {
                error_log("Processing recommendations for user_id: $user_id");

                // Fetch latest user preferences
                $pref = $conn->prepare("
                    SELECT * FROM user_preferences 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $pref->execute([$user_id]);
                $preferences = $pref->fetch(PDO::FETCH_ASSOC);
                error_log("User preferences: " . print_r($preferences, true));

                $where_clauses = ["p.user_id != :user_id"];
                $params = [':user_id' => $user_id];

                // Check if property status column exists
                $has_available = $conn->query("SELECT 1 FROM property WHERE status = 'available' LIMIT 1")->rowCount() > 0;
                if ($has_available) {
                    $where_clauses[] = "p.status = 'available'";
                }

                if ($preferences) {
                    if (!empty($preferences['bhk'])) {
                        $where_clauses[] = 'IFNULL(p.bhk, 0) = :bhk';
                        $params[':bhk'] = $preferences['bhk'];
                    }
                    if (!empty($preferences['property_type'])) {
                        $where_clauses[] = 'p.type = :property_type';
                        $params[':property_type'] = $preferences['property_type'];
                    }
                    if (!empty($preferences['offer_type'])) {
                        $where_clauses[] = 'p.offer = :offer';
                        $params[':offer'] = $preferences['offer_type'];
                    }
                    if (!empty($preferences['furnished'])) {
                        $where_clauses[] = 'p.furnished = :furnished';
                        $params[':furnished'] = $preferences['furnished'];
                    }
                    if (!empty($preferences['min_budget'])) {
                        $where_clauses[] = 'p.price >= :min_budget';
                        $params[':min_budget'] = $preferences['min_budget'];
                    }
                    if (!empty($preferences['max_budget'])) {
                        $where_clauses[] = 'p.price <= :max_budget';
                        $params[':max_budget'] = $preferences['max_budget'];
                    }
                    if (!empty($preferences['location_lat']) && !empty($preferences['location_lng']) && !empty($preferences['radius'])) {
                        $where_clauses[] = "p.location_lat IS NOT NULL AND p.location_lng IS NOT NULL";
                        $where_clauses[] = "(6371 * acos(
                            cos(radians(:location_lat)) * cos(radians(p.location_lat)) * 
                            cos(radians(p.location_lng) - radians(:location_lng)) + 
                            sin(radians(:location_lat)) * sin(radians(p.location_lat))
                        )) <= :radius";
                        $params[':location_lat'] = $preferences['location_lat'];
                        $params[':location_lng'] = $preferences['location_lng'];
                        $params[':radius'] = $preferences['radius'];
                    }
                }

                $where_sql = implode(" AND ", $where_clauses);

                // Check if user_activity table exists
                $table_exists = $conn->query("SHOW TABLES LIKE 'user_activity'")->rowCount() > 0;
                $join_clause = $table_exists ? "LEFT JOIN user_activity a ON p.id = a.property_id AND a.user_id = :user_id" : "";
                $score_clause = $table_exists ? "COALESCE(SUM(
                    CASE 
                        WHEN a.action = 'inquire' THEN 10
                        WHEN a.action = 'view' THEN 5
                        WHEN a.action = 'save' THEN 7
                        ELSE 0
                    END
                ), 0)" : "0";

                // Enhanced Recommendation SQL
                $query = "
                    SELECT p.*, 
                        $score_clause AS score
                    FROM property p
                    $join_clause
                    WHERE $where_sql
                    GROUP BY p.id
                    ORDER BY score DESC, p.date DESC
                    LIMIT 6
                ";

                error_log("Recommendation query: $query");
                error_log("Query parameters: " . print_r($params, true));

                $stmt = $conn->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmt->execute();
                $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $row_count = $stmt->rowCount();
                error_log("Recommendation query returned $row_count rows");

                if ($row_count > 0) {
                    $heading = '🎯 Recommended for You';
                } else {
                    // Fallback query for logged-in users
                    $stmt = $conn->prepare("SELECT * FROM property WHERE user_id != ? ORDER BY date DESC LIMIT 6");
                    $stmt->execute([$user_id]);
                    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log("Fallback query for logged-in user returned " . $stmt->rowCount() . " rows");
                }
            } else {
                // Default query for non-logged-in users
                $stmt = $conn->prepare("SELECT * FROM property ORDER BY date DESC LIMIT 6");
                $stmt->execute();
                $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Default query for non-logged-in user returned " . $stmt->rowCount() . " rows");
            }

            // Process properties for display
            $result = [];
            foreach ($properties as $property) {
                // Fetch user name
                $select_user = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
                $select_user->execute([$property['user_id']]);
                $user_name = $select_user->fetchColumn() ?: 'Unknown';
                $user_initial = $user_name ? substr($user_name, 0, 1) : '?';

                // Count images
                $total_images = 1;
                for ($i = 2; $i <= 5; $i++) {
                    if (!empty($property["image_0$i"])) {
                        $total_images++;
                    }
                }

                // Check if property is saved
                $is_saved = false;
                if ($user_id) {
                    $select_saved = $conn->prepare("SELECT * FROM saved WHERE property_id = ? AND user_id = ?");
                    $select_saved->execute([$property['id'], $user_id]);
                    $is_saved = $select_saved->rowCount() > 0;
                }

                // Check application status
                $has_applied = false;
                $has_passed = false;
                if ($user_id) {
                    $check_application = $conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
                    $check_application->execute([$property['id'], $user_id]);
                    $has_applied = $check_application->fetchColumn() > 0;

                    if ($has_applied) {
                        $min_score = 50; // Adjust as needed
                        $check_score = $conn->prepare("SELECT score FROM tenant_scores WHERE property_id = ? AND user_id = ?");
                        $check_score->execute([$property['id'], $user_id]);
                        $score = $check_score->fetchColumn();
                        $has_passed = $score !== false && $score >= $min_score;
                    }
                }

                $result[] = [
                    'property' => $property,
                    'user_name' => $user_name,
                    'user_initial' => $user_initial,
                    'total_images' => $total_images,
                    'is_saved' => $is_saved,
                    'has_applied' => $has_applied,
                    'has_passed' => $has_passed
                ];
            }

            return [
                'success' => true,
                'heading' => $heading,
                'properties' => $result
            ];
        } catch (PDOException $e) {
            error_log("Recommendations::getRecommendedProperties error: " . $e->getMessage());
            return [
                'error' => 'Failed to load recommendations: ' . $e->getMessage(),
                'heading' => '✨ Latest Dream Homes Just for You! 🏡💫',
                'properties' => []
            ];
        }
    }
}