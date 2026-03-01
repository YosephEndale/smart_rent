<?php
namespace App\Admin\Presentation;

session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/components/connect.php';

use App\Admin\Data\MessageData;
use App\Admin\Logic\MessageLogic;

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$admin_id = $_SESSION['admin_id'];

$messageLogic = new MessageLogic($conn);
$messageData = new MessageData($conn);
$success_msg = [];
$warning_msg = [];

function safeHtml($text, $isSystemNotification = false) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if ($isSystemNotification) {
        $text = preg_replace(
            '/View: \[button:([^\]]*)\]([^\[]+)\[\/button\]/',
            'View: <button onclick="window.location.href=\'$1\'" class="view-btn">$2</button>',
            $text
        );
    }
    return $text;
}

if (isset($_POST['delete'])) {
    $delete_id = htmlspecialchars($_POST['delete_id'], ENT_QUOTES, 'UTF-8');
    $result = $messageLogic->deleteMessage($delete_id);
    if ($result['success']) {
        $success_msg[] = $result['message'];
    } else {
        $warning_msg[] = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
    <style>
        .view-btn {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .view-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="grid">
        <h1 class="heading">Messages</h1>
        <form action="" method="POST" class="search-form">
            <input type="text" name="search_box" placeholder="Search messages..." maxlength="100" required>
            <button type="submit" class="fas fa-search" name="search_btn"></button>
        </form>
        <div class="box-container">
            <?php
            $search = filter_input(INPUT_POST, 'search_box', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $messages = $messageData->getMessages($search);
            error_log("messages.php: Loaded " . count($messages) . " messages, search='$search'");
            if ($messages) {
                foreach ($messages as $message) {
                    $isSystemNotification = (
                        ($message['name'] === 'Scam Detector' && $message['email'] === 'scamdetector@rentweb.com') ||
                        ($message['name'] === 'Expiration Monitor' && $message['email'] === 'expiration@rentweb.com')
                    );
                    error_log("messages.php: Displaying message id={$message['id']}, name={$message['name']}, email={$message['email']}");
                    ?>
                    <div class="box">
                        <p class="name">Name: <span><?= htmlspecialchars($message['name']); ?></span></p>
                        <p class="email">Email: <span><a href="mailto:<?= htmlspecialchars($message['email']); ?>"><?= htmlspecialchars($message['email']); ?></a></span></p>
                        <p class="number">Number: <span><a href="tel:<?= htmlspecialchars($message['number']); ?>"><?= htmlspecialchars($message['number']); ?></a></span></p>
                        <p class="message">Message: <span><?= safeHtml($message['message'], $isSystemNotification); ?></span></p>
                        <form action="" method="POST">
                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars($message['id']); ?>">
                            <input type="submit" value="Delete Message" onclick="return confirm('Delete this message?');" name="delete" class="delete-btn">
                        </form>
                    </div>
                    <?php
                }
            } else {
                echo '<p class="empty">No messages found!</p>';
                error_log("messages.php: No messages found for search='$search'");
            }
            ?>
        </div>
    </section>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script src="../../../public/js/admin_script.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>