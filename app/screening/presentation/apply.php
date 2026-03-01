<?php
session_start();

// Include Composer autoloader
require_once __DIR__ . '/../../../vendor/autoload.php';

// Define ROOT_DIR if not already defined (fallback)
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); 
}

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
if (!file_exists(ROOT_DIR . '/app/screening/data/ScreeningData.php')) {
    error_log("ScreeningData not found at: " . ROOT_DIR . "/app/screening/data/ScreeningData.php");
    die("Configuration error: ScreeningData.php not found.");
}

require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/screening/logic/ScreeningLogic.php';
require_once ROOT_DIR . '/app/screening/data/ScreeningData.php';
require_once ROOT_DIR . '/app/notifications/logic/SendNotification.php';

use App\Screening\Logic\ScreeningLogic;
use App\Screening\Data\ScreeningData;
use App\Notifications\Logic\SendNotification;

$conn = get_db_connection();
$sendNotification = new SendNotification($conn);
$screeningLogic = new ScreeningLogic($conn, $_ENV, $sendNotification); // Use $_ENV instead of $config
$dataLayer = new ScreeningData($conn);

$user_id = $_SESSION['user_id'] ?? '';
if (empty($user_id)) {
    error_log("apply.php: No user_id in session, redirecting to login");
    $_SESSION['warning_msg'] = ['Please log in to apply for this property.'];
    header('Location: /app/auth/presentation/login.php');
    exit();
}

$property_id = filter_var($_GET['property_id'] ?? '', FILTER_VALIDATE_INT);
$warning_msg = $_SESSION['warning_msg'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? [];
$_SESSION['warning_msg'] = [];
$_SESSION['success_msg'] = [];

if (!$property_id) {
    error_log("apply.php: Missing property_id=$property_id");
    $_SESSION['warning_msg'] = ['No property ID provided.'];
    header('Location: /index.php');
    exit();
}

// Verify the property exists
$property = $dataLayer->getPropertyDetails($property_id);
error_log("apply.php: getPropertyDetails($property_id) returned: " . json_encode($property));
if (!$property) {
    error_log("apply.php: Property not found for property_id=$property_id");
    $_SESSION['warning_msg'] = ['Property not found.'];
    header('Location: /index.php');
    exit();
}

// Fetch the property owner's user_id
$owner_stmt = $conn->prepare("SELECT user_id FROM property WHERE id = ?");
$owner_stmt->execute([$property_id]);
$owner_id = $owner_stmt->fetchColumn();
error_log("apply.php: Fetched owner_id=$owner_id for property_id=$property_id");

if ($owner_id === false) {
    error_log("apply.php: No owner found for property_id=$property_id");
    $_SESSION['warning_msg'] = ['Property owner not found.'];
    header('Location: /index.php');
    exit();
}

// Check if the user is trying to apply to their own property
if ($user_id == $owner_id) {
    error_log("apply.php: User attempted to apply to own property, user_id=$user_id, property_id=$property_id");
    $_SESSION['warning_msg'] = ['You cannot apply to chat for your own property.'];
    header('Location: /index.php');
    exit();
}

// Check if the user has already applied
if ($dataLayer->hasApplied($property_id, $user_id)) {
    error_log("apply.php: User already applied for property_id=$property_id, user_id=$user_id");
    $_SESSION['warning_msg'] = ['You have already applied for this property.'];
    header('Location: /index.php');
    exit();
}

$questions = $dataLayer->getScreeningQuestions($property_id);
error_log("apply.php: getScreeningQuestions($property_id) returned: " . json_encode($questions));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $result = $screeningLogic->handleApply($property_id, $user_id, $_POST);
    error_log("apply.php: handleApply result: " . json_encode($result));
    if ($result['success']) {
        $_SESSION['success_msg'] = $result['success_msg'];
        header("Location: /index.php");
        exit();
    } else {
        $_SESSION['warning_msg'] = $result['warning_msg'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Property</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="property-form">
    <form action="" method="POST">
        <h3>Apply for <?php echo htmlspecialchars($property['property_name']); ?></h3>
        <div class="box">
            <p>Property Address</p>
            <p class="input"><?php echo htmlspecialchars($property['address']); ?></p>
        </div>
        <?php if (empty($questions)): ?>
            <p>No screening questions available for this property.</p>
            <input type="submit" value="Submit Application" class="btn" name="submit_application">
        <?php else: ?>
            <?php foreach ($questions as $question): ?>
                <div class="box">
                    <p><?php echo htmlspecialchars($question['question_text']); ?> <span>*</span></p>
                    <textarea name="answers[<?php echo $question['id']; ?>]" maxlength="1000" class="input" required cols="30" rows="5"
                             placeholder="Enter your answer"></textarea>
                </div>
            <?php endforeach; ?>
            <input type="submit" value="Submit Application" class="btn" name="submit_application">
        <?php endif; ?>
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