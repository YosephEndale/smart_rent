<?php
session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/auth/logic/resend.php';

if (!isset($_SESSION['mfa_user_id'])) {
    header('Location: /app/auth/presentation/login.php');
    exit();
}

$warning_msg = [];
$success_msg = [];

$result = resendOtp((int)$_SESSION['mfa_user_id']);

if (isset($result['redirect'])) {
    header('Location: ' . $result['redirect']);
    exit();
}
if (isset($result['warning_msg'])) $warning_msg = $result['warning_msg'];
if (isset($result['success_msg'])) $success_msg = $result['success_msg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend OTP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="form-container">
    <h3>Resend OTP</h3>
    <p>A new OTP has been sent to your email address. Please check your inbox and spam folder.</p>
    <a href="/app/auth/presentation/enter_otp.php" class="btn">Go to OTP page</a>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
<?php require_once ROOT_DIR . '/components/message.php'; ?>
</body>
</html>