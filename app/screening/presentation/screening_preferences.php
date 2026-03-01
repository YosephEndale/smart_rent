<?php
ob_start(); // Start output buffering to prevent headers-already-sent errors
session_start();

// Define ROOT_DIR if not already defined
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); // Resolves to C:\Users\yosep\OneDrive\Desktop\smart_rent
}

// Load Composer autoloader and configuration
require_once ROOT_DIR . '/vendor/autoload.php';
$config = require ROOT_DIR . '/config/env.php';

// Debug: Verify file paths
if (!file_exists(ROOT_DIR . '/vendor/autoload.php')) {
    error_log("Autoloader not found at: " . ROOT_DIR . "/vendor/autoload.php");
    die("Configuration error: Composer autoloader not found. Run 'composer install' or 'composer update'.");
}
if (!file_exists(ROOT_DIR . '/app/screening/logic/ScreeningLogic.php')) {
    error_log("ScreeningLogic not found at: " . ROOT_DIR . "/app/screening/logic/ScreeningLogic.php");
    die("Configuration error: ScreeningLogic.php not found.");
}
if (!file_exists(ROOT_DIR . '/app/notifications/logic/SendNotification.php')) {
    error_log("SendNotification not found at: " . ROOT_DIR . "/app/notifications/logic/SendNotification.php");
    die("Configuration error: SendNotification.php not found.");
}

require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/screening/logic/ScreeningLogic.php';
require_once ROOT_DIR . '/app/notifications/logic/SendNotification.php';

use App\Screening\Logic\ScreeningLogic;
use App\Notifications\Logic\SendNotification;

$conn = get_db_connection();
$sendNotification = new SendNotification($conn);
$screeningLogic = new ScreeningLogic($conn, $config, $sendNotification);

$user_id = $_SESSION['user_id'] ?? '';
if (empty($user_id)) {
    error_log("screening_preferences.php: No user_id in session, redirecting to login");
    header('Location: /app/auth/presentation/login.php');
    exit();
}

$property_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$warning_msg = $_SESSION['warning_msg'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? [];
$_SESSION['warning_msg'] = [];
$_SESSION['success_msg'] = [];

// Handle success message from post_property.php redirect
if (isset($_GET['success']) && $_GET['success'] === 'property_posted') {
    $success_msg[] = "Your property has been posted successfully! Set your screening preferences below or skip.";
}

$validation = $screeningLogic->validatePropertyId($property_id, $user_id);
if (!$validation['success']) {
    error_log("screening_preferences.php: Property validation failed for property_id=$property_id, user_id=$user_id: " . $validation['message']);
    $_SESSION['warning_msg'] = [$validation['message']];
    header('Location: /index.php');
    exit();
}

$property_name = $validation['property']['property_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_preferences'])) {
        $result = $screeningLogic->handleScreeningPreferences($property_id, $user_id, $_POST);
        if ($result['success']) {
            $_SESSION['success_msg'] = $result['success_msg'];
            header('Location: review_questions.php?id=' . urlencode($property_id));
            exit();
        } else {
            $_SESSION['warning_msg'] = $result['warning_msg'];
        }
    } elseif (isset($_POST['skip_preferences'])) {
        error_log("screening_preferences.php: Skip preferences clicked for property_id=$property_id, user_id=$user_id");
        $_SESSION['success_msg'] = ['Screening preferences skipped successfully.'];
        header('Location: /index.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Tenant Screening Preferences</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="property-form">
    <?php if (!empty($warning_msg)): ?>
        <div class="alert">
            <?php foreach ($warning_msg as $msg): ?>
                <p><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <?php foreach ($success_msg as $msg): ?>
                <p><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form action="" method="POST">
        <h3>Tenant Screening Preferences for <?php echo htmlspecialchars($property_name); ?></h3>
        <div class="box">
            <p>Tenant Preferences <span>*</span></p>
            <textarea name="tenant_preferences" maxlength="1000" class="input" cols="30" rows="10" placeholder="Describe your ideal tenant (e.g., no pets, minimum income $3000/month, non-smoker)"><?php echo htmlspecialchars($_POST['tenant_preferences'] ?? ''); ?></textarea>
        </div>
        <div class="box">
            <p>Minimum Credit Score</p>
            <input type="number" name="min_credit_score" min="300" max="850" placeholder="Enter minimum credit score (300-850)" class="input" value="<?php echo htmlspecialchars($_POST['min_credit_score'] ?? ''); ?>">
        </div>
        <div class="box">
            <p>Minimum Monthly Income ($)</p>
            <input type="number" name="min_income" min="0" step="0.01" placeholder="Enter minimum monthly income" class="input" value="<?php echo htmlspecialchars($_POST['min_income'] ?? ''); ?>">
        </div>
        <div class="box">
            <p>Other Requirements</p>
            <textarea name="other_requirements" maxlength="1000" class="input" cols="30" rows="5" placeholder="Any additional requirements (e.g., no criminal record, references required)"><?php echo htmlspecialchars($_POST['other_requirements'] ?? ''); ?></textarea>
        </div>
        <input type="submit" name="submit_preferences" value="Save Preferences" class="btn">
        <input type="submit" name="skip_preferences" value="Skip Preferences" class="btn">
    </form>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php if (!empty($success_msg)): ?>
<script>
    swal({
        title: "Success!",
        text: "<?php echo htmlspecialchars($success_msg[0]); ?>",
        icon: "success",
        button: "OK"
    });
</script>
<?php endif; ?>
<?php if (!empty($warning_msg)): ?>
<script>
    swal({
        title: "Warning!",
        text: "<?php echo htmlspecialchars(implode('\n', $warning_msg)); ?>",
        icon: "warning",
        button: "OK"
    });
</script>
<?php endif; ?>

<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>
<?php ob_end_flush();  ?>