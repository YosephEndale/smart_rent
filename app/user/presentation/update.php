<?php
namespace App\User\Presentation;

use App\User\Logic\UserLogic;

require_once __DIR__ . '/../../../vendor/autoload.php'; // Include Composer autoloader
require_once __DIR__ . '/../../../config/env.php'; // Include env.php to define ROOT_DIR and load .env
require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';


session_start();
$user_id = $_SESSION['user_id'] ?? '';
if (!$user_id) {
    header('Location: /app/auth/presentation/login.php');
    exit;
}

$userLogic = new UserLogic(get_db_connection());
$profile = $userLogic->getUserProfile($user_id);
$warning_msg = [];
$success_msg = [];

if (isset($profile['error'])) {
    $warning_msg[] = $profile['error'];
    header('Location: /app/auth/presentation/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $result = $userLogic->updateProfile($user_id, $_POST, $_ENV['ENCRYPTION_KEY']);
    $warning_msg = $result['error'] ?? [];
    $success_msg = $result['success'] ?? [];
}

$fetch_user = $profile['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Change Your Profile! 🌟</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;700&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="/public/css/style.css">
   <link rel="stylesheet" href="/public/css/chatbot.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="form-container update-profile">
   <form action="" method="post">
      <h3>Update Your Profile! 🌟</h3>
      <div class="box">
         <i class="fas fa-user"></i>
         <input type="text" name="name" maxlength="50" placeholder="Name😊" value="<?= htmlspecialchars($fetch_user['name'] ?? '') ?>" class="input">
      </div>
      <div class="box">
         <i class="fas fa-envelope"></i>
         <input type="email" name="email" maxlength="50" placeholder="Your Email Address! ✉️" value="<?= htmlspecialchars($fetch_user['email'] ?? '') ?>" class="input">
      </div>
      <div class="box">
         <i class="fas fa-phone"></i>
         <input type="text" name="number" maxlength="10" placeholder="Your Phone Number! 📱" value="<?= htmlspecialchars($fetch_user['number'] ?? '') ?>" class="input">
      </div>
      <div class="box">
         <i class="fas fa-telegram"></i>
         <input type="text" name="telegram_id" maxlength="15" placeholder="Your Telegram ID! 📬" value="" class="input">
      </div>
      <div class="box">
         <i class="fas fa-lock"></i>
         <input type="password" name="old_pass" maxlength="20" placeholder="Your Current Password 🔒" class="input">
      </div>
      <div class="box">
         <i class="fas fa-key"></i>
         <input type="password" name="new_pass" maxlength="20" placeholder="New Secret Password! 🌟" class="input">
      </div>
      <div class="box">
         <i class="fas fa-key"></i>
         <input type="password" name="c_pass" maxlength="20" placeholder="Confirm New Password! ✅" class="input">
      </div>
      <input type="submit" value="Save My Profile!" name="submit" class="btn">
   </form>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>