<?php
namespace App\User\Presentation;

use App\User\Logic\UserLogic;

// Define ROOT_DIR if not already defined
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2)); // Resolves to C:\Users\yosep\OneDrive\Desktop\smart_rent from app/user/presentation/
}

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/components/user_header.php';
require_once ROOT_DIR . '/components/footer.php';
require_once ROOT_DIR . '/components/message.php';
require_once ROOT_DIR . '/app/chatbot/presentation/chatbot.php';

session_start();
$user_id = $_SESSION['user_id'] ?? '';
if (!$user_id) {
    header('Location: /app/auth/presentation/login.php');
    exit;
}

$userLogic = new UserLogic(get_db_connection());
$property_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$warning_msg = [];
$success_msg = [];

if (!$property_id) {
    $warning_msg[] = 'Invalid property ID';
    header('Location: /app/user/presentation/my_listings.php');
    exit;
}

$result = $userLogic->getApplicants($user_id, $property_id);
if (isset($result['error'])) {
    $warning_msg[] = $result['error'];
    header('Location: /app/user/presentation/my_listings.php');
    exit;
}

$property = $result['property'];
$applicants = $result['applicants'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>View Applicants</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
   <link rel="stylesheet" href="/public/css/style.css">
   <link rel="stylesheet" href="/public/css/chatbot.css">
   <style>
      .applicant-details { display: none; margin-top: 10px; padding: 10px; border: 1px solid #ddd; }
      .toggle-details { cursor: pointer; color: #007bff; }
      .criteria-breakdown, .answers { margin-left: 20px; }
      .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
      .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      .table th { background-color: #f2f2f2; }
   </style>
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="property-form">
   <h3>Applicants for <?= htmlspecialchars($property['property_name']); ?></h3>
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
                  <td><?php echo $index + 1; ?></td>
                  <td><?php echo htmlspecialchars($applicant['name']); ?></td>
                  <td><?php echo $applicant['score']; ?>/100</td>
                  <td>
                     <span class="toggle-details" onclick="toggleDetails('details-<?php echo $applicant['user_id']; ?>')">View Details</span>
                     <div id="details-<?php echo $applicant['user_id']; ?>" class="applicant-details">
                        <p><strong>Scoring Explanation:</strong> <?php echo htmlspecialchars($applicant['explanation']); ?></p>
                        <p><strong>Criteria Breakdown:</strong></p>
                        <ul class="criteria-breakdown">
                           <?php foreach ($applicant['criteria_breakdown'] as $criterion => $points): ?>
                              <li><?php echo htmlspecialchars($criterion); ?>: <?php echo $points; ?> points</li>
                           <?php endforeach; ?>
                        </ul>
                        <p><strong>Answers:</strong></p>
                        <ul class="answers">
                           <?php foreach ($applicant['answers'] as $answer): ?>
                              <li>
                                 <strong>Q:</strong> <?php echo htmlspecialchars($answer['question_text']); ?><br>
                                 <strong>A:</strong> <?php echo htmlspecialchars($answer['answer_text']); ?>
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
      <a href="/app/user/presentation/my_listings.php" class="btn">Return to Listings</a>
   </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>
<?php include ROOT_DIR . '/app/chatbot/presentation/chatbot.php'; ?>
<script src="/public/js/chatbot.js"></script>

<script>
function toggleDetails(id) {
   const element = document.getElementById(id);
   element.style.display = element.style.display === 'block' ? 'none' : 'block';
}
</script>
</body>
</html>