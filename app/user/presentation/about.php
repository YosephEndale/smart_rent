<?php
session_start(); // Start the session

// Check if the user ID is stored in the session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = ''; // Default value if not set
}

// Include Composer autoloader and env.php to define ROOT_DIR and PUBLIC_URL
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/css/style.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>
<section class="about">
    <div class="row">
        <div class="image">
            <img src="<?= PUBLIC_URL ?>/images/aboutus.webp" alt="About Us">
        </div>
        <div class="content">
            <h3>Why Choose Us? 🏡✨</h3>
            <p>Looking for your dream home? 🌟 We're here to make it happen! From cozy apartments to spacious houses, we’ve got something special for everyone. 💖 Our team is dedicated to helping you find the perfect place to call home. 😊</p>
            <a href="contact" class="inline-btn">Let's Chat! 💌</a>
        </div>
    </div>
</section>

<section class="steps">
    <h1 class="heading">3 Simple Steps to Your Dream Home! 🏡✨</h1>
    <div class="box-container">
        <div class="box">
            <img src="<?= PUBLIC_URL ?>/images/step-1.png" alt="search property">
            <h3>Search for Your Dream Property 🔍</h3>
            <p>Start by browsing through our beautiful listings! Find the perfect place that feels just right. 💖</p>
        </div>

        <div class="box">
            <img src="<?= PUBLIC_URL ?>/images/step-2.png" alt="chat with owner">
            <h3>Chat with the Owner 💬</h3>
            <p>Once you've found the one, talk directly with the owner to get all the details! It’s that easy. ✨</p>
        </div>

        <div class="box">
            <img src="<?= PUBLIC_URL ?>/images/step-3.png" alt="enjoy property">
            <h3>Enjoy Your New Home 🎉</h3>
            <p>Congratulations! You’re all set to move in and enjoy your new home. 🏠💫</p>
        </div>
    </div>
</section>

<?php include ROOT_DIR . '/components/footer.php'; ?>

<!-- custom js file links -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script src="<?= PUBLIC_URL ?>/js/sweetalert.min.js"></script>
<script src="<?= PUBLIC_URL ?>/js/script.js"></script>
<script>
    if (typeof swal === 'undefined') {
        console.error('SweetAlert failed to load from both CDN and local source.');
    } else {
        console.log('SweetAlert loaded successfully.');
    }
</script>
</body>
</html>