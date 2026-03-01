<?php
session_start();
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/chat/logic/chat_processor.php';

use App\Chat\Logic\chat_processor;

$user_id = $_SESSION['user_id'] ?? '';
if (empty($user_id)) {
    header('Location: ' . ROOT_DIR . '/app/auth/presentation/login.php?error=Please log in to view chats');
    exit;
}

$chat_processor = new chat_processor();
$property_id = $_GET['property_id'] ?? '';
$other_user_id = $_GET['other_user_id'] ?? '';
$conversations = $chat_processor->getConversations($user_id);
$messages = [];
$error_msg = '';
$success_msg = '';

if (!empty($property_id)) {
    $property_query = $conn->prepare("SELECT id, user_id, property_name FROM `property` WHERE id = ?");
    $property_query->execute([$property_id]);
    $property = $property_query->fetch(PDO::FETCH_ASSOC);

    if (!$property) {
        $error_msg = "Property not found";
    } else {
        if (empty($other_user_id)) {
            $other_user_id = $property['user_id'];
        }

        if ($other_user_id == $user_id) {
            $error_msg = "You cannot chat with yourself";
        } else {
            $messages = $chat_processor->getMessages($user_id, $other_user_id, $property_id);
            if (isset($messages['error'])) {
                $error_msg = $messages['error'];
                $messages = [];
            } else {
                $chat_processor->markMessagesAsRead($user_id, $property_id);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && !empty($property_id) && !empty($other_user_id)) {
    $message = htmlspecialchars(trim($_POST['message']), ENT_QUOTES, 'UTF-8');
    $result = $chat_processor->sendMessage($user_id, $other_user_id, $property_id, $message);
    if (isset($result['error'])) {
        $error_msg = $result['error'];
    } else {
        header("Location: chat.php?property_id=$property_id&other_user_id=$other_user_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat Corner</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>
<section class="chat-page">
    <div class="conversation-list">
        <h3>Your Chats</h3>
        <?php if (empty($conversations)): ?>
            <p>No conversations yet.</p>
        <?php else: ?>
            <?php foreach ($conversations as $chat): ?>
                <div class="conversation-item <?= ($property_id == $chat['property_id'] && $other_user_id == $chat['other_user_id']) ? 'active' : '' ?>">
                    <a href="chat.php?property_id=<?= htmlspecialchars($chat['property_id']) ?>&other_user_id=<?= htmlspecialchars($chat['other_user_id']) ?>">
                        <?= htmlspecialchars($chat['property_name'] ?? 'Unknown Property') ?> (with <?= htmlspecialchars($chat['receiver_name'] ?? 'Unknown User') ?>)
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="chat-box">
        <h2>Chat</h2>
        <?php if (!empty($error_msg)): ?>
            <p class="error"><?= htmlspecialchars($error_msg) ?></p>
        <?php elseif (empty($property_id) || empty($other_user_id)): ?>
            <p>Select a chat to begin</p>
        <?php else: ?>
            <div class="chat-messages" id="chat-messages">
                <?php 
                $user_lang_stmt = $conn->prepare("SELECT language FROM users WHERE user_id = ?");
                $user_lang_stmt->execute([$user_id]);
                $user_language = $user_lang_stmt->fetch(PDO::FETCH_ASSOC)['language'] ?? 'en';

                foreach ($messages as $msg): 
                    $display_message = $msg['display_message'] ?? '[Error displaying message]';
                    $sender_name = $msg['sender_name'] ?? 'Unknown User';
                ?>
                    <div class="message <?= $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                        <p>
                            <strong><?= htmlspecialchars($sender_name) ?>:</strong> 
                            <span class="message-text"><?= htmlspecialchars($display_message) ?></span>
                        </p>
                        <small><?= date('M d, Y H:i', strtotime($msg['timestamp'] ?? 'now')) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="post" class="chat-form">
                <input type="text" name="message" placeholder="Type your message..." required>
                <button type="submit" name="send_message">Send</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
</script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
</body>
</html>