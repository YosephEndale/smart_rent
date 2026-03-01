<?php
namespace App\User\Presentation;

use App\User\Logic\UserLogic;

// Define ROOT_DIR if not already defined
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); // Resolves to C:\Users\yosep\OneDrive\Desktop\smart_rent
}

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/components/message.php';


session_start();
$user_id = $_SESSION['user_id'] ?? '';
if (!$user_id) {
    header('Location: /app/auth/presentation/login.php');
    exit;
}

$userLogic = new UserLogic(get_db_connection());
$warning_msg = [];
$success_msg = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $property_id = filter_var($_POST['property_id'], FILTER_VALIDATE_INT);
    if ($property_id) {
        $result = $userLogic->deleteProperty($user_id, $property_id);
        if (isset($result['error'])) {
            $warning_msg[] = $result['error'];
        } else {
            $success_msg[] = $result['success'];
        }
    } else {
        $warning_msg[] = 'Invalid property ID for deletion';
    }
}

$selected_property_id = filter_var($_GET['view_applicants'] ?? '', FILTER_VALIDATE_INT);
$selected_property = null;
$applicants = [];

if ($selected_property_id) {
    $result = $userLogic->getApplicants($user_id, $selected_property_id);
    if (isset($result['error'])) {
        $warning_msg[] = $result['error'];
        header('Location: /app/user/presentation/my_listings.php');
        exit;
    }
    $selected_property = $result['property'];
    $applicants = $result['applicants'];
}

$properties = $userLogic->getDashboardData($user_id)['properties'];
$applicant_counts = $userLogic->getDashboardData($user_id)['applicant_counts'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Lovely Listings! 🏡✨</title>
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
        .flex-btn { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin: 10px 0; }
        .flex-btn .btn { flex: 1 1 auto; min-width: 100px; text-align: center; }
    </style>
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="my-listings">
    <h1 class="heading">Your Lovely Listings! 🏡✨</h1>
    <div class="box-container">
    <?php if (empty($properties)): ?>
        <p class="empty">No homes listed yet! 😔 <a href="/app/property/presentation/post_property.php" class="btn">Add a Cozy Home! 🏠</a></p>
    <?php else: ?>
        <?php foreach ($properties as $fetch_property):
            $image_count = 1;
            for ($i = 2; $i <= 5; $i++) {
                if (!empty($fetch_property["image_0$i"])) {
                    $image_count++;
                }
            }
            // Provide defaults for missing fields
            $image_01 = $fetch_property['image_01'] ?? 'default.jpg';
            $price = $fetch_property['price'] ?? 0.00;
            $address = $fetch_property['address'] ?? 'Unknown location';
            $property_name = $fetch_property['property_name'] ?? 'Unnamed Property';
            // Verify image exists, fallback to default if not
            $image_path = ROOT_DIR . '/uploaded_files/' . $image_01;
            $image_url = file_exists($image_path) ? '/uploaded_files/' . htmlspecialchars($image_01) : '/public/images/default.jpg';
        ?>
        <form method="POST" class="box">
            <input type="hidden" name="property_id" value="<?= htmlspecialchars($fetch_property['id'] ?? ''); ?>">
            <div class="thumb">
                <p><i class="far fa-image"></i><span><?= htmlspecialchars($image_count); ?> 📸</span></p>
                <img src="<?= $image_url; ?>" alt="<?= htmlspecialchars($property_name); ?>">
            </div>
            <div class="price"><span>€</span><?= number_format((float)$price, 2); ?></div>
            <h3 class="name"><?= htmlspecialchars($property_name); ?> 🏠</h3>
            <p class="location"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($address); ?> 📍</span></p>
            <p class="applicants"><i class="fas fa-users"></i><span>Applicants: <?= htmlspecialchars($applicant_counts[$fetch_property['id']] ?? 0); ?> 👥</span></p>
            <div class="flex-btn">
                <a href="/app/property/presentation/update_property.php?get_id=<?= urlencode($fetch_property['id'] ?? ''); ?>" class="btn">Update 🔧</a>
                <input type="submit" name="delete" value="Delete 😢" class="btn" onclick="return confirm('Remove this lovely listing? 🥺');">
                <a href="/app/user/presentation/my_listings.php?view_applicants=<?= urlencode($fetch_property['id'] ?? ''); ?>" class="btn">View Applicants 👥</a>
                <a href="/app/screening/presentation/review_questions.php?id=<?= urlencode($fetch_property['id'] ?? ''); ?>" class="btn">Review Questions ❓</a>
            </div>
            <a href="/app/property/presentation/view_property.php?get_id=<?= urlencode($fetch_property['id'] ?? ''); ?>" class="btn">Explore Your Home! 🌟</a>
        </form>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <?php if ($selected_property): ?>
        <div class="applicant-list">
            <h2 class="heading">Applicants for <?= htmlspecialchars($selected_property['property_name'] ?? 'Unnamed Property'); ?> 👥</h2>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applicants as $index => $applicant): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= htmlspecialchars($applicant['name'] ?? 'Unknown'); ?></td>
                                <td><?= htmlspecialchars($applicant['score'] ?? 0); ?>/100</td>
                                <td>
                                    <span class="toggle-details" onclick="toggleDetails('details-<?= htmlspecialchars($applicant['user_id'] ?? ''); ?>')">View Details</span>
                                    <div id="details-<?= htmlspecialchars($applicant['user_id'] ?? ''); ?>" class="applicant-details">
                                        <p><strong>Scoring Explanation:</strong> <?= htmlspecialchars($applicant['explanation'] ?? 'No explanation available'); ?></p>
                                        <p><strong>Criteria Breakdown:</strong></p>
                                        <ul class="criteria-breakdown">
                                            <?php foreach ($applicant['criteria_breakdown'] ?? [] as $criterion => $points): ?>
                                                <li><?= htmlspecialchars($criterion); ?>: <?= htmlspecialchars($points); ?> points</li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p><strong>Answers:</strong></p>
                                        <ul class="answers">
                                            <?php foreach ($applicant['answers'] ?? [] as $answer): ?>
                                                <li>
                                                    <strong>Q:</strong> <?= htmlspecialchars($answer['question_text'] ?? 'No question'); ?><br>
                                                    <strong>A:</strong> <?= htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div class="flex">
                <a href="/app/user/presentation/my_listings.php" class="btn">Back to Listings</a>
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