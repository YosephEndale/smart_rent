<?php
namespace App\User\Presentation;

use PDO;
use Exception;
use App\Scam\Logic\ScamLogic;

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/scam/logic/ScamLogic.php';

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    error_log("Session start error in listings.php: " . $e->getMessage());
}

$lastRunFile = ROOT_DIR . '/cache/scam_detection_last_run.txt';
$runInterval = 3600;
$shouldRun = false;

if (!file_exists($lastRunFile) || (time() - filemtime($lastRunFile)) > $runInterval) {
    $shouldRun = true;
    if (!is_dir(ROOT_DIR . '/cache')) {
        mkdir(ROOT_DIR . '/cache', 0755, true);
    }
    file_put_contents($lastRunFile, time());
}

if ($shouldRun) {
    try {
        $conn = get_db_connection();
        $scamLogic = new ScamLogic($conn);
        $batchResult = $scamLogic->runBatchScamDetection();
        $expirationResult = $scamLogic->checkExpiredProperties();
        error_log("listings.php: Automatic batch scam detection and expiration check completed - batch_processed={$batchResult['processed']}, suspicious={$batchResult['suspicious']}, expired={$expirationResult['expired']}");
    } catch (Exception $e) {
        error_log("listings.php: Automatic batch scam detection failed: " . $e->getMessage());
    }
}

