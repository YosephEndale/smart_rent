<?php
ob_start();

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/config/env.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/auth/logic/auth.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$warning_msg = [];

if (isset($_POST['submit'])) {
    $result = handleLogin($_POST['email'] ?? '', $_POST['pass'] ?? '');

    if (isset($result['redirect'])) {
        header('Location: enter_otp.php');
        ob_end_flush();
        exit;
    }

    if (isset($result['warning_msg'])) {
        $warning_msg = $result['warning_msg'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['language'] ?? 'en'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login 🌟</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="form-container">
    <form action="" method="post">
        <h3>Welcome Back! 😊</h3>
        <input type="email"    name="email" required maxlength="50" placeholder="Enter your email"    class="box">
        <input type="password" name="pass"  required maxlength="20" placeholder="Enter your password" class="box">
        <p>Don't have an account? <a href="/app/auth/presentation/register.php">Register Now</a></p>
        <input type="submit" value="Login Now" name="submit" class="btn">
    </form>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>

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