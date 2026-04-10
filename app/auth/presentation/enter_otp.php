<?php
ob_start();
session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/auth/logic/verify_otp.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('Europe/Rome');

if (!isset($_SESSION['mfa_user_id'])) {
    header('Location: /app/auth/presentation/login.php');
    ob_end_flush();
    exit();
}

// Mask the email for display: user@example.com → u***@example.com
$rawEmail     = $_SESSION['mfa_user_email'] ?? '';
$maskedEmail  = '';
if ($rawEmail) {
    [$local, $domain] = explode('@', $rawEmail, 2);
    $maskedEmail = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1)) . '@' . $domain;
}

$warning_msg = [];
if (isset($_POST['submit'])) {
    $result = verifyOtp($_POST['otp'] ?? '', (int)$_SESSION['mfa_user_id']);
    if (isset($result['warning_msg'])) {
        $warning_msg = $result['warning_msg'];
    }
    if (isset($result['redirect'])) {
        session_write_close();
        header('Location: ' . $result['redirect']);
        ob_end_flush();
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter OTP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="form-container">
    <form action="" method="post">
        <h3>Enter OTP</h3>
        <p>
            A 6-digit code was sent to
            <?= $maskedEmail ? '<strong>' . htmlspecialchars($maskedEmail) . '</strong>' : 'your email address'; ?>.
            Please check your inbox (and spam folder).
        </p>

        <?php if (!empty($warning_msg)): ?>
            <p class="error"><?= htmlspecialchars(implode(' ', $warning_msg)); ?></p>
        <?php endif; ?>

        <input type="text" name="otp" required placeholder="Enter 6-digit OTP"
               maxlength="6" autocomplete="one-time-code" class="box">
        <input type="submit" value="Verify OTP" name="submit" class="btn">
        <p><a href="/app/auth/presentation/resend_otp.php">Resend OTP</a></p>
    </form>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php ob_end_flush(); ?>