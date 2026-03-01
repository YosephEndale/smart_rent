<?php
ob_start(); // Start output buffering to prevent headers-already-sent errors
session_start();

// Define ROOT_DIR if not already defined (fallback)
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); // Resolves to C:\Users\yosep\OneDrive\Desktop\smart_rent
}

// Load configuration first
$config = require ROOT_DIR . '/config/env.php';

// Load dependencies
require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/screening/logic/ScreeningLogic.php';
require_once ROOT_DIR . '/app/notifications/logic/SendNotification.php';

// Debug: Verify file paths
if (!file_exists(ROOT_DIR . '/vendor/autoload.php')) {
    error_log("review_questions.php: Autoloader not found at: " . ROOT_DIR . "/vendor/autoload.php");
    die("Configuration error: Composer autoloader not found. Run 'composer install' or 'composer update'.");
}
if (!file_exists(ROOT_DIR . '/app/screening/logic/ScreeningLogic.php')) {
    error_log("review_questions.php: ScreeningLogic not found at: " . ROOT_DIR . "/app/screening/logic/ScreeningLogic.php");
    die("Configuration error: ScreeningLogic.php not found.");
}
if (!file_exists(ROOT_DIR . '/app/notifications/logic/SendNotification.php')) {
    error_log("review_questions.php: SendNotification not found at: " . ROOT_DIR . "/app/notifications/logic/SendNotification.php");
    die("Configuration error: SendNotification.php not found.");
}

use App\Screening\Logic\ScreeningLogic;
use App\Notifications\Logic\SendNotification;

$conn = get_db_connection();
$sendNotification = new SendNotification($conn);
$screeningMgr = new ScreeningLogic($conn, $config, $sendNotification);

$user_id = $_SESSION['user_id'] ?? '';
if (empty($user_id)) {
    error_log("review_questions.php: No user_id in session, redirecting to /index.php");
    header('Location: /index.php');
    exit();
}

$property_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$warning_msg = $_SESSION['warning_msg'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? [];
$_SESSION['warning_msg'] = [];
$_SESSION['success_msg'] = [];

$validation = $screeningMgr->validatePropertyId($property_id, $user_id);
if (!$validation['success']) {
    error_log("review_questions.php: Invalid property_id=$property_id for user_id=$user_id, redirecting to /index.php");
    $_SESSION['warning_msg'] = [$validation['message']];
    header('Location: /index.php');
    exit();
}

$property_name = $validation['property']['property_name'];
$result = $screeningMgr->handleReviewQuestions($property_id, $user_id, $_POST);
$questions = $result['questions'] ?? [];
$success_msg = array_merge($success_msg, $result['success_msg'] ?? []);
$warning_msg = array_merge($warning_msg, $result['warning_msg'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_questions'])) {
    error_log("review_questions.php: Confirm questions submitted for property_id=$property_id, user_id=$user_id, redirecting to /index.php");
    header('Location: /index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Screening Questions</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .alert { background: #f8d7da; padding: 10px; margin-bottom: 10px; border: 1px solid; }
        .alert-success { background: #d4edda; border: 1px solid #28a745; }
        .box { margin-bottom: 15px; }
        .input { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { padding: 10px; margin: 5px; }
    </style>
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
        <h3>Review Screening Questions for <?php echo htmlspecialchars($property_name); ?></h3>
        <?php if (empty($questions)): ?>
            <p>No questions generated. Please add one below.</p>
        <?php else: ?>
            <?php foreach ($questions as $question): ?>
                <div class="box">
                    <p>Question</p>
                    <input type="text" name="questions[<?php echo $question['id']; ?>]" value="<?php echo htmlspecialchars($question['question_text']); ?>"
                           class="input" maxlength="500" required>
                    <label><input type="checkbox" name="delete_questions[]" value="<?php echo $question['id']; ?>"> Delete</label>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="box">
            <p>Add New Question (Optional)</p>
            <input type="text" name="new_question" class="input" maxlength="500" placeholder="Enter a new question">
        </div>
        <div class="flex">
            <input type="submit" value="Save Changes" class="btn" name="save_questions">
            <input type="submit" value="Confirm Questions" class="btn" name="confirm_questions">
        </div>
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
<?php ob_end_flush(); // End output buffering ?>