<?php
header('Content-Type: application/json; charset=utf-8');
// Suppress warnings during development, but log them
error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../components/connect.php';
require_once __DIR__ . '/../data/ReviewData.php';
require_once __DIR__ . '/../logic/ReviewLogic.php';

use App\Reviews\Data\ReviewData;
use App\Reviews\Logic\ReviewLogic;

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    error_log("Session start error in review_landlord.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Session error.', 'type' => 'error']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$landlord_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$property_id = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT);

if (!$user_id || !$landlord_id || !$property_id) {
    error_log("Missing parameters: user_id=$user_id, landlord_id=$landlord_id, property_id=$property_id");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access or missing parameters.', 'type' => 'error']);
    exit;
}

$conn = get_db_connection();
$reviewData = new ReviewData($conn);
$reviewLogic = new ReviewLogic();

$result = $reviewLogic->handleReviewSubmission($reviewData, $user_id, $landlord_id, $property_id, $_POST);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;