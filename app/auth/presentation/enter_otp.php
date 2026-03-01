<?php
ob_start(); // Start output buffering
session_start();

// Include necessary files
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/auth/logic/verify_otp.php';

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('Europe/Rome');

// Check if user is authenticated for MFA
if (!isset($_SESSION['mfa_user_id'])) {
    header('Location: /app/auth/presentation/login.php');
    ob_end_flush();
    exit();
}

// Handle OTP submission
$warning_msg = null;
if (isset($_POST['submit'])) {
    $entered_otp = $_POST['otp'];
    $result = verifyOtp($entered_otp, $_SESSION['mfa_user_id']);
    
    if (isset($result['warning_msg'])) {
        $warning_msg = $result['warning_msg'];
    }
    
    if (isset($result['redirect'])) {
        session_write_close(); // Save session data
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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
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
        <p>A 6-digit OTP has been sent to your Telegram. Please enter it below.</p>
        <?php if ($warning_msg): ?>
            <p class="error"><?php echo htmlspecialchars(implode(' ', $warning_msg)); ?></p>
        <?php endif; ?>
        <input type="text" name="otp" required placeholder="Enter 6-digit OTP" class="box">
        <input type="submit" value="Verify OTP" name="submit" class="btn">
        <p><a href="/app/auth/presentation/resend_otp.php">Resend OTP</a></p>
    </form>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="/public/js/script.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>