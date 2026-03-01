<?php
namespace App\Scam\Presentation;

use Exception;
use App\Scam\Logic\ScamLogic;

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 5));
}

require_once ROOT_DIR . '/components/connect.php';

class Scam_Detection {
    private $scamLogic;

    public function __construct($conn) {
        $this->scamLogic = new ScamLogic($conn);
    }

    /**
     * Handles single property scam detection request.
     * @param int $propId Property ID
     * @param string $trigger Trigger type
     */
    public function detectScam($propId, $trigger = 'report') {
        header('Content-Type: application/json');
        $result = $this->scamLogic->runScamDetection($propId, $trigger);
        echo json_encode($result);
    }

    /**
     * Handles batch scam detection request.
     */
    public function detectBatchScam() {
        header('Content-Type: application/json');
        $result = $this->scamLogic->runBatchScamDetection();
        echo json_encode([
            'success' => $result['success'],
            'batch_result' => $result
        ]);
    }

    /**
     * Handles single property expiration check request.
     * @param int $propId Property ID
     */
    public function checkExpiration($propId) {
        header('Content-Type: application/json');
        $result = $this->scamLogic->checkSinglePropertyExpiration($propId);
        echo json_encode($result);
    }

    /**
     * Handles batch expiration check request.
     */
    public function checkBatchExpiration() {
        header('Content-Type: application/json');
        $result = $this->scamLogic->checkExpiredProperties();
        echo json_encode([
            'success' => $result['success'],
            'expiration_result' => $result
        ]);
    }
}

// Only execute if this is the main script
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $conn = get_db_connection();
            $scamDetection = new Scam_Detection($conn);

            if (isset($_POST['propId'])) {
                $propId = filter_var($_POST['propId'], FILTER_VALIDATE_INT);
                if ($propId === false) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid property ID']);
                    exit;
                }
                $trigger = $_POST['trigger'] ?? 'report';
                if (isset($_POST['action']) && $_POST['action'] === 'check_expiration') {
                    $scamDetection->checkExpiration($propId);
                } else {
                    $scamDetection->detectScam($propId, $trigger);
                }
            } elseif (isset($_POST['batch']) && $_POST['batch'] === 'true') {
                $scamDetection->detectBatchScam();
            } elseif (isset($_POST['batch_expiration']) && $_POST['batch_expiration'] === 'true') {
                $scamDetection->checkBatchExpiration();
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Scam detection error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error: ' . htmlspecialchars($e->getMessage())]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
}