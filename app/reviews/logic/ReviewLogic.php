<?php
namespace App\Reviews\Logic;

use App\Reviews\Data\ReviewData;

class ReviewLogic
{
    /**
     * Handle review submission.
     */
    public function handleReviewSubmission(ReviewData $reviewData, $user_id, $landlord_id, $property_id, $post_data): array
    {
        $result = ['success' => false, 'message' => '', 'type' => 'error'];

        // Validate user is logged in and not the landlord
        if (empty($user_id) || session_status() === PHP_SESSION_NONE) {
            error_log("User not logged in for review submission");
            $result['message'] = 'Please log in to submit a review.';
            return $result;
        }

        if ($user_id == $landlord_id) {
            error_log("User attempted to review themselves: user_id=$user_id, landlord_id=$landlord_id");
            $result['message'] = 'You cannot review yourself!';
            return $result;
        }

        // Check prior interaction
        if (!$reviewData->hasUserInteraction($user_id, $property_id)) {
            error_log("No prior interaction: user_id=$user_id, property_id=$property_id");
            $result['message'] = 'You must interact with the landlord (e.g., send a message) before submitting a review.';
            return $result;
        }

        // Validate landlord and property
        $details = $reviewData->getLandlordAndProperty($landlord_id, $property_id);
        if (!$details) {
            error_log("Invalid landlord or property: landlord_id=$landlord_id, property_id=$property_id");
            $result['message'] = 'Invalid landlord or property.';
            return $result;
        }

        // Validate form data
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($post_data['submit_review']) || $post_data['submit_review'] !== 'submit') {
            error_log("Invalid request: method={$_SERVER['REQUEST_METHOD']}, submit_review=" . ($post_data['submit_review'] ?? 'not set'));
            $result['message'] = 'Invalid request.';
            return $result;
        }

        $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
        $review_text = trim(filter_input(INPUT_POST, 'review_text', FILTER_DEFAULT));

        if ($rating === false) {
            error_log("Invalid rating: " . ($post_data['rating'] ?? 'not set'));
            $result['message'] = 'Please select a valid rating (1-5 stars).';
            return $result;
        }

        if (strlen($review_text) > 1000) {
            error_log("Review text too long: " . strlen($review_text));
            $result['message'] = 'Review text cannot exceed 1000 characters.';
            return $result;
        }

        // Check for existing review
        if ($reviewData->hasExistingReview($landlord_id, $user_id, $property_id)) {
            error_log("Duplicate review: user_id=$user_id, landlord_id=$landlord_id, property_id=$property_id");
            $result['message'] = 'You have already submitted a review for this landlord and property.';
            return $result;
        }

        // Insert review and update metrics
        try {
            if ($reviewData->insertReview($landlord_id, $user_id, $property_id, $rating, $review_text)) {
                $metrics = $reviewData->calculateMetrics($landlord_id, $property_id);
                if ($reviewData->updateMetrics($landlord_id, $property_id, $metrics['review_count'], $metrics['reputation_score'])) {
                    error_log("Review submitted successfully: user_id=$user_id, landlord_id=$landlord_id, property_id=$property_id");
                    $result['success'] = true;
                    $result['message'] = 'Review submitted successfully!';
                    $result['type'] = 'success';
                } else {
                    throw new \Exception('Failed to update review metrics.');
                }
            } else {
                throw new \Exception('Failed to insert review.');
            }
        } catch (\Exception $e) {
            error_log("Review submission error: " . $e->getMessage());
            $result['message'] = 'Failed to submit review: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Retrieve review data for display.
     */
    public function getReviewData(ReviewData $reviewData, $landlord_id, $property_id): array
    {
        $metrics = $reviewData->calculateMetrics($landlord_id, $property_id);
        $reviews = $reviewData->getReviews($landlord_id, $property_id);

        return [
            'metrics' => $metrics,
            'reviews' => $reviews
        ];
    }
}