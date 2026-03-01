<?php
namespace App\User\Presentation;
use Exception;
use App\User\Logic\UserLogic;

// Start output buffering
ob_start();

// Define ROOT_DIR
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); 
    error_log("ROOT_DIR defined as: " . ROOT_DIR);
}

$autoloadPath = ROOT_DIR . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Fatal Error: vendor/autoload.php not found at $autoloadPath. Please run 'composer install' in the project root.");
}

require_once $autoloadPath;
require_once ROOT_DIR . '/components/connect.php';

// Start session only if not already active (should be handled by user_header.php)
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start error in dashboard.php: " . $e->getMessage());
    }
}

// Check user session and redirect if not logged in
$user_id = $_SESSION['user_id'] ?? '';
if (!$user_id) {
    header('Location: /app/auth/presentation/login.php');
    exit;
}

require_once ROOT_DIR . '/components/message.php';

$userLogic = new UserLogic(get_db_connection());
$warning_msg = $_SESSION['warning_msg'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? [];
$_SESSION['warning_msg'] = [];
$_SESSION['success_msg'] = [];

$data = $userLogic->getDashboardData($user_id);
$profile = $data['profile'];
$properties = $data['properties'];
$total_properties = $data['total_properties'];
$total_chats = $data['total_chats'];
$total_saved_properties = $data['total_saved_properties'];
$applicant_counts = $data['applicant_counts'];

$selected_property_id = filter_var($_GET['view_applicants'] ?? '', FILTER_VALIDATE_INT);
$selected_property = null;
$applicants = [];

if ($selected_property_id) {
    $result = $userLogic->getApplicants($user_id, $selected_property_id);
    if (isset($result['error'])) {
        $warning_msg[] = $result['error'];
        header('Location: /app/user/presentation/dashboard.php');
        exit;
    }
    $selected_property = $result['property'];
    $applicants = $result['applicants'];
}

// Handle chat initiation from applicant list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_chat']) && $user_id) {
    $property_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
    $other_user_id = filter_var($_POST['other_user_id'] ?? '', FILTER_VALIDATE_INT);
    
    if ($property_id && $other_user_id && $other_user_id != $user_id) {
        $conn = get_db_connection();
        // Log user activity
        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, property_id, action, created_at) VALUES (?, ?, 'chat', NOW())");
        $stmt->execute([$user_id, $property_id]);
        error_log("dashboard.php: Initiating chat for property_id=$property_id, other_user_id=$other_user_id");
        $success_msg[] = 'Chat opened successfully!';
        header("Location: /app/chat/presentation/chat.php?property_id=$property_id&other_user_id=$other_user_id");
        exit;
    } else {
        error_log("dashboard.php: Invalid chat initiation attempt for property_id=$property_id, other_user_id=$other_user_id");
        $warning_msg[] = 'Invalid chat request';
        header('Location: /app/user/presentation/dashboard.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard 🌟</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/chatbot.css">
    <style>
        .applicant-details { display: none; margin-top: 10px; padding: 10px; border: 1px solid #ddd; }
        .toggle-details { cursor: pointer; color: #007bff; }
        .criteria-breakdown, .answers { margin-left: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .property-list .box-container { display: flex; flex-wrap: wrap; gap: 15px; }
        .property-list .box { flex: 1 1 200px; padding: 20px; text-align: center; border: 1px solid #ddd; border-radius: 5px; }
        .property-list .box h3 { margin: 0 0 10px; font-size: 18px; }
        .property-list .box p { margin: 0 0 15px; font-size: 14px; }
        .chat-btn { cursor: pointer; background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; }
        .chat-btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="dashboard">
    <h1 class="heading">Welcome 🏡✨</h1>
    <div class="box-container">
        <div class="box">
            <h3>Welcome Home! 😊</h3>
            <p><?= htmlspecialchars($profile['name'] ?? '') ?> 🌼</p>
            <a href="/app/user/presentation/update.php" class="btn">Update Your Profile</a>
        </div>
        <div class="box">
            <h3>Find Your Dream Home!</h3>
            <p>Explore Your Perfect Property!</p>
            <a href="/app/property/presentation/search.php" class="btn">Start Searching! 🚀</a>
        </div>
        <div class="box">
            <h3><?= $total_properties; ?> 🏠</h3>
            <p>Your Listed Properties</p>
            <a href="/app/user/presentation/my_listings.php" class="btn">See My Listings! �0</a>
        </div>
        <div class="box">
            <h3><?= $total_chats; ?> 💬</h3>
            <p>Your Active Chats</p>
            <a href="/app/chat/presentation/chat.php" class="btn">Check Messages! 📬</a>
        </div>
        <div class="box">
            <h3><?= $total_saved_properties; ?> ❤️</h3>
            <p>Your Saved Properties</p>
            <a href="/app/user/presentation/saved.php" class="btn">View Favorites! 🌟</a>
        </div>
    </div>

    <div class="property-list">
        <h2 class="heading">Your Properties & Applicants 📋</h2>
        <?php if (empty($properties)): ?>
            <p>You have no listed properties. <a href="/app/property/presentation/post_property.php">Add one now!</a></p>
        <?php else: ?>
            <div class="box-container">
                <?php foreach ($properties as $property): ?>
                    <div class="box">
                        <h3><?= htmlspecialchars($property['property_name']); ?></h3>
                        <p>Applicants: <?= $applicant_counts[$property['id']] ?? 0; ?></p>
                        <a href="/app/user/presentation/dashboard.php?view_applicants=<?= urlencode($property['id']); ?>" class="btn">View Applicants</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selected_property): ?>
        <div class="applicant-list">
            <h2 class="heading">Applicants for <?= htmlspecialchars($selected_property['property_name']); ?></h2>
            <?php if (empty($applicants)): ?>
                <p>No applicants have applied for this property yet.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Tenant Name</th>
                            <th>Score</th>
                            <th>Details</th>
                            <th>Chat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applicants as $index => $applicant): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= htmlspecialchars($applicant['name']); ?></td>
                                <td><?= $applicant['score']; ?>/100</td>
                                <td>
                                    <span class="toggle-details" onclick="toggleDetails('details-<?= $applicant['user_id']; ?>')">View Details</span>
                                    <div id="details-<?= $applicant['user_id']; ?>" class="applicant-details">
                                        <p><strong>Scoring Explanation:</strong> <?= htmlspecialchars($applicant['explanation']); ?></p>
                                        <p><strong>Criteria Breakdown:</strong></p>
                                        <ul class="criteria-breakdown">
                                            <?php foreach ($applicant['criteria_breakdown'] as $criterion => $points): ?>
                                                <li><?= htmlspecialchars($criterion); ?>: <?= $points; ?> points</li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p><strong>Answers:</strong></p>
                                        <ul class="answers">
                                            <?php foreach ($applicant['answers'] as $answer): ?>
                                                <li>
                                                    <strong>Q:</strong> <?= htmlspecialchars($answer['question_text']); ?><br>
                                                    <strong>A:</strong> <?= htmlspecialchars($answer['answer_text']); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user_id != $applicant['user_id']): ?>
                                        <form action="" method="POST" style="display: inline;">
                                            <input type="hidden" name="property_id" value="<?= htmlspecialchars($selected_property['id']); ?>">
                                            <input type="hidden" name="other_user_id" value="<?= htmlspecialchars($applicant['user_id']); ?>">
                                            <button type="submit" name="start_chat" class="chat-btn">💬 Chat</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div class="flex">
                <a href="/app/user/presentation/dashboard.php" class="btn">Back to Dashboard</a>
            </div>
        </div>
    <?php endif; ?>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>

<script>
function toggleDetails(id) {
    const element = document.getElementById(id);
    element.style.display = element.style.display === 'block' ? 'none' : 'block';
}
</script>

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
<?php ob_end_flush(); ?>