<?php
session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/components/connect.php';

use App\Admin\Data\PropertyData;
use App\Admin\Data\UserData;
use App\Admin\Logic\PropertyLogic;

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$admin_id = $_SESSION['admin_id'];
$get_id = filter_var($_GET['get_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$get_id) {
    header('Location: dashboard.php');
    exit();
}

$propertyLogic = new PropertyLogic($conn);
$propertyData = new PropertyData($conn);
$userData = new UserData($conn);
$success_msg = [];
$warning_msg = [];

if (isset($_POST['delete'])) {
    $delete_id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);
    if ($delete_id === false) {
        $warning_msg[] = 'Invalid listing ID!';
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
    <title>Property Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/admin_style.css">
</head>
<body>
    <?php include ROOT_DIR . '/components/admin_header.php'; ?>
    <section class="view-property">
        <h1 class="heading">Property Details</h1>
        <div class="messages">
            <?php foreach ($warning_msg as $msg): ?>
                <p class="alert alert-warning"><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
            <?php foreach ($success_msg as $msg): ?>
                <p class="alert alert-success"><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
        <?php
        $property = $propertyData->getPropertyById($get_id);
        if ($property) {
            $property_id = $property['id'];
            $user = $userData->getUserById($property['user_id']);
            $suspicion_data = $propertyData->getSuspicionData($property_id);
            ?>
            <div class="details">
                <div class="swiper images-container">
                    <div class="swiper-wrapper">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if (!empty($property["image_0$i"])): ?>
                                <img src="../../../uploaded_files/<?= htmlspecialchars($property["image_0$i"]); ?>" alt="" class="swiper-slide">
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
                <h3 class="name"><?= htmlspecialchars($property['property_name']); ?></h3>
                <p class="location"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($property['address']); ?></span></p>
                <div class="info">
                    <p><i class="fas fa-euro-sign"></i><span><?= htmlspecialchars($property['price']); ?> EUR</span></p>
                    <p><i class="fas fa-user"></i><span><?= htmlspecialchars($user['name'] ?? 'Unknown'); ?></span></p>
                    <p><i class="fas fa-phone"></i><a href="tel:<?= htmlspecialchars($user['number'] ?? ''); ?>"><?= htmlspecialchars($user['number'] ?? 'N/A'); ?></a></p>
                    <p><i class="fas fa-building"></i><span><?= htmlspecialchars($property['type']); ?></span></p>
                    <p><i class="fas fa-house"></i><span><?= htmlspecialchars($property['offer']); ?></span></p>
                    <p><i class="fas fa-calendar"></i><span><?= htmlspecialchars($property['date']); ?></span></p>
                </div>
                <?php if ($suspicion_data): ?>
                    <h3 class="title">Suspicion Details</h3>
                    <div class="box">
                        <p><i class="fas fa-exclamation-triangle"></i><span>Score: <?= htmlspecialchars($suspicion_data['suspicion_score']); ?>/100</span></p>
                        <p><i class="fas fa-clock"></i><span>Flagged: <?= htmlspecialchars($suspicion_data['flagged_at']); ?></span></p>
                        <p><i class="fas fa-info-circle"></i><span>Status: <?= htmlspecialchars($suspicion_data['admin_review_status']); ?></span></p>
                        <a href="review_suspicious.php?property_id=<?= $property_id ?>" class="option-btn">Review Suspicious Listing</a>
                    </div>
                <?php endif; ?>
                <h3 class="title">Details</h3>
                <div class="flex">
                    <div class="box">
                        <p><i>Rooms:</i><span><?= htmlspecialchars($property['bhk']); ?> BHK</span></p>
                        <p><i>Deposit amount:</i><span>€<?= htmlspecialchars($property['deposite']); ?></span></p>
                        <p><i>Status:</i><span><?= htmlspecialchars($property['status']); ?></span></p>
                        <p><i>Bedroom:</i><span><?= htmlspecialchars($property['bedroom']); ?></span></p>
                        <p><i>Bathroom:</i><span><?= htmlspecialchars($property['bathroom']); ?></span></p>
                        <p><i>Balcony:</i><span><?= htmlspecialchars($property['balcony']); ?></span></p>
                    </div>
                    <div class="box">
                        <p><i>Carpet area:</i><span><?= htmlspecialchars($property['carpet']); ?> sqft</span></p>
                        <p><i>Age:</i><span><?= htmlspecialchars($property['age']); ?> years</span></p>
                        <p><i>Total floors:</i><span><?= htmlspecialchars($property['total_floors']); ?></span></p>
                        <p><i>Room floor:</i><span><?= htmlspecialchars($property['room_floor']); ?></span></p>
                        <p><i>Furnished:</i><span><?= htmlspecialchars($property['furnished']); ?></span></p>
                        <p><i>Loan:</i><span><?= htmlspecialchars($property['loan']); ?></span></p>
                    </div>
                </div>
                <h3 class="title">Amenities</h3>
                <div class="flex">
                    <div class="box">
                        <?php
                        $amenities1 = ['lift', 'security_guard', 'play_ground', 'garden', 'water_supply', 'power_backup'];
                        foreach ($amenities1 as $item) {
                            $icon = ($property[$item] === 'yes') ? 'check' : 'times';
                            echo "<p><i class='fas fa-$icon'></i><span>$item</span></p>";
                        }
                        ?>
                    </div>
                    <div class="box">
                        <?php
                        $amenities2 = ['parking_area', 'gym', 'shopping_mall', 'hospital', 'school', 'market_area'];
                        foreach ($amenities2 as $item) {
                            $icon = ($property[$item] === 'yes') ? 'check' : 'times';
                            echo "<p><i class='fas fa-$icon'></i><span>$item</span></p>";
                        }
                        ?>
                    </div>
                </div>
                <h3 class="title">Description</h3>
                <p class="description"><?= htmlspecialchars($property['description']); ?></p>
                <form action="" method="post" class="flex-btn">
                    <input type="hidden" name="delete_id" value="<?= $property_id; ?>">
                    <input type="submit" value="Delete Property" name="delete" class="delete-btn" onclick="return confirm('Delete this listing?');">
                </form>
            </div>
            <?php
        } else {
            echo '<p class="empty">🏠 Property not found! <a href="listings.php" class="option-btn">Go to listings</a></p>';
        }
        ?>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script src="../../../public/js/admin_script.js"></script>
    <?php include ROOT_DIR . '/components/message.php'; ?>
    <script>
        var swiper = new Swiper(".images-container", {
            effect: "coverflow",
            grabCursor: true,
            centeredSlides: true,
            slidesPerView: "auto",
            loop: true,
            coverflowEffect: {
                rotate: 0,
                stretch: 0,
                depth: 200,
                modifier: 3,
                slideShadows: true,
            },
            pagination: {
                el: ".swiper-pagination",
            },
        });
    </script>
</body>
</html>