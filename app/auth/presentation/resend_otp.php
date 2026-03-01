<?php
session_start();
include '../../../components/connect.php';
require '../logic/resend.php';

// Check if the user is in the middle of an MFA process
if (!isset($_SESSION['mfa_user_id'])) {
    header('location: login.php');
    exit();
}

$result = resendOtp($_SESSION['mfa_user_id']);
if (isset($result['redirect'])) {
    header("location: {$result['redirect']}");
    exit();
}
if (isset($result['warning_msg'])) {
    $warning_msg = $result['warning_msg'];
}
if (isset($result['success_msg'])) {
    $success_msg = $result['success_msg'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Resend OTP</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
   <link rel="stylesheet" href="../../../public/css/style.css">
</head>
<body>
<?php include '../../../components/user_header.php'; ?>
<section class="form-container">
   <h3>Resend OTP</h3>
   <p>We've sent a new OTP to your Telegram. Please check your messages.</p>
   <a href="enter_otp.php" class="btn">Go to OTP page</a>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<?php include '../../../components/footer.php'; ?>
<script src="../../../public/js/script.js"></script>
<?php include '../../../components/message.php'; ?>
</body>
</html>