$user_id = $_SESSION['user_id'] ?? '';
$warning_msg = $_SESSION['warning_msg'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? [];
$_SESSION['warning_msg'] = [];
$_SESSION['success_msg'] = [];

// Handle search parameters
$search_query = $_GET['search_query'] ?? '';
$min_price = filter_var($_GET['min_price'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$max_price = filter_var($_GET['max_price'] ?? 999999, FILTER_VALIDATE_INT) ?: 999999;
$offer = $_GET['offer'] ?? '';
$type = $_GET['type'] ?? '';
$bhk = $_GET['bhk'] ?? '';

$conn = get_db_connection();

// Build the search query
$sql = "
    SELECT p.*, 
           CASE WHEN ps.is_suspicious = 1 THEN 1 ELSE 0 END AS is_suspicious
    FROM property p 
    LEFT JOIN property_suspicion ps ON p.id = ps.property_id 
    WHERE 1=1
";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (p.property_name LIKE ? OR p.address LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
if ($min_price > 0) {
    $sql .= " AND p.price >= ?";
    $params[] = $min_price;
}
if ($max_price < 999999) {
    $sql .= " AND p.price <= ?";
    $params[] = $max_price;
}
if (!empty($offer)) {
    $sql .= " AND p.offer = ?";
    $params[] = $offer;
}
if (!empty($type)) {
    $sql .= " AND p.type = ?";
    $params[] = $type;
}
if (!empty($bhk)) {
    $sql .= " AND p.bhk = ?";
    $params[] = $bhk;
}

$sql .= " ORDER BY p.date DESC";
$select_properties = $conn->prepare($sql);
$select_properties->execute($params);
$properties = $select_properties->fetchAll(PDO::FETCH_ASSOC);

// Set heading based on search parameters
$heading = 'All Listings';
if (!empty($search_query) || $min_price > 0 || $max_price < 999999 || !empty($offer) || !empty($type) || !empty($bhk)) {
    $heading = 'Search Results';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save']) && $user_id) {
        $property_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
        if ($property_id) {
            // Check if already saved
            $check_saved = $conn->prepare("SELECT * FROM saved WHERE property_id = ? AND user_id = ?");
            $check_saved->execute([$property_id, $user_id]);
            if ($check_saved->rowCount() > 0) {
                $success_msg[] = '❤️ Property already saved!';
            } else {
                $save_property = $conn->prepare("INSERT INTO saved (user_id, property_id) VALUES (?, ?)");
                if ($save_property->execute([$user_id, $property_id])) {
                    $success_msg[] = '❤️ Property saved successfully!';
                } else {
                    $warning_msg[] = '😔 Failed to save property';
                }
            }
        } else {
            $warning_msg[] = '😔 Invalid property ID';
        }
        header('Location: /app/user/presentation/listings.php?' . http_build_query($_GET));
        exit;
    }
    if (isset($_POST['send']) && $user_id) {
        $property_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
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
                error_log("listings.php: Redirecting to apply.php for property_id=$property_id");
                $_SESSION['success_msg'][] = '📝 Please complete the application to chat with the owner!';
                header("Location: /app/screening/presentation/apply.php?property_id=$property_id");
            } elseif ($has_applied && !$has_passed) {
                error_log("listings.php: Application pending for property_id=$property_id");
                $_SESSION['warning_msg'][] = '⏳ Your application is pending owner review.';
                header("Location: /app/user/presentation/listings.php?" . http_build_query($_GET));
            } else {
                error_log("listings.php: Redirecting to chat.php for property_id=$property_id, other_user_id=$owner_id");
                $_SESSION['success_msg'][] = '💬 Chat opened successfully!';
                header("Location: /app/chat/presentation/chat.php?property_id=$property_id&other_user_id=$owner_id");
            }
            exit;
        } else {
            error_log("listings.php: Property owner not found for property_id=$property_id");
            $_SESSION['warning_msg'][] = '😔 Property owner not found';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Listings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="listings">
    <h1 class="heading"><?= htmlspecialchars($heading); ?></h1>
    <div class="box-container">
        <?php if (empty($properties)): ?>
            <p class="empty">😥 No properties added yet! <a href="/app/property/presentation/post_property.php" class="btn">➕ Add new</a></p>
        <?php else: ?>
            <?php foreach ($properties as $property):
                $select_user = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
                $select_user->execute([$property['user_id']]);
                $fetch_user = $select_user->fetch(PDO::FETCH_ASSOC);
                $user_name = $fetch_user['name'] ?? 'Unknown';
                $user_initial = substr($user_name, 0, 1);
                $total_images = 1;
                for ($i = 2; $i <= 5; $i++) {
                    if (!empty($property["image_0$i"])) $total_images++;
                }
                $select_saved = $conn->prepare("SELECT * FROM saved WHERE property_id = ? AND user_id = ?");
                $select_saved->execute([$property['id'], $user_id]);
                $is_saved = $select_saved->rowCount() > 0;

                $owner_id = $property['user_id'];
                $check_subscription = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
                $check_subscription->execute([$owner_id]);
                $owner_premium = $check_subscription->fetchColumn();

                $check_preferences = $conn->prepare("SELECT COUNT(*) FROM screening_preferences WHERE user_id = ? AND property_id = ?");
                $check_preferences->execute([$owner_id, $property['id']]);
                $has_preferences = $check_preferences->fetchColumn() > 0;

                $check_applied = $conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
                $check_applied->execute([$property['id'], $user_id]);
                $has_applied = $check_applied->fetchColumn() > 0;

                $has_passed = false; // Replace with actual logic if available

                $chat_button = ($user_id && $user_id != $owner_id) ? 
                    (($owner_premium && $has_preferences && !$has_applied) ? 
                        '<input type="submit" value="📝 Apply to Chat" name="send" class="btn">' : 
                        ($has_applied && $has_passed ? 
                            '<input type="submit" value="💬 Chat with the owner" name="send" class="btn">' : 
                            (!$owner_premium || !$has_preferences ? 
                                '<input type="submit" value="💬 Chat with the owner" name="send" class="btn">' : ''))) : '';
                ?>
                <form action="" method="POST">
                    <div class="box">
                        <input type="hidden" name="property_id" value="<?= htmlspecialchars($property['id']); ?>">
                        <button type="submit" name="save" class="save">
                            <i class="<?= $is_saved ? 'fas' : 'far'; ?> fa-heart"></i>
                            <span> <?= $is_saved ? 'Saved' : 'Save'; ?></span>
                        </button>
                        <div class="thumb">
                            <p class="total-images"><i class="far fa-image"></i><span><?= $total_images; ?></span></p>
                            <img src="/uploaded_files/<?= htmlspecialchars($property['image_01']); ?>" alt="">
                        </div>
                        <div class="admin">
                            <h3><?= htmlspecialchars($user_initial); ?></h3>
                            <div>
                                <p><?= htmlspecialchars($user_name); ?></p>
                                <span><?= htmlspecialchars($property['date']); ?></span>
                            </div>
        </div>
                        <h3 class="name">
                            <?= htmlspecialchars($property['property_name']); ?>
                            <?php if ($property['is_suspicious']): ?>
                                <span style="color: #e67e22; font-weight: bold; margin-left: 1rem;">🚨 (Suspicious)</span>
                            <?php endif; ?>
                        </h3>
                        <p class="location"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($property['address']); ?></span></p>
                        <div class="flex">
                            <p><i class="fas fa-house"></i><span><?= htmlspecialchars($property['type']); ?></span></p>
                            <p><i class="fas fa-tag"></i><span><?= htmlspecialchars($property['offer']); ?></span></p>
                            <p><i class="fas fa-bed"></i><span><?= htmlspecialchars($property['bhk']); ?> BHK</span></p>
                            <p><i class="fas fa-trowel"></i><span><?= htmlspecialchars($property['status']); ?></span></p>
                            <p><i class="fas fa-couch"></i><span><?= htmlspecialchars($property['furnished']); ?></span></p>
                            <p><i class="fas fa-maximize"></i><span><?= htmlspecialchars($property['carpet']); ?>sqft</span></p>
                        </div>
                        <div class="flex-btn">
                            <a href="/app/property/presentation/view_property.php?get_id=<?= htmlspecialchars($property['id']); ?>" class="btn">🔍 View property</a>
                            <?php if ($user_id && $user_id != $property['user_id']): ?>
                                <?= $chat_button; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>