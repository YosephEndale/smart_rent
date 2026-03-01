<?php
session_start();

// Define root directory constant
define('ROOT_DIR', dirname(__DIR__, 3));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';

use App\Admin\Data\AdminData;
use App\Admin\Data\PropertyData;
use App\Admin\Data\UserData;
use App\Admin\Data\MessageData;

if (!class_exists('App\Admin\Data\AdminData')) {
    die('Error: Class App\Admin\Data\AdminData not found. Check autoloader and file paths.');
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$admin_id = $_SESSION['admin_id'];

$adminData = new AdminData($conn);
$propertyData = new PropertyData($conn);
$userData = new UserData($conn);
$messageData = new MessageData($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="dashboard">
        <h1 class="heading">Dashboard</h1>
        <div class="box-container">
            <div class="box">
                <?php
                $profile = $adminData->getAdminById($admin_id);
                if ($profile) {
                    ?>
                    <h3>Welcome!</h3>
                    <p><?= htmlspecialchars($profile['name']); ?></p>
                    <a href="update.php" class="btn">Update Profile</a>
                    <?php
                } else {
                    echo '<p class="error">Error: Admin profile not found.</p>';
                    session_unset();
                    session_destroy();
                    header('Location: login.php');
                    exit();
                }
                ?>
            </div>
            <div class="box">
                <?php
                $properties = $propertyData->getProperties();
                $count_listings = count($properties);
                ?>
                <h3><?= $count_listings; ?></h3>
                <p>Property Posted</p>
                <a href="listings.php" class="btn">View Listings</a>
            </div>
            <div class="box">
                <?php
                $users = $userData->getUsers();
                $count_users = count($users);
                ?>
                <h3><?= $count_users; ?></h3>
                <p>Total Users</p>
                <a href="users.php" class="btn">View Users</a>
            </div>
            <div class="box">
                <?php
                $admins = $adminData->getAdmins();
                $count_admins = count($admins);
                ?>
                <h3><?= $count_admins; ?></h3>
                <p>Total Admins</p>
                <a href="admins.php" class="btn">View Admins</a>
            </div>
            <div class="box">
                <?php
                $messages = $messageData->getMessages();
                $count_messages = count($messages);
                ?>
                <h3><?= $count_messages; ?></h3>
                <p>New Messages</p>
                <a href="messages.php" class="btn">View Messages</a>
            </div>
        </div>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script src="../../../public/js/admin_script.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>