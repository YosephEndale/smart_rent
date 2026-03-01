<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/notifications/logic/SendNotification.php';

use App\Notifications\Logic\SendNotification;

// Initialize database connection
$conn = get_db_connection();
$notificationSender = new SendNotification($conn);

// Cache previous prices in a JSON file
$price_cache_file = __DIR__ . '/../cache/price_cache.json';
$previous_prices = file_exists($price_cache_file) ? json_decode(file_get_contents($price_cache_file), true) : [];

// Fetch current properties
$properties = $conn->query("SELECT id, price, property_name FROM property WHERE status = 'ready to move'")->fetchAll(PDO::FETCH_ASSOC);
$current_prices = [];
$price_drops = [];

foreach ($properties as $property) {
    $current_prices[$property['id']] = $property['price'];
    if (isset($previous_prices[$property['id']])) {
        $old_price = $previous_prices[$property['id']];
        $new_price = $property['price'];
        $drop_percent = (($old_price - $new_price) / $old_price) * 100;
        if ($drop_percent > 5) { // Notify for drops > 5%
            $price_drops[$property['id']] = [
                'old_price' => $old_price,
                'new_price' => $new_price,
                'property_name' => $property['property_name']
            ];
        }
    }
}

// Save current prices to cache
file_put_contents($price_cache_file, json_encode($current_prices));

// Notify users who saved or viewed properties with price drops
foreach ($price_drops as $property_id => $drop) {
    $select_users = $conn->prepare("
        SELECT DISTINCT ua.user_id, u.name
        FROM user_activity ua
        JOIN users u ON ua.user_id = u.user_id
        WHERE ua.property_id = ? AND ua.action IN ('view', 'save')
    ");
    $select_users->execute([$property_id]);

    while ($user = $select_users->fetch(PDO::FETCH_ASSOC)) {
        $message_text = "Price drop: <b>{$drop['property_name']}</b> now €{$drop['new_price']}/month (was €{$drop['old_price']}). Save €" . ($drop['old_price'] - $drop['new_price']) . "! <a href='/app/property/presentation/view_property.php?get_id=$property_id'>View Property</a>";
        $notificationSender->sendTelegramNotification($user['user_id'], $message_text, $property_id);
    }
}

error_log("Price drop check completed at " . date('Y-m-d H:i:s'));
?>