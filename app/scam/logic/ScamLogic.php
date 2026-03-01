<?php
namespace App\Scam\Logic;

use Exception;
use App\Scam\Data\ScamData;

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

require_once ROOT_DIR . '/app/scam/data/ScamData.php';

class ScamLogic {
    private $scamData;
    private $viewPropertyUrl = '/app/admin/presentation/view_property.php';

    public function __construct($conn) {
        $this->scamData = new ScamData($conn);
    }

    /**
     * Generates a unique ID for messages.
     * @return string 20-character unique ID
     */
    private function generateUniqueId() {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        for ($i = 0; $i < 20; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $result;
    }

    /**
     * Checks for missing fields in property data.
     * @param array $data Property data
     * @param int $score Scam score
     * @param array $details Detection details
     * @param string $confidence Confidence level
     * @return array Updated score, details, confidence
     */
    private function checkMissingFields($data, $score, $details, $confidence) {
        $fields = [
            'address' => 15, // Reduced weight to make scoring less aggressive
            'description' => 8,
            'image_01' => 10,
            'image_02' => 5,
            'image_03' => 5,
            'image_04' => 5,
            'image_05' => 5
        ];
        foreach ($fields as $field => $weight) {
            if (empty($data[$field])) {
                $score += $weight;
                $details[] = "Missing $field (+$weight)";
                if ($field === 'address' || $field === 'image_01') {
                    $confidence = 'medium';
                }
            }
        }
        error_log("checkMissingFields: score=$score, details=" . implode('; ', $details));
        return [$score, $details, $confidence];
    }

    /**
     * Checks user reports for a property.
     * @param int $propId Property ID
     * @param string $createdAt Property creation date
     * @param int $score Scam score
     * @param array $details Detection details
     * @return array Updated score, details, report data
     */
    private function checkUserReports($propId, $createdAt, $score, $details) {
        $reportData = $this->scamData->getUserReports($propId);
        $reportCount = (int)($reportData['report_count'] ?? 0);
        $threshold = (strtotime($createdAt) > strtotime('-7 days')) ? 1 : 2; // Lowered threshold for new listings
        if ($reportCount > $threshold) {
            $score += 25;
            $details[] = "Exceeds report threshold ($reportCount > $threshold) (+25)";
        } elseif ($reportCount > 0) {
            $score += $reportCount * 5;
            $details[] = "$reportCount user reports (+".($reportCount * 5).")";
        }
        error_log("checkUserReports for propId=$propId: score=$score, report_count=$reportCount");
        return [$score, $details, $reportData];
    }

    /**
     * Checks for duplicate listings.
     * @param array $data Property data
     * @param int $propId Property ID
     * @param int $score Scam score
     * @param array $details Detection details
     * @return array Updated score, details
     */
    private function checkDuplicates($data, $propId, $score, $details) {
        if ($this->scamData->checkDuplicates($data, $propId)) {
            $score += 20;
            $details[] = "Duplicate listing detected (+20)";
        }
        error_log("checkDuplicates for propId=$propId: score=$score");
        return [$score, $details];
    }

    /**
     * Checks for price outliers.
     * @param array $data Property data
     * @param int $score Scam score
     * @param array $details Detection details
     * @param string $confidence Confidence level
     * @return array Updated score, details, confidence
     */
    private function checkPriceOutliers($data, $score, $details, $confidence) {
        $carpet = filter_var($data['carpet'], FILTER_VALIDATE_FLOAT);
        $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
        if ($carpet === false || $price === false || $carpet <= 0) {
            $details[] = "Invalid carpet or price data";
            $confidence = 'low';
            error_log("checkPriceOutliers for propId: Invalid data, confidence=$confidence");
            return [$score, $details, $confidence];
        }
        $avgPrice = $this->scamData->getAreaPriceStats($data['location_place_id']);
        if ($avgPrice) {
            $pricePerSqft = $price / $carpet;
            $lower = $avgPrice * 0.6; // Relaxed bounds
            $upper = $avgPrice * 1.4;
            if ($pricePerSqft < $lower || $pricePerSqft > $upper) {
                $score += 12;
                $details[] = "Price outlier: $pricePerSqft vs avg $avgPrice (+12)";
            }
        } else {
            $confidence = 'medium';
            $details[] = "No price stats available, using fallback";
        }
        error_log("checkPriceOutliers for propId: score=$score, confidence=$confidence");
        return [$score, $details, $confidence];
    }

    /**
     * Checks for inconsistent property details.
     * @param array $data Property data
     * @param int $score Scam score
     * @param array $details Detection details
     * @return array Updated score, details
     */
    private function checkInconsistencies($data, $score, $details) {
        $carpet = filter_var($data['carpet'], FILTER_VALIDATE_FLOAT);
        $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
        if ($carpet === false || $price === false) {
            $details[] = "Invalid carpet or price data";
            return [$score, $details];
        }
        if ($data['bhk'] == '1' && $carpet > 3000) { // Reduced threshold
            $score += 8;
            $details[] = "Inconsistent: 1 BHK with carpet > 3000 sqft (+8)";
        }
        if ((int)$data['bedroom'] > (int)$data['total_floors']) {
            $score += 8;
            $details[] = "Inconsistent: Bedrooms > total floors (+8)";
        }
        if ($carpet < 150 && $price > 15000) { // Adjusted thresholds
            $score += 8;
            $details[] = "Inconsistent: Small carpet (<150 sqft) with high price (>$15,000) (+8)";
        }
        error_log("checkInconsistencies: score=$score");
        return [$score, $details];
    }

    /**
     * Checks for scam keywords in description.
     * @param string $desc Property description
     * @param int $score Scam score
     * @param array $details Detection details
     * @return array Updated score, details
     */
    private function checkScamKeywords($desc, $score, $details) {
        $keywords = ['urgent', 'quick sale', 'deal', 'must go', 'immediate', 'hurry', 'fast', 'cash only', 'limited offer', 'act now'];
        $desc = strtolower($desc ?? '');
        $count = 0;
        foreach ($keywords as $keyword) {
            if (stripos($desc, $keyword) !== false) {
                $count++;
                $details[] = "Scam keyword '$keyword' detected (+8)";
            }
        }
        $score += $count * 8;
        error_log("checkScamKeywords: score=$score, keywords_found=$count");
        return [$score, $details];
    }

    /**
     * Checks user behavior patterns.
     * @param int $userId User ID
     * @param int $score Scam score
     * @param array $details Detection details
     * @return array Updated score, details
     */
    private function checkUserBehavior($userId, $score, $details) {
        $data = $this->scamData->getUserBehavior($userId);
        if ((int)$data['user_listings'] > 8) { // Lowered threshold
            $score += 12;
            $details[] = "High user activity: {$data['user_listings']} listings in 30 days (+12)";
        }
        if ((int)$data['suspicious_count'] > 1) { // Lowered threshold
            $score += 15;
            $details[] = "User has {$data['suspicious_count']} suspicious listings (+15)";
        }
        error_log("checkUserBehavior for userId=$userId: score=$score");
        return [$score, $details];
    }

    /**
     * Checks temporal patterns in reports.
     * @param array $reportData Report data
     * @param string $createdAt Property creation date
     * @param int $score Scam score
     * @param array $details Detection details
     * @return array Updated score, details
     */
    private function checkTemporalPatterns($reportData, $createdAt, $score, $details) {
        if (!empty($reportData['last_report']) && strtotime($reportData['last_report']) > strtotime('-24 hours')) {
            $score += 8;
            $details[] = "Recent report within 24 hours (+8)";
        }
        if (strtotime($createdAt) > strtotime('-48 hours')) {
            $score += 5;
            $details[] = "New listing within 48 hours (+5)";
        }
        error_log("checkTemporalPatterns: score=$score");
        return [$score, $details];
    }

    /**
     * Checks user reputation score.
     * @param int $userId User ID
     * @param int $score Scam score
     * @param array $details Detection details
     * @return array Updated score, details
     */
    private function checkReputation($userId, $score, $details) {
        $data = $this->scamData->getReputation($userId);
        $avgScore = (float)($data['avg_score'] ?? 0);
        if ($avgScore > 0 && $avgScore < 2.5 && (int)$data['review_count'] >= 2) { // Adjusted thresholds
            $score += 8;
            $details[] = "Low reputation score ($avgScore with {$data['review_count']} reviews) (+8)";
        }
        error_log("checkReputation for userId=$userId: score=$score");
        return [$score, $details];
    }

    /**
     * Checks for expired properties and sends notifications.
     * @return array Result of expiration check
     */
    public function checkExpiredProperties() {
        try {
            $properties = $this->scamData->getOldProperties();
            $results = ['success' => true, 'processed' => 0, 'expired' => 0, 'errors' => []];

            if (empty($properties)) {
                error_log("checkExpiredProperties: No properties older than 30 days found");
                $results['message'] = 'No properties eligible for expiration';
            }

            foreach ($properties as $property) {
                $propId = $property['id'];
                try {
                    $this->scamData->markAsExpired($propId);
                    $viewLink = htmlspecialchars($this->viewPropertyUrl . "?get_id=$propId", ENT_QUOTES, 'UTF-8');
                    $message = "Expired Listing\nProperty ID: $propId\nName: {$property['property_name']}\nAddress: {$property['address']}\nPrice: {$property['price']} EUR\nPosted: {$property['date']}\nView: [button:$viewLink]View Property[/button]";
                    $this->scamData->sendAdminNotification([
                        'id' => $this->generateUniqueId(),
                        'name' => 'Expiration Monitor',
                        'email' => 'expiration@rentweb.com',
                        'number' => '0000000000',
                        'message' => $message
                    ]);
                    error_log("checkExpiredProperties: Notified admin for expired propId=$propId");
                    $results['expired']++;
                } catch (Exception $e) {
                    $results['errors'][] = "propId=$propId: {$e->getMessage()}";
                    error_log("checkExpiredProperties: Error for propId=$propId: " . $e->getMessage());
                }
                $results['processed']++;
            }

            error_log("checkExpiredProperties: Completed - processed={$results['processed']}, expired={$results['expired']}, errors=" . count($results['errors']));
            return $results;
        } catch (Exception $e) {
            error_log("checkExpiredProperties: Failed - " . $e->getMessage());
            return ['success' => false, 'processed' => 0, 'expired' => 0, 'errors' => [$e->getMessage()], 'message' => 'Expiration check failed'];
        }
    }

    /**
     * Runs scam detection on a single property without triggering batch or expiration checks.
     * @param int $propId Property ID
     * @param string $trigger Trigger type
     * @return array Detection result
     */
    private function runScamDetectionSingle($propId, $trigger = 'single') {
        try {
            if (!filter_var($propId, FILTER_VALIDATE_INT)) {
                error_log("runScamDetectionSingle: Invalid property_id: $propId");
                return ['success' => false, 'message' => 'Invalid property ID'];
            }

            $score = 0;
            $details = [];
            $confidence = 'high';

            $data = $this->scamData->getPropertyData($propId);
            if (!$data) {
                error_log("runScamDetectionSingle: Property not found, propId=$propId");
                return ['success' => false, 'message' => 'Property not found'];
            }

            list($score, $details, $confidence) = $this->checkMissingFields($data, $score, $details, $confidence);
            list($score, $details, $reportData) = $this->checkUserReports($propId, $data['date'], $score, $details);
            list($score, $details) = $this->checkDuplicates($data, $propId, $score, $details);
            list($score, $details, $confidence) = $this->checkPriceOutliers($data, $score, $details, $confidence);
            list($score, $details) = $this->checkInconsistencies($data, $score, $details);
            list($score, $details) = $this->checkScamKeywords($data['description'], $score, $details);
            list($score, $details) = $this->checkUserBehavior($data['user_id'], $score, $details);
            list($score, $details) = $this->checkTemporalPatterns($reportData, $data['date'], $score, $details);
            list($score, $details) = $this->checkReputation($data['user_id'], $score, $details);

            $score = min($score, 100);
            $isSuspicious = $score > 40; // Lowered threshold

            $this->scamData->saveDetectionResult($propId, $score, $isSuspicious);

            if ($isSuspicious) {
                $status = $this->scamData->getAdminReviewStatus($propId);
                if ($status === 'pending') {
                    $viewLink = htmlspecialchars($this->viewPropertyUrl . "?get_id=$propId", ENT_QUOTES, 'UTF-8');
                    $message = "Suspicious Listing\nProperty ID: $propId\nName: {$data['property_name']}\nAddress: {$data['address']}\nPrice: {$data['price']} EUR\nCarpet: {$data['carpet']} sqft\nBHK: {$data['bhk']}\nScore: $score/100\nConfidence: $confidence\nReasons: " . implode('; ', $details) . "\nPosted: {$data['date']}\nView: [button:$viewLink]View Property[/button]";
                    $this->scamData->sendAdminNotification([
                        'id' => $this->generateUniqueId(),
                        'name' => 'Scam Detector',
                        'email' => 'scamdetector@rentweb.com',
                        'number' => '0000000000',
                        'message' => $message
                    ]);
                    error_log("runScamDetectionSingle: Admin notified for suspicious propId=$propId, score=$score");
                }
            }

            error_log("runScamDetectionSingle: Completed for propId=$propId, trigger=$trigger, score=$score, isSuspicious=$isSuspicious");
            return [
                'success' => true,
                'message' => 'Scam detection completed',
                'score' => $score,
                'confidence' => $confidence,
                'details' => $details
            ];
        } catch (Exception $e) {
            error_log("runScamDetectionSingle: Error for propId=$propId, trigger=$trigger: " . $e->getMessage());
            return ['success' => false, 'message' => 'Scam detection failed: ' . htmlspecialchars($e->getMessage())];
        }
    }

    /**
     * Runs scam detection on a single property, optionally with batch and expiration checks.
     * @param int $propId Property ID
     * @param string $trigger Trigger type
     * @return array Detection result
     */
    public function runScamDetection($propId, $trigger = 'report') {
        try {
            $result = $this->runScamDetectionSingle($propId, $trigger);
            if (!$result['success']) {
                return $result;
            }

            // Run batch and expiration checks only for 'report' trigger
            $batchResult = ['success' => true, 'message' => 'Batch detection not run'];
            $expirationResult = ['success' => true, 'message' => 'Expiration check not run'];
            if ($trigger === 'report') {
                $batchResult = $this->runBatchScamDetection();
                $expirationResult = $this->checkExpiredProperties();
            }

            error_log("runScamDetection: Completed for propId=$propId, trigger=$trigger, score={$result['score']}");
            return [
                'success' => true,
                'message' => 'Scam detection completed',
                'score' => $result['score'],
                'confidence' => $result['confidence'],
                'details' => $result['details'],
                'batch_result' => $batchResult,
                'expiration_result' => $expirationResult
            ];
        } catch (Exception $e) {
            error_log("runScamDetection: Error for propId=$propId, trigger=$trigger: " . $e->getMessage());
            return ['success' => false, 'message' => 'Scam detection failed: ' . htmlspecialchars($e->getMessage())];
        }
    }

    /**
     * Runs scam detection on all properties with limit.
     * @return array Batch detection result
     */
    public function runBatchScamDetection() {
        try {
            $properties = $this->scamData->getAllPropertyIds(100); // Limit to 100 properties per batch
            $results = ['success' => true, 'processed' => 0, 'suspicious' => 0, 'errors' => []];

            if (empty($properties)) {
                error_log("runBatchScamDetection: No properties found");
                $results['message'] = 'No properties to process';
            }

            foreach ($properties as $property) {
                $propId = $property['id'];
                $result = $this->runScamDetectionSingle($propId, 'batch');
                if ($result['success']) {
                    $results['processed']++;
                    if ($result['score'] > 40) {
                        $results['suspicious']++;
                    }
                    error_log("runBatchScamDetection: Processed propId=$propId, score={$result['score']}");
                } else {
                    $results['errors'][] = "propId=$propId: {$result['message']}";
                    error_log("runBatchScamDetection: Error for propId=$propId: {$result['message']}");
                }
            }

            error_log("runBatchScamDetection: Completed - processed={$results['processed']}, suspicious={$results['suspicious']}, errors=" . count($results['errors']));
            return $results;
        } catch (Exception $e) {
            error_log("runBatchScamDetection: Failed - " . $e->getMessage());
            return ['success' => false, 'message' => 'Batch detection failed: ' . htmlspecialchars($e->getMessage()), 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Checks a single property for scam indicators.
     * @param int $propId Property ID
     * @return array Detection result with is_suspicious and suspicion_score
     */
    public function checkSingleProperty($propId) {
        try {
            if (!filter_var($propId, FILTER_VALIDATE_INT)) {
                error_log("checkSingleProperty: Invalid property_id: $propId");
                return ['is_suspicious' => false, 'suspicion_score' => 0, 'message' => 'Invalid property ID'];
            }

            $result = $this->runScamDetectionSingle($propId, 'single_check');
            if (!$result['success']) {
                error_log("checkSingleProperty: Failed for propId=$propId: " . $result['message']);
                return ['is_suspicious' => false, 'suspicion_score' => 0, 'message' => $result['message']];
            }

            if ($result['score'] > 40) {
                $this->scamData->updatePropertyStatus($propId, 'pending_review');
            }

            error_log("checkSingleProperty: Completed for propId=$propId, score={$result['score']}");
            return [
                'is_suspicious' => $result['score'] > 40,
                'suspicion_score' => $result['score'],
                'message' => $result['message'],
                'details' => $result['details'],
                'confidence' => $result['confidence']
            ];
        } catch (Exception $e) {
            error_log("checkSingleProperty: Error for propId=$propId: " . $e->getMessage());
            return ['is_suspicious' => false, 'suspicion_score' => 0, 'message' => 'Scam detection failed: ' . htmlspecialchars($e->getMessage())];
        }
    }

    /**
     * Checks if a single property is expired and updates its status if necessary.
     * @param int $propId Property ID
     * @return array Expiration check result with is_expired
     */
    public function checkSinglePropertyExpiration($propId) {
        try {
            if (!filter_var($propId, FILTER_VALIDATE_INT)) {
                error_log("checkSinglePropertyExpiration: Invalid property_id: $propId");
                return ['is_expired' => false, 'message' => 'Invalid property ID'];
            }

            $data = $this->scamData->getPropertyData($propId);
            if (!$data) {
                error_log("checkSinglePropertyExpiration: Property not found, propId=$propId");
                return ['is_expired' => false, 'message' => 'Property not found'];
            }

            $is_expired = strtotime($data['date']) < strtotime('-30 days');
            if ($is_expired && $data['status'] !== 'expired') {
                $this->scamData->markAsExpired($propId);
                $viewLink = htmlspecialchars($this->viewPropertyUrl . "?get_id=$propId", ENT_QUOTES, 'UTF-8');
                $message = "Expired Listing\nProperty ID: $propId\nName: {$data['property_name']}\nAddress: {$data['address']}\nPrice: {$data['price']} EUR\nPosted: {$data['date']}\nView: [button:$viewLink]View Property[/button]";
                $this->scamData->sendAdminNotification([
                    'id' => $this->generateUniqueId(),
                    'name' => 'Expiration Monitor',
                    'email' => 'expiration@rentweb.com',
                    'number' => '0000000000',
                    'message' => $message
                ]);
                error_log("checkSinglePropertyExpiration: Notified admin for expired propId=$propId");
            }

            error_log("checkSinglePropertyExpiration: Completed for propId=$propId, is_expired=$is_expired");
            return [
                'is_expired' => $is_expired,
                'message' => $is_expired ? 'Property marked as expired' : 'Property is not expired'
            ];
        } catch (Exception $e) {
            error_log("checkSinglePropertyExpiration: Error for propId=$propId: " . $e->getMessage());
            return ['is_expired' => false, 'message' => 'Expiration check failed: ' . htmlspecialchars($e->getMessage())];
        }
    }
}