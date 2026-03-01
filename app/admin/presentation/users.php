<?php
session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/components/connect.php';

use App\Admin\Data\UserData;
use App\Admin\Data\PropertyData;

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$admin_id = $_SESSION['admin_id'];

$userData = new UserData($conn);
$propertyData = new PropertyData($conn);
$success_msg = [];
$warning_msg = [];

if (isset($_POST['delete'])) {
    $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id === false) {
        $warning_msg[] = 'Invalid user ID!';
    } else {
        if ($userData->deleteUser($delete_id)) {
            $success_msg[] = 'User deleted!';
        } else {
            $warning_msg[] = 'User already deleted!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="grid">
        <h1 class="heading">Users</h1>
        <form action="" method="POST" class="search-form">
            <input type="text" name="search_box" placeholder="Search users..." maxlength="100" required>
            <button type="submit" class="fas fa-search" name="search_btn"></button>
        </form>
        <div class="box-container">
            <?php
            $search = filter_input(INPUT_POST, 'search_box', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $users = $userData->getUsers($search);
            if ($users) {
                foreach ($users as $user) {
                    $count_property = $propertyData->getProperties();
                    $total_properties = count(array_filter($count_property, fn($p) => $p['user_id'] == $user['user_id']));
                    ?>
                    <div class="box">
                        <p>Name: <span><?= htmlspecialchars($user['name']); ?></span></p>
                        <p>Number: <a href="tel:<?= htmlspecialchars($user['number']); ?>"><?= htmlspecialchars($user['number']); ?></a></p>
                        <p>Email: <a href="mailto:<?= htmlspecialchars($user['email']); ?>"><?= htmlspecialchars($user['email']); ?></a></p>
                        <p>Properties listed: <span><?= $total_properties; ?></span></p>
                        <form action="" method="POST">
                            <input type="hidden" name="delete_id" value="<?= $user['user_id']; ?>">
                            <input type="submit" value="Delete User" onclick="return confirm('Delete this user?');" name="delete" class="delete-btn">
                        </form>
                    </div>
                    <?php
                }
            } elseif ($search) {
                echo '<p class="empty">Results not found!</p>';
            } else {
                echo '<p class="empty">No user accounts added yet!</p>';
            }
            ?>
        </div>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script src="../../../public/js/admin_script.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>