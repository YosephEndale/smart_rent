<?php
namespace App\Property\Presentation;

use PDO;
use Exception;
use App\Reviews\Data\ReviewData;
use App\Reviews\Logic\ReviewLogic;
use App\Scam\Logic\ScamLogic;

// Start output buffering to prevent stray output
ob_start();

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/reviews/data/ReviewData.php';
require_once ROOT_DIR . '/app/reviews/logic/ReviewLogic.php';
require_once ROOT_DIR . '/app/scam/logic/ScamLogic.php';

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    error_log("Session start error in view_property.php: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'] ?? '';
$get_id = filter_input(INPUT_GET, 'get_id', FILTER_VALIDATE_INT);
$warning_msg = $_SESSION['warning_msg'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? [];
$_SESSION['warning_msg'] = [];
$_SESSION['success_msg'] = [];

// Run scam detection and expiration checks automatically with locking mechanism
$lastRunFile = ROOT_DIR . '/cache/scam_detection_last_run.txt';
$lockFile = ROOT_DIR . '/cache/scam_detection.lock';
$runInterval = 3600; // Run every hour

$shouldRun = false;
if (!file_exists($lastRunFile) || (time() - filemtime($lastRunFile)) > $runInterval) {
    // Use file locking to prevent concurrent executions
    $lockHandle = fopen($lockFile, 'w');
    if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
        // Double-check last run time to avoid race conditions
        if (!file_exists($lastRunFile) || (time() - filemtime($lastRunFile)) > $runInterval) {
            $shouldRun = true;
            if (!is_dir(ROOT_DIR . '/cache')) {
                mkdir(ROOT_DIR . '/cache', 0755, true);
            }
        }
        flock($lockHandle, LOCK_UN);
    }
    fclose($lockHandle);
}

if ($shouldRun) {
    try {
        $conn = get_db_connection();
        $scamLogic = new ScamLogic($conn);
        $batchResult = $scamLogic->runBatchScamDetection();
        $expirationResult = $scamLogic->checkExpiredProperties();
        file_put_contents($lastRunFile, time());
        error_log("view_property.php: Automatic batch scam detection and expiration check completed - batch_processed={$batchResult['processed']}, suspicious={$batchResult['suspicious']}, expired={$expirationResult['expired']}");
    } catch (Exception $e) {
        error_log("view_property.php: Automatic batch scam detection failed: " . $e->getMessage());
    }
}

$conn = get_db_connection();
$reviewData = new ReviewData($conn);
$reviewLogic = new ReviewLogic();

// Validate property ID
if (!$get_id) {
    $result = ['success' => false];
    $warning_msg[] = '😔 Invalid property ID';
} else {
    // Fetch property and user details
    $query = "
        SELECT p.*, 
               CASE WHEN ps.is_suspicious = 1 THEN 1 ELSE 0 END AS is_suspicious,
               u.user_id AS owner_id, u.name, u.number
        FROM property p
        LEFT JOIN property_suspicion ps ON p.id = ps.property_id
        JOIN users u ON p.user_id = u.user_id
        WHERE p.id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$get_id]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($property) {
        $select_saved = $conn->prepare("SELECT * FROM saved WHERE property_id = ? AND user_id = ?");
        $select_saved->execute([$get_id, $user_id]);
        $is_saved = $select_saved->rowCount() > 0;
        $result = [
            'success' => true,
            'property' => $property,
            'user' => ['name' => $property['name'], 'number' => $property['number']],
            'owner_id' => $property['owner_id'],
            'is_saved' => $is_saved
        ];
    } else {
        $result = ['success' => false];
        $warning_msg[] = '😥 Property not found';
    }
}

