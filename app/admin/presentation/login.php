<?php
session_start();

// Define ROOT_DIR if not already defined
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

// Verify autoloader exists
if (!file_exists(ROOT_DIR . '/vendor/autoload.php')) {
    die('Error: Composer autoloader not found at ' . ROOT_DIR . '/vendor/autoload.php. Run "composer install" or "composer dump-autoload -o".');
}
require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/scam/presentation/scam_detection.php';

use App\Admin\Logic\AdminLogic;

// The PSR-4 autoloader should handle loading AdminLogic
// No need to check classmap as it uses dynamic PSR-4 loading

try {
    $adminLogic = new AdminLogic($conn);
} catch (Throwable $e) {
    error_log('Failed to load AdminLogic: ' . $e->getMessage());
    die('Error: Failed to load AdminLogic class: ' . htmlspecialchars($e->getMessage()));
}

$warning_msg = [];

if (isset($_POST['submit'])) {
    $name = filter_var(trim($_POST['name']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $pass = trim($_POST['pass']);
    $admin_id = $adminLogic->authenticate($name, $pass);
    if ($admin_id) {
        $_SESSION['admin_id'] = $admin_id;
        header('Location: dashboard.php');
        exit();
    } else {
        $warning_msg[] = 'Incorrect username or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
</head>
<body style="padding-left: 0;">
    <section class="form-container" style="min-height: 100vh;">
        <form action="" method="POST">
            <h3>Welcome Back!</h3>
            <?php foreach ($warning_msg as $msg): ?>
                <p class="warning"><?php echo htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
            <input type="text" name="name" placeholder="Enter username" maxlength="20" class="box" required oninput="this.value = this.value.replace(/\s/g, '')">
            <input type="password" name="pass" placeholder="Enter password" maxlength="20" class="box" required oninput="this.value = this.value.replace(/\s/g, '')">
            <input type="submit" value="Login Now" name="submit" class="btn">
        </form>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>