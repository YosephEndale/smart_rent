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

if (isset($_POST['delete'])) {
    $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id === false) {
        $warning_msg[] = 'Invalid admin ID!';
    } else {
        $result = $adminLogic->deleteAdmin($delete_id);
        if ($result['success']) {
            $success_msg[] = $result['message'];
        } else {
            $warning_msg[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admins</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="grid">
        <h1 class="heading">Admins</h1>
        <form action="" method="POST" class="search-form">
            <input type="text" name="search_box" placeholder="Search admins..." maxlength="100" required>
            <button type="submit" class="fas fa-search" name="search_btn"></button>
        </form>
        <div class="box-container">
            <?php
            $search = filter_input(INPUT_POST, 'search_box', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $admins = $adminData->getAdmins($search);
            if ($admins) {
                foreach ($admins as $admin) {
                    ?>
                    <?php if ($admin['id'] == $admin_id): ?>
                        <div class="box" style="order: -1;">
                            <p>Name: <span><?= htmlspecialchars($admin['name']); ?></span></p>
                            <a href="update.php" class="option-btn">Update Account</a>
                            <a href="register.php" class="btn">Register New</a>
                        </div>
                    <?php else: ?>
                        <div class="box">
                            <p>Name: <span><?= htmlspecialchars($admin['name']); ?></span></p>
                            <form action="" method="POST">
                                <input type="hidden" name="delete_id" value="<?= htmlspecialchars($admin['id']); ?>">
                                <input type="submit" value="Delete Admin" onclick="return confirm('Delete this admin?');" name="delete" class="delete-btn">
                            </form>
                        </div>
                    <?php endif; ?>
                    <?php
                }
            } elseif (isset($_POST['search_box']) || isset($_POST['search_btn'])) {
                echo '<p class="empty">No results found!</p>';
            } else {
                ?>
                <p class="empty">No admins added yet!</p>
                <div class="box" style="text-align: center;">
                    <p>Create a new admin</p>
                    <a href="register.php" class="btn">Register Now</a>
                </div>
                <?php
            }
            ?>
        </div>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script src="../../../public/js/admin_script.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>