// Handle chat initiation
if (isset($_GET['init_chat']) && $user_id) {
    $property_id = filter_var($_GET['property_id'] ?? '', FILTER_VALIDATE_INT);
    if ($property_id) {
        $owner_id = $conn->prepare("SELECT user_id FROM property WHERE id = ?");
        $owner_id->execute([$property_id]);
        $owner_id = $owner_id->fetchColumn();

        if ($owner_id) {
            $check_subscription = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
            $check_subscription->execute([$owner_id]);
            $owner_premium = $check_subscription->fetchColumn();

            $check_preferences = $conn->prepare("SELECT COUNT(*) FROM screening_preferences WHERE user_id = ? AND property_id = ?");
            $check_preferences->execute([$owner_id, $property_id]);
            $has_preferences = $check_preferences->fetchColumn() > 0;

            $check_applied = $conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
            $check_applied->execute([$property_id, $user_id]);
            $has_applied = $check_applied->fetchColumn() > 0;

            $has_passed = false; // Replace with actual logic if available

            $stmt = $conn->prepare("INSERT INTO user_activity (user_id, property_id, action, created_at) VALUES (?, ?, 'chat', NOW())");
            $stmt->execute([$user_id, $property_id]);

            if ($owner_premium && $has_preferences && !$has_applied) {
                error_log("view_property.php: Redirecting to apply.php for property_id=$property_id");
                $_SESSION['success_msg'][] = '📝 Please complete the application to chat with the owner!';
                header("Location: /app/screening/presentation/apply.php?property_id=$property_id");
            } else if ($has_applied && !$has_passed) {
                error_log("view_property.php: Application pending for property_id=$property_id");
                $_SESSION['warning_msg'][] = '⏳ Your application is pending owner review.';
                header("Location: /app/property/presentation/view_property.php?get_id=$property_id");
            } else {
                error_log("view_property.php: Redirecting to chat.php for property_id=$property_id, other_user_id=$owner_id");
                $_SESSION['success_msg'][] = '💬 Chat opened successfully!';
                header("Location: /app/chat/presentation/chat.php?property_id=$property_id&other_user_id=$owner_id");
            }
            ob_end_flush();
            exit;
        } else {
            error_log("view_property.php: Property owner not found for property_id=$property_id");
            $_SESSION['warning_msg'][] = '😔 Property owner not found';
        }
    }
}

// Fetch review data
$owner_id = $result['owner_id'] ?? 0;
error_log("Fetching reviews with owner_id=$owner_id, property_id=$get_id");
$review_data = $reviewLogic->getReviewData($reviewData, $owner_id, $get_id);
error_log("Fetched reviews: " . json_encode($review_data['reviews']));

// Handle form submissions (save only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && !empty($_POST['property_id'])) {
    $property_id = filter_var($_POST['property_id'], FILTER_VALIDATE_INT);
    if ($property_id && $user_id) {
        $check_saved = $conn->prepare("SELECT * FROM saved WHERE property_id = ? AND user_id = ?");
        $check_saved->execute([$property_id, $user_id]);
        if ($check_saved->rowCount() > 0) {
            $success_msg[] = '❤️ Property already saved!';
        } else {
            $save_property = $conn->prepare("INSERT INTO saved (user_id, property_id) VALUES (?, ?)");
            if ($save_property->execute([$user_id, $property_id])) {
                $success_msg[] = '❤️ Property saved successfully!';
                $result['is_saved'] = true; // Update is_saved for display
            } else {
                $warning_msg[] = '😔 Failed to save property';
            }
        }
    } else {
        $warning_msg[] = '😔 Invalid property ID or user not logged in';
    }
    header('Location: /app/property/presentation/view_property.php?get_id=' . $get_id);
    ob_end_flush();
    exit;
}

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['property_id']) && isset($_POST['reason'])) {
    // Ensure no output has been sent yet
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json');

    if (empty($user_id)) {
        echo json_encode([
            'success' => false,
            'message' => '😔 You must be logged in to submit a report.',
            'type' => 'error'
        ]);
        ob_end_flush();
        exit;
    }

    $property_id = filter_var($_POST['property_id'], FILTER_VALIDATE_INT);
    $reason = htmlspecialchars($_POST['reason'], ENT_QUOTES, 'UTF-8');
    $valid_reasons = ['Fake Listing', 'Suspicious Price', 'Inappropriate Content', 'Other'];

    if (!$property_id || !in_array($reason, $valid_reasons)) {
        echo json_encode([
            'success' => false,
            'message' => '😔 Invalid property ID or reason.',
            'type' => 'error'
        ]);
        ob_end_flush();
        exit;
    }

    try {
        // Check if the user has already reported this property
        $check_report = $conn->prepare("SELECT COUNT(*) FROM property_reports WHERE property_id = ? AND user_id = ?");
        $check_report->execute([$property_id, $user_id]);
        if ($check_report->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'message' => '⚠️ You have already reported this property.',
                'type' => 'warning'
            ]);
            ob_end_flush();
            exit;
        }

        // Insert the report into the property_reports table
        $insert_report = $conn->prepare("INSERT INTO property_reports (property_id, user_id, reason, created_at) VALUES (?, ?, ?, NOW())");
        if ($insert_report->execute([$property_id, $user_id, $reason])) {
            error_log("view_property.php: Report submitted successfully for property_id=$property_id by user_id=$user_id, reason=$reason");
            echo json_encode([
                'success' => true,
                'message' => '🚨 Report submitted successfully! Thank you for your feedback.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '😔 Failed to submit report. Please try again.',
                'type' => 'error'
            ]);
        }
    } catch (Exception $e) {
        error_log("view_property.php: Report submission failed for property_id=$property_id: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => '😔 Error submitting report: ' . $e->getMessage(),
            'type' => 'error'
        ]);
    }
    ob_end_flush();
    exit;
}

