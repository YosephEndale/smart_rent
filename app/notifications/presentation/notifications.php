<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/notifications/data/notification_logs.php';

use App\Notifications\Data\notification_logs;

$user_id = $_SESSION['user_id'] ?? '';
if (empty($user_id)) {
    header('Location: ' . ROOT_DIR . '/app/auth/presentation/login.php?error=Please log in to view notifications');
    exit;
}

$logs = new notification_logs(get_db_connection());
$notifications = $logs->getNotifications($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="/public/js/script.js"></script>
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>
<section class="notifications">
    <h2>Your Notifications</h2>
    <?php if (empty($notifications)): ?>
        <p>No notifications yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($notifications as $notif): ?>
                <li>
                    <p><?php echo htmlspecialchars($notif['message']); ?></p>
                    <?php if ($notif['property_id']): ?>
                        <p>Property: <?php echo htmlspecialchars($notif['property_name'] ?? 'Unknown'); ?></p>
                    <?php endif; ?>
                    <small><?php echo date('M d, Y H:i', strtotime($notif['sent_at'])); ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php include ROOT_DIR . '/components/footer.php'; ?>
</body>
</html>