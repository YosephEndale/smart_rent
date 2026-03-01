<?php
session_start();

// Use the provided require_once statements
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('location: ' . ROOT_DIR . '/app/user/presentation/dashboard.php'); // Adjusted to dashboard.php
    exit();
}

// Load encryption key from environment and decode it
$ENCRYPTION_KEY = base64_decode(getenv('ENCRYPTION_KEY'));
if (!$ENCRYPTION_KEY || strlen($ENCRYPTION_KEY) !== 32) {
    $warning_msg[] = 'Invalid or missing encryption key!';
    error_log('ENCRYPTION_KEY invalid or not set in .env file');
    exit();
}

// Set OpenSSL environment variables
putenv("OPENSSL_CONF=" . ROOT_DIR . "/config/openssl.cnf"); // Adjust to config directory
putenv("TMP=" . ROOT_DIR . "/tmp");
putenv("TEMP=" . ROOT_DIR . "/tmp");

// Create tmp directory if it doesn't exist
$tmp_dir = ROOT_DIR . "/tmp";
if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0777, true);
}

if (isset($_POST['submit'])) {
    // Sanitize inputs
    $name = strip_tags($_POST['name']);
    $number = strip_tags($_POST['number']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $telegram_id = strip_tags($_POST['telegram_id']);
    $pass = $_POST['pass'];
    $c_pass = $_POST['c_pass'];

    // Debug: Log inputs
    error_log("Register - Name: $name, Email: $email, Telegram ID: $telegram_id");

    // Validate inputs
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $warning_msg[] = 'Invalid email format!';
    } elseif (empty($name) || strlen($name) > 50) {
        $warning_msg[] = 'Name must be 1-50 characters!';
    } elseif (!empty($number) && !preg_match('/^[0-9]{0,10}$/', $number)) {
        $warning_msg[] = 'Number must be up to 10 digits!';
    } elseif (!empty($telegram_id) && !preg_match('/^[0-9]{5,15}$/', $telegram_id)) {
        $warning_msg[] = 'Telegram ID must be a valid numeric ID (5-15 digits)!';
    } else {
        // Check if passwords match
        if ($pass !== $c_pass) {
            $warning_msg[] = 'Passwords do not match!';
        } else {
            // Check if email already exists
            $select_users = $conn->prepare("SELECT * FROM `users` WHERE email = ?");
            $select_users->execute([$email]);

            if ($select_users->rowCount() > 0) {
                $warning_msg[] = 'Email already taken!';
            } else {
                // Hash the password
                $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

                // Hash and encrypt the Telegram ID if provided
                $hashed_telegram_id = !empty($telegram_id) ? password_hash($telegram_id, PASSWORD_DEFAULT) : null;
                $encrypted_telegram_id = !empty($telegram_id) ? base64_encode(openssl_encrypt($telegram_id, 'AES-256-CBC', $ENCRYPTION_KEY, 0, substr($ENCRYPTION_KEY, 0, 16))) : null;

                // Debug: Log hashed and encrypted Telegram ID
                error_log("Hashed Telegram ID: $hashed_telegram_id");
                error_log("Encrypted Telegram ID: $encrypted_telegram_id");

                // Insert the new user
                $insert_user = $conn->prepare("INSERT INTO `users` (name, number, email, password, telegram_id, encrypted_telegram_id) VALUES (?, ?, ?, ?, ?, ?)");
                $success = $insert_user->execute([$name, $number, $email, $hashed_pass, $hashed_telegram_id, $encrypted_telegram_id]);
                error_log("Insert query success: " . ($success ? 'true' : 'false'));

                if ($success) {
                    // Get the new user_id
                    $user_id = $conn->lastInsertId();

                    // Generate key pair
                    $key_dir = ROOT_DIR . "/secure_keys";
                    if (!is_dir($key_dir)) mkdir($key_dir, 0777, true);
                    if (!is_writable($key_dir)) {
                        $warning_msg[] = "Key directory $key_dir is not writable!";
                    } else {
                        $config = [
                            "private_key_bits" => 2048,
                            "private_key_type" => OPENSSL_KEYTYPE_RSA,
                            "config" => ROOT_DIR . "/config/openssl.cnf" // Adjust to config directory
                        ];

                        while (openssl_error_string()) {}
                        $private_key_resource = openssl_pkey_new($config);
                        if (!$private_key_resource) {
                            $warning_msg[] = "Failed to generate key for user ID: $user_id";
                            error_log("Key generation failed for user $user_id: " . openssl_error_string());
                        } else {
                            $private_key_path = "$key_dir/{$user_id}_private.pem";
                            if (!openssl_pkey_export($private_key_resource, $private_key_pem, null, $config)) {
                                $warning_msg[] = "Failed to export private key for user ID: $user_id";
                                error_log("Private key export failed for user $user_id: " . openssl_error_string());
                            } elseif (!file_put_contents($private_key_path, $private_key_pem)) {
                                $warning_msg[] = "Failed to write private key for user ID: $user_id";
                            } else {
                                $key_details = openssl_pkey_get_details($private_key_resource);
                                if (!$key_details) {
                                    $warning_msg[] = "Failed to get public key details for user ID: $user_id";
                                } else {
                                    $public_key_pem = $key_details['key'];
                                    $update_query = $conn->prepare("UPDATE users SET public_key = ? WHERE user_id = ?");
                                    if (!$update_query->execute([$public_key_pem, $user_id])) {
                                        $warning_msg[] = "Failed to update public key for user ID: $user_id";
                                    }
                                }
                            }
                        }
                    }

                    $success_msg[] = 'Registration successful! Please log in.';
                } else {
                    $warning_msg[] = 'Registration failed: ' . $conn->errorInfo()[2];
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
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Register</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
   <link rel="stylesheet" href="<?php echo ROOT_DIR; ?>/public/css/style.css">
</head>
<body>
<?php require_once ROOT_DIR . '/components/user_header.php'; ?>
<section class="form-container">
   <form action="" method="post">
      <h3>Create an account!</h3>
      <input type="text" name="name" required maxlength="50" placeholder="Enter your name" class="box">
      <input type="email" name="email" required maxlength="50" placeholder="Enter your email" class="box">
      <input type="text" name="number" maxlength="10" placeholder="Enter your number" class="box">
      <input type="text" name="telegram_id" maxlength="15" placeholder="Enter your Telegram ID (optional)" class="box">
      <p>Send "/start" to @RentalMFA_Bot to receive OTPs</p>
      <input type="password" name="pass" required maxlength="20" placeholder="Enter your password" class="box">
      <input type="password" name="c_pass" required maxlength="20" placeholder="Confirm your password" class="box">
      <p>Already have an account? <a href="<?php echo ROOT_DIR; ?>/app/auth/presentation/login.php">Login now</a></p>
      <input type="submit" value="Register now" name="submit" class="btn">
   </form>
</section>
<?php require_once ROOT_DIR . '/components/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script src="<?php echo ROOT_DIR; ?>/public/js/script.js"></script>
<?php require_once ROOT_DIR . '/components/message.php'; ?>
</body>
</html>