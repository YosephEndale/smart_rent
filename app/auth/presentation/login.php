<?php
namespace App\Auth\Presentation;
use Exception;
use PDO;
use App\Auth\Logic\AuthLogic;

// Start output buffering
ob_start();

// Define ROOT_DIR
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); // Resolves to project root
    error_log("ROOT_DIR defined as: " . ROOT_DIR);
}

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start error in login.php: " . $e->getMessage());
    }
}

require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/auth/logic/auth.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $pass = $_POST['pass'];
    $result = handleLogin($email, $pass);
    if (isset($result['redirect'])) {
        // Fetch user's language from the database after successful login
        try {
            $conn = get_db_connection();
            $stmt = $conn->prepare("SELECT language FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && isset($user['language'])) {
                $_SESSION['language'] = $user['language'];
                error_log("Set session language after login: " . $_SESSION['language']);
            } else {
                $_SESSION['language'] = 'en'; // Fallback to English if no language is set
                error_log("No language found for user with email: $email, defaulting to English");
            }
        } catch (Exception $e) {
            error_log("Error fetching language after login: " . $e->getMessage());
            $_SESSION['language'] = 'en'; // Fallback to English on error
        }
        header("Location: enter_otp.php");
        exit;
    }
    if (isset($result['warning_msg'])) {
        $warning_msg = $result['warning_msg'];
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
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
      <input type="email" name="email" required maxlength="50" placeholder="Enter your email" class="box">
      <input type="password" name="pass" required maxlength="20" placeholder="Enter your password" class="box">
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
        text: "<?= htmlspecialchars(implode('\n', (array)$warning_msg)); ?>",
        icon: "warning",
        button: "OK"
    });
</script>
<?php endif; ?>
</body>
</html>
<?php ob_end_flush(); ?>