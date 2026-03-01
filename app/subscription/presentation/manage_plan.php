<?php
namespace App\Subscription\Presentation;
use App\Subscription\Logic\SubscriptionLogic;
use Exception;

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); 
    error_log("ROOT_DIR defined as: " . ROOT_DIR);
}

// Include Composer autoloader
require_once ROOT_DIR . '/vendor/autoload.php';

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start error in manage_plan.php: " . $e->getMessage());
    }
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
if (empty($user_id)) {
    header('Location: /app/auth/presentation/login.php');
    exit;
}

try {
    $subscriptionLogic = new SubscriptionLogic();
    if (!$subscriptionLogic->getUserSubscriptionStatus($user_id)) {
        header('Location: /app/subscription/presentation/subscribe.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error instantiating SubscriptionLogic in manage_plan.php: " . $e->getMessage());
    $_SESSION['error_msg'] = "An error occurred. Please try again later.";
    header('Location: /index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['downgrade'])) {
    $result = $subscriptionLogic->downgradePlan($user_id);
    if (is_array($result) && isset($result['message'])) {
        $message = is_string($result['message']) ? $result['message'] : 'Invalid response from downgrade process.';
        $_SESSION['success_msg'] = $message;
        if ($result['success']) {
            $_SESSION['is_premium'] = false;
            header('Location: /app/subscription/presentation/subscribe.php');
            exit;
        } else {
            $_SESSION['error_msg'] = $message;
        }
    } else {
        $_SESSION['error_msg'] = "An unexpected error occurred. Please try again.";
        error_log("Invalid downgradePlan result: " . print_r($result, true));
    }
}

$can_access_screening = $subscriptionLogic->canAccessTenantScreening($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Your Plan - Smart Rent</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <!-- Include SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php require_once ROOT_DIR . '/components/user_header.php'; ?>

    <section class="form-container">
        <h1 class="heading">Manage Your Premium Plan</h1>
        <?php if (isset($_SESSION['success_msg'])): ?>
            <p class="empty" style="background-color: var(--main-color); color: var(--white);"
               id="success-alert">
                <?php echo is_string($_SESSION['success_msg']) ? htmlspecialchars($_SESSION['success_msg']) : htmlspecialchars('An unexpected error occurred with the success message.'); ?>
                <?php unset($_SESSION['success_msg']); ?>
            </p>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_msg'])): ?>
            <p class="empty" id="error-alert">
                <?php echo is_string($_SESSION['error_msg']) ? htmlspecialchars($_SESSION['error_msg']) : htmlspecialchars('An unexpected error occurred.'); ?>
                <?php unset($_SESSION['error_msg']); ?>
            </p>
        <?php endif; ?>
        <div class="box">
            <h3>Your Premium Plan</h3>
            <p><strong>Status:</strong> Active</p>
            <p><strong>Benefits:</strong></p>
            <ul>
                <li><i class="fas fa-check"></i> Access to Tenant Screening Features (for property posters)</li>
                <li><i class="fas fa-check"></i> Set Screening Preferences for Your Properties</li>
                <li><i class="fas fa-check"></i> View Tenant Answers and Scores</li>
                <li><i class="fas fa-check"></i> Priority Support</li>
                <li><i class="fas fa-check"></i> Unlimited Property Postings</li>
            </ul>
            <p><strong>Price:</strong> Check pricing details at <a href="#">coming-soon</a></p>
        </div>
        <form action="" method="post" class="box">
            <h3>Downgrade Plan</h3>
            <p>Downgrading to the Free Plan will remove access to premium features<?php echo $can_access_screening ? ", including tenant screening." : "."; ?></p>
            <button type="submit" name="downgrade" class="btn" onclick="return confirm('Are you sure you want to downgrade to the Free Plan?');">Downgrade to Free</button>
        </form>
    </section>

    <?php require_once ROOT_DIR . '/components/footer.php'; ?>
    <script src="/public/js/script.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>