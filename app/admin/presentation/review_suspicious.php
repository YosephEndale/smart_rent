<?php
session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/components/connect.php';
require_once ROOT_DIR . '/app/scam/presentation/scam_detection.php';

use App\Admin\Data\PropertyData;
use App\Admin\Data\UserData;
use App\Admin\Logic\PropertyLogic;

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$admin_id = $_SESSION['admin_id'];

$property_id = filter_var($_GET['property_id'] ?? 0, FILTER_VALIDATE_INT);
$propertyLogic = new PropertyLogic($conn);
$propertyData = new PropertyData($conn);
$userData = new UserData($conn);
$success_msg = [];
$warning_msg = [];

$property_data = $property_id ? $propertyData->getPropertyById($property_id) : [];
$suspicion_data = $property_id ? $propertyData->getSuspicionData($property_id) : [];
$user_reports = $property_id ? $propertyData->getUserReports($property_id) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_review'])) {
    $review_status = $_POST['review_status'];
    $admin_comment = htmlspecialchars($_POST['admin_comment'] ?? '', ENT_QUOTES, 'UTF-8');
    $delete_property = isset($_POST['delete_property']) && $_POST['delete_property'] === 'yes';
    $notify_user = isset($_POST['notify_user']) && $_POST['notify_user'] === 'yes';
    $bot_token = 'YOUR_BOT_TOKEN_HERE'; // Replace with actual Telegram bot token
    $encryption_key = 'YOUR_ENCRYPTION_KEY_HERE'; // Replace with actual encryption key

    $result = $propertyLogic->reviewSuspiciousProperty($property_id, $review_status, $admin_comment, $delete_property, $notify_user, $admin_id, $bot_token, $encryption_key);
    $success_msg = array_merge($success_msg, $result['success']);
    $warning_msg = array_merge($warning_msg, $result['errors']);

    if ($review_status === 'approved') {
        header('Location: listings.php');
        exit();
    }
}

