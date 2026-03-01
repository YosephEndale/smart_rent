<?php
namespace App\Reviews\Data;

use PDO;
use PDOException;

class ReviewData
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Check if user has interacted with the property (e.g., via chat).
     */
    public function hasUserInteraction($user_id, $property_id): bool
    {
        try {
            $check_interaction = $this->conn->prepare(
                "SELECT id FROM user_activity WHERE user_id = ? AND property_id = ? AND action = 'chat'"
            );
            $check_interaction->execute([$user_id, $property_id]);
            return $check_interaction->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error checking user interaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has already reviewed the landlord for this property.
     */
    public function hasExistingReview($landlord_id, $reviewer_id, $property_id): bool
    {
        try {
            $check_review = $this->conn->prepare(
                "SELECT id FROM landlord_reviews WHERE user_id = ? AND reviewer_user_id = ? AND property_id = ? AND review_text IS NOT NULL"
            );
            $check_review->execute([$landlord_id, $reviewer_id, $property_id]);
            return $check_review->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error checking existing review: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch landlord and property details.
     */
    public function getLandlordAndProperty($landlord_id, $property_id): ?array
    {
        try {
            $select_landlord = $this->conn->prepare("SELECT name FROM users WHERE user_id = ?");
            $select_landlord->execute([$landlord_id]);
            $landlord = $select_landlord->fetch(PDO::FETCH_ASSOC);

            $select_property = $this->conn->prepare("SELECT property_name FROM property WHERE id = ?");
            $select_property->execute([$property_id]);
            $property = $select_property->fetch(PDO::FETCH_ASSOC);

            if ($landlord && $property) {
                return ['landlord' => $landlord, 'property' => $property];
            }
            return null;
        } catch (PDOException $e) {
            error_log("Error fetching landlord/property: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Insert a new review with transaction handling.
     */
    public function insertReview($landlord_id, $reviewer_id, $property_id, $rating, $review_text): bool
    {
        try {
            $this->conn->beginTransaction();
            $insert_review = $this->conn->prepare(
                "INSERT INTO landlord_reviews (user_id, reviewer_user_id, property_id, rating, review_text, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $success = $insert_review->execute([$landlord_id, $reviewer_id, $property_id, $rating, $review_text]);
            if ($success) {
                $this->conn->commit();
                error_log("Review inserted: landlord_id=$landlord_id, reviewer_id=$reviewer_id, property_id=$property_id, rating=$rating, review_text='$review_text'");
                return true;
            }
            $this->conn->rollBack();
            return false;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error inserting review: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate and retrieve review metrics (count and average rating).
     */
    public function calculateMetrics($landlord_id, $property_id): array
    {
        try {
            $calc_metrics = $this->conn->prepare(
                "SELECT COUNT(*) as review_count, COALESCE(AVG(rating), 0) as reputation_score 
                FROM landlord_reviews 
                WHERE user_id = ? AND property_id = ? AND review_text IS NOT NULL"
            );
            $calc_metrics->execute([$landlord_id, $property_id]);
            return $calc_metrics->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error calculating metrics: " . $e->getMessage());
            return ['review_count' => 0, 'reputation_score' => 0];
        }
    }

    /**
     * Update or insert review metrics with transaction handling.
     */
    public function updateMetrics($landlord_id, $property_id, $review_count, $reputation_score): bool
    {
        try {
            $this->conn->beginTransaction();
            $check_existing = $this->conn->prepare(
                "SELECT id FROM landlord_reviews WHERE user_id = ? AND property_id = ? AND review_text IS NULL"
            );
            $check_existing->execute([$landlord_id, $property_id]);

            if ($check_existing->rowCount() > 0) {
                $update_metrics = $this->conn->prepare(
                    "UPDATE landlord_reviews SET review_count = ?, reputation_score = ? 
                    WHERE user_id = ? AND property_id = ? AND review_text IS NULL"
                );
                $success = $update_metrics->execute([$review_count, $reputation_score, $landlord_id, $property_id]);
            } else {
                $insert_metrics = $this->conn->prepare(
                    "INSERT INTO landlord_reviews (user_id, property_id, review_count, reputation_score) 
                    VALUES (?, ?, ?, ?)"
                );
                $success = $insert_metrics->execute([$landlord_id, $property_id, $review_count, $reputation_score]);
            }

            if ($success) {
                $this->conn->commit();
                return true;
            }
            $this->conn->rollBack();
            return false;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error updating metrics: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch reviews for display.
     */
    public function getReviews($landlord_id, $property_id): array
    {
        try {
            $select_reviews = $this->conn->prepare(
                "SELECT rating, review_text, created_at 
                FROM landlord_reviews 
                WHERE user_id = ? AND property_id = ? AND review_text IS NOT NULL 
                ORDER BY created_at DESC"
            );
            $select_reviews->execute([$landlord_id, $property_id]);
            $reviews = $select_reviews->fetchAll(PDO::FETCH_ASSOC);
            error_log("getReviews: landlord_id=$landlord_id, property_id=$property_id, fetched=" . count($reviews) . ", reviews=" . json_encode($reviews));
            return $reviews;
        } catch (PDOException $e) {
            error_log("Error fetching reviews: " . $e->getMessage());
            return [];
        }
    }
}