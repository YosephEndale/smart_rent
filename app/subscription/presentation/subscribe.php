<?php
namespace App\Subscription\Presentation;
use App\Subscription\Logic\SubscriptionLogic;
use Exception;

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); // Resolves to C:\Users\yosep\OneDrive\Desktop\smart_rent
    error_log("ROOT_DIR defined as: " . ROOT_DIR);
}

// Include Composer autoloader
require_once ROOT_DIR . '/vendor/autoload.php';

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start error in subscribe.php: " . $e->getMessage());
    }
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
if (empty($user_id)) {
    header('Location: /app/auth/presentation/login.php');
    exit;
}

try {
    $subscriptionLogic = new SubscriptionLogic();
    if ($subscriptionLogic->getUserSubscriptionStatus($user_id)) {
        header('Location: /app/subscription/presentation/manage_plan.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error instantiating SubscriptionLogic in subscribe.php: " . $e->getMessage());
    $_SESSION['error_msg'] = "An error occurred. Please try again later.";
    header('Location: /index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade'])) {
    $result = $subscriptionLogic->upgradePlan($user_id);
    if (is_array($result) && isset($result['message'])) {
        $message = is_string($result['message']) ? $result['message'] : 'Invalid response from upgrade process.';
        $_SESSION['success_msg'] = $message;
        if ($result['success']) {
            $_SESSION['is_premium'] = true;
            header('Location: /app/subscription/presentation/manage_plan.php');
            exit;
        } else {
            $_SESSION['error_msg'] = $message;
        }
    } else {
        $_SESSION['error_msg'] = "An unexpected error occurred. Please try again.";
        error_log("Invalid upgradePlan result: " . print_r($result, true));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade to Premium - Smart Rent</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <!-- Include SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php require_once ROOT_DIR . '/components/user_header.php'; ?>

    <section class="form-container">
        <h1 class="heading">Upgrade to Premium</h1>
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
        <form action="" method="post" class="box">
            <h3>Premium Plan Benefits</h3>
            <ul>
                <li><i class="fas fa-check"></i> Access to Tenant Screening Features (for property posters)</li>
                <li><i class="fas fa-check"></i> Set Screening Preferences for Your Properties</li>
                <li><i class="fas fa-check"></i> View Tenant Answers and Scores</li>
                <li><i class="fas fa-check"></i> Priority Support</li>
                <li><i class="fas fa-check"></i> Unlimited Property Postings</li>
            </ul>
            <p><strong>Price:</strong> Check pricing details at <a href="#">comming-soon</a></p>
            <p>Unlock premium features by upgrading your account.</p>
            <button type="submit" name="upgrade" class="btn">Upgrade to Premium</button>
        </form>
    </section>

    <?php require_once ROOT_DIR . '/components/footer.php'; ?>
    <script src="/public/js/script.js"></script>
    
</body>
</html>
<?php ob_end_flush(); ?>