// Check owner's subscription and screening preferences
$check_subscription = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
$check_subscription->execute([$owner_id]);
$owner_premium = $check_subscription->fetchColumn();

$check_preferences = $conn->prepare("SELECT COUNT(*) FROM screening_preferences WHERE user_id = ? AND property_id = ?");
$check_preferences->execute([$owner_id, $get_id]);
$has_preferences = $check_preferences->fetchColumn() > 0;

$check_applied = $conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
$check_applied->execute([$get_id, $user_id]);
$has_applied = $check_applied->fetchColumn() > 0;

$has_passed = false; // Replace with actual logic if available
$chat_button = ($user_id && $user_id != $owner_id) ? 
    ($owner_premium && $has_preferences && !$has_applied ? 
        '<a href="/app/screening/presentation/apply.php?property_id=' . htmlspecialchars($get_id) . '" class="btn">📝 Apply to chat</a>' : 
        ($has_applied && $has_passed ? 
            '<a href="/app/chat/presentation/chat.php?property_id=' . htmlspecialchars($get_id) . '&other_user_id=' . htmlspecialchars($owner_id) . '" class="btn">💬 Chat with the owner</a>' : 
            (!$owner_premium || !$has_preferences ? 
                '<a href="/app/chat/presentation/chat.php?property_id=' . htmlspecialchars($get_id) . '&other_user_id=' . htmlspecialchars($owner_id) . '" class="btn">💬 Chat with the owner</a>' : ''))) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Property ✨</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/chatbot.css">
    <style>
        .info p, .box p, .flex .box span, .star-rating-display { display: flex; align-items: center; gap: 0.5rem; }
        .empty { color: #cc0000; margin-top: 0.5rem; display: none; }
        .empty.show { display: block; }
        .empty.warning { color: #e67e22; }
        .review-section .title, .view-property .title { margin-top: 1.5rem; }
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 0.3rem; }
        .star-rating input { display: none; }
        .star-rating label { cursor: pointer; color: #ccc; font-size: 1.5rem; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #f5b301; }
        .review-item { background: #f9f9f9; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .review-list { max-height: 300px; overflow-y: auto; }
        .report-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .report-modal.show { display: flex; }
        .modal-content { background: #fff; padding: 1.5rem; border-radius: 0.5rem; width: 300px; max-width: 90%; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); position: relative; }
        .modal-content h3 { margin: 0 0 1rem; font-size: 1.2rem; }
        .modal-content select { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 0.3rem; margin-bottom: 1rem; font-size: 1rem; }
        .modal-content .btn { width: 100%; padding: 0.5rem; font-size: 1rem; }
        .close-modal { position: absolute; top: 0.5rem; right: 0.5rem; cursor: pointer; font-size: 1.2rem; color: #666; }
        .close-modal:hover { color: #000; }
        @media (max-width: 768px) {
            .star-rating label { font-size: 1.2rem; }
            .review-item { padding: 0.8rem; }
            .review-list { max-height: 200px; }
            .modal-content { width: 90%; padding: 1rem; }
            .modal-content h3 { font-size: 1rem; }
            .modal-content select, .modal-content .btn { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>
<section class="view-property">
    <h1 class="heading">Property Details ✨</h1>
    <?php if ($result['success']): ?>
        <?php
        $fetch_property = $result['property'];
        $property_id = $fetch_property['id'];
        $fetch_user = $result['user'];
        $reputation_score = $review_data['metrics']['reputation_score'] ?? 0;
        $review_count = $review_data['metrics']['review_count'] ?? 0;
        $is_saved = $result['is_saved'];
        $reviews = $review_data['reviews'];
        error_log("Rendering reviews: count=" . count($reviews));
        ?>
        <div class="details">
            <div class="swiper images-container">
                <div class="swiper-wrapper">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if (!empty($fetch_property["image_0$i"])): ?>
                            <img src="/uploaded_files/<?= htmlspecialchars($fetch_property["image_0$i"]); ?>" class="swiper-slide" alt="Property image">
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
            <h3 class="name">✨ <?= htmlspecialchars($fetch_property['property_name']); ?>
                <?php if ($fetch_property['is_suspicious']): ?>
                    <span style="color: #e67e22; font-weight: bold; margin-left: 1rem;">🚨 (Suspicious)</span>
                <?php endif; ?>
            </h3>
            <p class="location"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($fetch_property['address']); ?></span></p>
            <div class="info">
                <p><i class="fas fa-euro-sign"></i><span><?= htmlspecialchars($fetch_property['price']); ?> EUR</span></p>
                <p><i class="fas fa-user"></i><span><?= htmlspecialchars($fetch_user['name']); ?></span></p>
                <p><i class="fas fa-phone"></i><a href="tel:<?= htmlspecialchars($fetch_user['number']); ?>"><?= htmlspecialchars($fetch_user['number']); ?></a></p>
                <p><i class="fas fa-building"></i><span><?= htmlspecialchars($fetch_property['type']); ?></span></p>
                <p><i class="fas fa-tag"></i><span><?= htmlspecialchars($fetch_property['offer']); ?></span></p>
                <p><i class="fas fa-calendar"></i><span><?= htmlspecialchars($fetch_property['date']); ?></span></p>
                <p><i class="fas fa-star"></i><span class="star-rating-display">
                    <?= str_repeat('<i class="fas fa-star"></i>', round($reputation_score)); ?>
                    <?= str_repeat('<i class="far fa-star"></i>', 5 - round($reputation_score)); ?>
                    <?= number_format($reputation_score, 1); ?> / 5.0 (<?= $review_count; ?> reviews)
                </span></p>
            </div>
            <h3 class="title">📋 Quick Details</h3>
            <div class="flex">
                <div class="box">
                    <p><i class="fas fa-bed"></i><span><?= htmlspecialchars($fetch_property['bhk']); ?> BHK</span></p>
                    <p><i class="fas fa-euro-sign"></i><span><?= htmlspecialchars($fetch_property['deposite']); ?> EUR Deposit</span></p>
                    <p><i class="fas fa-home"></i><span><?= htmlspecialchars($fetch_property['status']); ?></span></p>
                    <p><i class="fas fa-bed"></i><span><?= htmlspecialchars($fetch_property['bedroom']); ?> Bedrooms</span></p>
                    <p><i class="fas fa-bath"></i><span><?= htmlspecialchars($fetch_property['bathroom']); ?> Bathrooms</span></p>
                    <p><i class="fas fa-balcony"></i><span><?= htmlspecialchars($fetch_property['balcony']); ?> Balconies</span></p>
                </div>
                <div class="box">
                    <p><i class="fas fa-ruler"></i><span><?= htmlspecialchars($fetch_property['carpet']); ?> sqft</span></p>
                    <p><i class="fas fa-clock"></i><span><?= htmlspecialchars($fetch_property['age']); ?> yrs</span></p>
                    <p><i class="fas fa-building"></i><span><?= htmlspecialchars($fetch_property['total_floors']); ?> Floors</span></p>
                    <p><i class="fas fa-layer-group"></i><span><?= htmlspecialchars($fetch_property['room_floor']); ?> Floor</span></p>
                    <p><i class="fas fa-couch"></i><span><?= htmlspecialchars($fetch_property['furnished']); ?></span></p>
                    <p><i class="fas fa-piggy-bank"></i><span><?= htmlspecialchars($fetch_property['loan']); ?></span></p>
                </div>
            </div>
            <h3 class="title">🎉 Amenities</h3>
            <div class="flex">
                <div class="box">
                    <?php
                    $amenities = [
                        'lift' => 'Elevator 🚀',
                        'security_guard' => 'Security Personnel 🛡️',
                        'play_ground' => 'Playground ⚽',
                        'garden' => 'Garden 🌳',
                        'water_supply' => 'Water Supply 💧',
                        'power_backup' => 'Power Backup ⚡️'
                    ];
                    foreach ($amenities as $amenity => $label): ?>
                        <?php if ($fetch_property[$amenity] == 'yes'): ?>
                            <p><i class="fas fa-check"></i><span><?= $label ?></span></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="box">
                    <?php
                    $amenities = [
                        'parking_area' => 'Parking Area 🚗',
                        'gym' => 'Gym 💪',
                        'shopping_mall' => 'Shopping Mall 🛍️',
                        'hospital' => 'Hospital 🩺',
                        'school' => 'School 📚',
                        'market_area' => 'Market Area 🏪'
                    ];
                    foreach ($amenities as $amenity => $label): ?>
                        <?php if ($fetch_property[$amenity] == 'yes'): ?>
                            <p><i class="fas fa-check"></i><span><?= $label ?></span></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <h3 class="title">📝 Description</h3>
            <p class="description">📖 <?= htmlspecialchars($fetch_property['description']); ?></p>
            <form action="" method="POST" class="flex-btn">
                <input type="hidden" name="property_id" value="<?= $property_id; ?>">
                <button type="submit" name="save" class="save">
                    <i class="<?= $is_saved ? 'fas' : 'far'; ?> fa-heart"></i>
                    <span> <?= $is_saved ? 'Saved' : 'Save'; ?></span>
                </button>
                <?php if (!empty($user_id) && $user_id != $result['owner_id']): ?>
                    <?= $chat_button; ?>
                    <input type="button" value="🚨 Report" class="btn report-btn" data-property-id="<?= $property_id; ?>">
                <?php else: ?>
                    <input type="button" value="🚨 Report" class="btn report-btn disabled" disabled title="Please log in to report this property.">
                <?php endif; ?>
            </form>
            <div class="report-modal" id="report-modal-<?= $property_id; ?>">
                <div class="modal-content">
                    <span class="close-modal">×</span>
                    <h3>🚨 Report Property</h3>
                    <div class="report-form">
                        <input type="hidden" name="property_id" value="<?= $property_id; ?>">
                        <select name="reason" required>
                            <option value="" disabled selected>Select a reason</option>
                            <option value="Fake Listing">Fake Listing</option>
                            <option value="Suspicious Price">Suspicious Price</option>
                            <option value="Inappropriate Content">Inappropriate Content</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="button" value="Submit Report" class="btn submit-report-btn">
                        <p class="empty"></p>
                    </div>
                </div>
            </div>
            <?php if (!empty($user_id) && $user_id != $result['owner_id']): ?>
                <div class="review-section">
                    <h3 class="title">💬 Rate & Review the Landlord</h3>
                    <form action="/app/reviews/presentation/review_landlord.php?user_id=<?= $result['owner_id']; ?>&property_id=<?= $property_id; ?>" method="POST" class="chat-form review-input">
                        <input type="hidden" name="submit_review" value="submit">
                        <input type="hidden" name="user_id" value="<?= $result['owner_id']; ?>">
                        <input type="hidden" name="property_id" value="<?= $property_id; ?>">
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="review-star<?= $i; ?>" value="<?= $i; ?>" required>
                                <label for="review-star<?= $i; ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        <textarea name="review_text" placeholder="💬 Share your experience..." required maxlength="1000"></textarea>
                        <button type="submit" name="submit_review_btn"><i class="fas fa-arrow-up"></i> 📤 Send</button>
                        <p class="empty"></p>
                    </form>
                    <div class="chat-messages review-list">
                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="message review-item received">
                                    <div class="stars star-rating-display">
                                        <?= str_repeat('<i class="fas fa-star"></i>', $review['rating']); ?>
                                        <?= str_repeat('<i class="far fa-star"></i>', 5 - $review['rating']); ?>
                                    </div>
                                    <p>💬 <?= htmlspecialchars($review['review_text'] ?? 'No comment provided.'); ?></p>
                                    <small>🕒 Posted on <?= date('M d, Y H:i', strtotime($review['created_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="chat-placeholder">💭 No reviews yet. Be the first to share your experience!</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (empty($user_id)): ?>
                <div class="review-section">
                    <p class="empty">🔐 Please <a href="/app/auth/presentation/login.php">log in</a> to submit a review.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="empty">😥 Property not found! <a href="/app/property/presentation/post_property.php" class="btn">➕ Add New</a></p>
    <?php endif; ?>
</section>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js" integrity="sha512-AA1Bzp5Q0K1KanKKmvN/4d3IRKVlv9PYgwFPvm32nPO6QS8yH1HO7LbgB1pgiOxPtfeg5zEn2ba64MUcqJx6CA==" crossorigin="anonymous"></script>
<script src="/public/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>
<script>
if (typeof swal === 'undefined') {
    console.error('SweetAlert not loaded. Check CDN or network.');
    alert('😔 Error: Alert system not loaded. Please try again later.');
}

var swiper = new Swiper(".images-container", {
    effect: "coverflow",
    grabCursor: true,
    centeredSlides: true,
    slidesPerView: "auto",
    loop: true,
    coverflowEffect: { rotate: 0, stretch: 0, depth: 200, modifier: 3, slideShadows: true },
    autoplay: { delay: 1000, disableOnInteraction: false },
    pagination: { el: ".swiper-pagination" },
});

document.querySelector('.review-input')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    const errorElement = form.querySelector('.empty');
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json'
        }
    })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Raw response:', text);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            if (data.success) {
                swal({
                    title: "🎉 Review Submitted!",
                    text: data.message,
                    icon: "success",
                    button: "OK"
                }).then(() => { form.reset(); window.location.reload(); });
            } else {
                errorElement.textContent = `❌ ${data.message}`;
                errorElement.classList.add('show');
                swal({
                    title: "😔 Error!",
                    text: data.message,
                    icon: "error",
                    button: "OK"
                });
            }
        })
        .catch(error => {
            console.error('Review submission error:', error);
            errorElement.textContent = `❌ Failed to submit review: ${error.message}`;
            errorElement.classList.add('show');
            swal({
                title: "😔 Error!",
                text: `Failed to submit review: ${error.message}`,
                icon: "error",
                button: "OK"
            });
        });
});

document.querySelectorAll('.report-btn').forEach(button => {
    button.addEventListener('click', function() {
        if (this.hasAttribute('disabled')) {
            swal({
                title: "🔐 Login Required",
                text: "Please log in to report this property.",
                icon: "warning",
                button: "OK"
            });
            return;
        }
        const property_id = this.dataset.propertyId;
        const modal = document.getElementById(`report-modal-${property_id}`);
        modal.classList.add('show');
    });
});

document.querySelectorAll('.close-modal').forEach(button => {
    button.addEventListener('click', function() {
        const modal = this.closest('.report-modal');
        modal.classList.remove('show');
        const errorElement = modal.querySelector('.empty');
        errorElement.classList.remove('show', 'warning');
        errorElement.textContent = '';
    });
});

document.querySelectorAll('.submit-report-btn').forEach(button => {
    button.addEventListener('click', function() {
        const form = this.closest('.report-form');
        const formData = new FormData();
        formData.append('property_id', form.querySelector('input[name="property_id"]').value);
        const reason = form.querySelector('select[name="reason"]').value;
        formData.append('reason', reason);
        const errorElement = form.querySelector('.empty');
        if (!reason) {
            errorElement.textContent = '❌ Please select a reason.';
            errorElement.classList.add('show');
            swal({
                title: "😔 Error!",
                text: "Please select a reason.",
                icon: "error",
                button: "OK"
            });
            return;
        }
        fetch('/app/property/presentation/view_property.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Raw response:', text);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    swal({
                        title: "🚨 Report Submitted!",
                        text: data.message,
                        icon: "success",
                        button: "OK"
                    }).then(() => {
                        form.reset();
                        form.closest('.report-modal').classList.remove('show');
                    });
                } else {
                    errorElement.textContent = `⚠️ ${data.message}`;
                    errorElement.classList.add('show');
                    if (data.type === 'warning') {
                        errorElement.classList.add('warning');
                    }
                    swal({
                        title: data.type === 'warning' ? "⚠️ Notice" : "😔 Error!",
                        text: data.message,
                        icon: data.type === 'warning' ? "warning" : "error",
                        button: "OK"
                    });
                }
            })
            .catch(error => {
                console.error('Report submission error:', error);
                errorElement.textContent = `❌ Error: ${error.message}`;
                errorElement.classList.add('show');
                swal({
                    title: "😔 Error!",
                    text: `Failed to submit report: ${error.message}`,
                    icon: "error",
                    button: "OK"
                });
            });
    });
});
</script>
</body>
</html>
<?php
// Clean up output buffer for regular page rendering
ob_end_flush();
?>