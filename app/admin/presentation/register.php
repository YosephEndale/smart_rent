<?php
session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/components/connect.php';

use App\Admin\Logic\AdminLogic;

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$admin_id = $_SESSION['admin_id'];

$adminLogic = new AdminLogic($conn);
$success_msg = [];
$warning_msg = [];

if (isset($_POST['submit'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $name = trim($name);
    $pass = filter_input(INPUT_POST, 'pass', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $pass = trim($pass);
    $c_pass = filter_input(INPUT_POST, 'c_pass', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $c_pass = trim($c_pass);
    $result = $adminLogic->register($name, $pass, $c_pass);
    if ($result['success']) {
        $_SESSION['admin_id'] = $result['admin_id'];
        header('Location: dashboard.php');
        exit();
    } else {
        $warning_msg[] = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="form-container">
        <form action="" method="POST">
            <h3>Register New Admin</h3>
            <?php foreach ($warning_msg as $msg): ?>
                <p class="warning"><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
            <?php foreach ($success_msg as $msg): ?>
                <p class="success"><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
            <input type="text" name="name" placeholder="Enter username" maxlength="20" class="box" required oninput="this.value = this.value.replace(/\s/g, '')">
            <input type="password" name="pass" placeholder="Enter password" maxlength="20" class="box" required oninput="this.value = this.value.replace(/\s/g, '')">
            <input type="password" name="c_pass" placeholder="Confirm password" maxlength="20" class="box" required oninput="this.value = this.value.replace(/\s/g, '')">
            <input type="submit" value="Register Now" name="submit" class="btn">
        </form>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script src="../../../public/js/admin_script.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>