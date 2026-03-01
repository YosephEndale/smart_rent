<?php
session_start();

// Destroy session and clear user_id
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Success message for Sweetalert
$success_msg[] = 'You’ve logged out! See you soon! 😊🏡';

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Logging Out... 😊</title>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
</head>
<body>
   <?php include 'message.php'; // Include from same directory ?>
   <script>
      // Redirect to login.php after Sweetalert
      setTimeout(function() {
         window.location.href = "../app/auth/presentation/login.php";
      }, 1500); // 1.5-second delay for Sweetalert to show
   </script>
</body>
</html>