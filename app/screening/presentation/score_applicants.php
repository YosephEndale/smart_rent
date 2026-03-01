<?php
session_start();

// Include Composer autoloader and env.php relatively
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';

// Define ROOT_DIR if not already defined (fallback)
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); // Resolves to C:\Users\yosep\OneDrive\Desktop\smart_rent from app/screening/presentation/
}

// Debug: Verify file paths
if (!file_exists(ROOT_DIR . '/vendor/autoload.php')) {
    error_log("Autoloader not found at: " . ROOT_DIR . "/vendor/autoload.php");
    die(json_encode(['error' => "Configuration error: Composer autoloader not found. Run 'composer install' or 'composer update'."]));
}
if (!file_exists(ROOT_DIR . '/app/screening/logic/ScreeningLogic.php')) {
    error_log("ScreeningLogic not found at: " . ROOT_DIR . "/app/screening/logic/ScreeningLogic.php");
    die(json_encode(['error' => "Configuration error: ScreeningLogic.php not found."]));
}
if (!file_exists(ROOT_DIR . '/app/notifications/logic/SendNotification.php')) {
    error_log("SendNotification not found at: " . ROOT_DIR . "/app/notifications/logic/SendNotification.php");
    die(json_encode(['error' => "Configuration error: SendNotification.php not found."]));
}

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/screening/logic/ScreeningLogic.php';
require_once ROOT_DIR . '/app/notifications/logic/SendNotification.php';

use App\Screening\Logic\ScreeningLogic;
use App\Notifications\Logic\SendNotification;

$conn = get_db_connection();
$config = require ROOT_DIR . '/config/env.php';
$sendNotification = new SendNotification($conn);
$screeningLogic = new ScreeningLogic($conn, $config, $sendNotification);

$user_id = $_SESSION['user_id'] ?? '';
if (empty($user_id) || !isset($_GET['property_id'])) {
    echo json_encode(['error' => 'Missing parameters or unauthorized access']);
    exit();
}

$property_id = filter_var($_GET['property_id'], FILTER_VALIDATE_INT);
if ($property_id === false) {
    echo json_encode(['error' => 'Invalid property ID']);
    exit();
}

$result = $screeningLogic->handleScoreApplicants($property_id, $user_id);
header('Content-Type: application/json');
echo json_encode($result);