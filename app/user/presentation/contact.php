<?php
session_start(); // Start the session

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';

// Check if the user ID is stored in the session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = ''; // Default value if not set
}

// Define the create_unique_id function
function create_unique_id() {
    return uniqid('msg_', true);
}

if (isset($_POST['send'])) {
    $msg_id = create_unique_id();

    // Sanitize inputs using htmlspecialchars
    $name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');
    $number = htmlspecialchars($_POST['number'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8');

    // Check if the message already exists in the database
    $verify_contact = $conn->prepare("SELECT * FROM `messages` WHERE name = ? AND email = ? AND number = ? AND message = ?");
    $verify_contact->execute([$name, $email, $number, $message]);

    if ($verify_contact->rowCount() > 0) {
        $warning_msg[] = 'Message already sent!';
    } else {
        // Insert the message into the database
        $send_message = $conn->prepare("INSERT INTO `messages`(id, name, email, number, message) VALUES(?,?,?,?,?)");
        $send_message->execute([$msg_id, $name, $email, $number, $message]);
        $success_msg[] = 'Message sent successfully!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us 💌</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/css/style.css">
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="contact">
    <div class="row">
        <div class="image">
            <img src="<?= PUBLIC_URL ?>/images/contact.png" alt="A cute contact image">
        </div>
        <form action="" method="post">
            <h3>Let’s Chat! 💬</h3>
            <input type="text" name="name" required maxlength="50" placeholder="What’s your name? 😊" class="box">
            <input type="email" name="email" required maxlength="50" placeholder="Your email (so we can reply) ✨" class="box">
            <input type="number" name="number" required maxlength="10" max="9999999999" min="0" placeholder="Your phone number 📱" class="box">
            <textarea name="message" placeholder="Tell us what’s on your mind 💌" required maxlength="1000" cols="30" rows="10" class="box"></textarea>
            <input type="submit" value="Send Message 💌" name="send" class="btn">
        </form>
    </div>
</section>

<!-- FAQ section -->
<section class="faq" id="faq">
    <h1 class="heading">Frequently Asked Questions 💬</h1>
    <div class="box-container">
        <div class="box active">
            <h3><span>When will I get possession of my new home? 🏡</span><i class="fas fa-angle-down"></i></h3>
            <p>Great question! You’ll get the keys to your new place once everything’s finalized. We’ll keep you updated every step of the way! 📅</p>
        </div>
        <div class="box">
            <h3><span>How can I contact the landlords? 📞</span><i class="fas fa-angle-down"></i></h3>
            <p>Just send them a message directly through our platform. It’s fast, simple, and secure! 💬</p>
        </div>
        <div class="box">
            <h3><span>Why is my listing not showing up? 🤔</span><i class="fas fa-angle-down"></i></h3>
            <p>If your listing isn't showing, check for any issues with your account or contact our support team. We’ll get it fixed in no time! 💪</p>
        </div>
        <div class="box">
            <h3><span>How do I promote my listing? 🚀</span><i class="fas fa-angle-down"></i></h3>
            <p>To give your listing a boost, try our premium promotion options. More visibility = more chances to sell! 🎯</p>
        </div>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script src="<?= PUBLIC_URL ?>/js/script.js"></script>
<script>
    if (typeof swal === 'undefined') {
        console.error('SweetAlert failed to load from both CDN and local source.');
    } else {
        console.log('SweetAlert loaded successfully.');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const faqBoxes = document.querySelectorAll('.faq .box-container .box');
        faqBoxes.forEach(box => {
            box.addEventListener('click', () => {
                box.classList.toggle('active');
                faqBoxes.forEach(otherBox => {
                    if (otherBox !== box) {
                        otherBox.classList.remove('active');
                    }
                });
            });
        });
    });
</script>
<?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>