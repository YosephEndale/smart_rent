<?php
namespace App\User\Presentation;
use PDO;
use App\User\Logic\UserLogic;

// Include Composer autoloader and env.php relatively
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';

// Define ROOT_DIR if not already defined (fallback)
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2)); // Resolves to C:\Users\yosep\OneDrive\Desktop\smart_rent from app/user/presentation/
}

// Debug: Verify autoloader path
if (!file_exists(ROOT_DIR . '/vendor/autoload.php')) {
    error_log("Autoloader not found at: " . ROOT_DIR . "/vendor/autoload.php");
    die("Configuration error: Composer autoloader not found. Run 'composer install' or 'composer update'.");
}
require_once ROOT_DIR . '/components/connect.php';
session_start();
$user_id = $_SESSION['user_id'] ?? '';
if (!$user_id) {
    header('Location: /app/auth/presentation/login.php');
    exit;
}

$userLogic = new UserLogic(get_db_connection());
$warning_msg = [];
$success_msg = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $property_id = htmlspecialchars($_POST['property_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $result = $userLogic->saveProperty($user_id, $property_id);
    if (isset($result['error'])) {
        $warning_msg[] = $result['error'];
    } else {
        $success_msg[] = $result['success'];
    }
}

// Handle chat initiation if triggered via query parameter
if (isset($_GET['init_chat']) && $user_id) {
    $property_id = filter_var($_GET['property_id'] ?? '', FILTER_VALIDATE_INT);
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
        $has_passed = false; // Replace with actual logic if available

        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, property_id, action, created_at) VALUES (?, ?, 'chat', NOW())");
        $stmt->execute([$user_id, $property_id]);

        if ($owner_premium && $has_preferences && !$has_applied) {
            error_log("saved_properties.php: Redirecting to apply.php for property_id=$property_id");
            $success_msg[] = 'Please complete the application to chat with the owner!';
            header("Location: /app/screening/presentation/apply.php?property_id=$property_id");
        } elseif ($has_applied && !$has_passed) {
            error_log("saved_properties.php: Application pending for property_id=$property_id");
            $warning_msg[] = 'Your application is pending owner review.';
            header("Location: /app/user/presentation/saved_properties.php");
        } else {
            error_log("saved_properties.php: Redirecting to chat.php for property_id=$property_id, other_user_id=$owner_id");
            $success_msg[] = 'Chat opened successfully!';
            header("Location: /app/chat/presentation/chat.php?property_id=$property_id&other_user_id=$owner_id");
        }
        exit;
    } else {
        error_log("saved_properties.php: Property owner not found for property_id=$property_id");
        $warning_msg[] = 'Property owner not found';
    }
}

$properties = $userLogic->getSavedProperties($user_id);
$conn = get_db_connection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Listings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="listings">
    <h1 class="heading">Saved Listings</h1>
    <div class="box-container">
        <?php if (empty($properties)): ?>
            <p class="empty">No properties saved yet! <a href="/app/user/presentation/listings.php" class="btn" style="margin-top:1.5rem;">Discover more</a></p>
        <?php else: ?>
            <?php foreach ($properties as $fetch_property):
                $select_user = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
                $select_user->execute([$fetch_property['user_id']]);
                $fetch_user = $select_user->fetch(PDO::FETCH_ASSOC);
                $image_count = 1;
                for ($i = 2; $i <= 5; $i++) {
                    if (!empty($fetch_property["image_0$i"])) {
                        $image_count++;
                    }
                }
                $is_owner = ($user_id == $fetch_property['user_id']);

                // Check owner's subscription and screening preferences
                $owner_id = $fetch_property['user_id'];
                $check_subscription = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
                $check_subscription->execute([$owner_id]);
                $owner_premium = $check_subscription->fetchColumn();

                $check_preferences = $conn->prepare("SELECT COUNT(*) FROM screening_preferences WHERE user_id = ? AND property_id = ?");
                $check_preferences->execute([$owner_id, $fetch_property['id']]);
                $has_preferences = $check_preferences->fetchColumn() > 0;

                // Check if the user has applied for this specific property
                $check_applied = $conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
                $check_applied->execute([$fetch_property['id'], $user_id]);
                $has_applied = $check_applied->fetchColumn() > 0;

                // Assume $has_passed comes from another source (e.g., tenant_scores); default to false if not set
                $has_passed = false; // Replace with actual logic if available
                $chat_button = ($user_id && $user_id != $owner_id) ? 
                    (($owner_premium && $has_preferences && !$has_applied) ? 
                        '<a href="/app/screening/presentation/apply.php?property_id=' . htmlspecialchars($fetch_property['id']) . '" class="btn">📝 Apply to Chat</a>' : 
                        ($has_applied && $has_passed ? 
                            '<a href="/app/chat/presentation/chat.php?property_id=' . htmlspecialchars($fetch_property['id']) . '&other_user_id=' . htmlspecialchars($owner_id) . '" class="btn">💬 Chat with the owner!</a>' : 
                            (!$owner_premium || !$has_preferences ? 
                                '<a href="/app/chat/presentation/chat.php?property_id=' . htmlspecialchars($fetch_property['id']) . '&other_user_id=' . htmlspecialchars($owner_id) . '" class="btn">💬 Chat with the owner!</a>' : ''))) : '';
                ?>
            <form action="" method="POST">
                <div class="box">
                    <input type="hidden" name="property_id" value="<?= $fetch_property['id']; ?>">
                    <button type="submit" name="save" class="save"><i class="fas fa-heart"></i><span>Remove from saved</span></button>
                    <div class="thumb">
                        <p class="total-images"><i class="far fa-image"></i><span><?= $image_count; ?></span></p>
                        <img src="/uploaded_files/<?= $fetch_property['image_01']; ?>" alt="Property Image">
                    </div>
                    <div class="admin">
                        <h3><?= substr($fetch_user['name'], 0, 1); ?></h3>
                        <div>
                            <p><?= $fetch_user['name']; ?></p>
                            <span><?= $fetch_property['date']; ?></span>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="price"><i class="fas fa-euro"></i><span><?= $fetch_property['price']; ?></span></div>
                    <h3 class="name"><?= $fetch_property['property_name']; ?></h3>
                    <p class="location"><i class="fas fa-map-marker-alt"></i><span><?= $fetch_property['address']; ?></span></p>
                    <div class="flex">
                        <p><i class="fas fa-house"></i><span><?= $fetch_property['type']; ?></span></p>
                        <p><i class="fas fa-tag"></i><span><?= $fetch_property['offer']; ?></span></p>
                        <p><i class="fas fa-bed"></i><span><?= $fetch_property['bhk']; ?> BHK</span></p>
                        <p><i class="fas fa-trowel"></i><span><?= $fetch_property['status']; ?></span></p>
                        <p><i class="fas fa-couch"></i><span><?= $fetch_property['furnished']; ?></span></p>
                        <p><i class="fas fa-maximize"></i><span><?= $fetch_property['carpet']; ?> sqft</span></p>
                    </div>
                    <div class="flex-btn">
                        <a href="/app/property/presentation/view_property.php?get_id=<?= $fetch_property['id']; ?>" class="btn">View property</a>
                        <?php if (!$is_owner): ?>
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