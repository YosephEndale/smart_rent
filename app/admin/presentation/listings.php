<?php
namespace App\Admin\Logic;

use App\Admin\Data\PropertyData;
use App\Admin\Data\UserData;

session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/components/connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$admin_id = $_SESSION['admin_id'];

$propertyLogic = new PropertyLogic($conn);
$propertyData = new PropertyData($conn);
$userData = new UserData($conn);
$success_msg = [];
$warning_msg = [];

if (isset($_POST['delete'])) {
    $delete_id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);
    if ($delete_id === false) {
        $warning_msg[] = 'Invalid listing ID! 🚫';
    } else {
        $result = $propertyLogic->deleteProperty($delete_id);
        if ($result['success']) {
            $success_msg[] = $result['message'];
        } else {
            $warning_msg[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="listings">
        <h1 class="heading">All Listings</h1>
        <div class="messages">
            <?php foreach ($warning_msg as $msg): ?>
                <p class="alert alert-warning"><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
            <?php foreach ($success_msg as $msg): ?>
                <p class="alert alert-success"><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
        <form action="" method="POST" class="search-form">
            <input type="text" name="search_box" placeholder="Search listings..." maxlength="100" required>
            <button type="submit" class="fas fa-search" name="search_btn"></button>
        </form>
        <div class="box-container">
            <?php
            $search = $_POST['search_box'] ?? '';
            $properties = $propertyData->getProperties($search);
            if ($properties) {
                foreach ($properties as $property) {
                    $listing_id = $property['id'];
                    $user = $userData->getUserById($property['user_id']);
                    $total_images = 1;
                    $total_images += !empty($property['image_02']) ? 1 : 0;
                    $total_images += !empty($property['image_03']) ? 1 : 0;
                    $total_images += !empty($property['image_04']) ? 1 : 0;
                    $total_images += !empty($property['image_05']) ? 1 : 0;
                    ?>
                    <div class="box">
                        <div class="thumb">
                            <p><i class="far fa-image"></i><span><?= htmlspecialchars($total_images); ?></span></p>
                            <img src="../../../uploaded_files/<?= htmlspecialchars($property['image_01']); ?>" alt="Property image">
                        </div>
                        <p class="price"><i class="fas fa-euro-sign"></i> 💶 <?= htmlspecialchars($property['price']); ?></p>
                        <h3 class="name"><?= htmlspecialchars($property['property_name']); ?></h3>
                        <p class="location"><i class="fas fa-map-marker-alt"></i> 📍 <?= htmlspecialchars($property['address']); ?></p>
                        <form action="" method="POST">
                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars($listing_id); ?>">
                            <a href="view_property.php?get_id=<?= $listing_id ?>" class="btn">View Property</a>
                            <input type="submit" value="Delete Listing" onclick="return confirm('Delete this listing?');" name="delete" class="delete-btn">
                        </form>
                    </div>
                    <?php
                }
            } elseif (isset($_POST['search_box']) || isset($_POST['search_btn'])) {
                echo '<p class="empty">No results found! 🚫</p>';
            } else {
                echo '<p class="empty">No properties posted yet! 🚫</p>';
            }
            ?>
        </div>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script src="../../../public/js/admin_script.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>