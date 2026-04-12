<?php
session_start();

$success_msg = [];
$warning_msg = [];

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';

try {
    require_once ROOT_DIR . '/components/connect.php';
} catch (Exception $e) {
    $warning_msg[] = 'Database connection error.';
    error_log('Register - DB error: ' . $e->getMessage());
}

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /app/user/presentation/dashboard.php');
    exit();
}

if (isset($_POST['submit'])) {
    if (empty($conn)) {
        $warning_msg[] = 'Database connection failed. Please try again later.';
    } else {
        $name   = strip_tags(trim($_POST['name']));
        $number = strip_tags(trim($_POST['number'] ?? ''));
        $email  = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $pass   = $_POST['pass'];
        $c_pass = $_POST['c_pass'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $warning_msg[] = 'Invalid email format!';
        } elseif (empty($name) || strlen($name) > 50) {
            $warning_msg[] = 'Name must be 1–50 characters!';
        } elseif (!empty($number) && !preg_match('/^[0-9]{0,10}$/', $number)) {
            $warning_msg[] = 'Phone number must be up to 10 digits!';
        } elseif ($pass !== $c_pass) {
            $warning_msg[] = 'Passwords do not match!';
        } else {
            $chk = $conn->prepare("SELECT 1 FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetchColumn()) {
                $warning_msg[] = 'Email already taken!';
            } else {
                $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

                $insert = $conn->prepare(
                    "INSERT INTO users (name, number, email, password, email_notifications)
                     VALUES (?, ?, ?, ?, 1)"
                );
                $success = $insert->execute([$name, $number, $email, $hashed_pass]);

                if ($success) {
                    $user_id = $conn->lastInsertId();

                    // Key directory comes from .env — completely outside the web root
                    $key_dir = $_ENV['KEY_STORAGE_PATH'] ?? '';

                    if (empty($key_dir)) {
                        error_log("Register: KEY_STORAGE_PATH not set in .env for user_id=$user_id");
                        $warning_msg[] = 'Account created but KEY_STORAGE_PATH is not configured. Please contact support.';
                    } elseif (!is_dir($key_dir) || !is_writable($key_dir)) {
                        error_log("Register: key directory '$key_dir' missing or not writable for user_id=$user_id");
                        $warning_msg[] = 'Account created but security keys could not be stored. Please contact support.';
                    } else {
                        $res = openssl_pkey_new([
                            'private_key_bits' => 2048,
                            'private_key_type' => OPENSSL_KEYTYPE_RSA,
                        ]);

                        if (!$res) {
                            error_log("Register: key generation failed for user_id=$user_id: " . openssl_error_string());
                            $warning_msg[] = 'Account created but failed to generate security keys. Please contact support.';
                        } else {
                            // Private key — outside web root, owner-only permissions
                            openssl_pkey_export($res, $private_key_pem);
                            $private_key_path = "$key_dir/{$user_id}_private.pem";
                            file_put_contents($private_key_path, $private_key_pem);
                            chmod($private_key_path, 0600);

                            // Public key — safe to store in DB
                            $details = openssl_pkey_get_details($res);
                            $conn->prepare("UPDATE users SET public_key = ? WHERE user_id = ?")
                                 ->execute([$details['key'], $user_id]);

                            error_log("Register: keys generated for user_id=$user_id at $private_key_path");
                        }
                    }

                    if (empty($warning_msg)) {
                        $success_msg[] = 'Registration successful! Please log in.';
                    }
                } else {
                    $warning_msg[] = 'Registration failed. Please try again.';
                }
            }
        }
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
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<?php require_once ROOT_DIR . '/components/user_header.php'; ?>

<section class="form-container">
    <form action="" method="post">
        <h3>Create an account!</h3>
        <input type="text"     name="name"   required maxlength="50" placeholder="Enter your name"             class="box">
        <input type="email"    name="email"  required maxlength="50" placeholder="Enter your email"            class="box">
        <input type="text"     name="number" maxlength="10"          placeholder="Enter your phone (optional)" class="box">
        <input type="password" name="pass"   required maxlength="20" placeholder="Enter your password"         class="box">
        <input type="password" name="c_pass" required maxlength="20" placeholder="Confirm your password"       class="box">
        <p>Already have an account? <a href="/app/auth/presentation/login.php">Login now</a></p>
        <input type="submit" value="Register now" name="submit" class="btn">
    </form>
</section>

<?php require_once ROOT_DIR . '/components/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script src="/public/js/script.js"></script>
<?php require_once ROOT_DIR . '/components/message.php'; ?>

<?php if (!empty($success_msg)): ?>
<script>
swal({ title: "Success!", text: "<?= htmlspecialchars($success_msg[0]); ?>", icon: "success", button: "OK" });
</script>
<?php endif; ?>

<?php if (!empty($warning_msg)): ?>
<script>
swal({ title: "Warning!", text: "<?= htmlspecialchars(implode('\n', $warning_msg)); ?>", icon: "warning", button: "OK" });
</script>
<?php endif; ?>

</body>
</html>