if (isset($_POST['delete'])) {
    $delete_id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);
    if ($delete_id === false) {
        $warning_msg[] = 'Invalid listing ID!';
    } else {
        $result = $propertyLogic->deleteProperty($delete_id);
        if ($result['success']) {
            $success_msg[] = $result['message'];
        } else {
            $warning_msg[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Suspicious Properties</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
    <style>
        .admin-review-form {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .admin-review-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .admin-review-form select, .admin-review-form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .admin-review-form select.rejected {
            border-color: #e74c3c;
            background-color: #fff3f3;
        }
        .admin-review-form textarea {
            min-height: 100px;
            resize: vertical;
        }
        .admin-review-form .btn {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .admin-review-form .checkbox-group {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="listings">
        <?php if ($property_id && $property_data): ?>
            <h1 class="heading">Review Suspicious Property (ID: <?= htmlspecialchars($property_id); ?>)</h1>
            <div class="messages">
                <?php foreach ($warning_msg as $msg): ?>
                    <p class="alert alert-warning"><?= htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
                <?php foreach ($success_msg as $msg): ?>
                    <p class="alert alert-success"><?= htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
            <div class="box-container">
                <div class="box">
                    <h3 class="name">Property Details</h3>
                    <p><i class="fas fa-home"></i> 🏠 <?= htmlspecialchars($property_data['property_name']); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> 📍 <?= htmlspecialchars($property_data['address']); ?></p>
                    <p><i class="fas fa-euro-sign"></i> 💶 <?= number_format($property_data['price'], 2); ?> EUR</p>
                    <p><i class="fas fa-ruler-combined"></i> 📏 <?= htmlspecialchars($property_data['carpet']); ?> sqft</p>
                    <p><i class="fas fa-bed"></i> 🛏️ <?= htmlspecialchars($property_data['bhk']); ?> BHK</p>
                    <p><i class="fas fa-user"></i> 👤 <?= htmlspecialchars($userData->getUserById($property_data['user_id'])['name'] ?? 'Unknown'); ?> (<?= htmlspecialchars($userData->getUserById($property_data['user_id'])['email'] ?? 'Unknown'); ?>)</p>
                    <p><i class="fas fa-calendar"></i> 📅 <?= htmlspecialchars($property_data['date']); ?></p>
                    <a href="view_property.php?get_id=<?= $property_id ?>" class="btn">View Full Details</a>
                </div>
                <div class="box">
                    <h3 class="name">Suspicion Details</h3>
                    <?php if ($suspicion_data): ?>
                        <p><i class="fas fa-exclamation-triangle"></i> ⚠️ Score: <?= htmlspecialchars($suspicion_data['suspicion_score']); ?>/100</p>
                        <p><i class="fas fa-clock"></i> ⏰ Flagged: <?= htmlspecialchars($suspicion_data['flagged_at']); ?></p>
                        <p><i class="fas fa-info-circle"></i> ℹ️ Status: <?= htmlspecialchars($suspicion_data['admin_review_status'] ?? 'pending'); ?></p>
                    <?php else: ?>
                        <p class="empty">No suspicion data available. 🚫</p>
                    <?php endif; ?>
                </div>
                <div class="box">
                    <h3 class="name">User Reports</h3>
                    <?php if ($user_reports): ?>
                        <?php foreach ($user_reports as $report): ?>
                            <p><i class="fas fa-file-alt"></i> 📝 <?= htmlspecialchars($report['created_at']); ?> - <?= htmlspecialchars($report['report_reason']); ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty">No user reports found. 🚫</p>
                    <?php endif; ?>
                </div>
                <div class="box">
                    <h3 class="name">Admin Review</h3>
                    <form action="" method="POST" class="admin-review-form" onsubmit="return confirmReview()">
                        <p>
                            <label for="review_status">Review Status:</label>
                            <select name="review_status" id="review_status" required onchange="updateSelectStyle()">
                                <option value="pending" <?= ($suspicion_data['admin_review_status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?= ($suspicion_data['admin_review_status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?= ($suspicion_data['admin_review_status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </p>
                        <p>
                            <label for="admin_comment">Admin Comment (optional):</label>
                            <textarea name="admin_comment" id="admin_comment" placeholder="Provide reasons for your decision (sent to user via Telegram)"></textarea>
                        </p>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="delete_property" value="yes"> Delete Property (if rejected)</label>
                        </div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="notify_user" value="yes" checked> Notify User via Telegram (if rejected)</label>
                        </div>
                        <input type="submit" name="update_review" value="Update Review" class="btn">
                    </form>
                </div>
            </div>
        <?php else: ?>
            <h1 class="heading">Suspicious Listings</h1>
            <form action="" method="POST" class="search-form">
                <input type="text" name="search_box" placeholder="Search suspicious listings..." maxlength="100" required>
                <button type="submit" class="fas fa-search" name="search_btn"></button>
            </form>
            <div class="box-container">
                <?php
                $search = $_POST['search_box'] ?? '';
                $properties = $propertyData->getSuspiciousProperties($search);
                if ($properties) {
                    foreach ($properties as $property) {
                        $listing_id = $property['id'];
                        $user = $userData->getUserById($property['user_id']);
                        $total_images = 1;
                        $total_images += !empty($property['image_02']) ? 1 : 0;
                        $total_images += !empty($property['image_03']) ? 1 : 0;
                        $total_images += !empty($property['image_04']) ? 1 : 0;
                        $total_images += !empty($property['image_05']) ? 1 : 0;
                        ?>
                        <div class="box">
                            <div class="thumb">
                                <p><i class="far fa-image"></i><span><?= htmlspecialchars($total_images); ?></span></p>
                                <img src="../../../uploaded_files/<?= htmlspecialchars($property['image_01']); ?>" alt="Property image">
                            </div>
                            <p class="price"><i class="fas fa-euro-sign"></i> 💶 <?= htmlspecialchars($property['price']); ?></p>
                            <h3 class="name"><?= htmlspecialchars($property['property_name']); ?></h3>
                            <p class="location"><i class="fas fa-map-marker-alt"></i> 📍 <?= htmlspecialchars($property['address']); ?></p>
                            <p class="suspicious"><i class="fas fa-exclamation-triangle"></i> ⚠️ Suspicious (Score: <?= htmlspecialchars($property['suspicion_score']); ?>/100, Status: <?= htmlspecialchars($project['admin_review_status'] ?? 'pending'); ?>)</p>
                            <form action="" method="POST">
                                <input type="hidden" name="delete_id" value="<?= htmlspecialchars($listing_id); ?>">
                                <a href="view_property.php?get_id=<?= $listing_id ?>" class="btn">View Property</a>
                                <a href="?property_id=<?= $listing_id ?>" class="option-btn">Review</a>
                                <input type="submit" value="Delete Listing" onclick="return confirm('Delete this listing?');" name="delete" class="delete-btn">
                            </form>
                        </div>
                        <?php
                    }
                } else {
                    echo '<p class="empty">No suspicious listings found! 🚫</p>';
                }
                ?>
            </div>
        <?php endif; ?>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script>
        function confirmReview() {
            const reviewStatus = document.getElementById('review_status').value;
            const deleteProperty = document.querySelector('input[name="delete_property"]').checked;
            let message = `Are you sure you want to set the review status to "${reviewStatus}"?`;
            if (reviewStatus === 'rejected' && deleteProperty) {
                message += ' The property will be deleted. 🗑️';
            }
            return confirm(message);
        }
        function updateSelectStyle() {
            const select = document.getElementById('review_status');
            select.classList.remove('rejected');
            if (select.value === 'rejected') {
                select.classList.add('rejected');
            }
        }
    </script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>