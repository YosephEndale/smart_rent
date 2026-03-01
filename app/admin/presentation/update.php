<?php
session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/components/connect.php';

use App\Admin\Data\AdminData;
use App\Admin\Logic\AdminLogic;

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$admin_id = $_SESSION['admin_id'];

$adminLogic = new AdminLogic($conn);
$adminData = new AdminData($conn);
$success_msg = [];
$warning_msg = [];

$profile = $adminData->getAdminById($admin_id);
if (!$profile) {
    $warning_msg[] = 'Admin not found!';
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

if (isset($_POST['submit'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $name = trim($name);
    $old_pass = filter_input(INPUT_POST, 'old_pass', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $old_pass = trim($old_pass);
    $new_pass = filter_input(INPUT_POST, 'new_pass', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $new_pass = trim($new_pass);
    $c_pass = filter_input(INPUT_POST, 'c_pass', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $c_pass = trim($c_pass);
    $result = $adminLogic->updateProfile($admin_id, $name, $old_pass, $new_pass, $c_pass);
    if (!empty($result['success'])) {
        $success_msg = array_merge($success_msg, $result['success']);
    }
    if (!empty($result['errors'])) {
        $warning_msg = array_merge($warning_msg, $result['errors']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="form-container">
        <form action="" method="POST">
            <h3>Update Profile</h3>
            <?php if (!empty($warning_msg)): ?>
                <?php foreach ($warning_msg as $msg): ?>
                    <p class="warning"><?= htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($success_msg)): ?>
                <?php foreach ($success_msg as $msg): ?>
                    <p class="success"><?= htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <input type="text" name="name" placeholder="<?= htmlspecialchars($profile['name']); ?>" maxlength="20" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
            <input type="password" name="old_pass" placeholder="Enter old password" maxlength="20" class="box">
            <input type="password" name="new_pass" placeholder="Enter new password" maxlength="20" class="box">
            <input type="password" name="c_pass" placeholder="Confirm new password" maxlength="20" class="box">
            <input type="submit" value="Update Now" name="submit" class="btn">
        </form>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script src="../../../public/js/admin_script.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>