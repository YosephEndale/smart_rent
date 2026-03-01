<?php
namespace App\User\Presentation;

use PDO;
use Exception;
use App\Scam\Logic\ScamLogic;
use App\User\Logic\UserLogic;
use App\Components\Recommendations;

// Define ROOT_DIR if not already defined
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__); 
}

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/components/currency.php';
require_once ROOT_DIR . '/components/recommendations.php';
require_once ROOT_DIR . '/components/message.php';

// Start session
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    error_log("Session start error in index.php: " . $e->getMessage());
}

// Run scam detection and expiration checks automatically
$lastRunFile = ROOT_DIR . '/cache/scam_detection_last_run.txt';
$runInterval = 3600; // Run every hour (3600 seconds)
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
        error_log("index.php: Automatic batch scam detection and expiration check completed - batch_processed={$batchResult['processed']}, suspicious={$batchResult['suspicious']}, expired={$expirationResult['expired']}");
    } catch (Exception $e) {
        error_log("index.php: Automatic batch scam detection failed: " . $e->getMessage());
    }
}

$user_id = $_SESSION['user_id'] ?? '';
$warning_msg = $_SESSION['warning_msg'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? [];
$_SESSION['warning_msg'] = [];
$_SESSION['success_msg'] = [];

$result = Recommendations::getRecommendedProperties($user_id, get_db_connection(), $rates);
$heading = $result['heading'] ?? 'Latest Dream Homes Just for You!';
$properties = $result['properties'] ?? [];

if (isset($result['error'])) {
    $warning_msg[] = $result['error'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && $user_id) {
    $userLogic = new UserLogic(get_db_connection());
    $property_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
    if ($property_id) {
        $save_result = $userLogic->saveProperty($user_id, $property_id);
        if (isset($save_result['success'])) {
            $success_msg[] = $save_result['success'];
        } elseif (isset($save_result['error'])) {
            $warning_msg[] = $save_result['error'];
        }
    } else {
        $warning_msg[] = 'Invalid property ID';
    }
    header('Location: /index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send']) && $user_id) {
    $property_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
    $conn = get_db_connection();
    $owner_id = $conn->prepare("SELECT user_id FROM property WHERE id = ?");
    $owner_id->execute([$property_id]);
    $owner_id = $owner_id->fetchColumn();

    if ($owner_id) {
        // Check if the owner is premium and has screening preferences
        $check_subscription = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
        $check_subscription->execute([$owner_id]);
        $owner_premium = $check_subscription->fetchColumn();

        $check_preferences = $conn->prepare("SELECT COUNT(*) FROM screening_preferences WHERE user_id = ? AND property_id = ?");
        $check_preferences->execute([$owner_id, $property_id]);
        $has_preferences = $check_preferences->fetchColumn() > 0;

        // Check if the user has applied
        $check_applied = $conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
        $check_applied->execute([$property_id, $user_id]);
        $has_applied = $check_applied->fetchColumn() > 0;

        // Assume $has_passed comes from another source (e.g., tenant_scores); default to false if not set
        $has_passed = $properties[array_search($property_id, array_column($properties, 'property_id'))]['has_passed'] ?? false;

        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, property_id, action, created_at) VALUES (?, ?, 'chat', NOW())");
        $stmt->execute([$user_id, $property_id]);

        if ($owner_premium && $has_preferences && !$has_applied) {
            error_log("index.php: Redirecting to apply.php for property_id=$property_id");
            $success_msg[] = 'Please complete the application to chat with the owner!';
            header("Location: /app/screening/presentation/apply.php?property_id=$property_id");
        } elseif ($has_applied && !$has_passed) {
            error_log("index.php: Application pending for property_id=$property_id");
            $warning_msg[] = 'Your application is pending owner review.';
            header("Location: /index.php");
        } else {
            error_log("index.php: Redirecting to chat.php for property_id=$property_id, other_user_id=$owner_id");
            $success_msg[] = 'Chat opened successfully!';
            header("Location: /app/chat/presentation/chat.php?property_id=$property_id&other_user_id=$owner_id");
        }
        exit;
    } else {
        error_log("index.php: Property owner not found for property_id=$property_id");
        $warning_msg[] = 'Property owner not found';
    }
}

$conn = get_db_connection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/chatbot.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="listings">
    <h1 class="heading"><?= htmlspecialchars($heading); ?></h1>
    <div class="box-container">
        <?php if (empty($properties)): ?>
            <p class="empty">No homes yet... but you can change that! <a href="/app/property/presentation/post_property.php" class="btn">Add new listing</a></p>
        <?php else: ?>
            <?php foreach ($properties as $item):
                $property = $item['property'];
                $user_name = $item['user_name'];
                $user_initial = $item['user_initial'];
                $total_images = $item['total_images'];
                $is_saved = $item['is_saved'];
                $has_passed = $item['has_passed'];

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
                            <span><?= $is_saved ? 'Saved' : 'Save'; ?></span>
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
                    </div>
                    <div class="box">
                        <div class="price"><?= convertCurrencyFromEUR($property['price'], $rates); ?></div>
                        <h3 class="name"><?= htmlspecialchars($property['property_name']); ?></h3>
                        <p class="location"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($property['address']); ?></span></p>
                        <div class="flex">
                            <p><i class="fas fa-house"></i><span><?= htmlspecialchars($property['type']); ?></span></p>
                            <p><i class="fas fa-tag"></i><span><?= htmlspecialchars($property['offer']); ?></span></p>
                            <p><i class="fas fa-bed"></i><span><?= htmlspecialchars($property['bhk']); ?> BHK</span></p>
                            <p><i class="fas fa-trowel"></i><span><?= htmlspecialchars($property['status']); ?></span></p>
                            <p><i class="fas fa-couch"></i><span><?= htmlspecialchars($property['furnished']); ?></span></p>
                            <p><i class="fas fa-maximize"></i><span><?= htmlspecialchars($property['carpet']); ?> sqft</span></p>
                        </div>
                        <div class="flex-btn">
                            <a href="/app/property/presentation/view_property.php?get_id=<?= htmlspecialchars($property['id']); ?>" class="btn">View Property</a>
                            <?php if ($user_id && $user_id != $property['user_id']): ?>
                                <?= $chat_button; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div style="margin-top: 2rem; text-align: center;">
        <a href="/app/user/presentation/listings.php" class="inline-btn">View All Listings</a>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>
<?php if (!empty($success_msg)): ?>
<script>
    swal({
        title: "Success!",
        text: "<?= htmlspecialchars($success_msg[0]); ?>",
        icon: "success",
        button: "OK"
    });
</script>
<?php endif; ?>

<?php if (!empty($warning_msg)): ?>
<script>
    swal({
        title: "Warning!",
        text: "<?= htmlspecialchars(implode('\n', $warning_msg)); ?>",
        icon: "warning",
        button: "OK"
    });
</script>
<?php endif; ?>
</body>
</html>