<?php
session_start();

// Include autoload and environment configuration
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';

// Include necessary files
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/notifications/logic/SendNotification.php';
require_once ROOT_DIR . '/app/scam/logic/ScamLogic.php';

use App\Notifications\Logic\SendNotification;
use App\Scam\Logic\ScamLogic;

// Check user authentication
$user_id = $_SESSION['user_id'] ?? '';
if (!$user_id) {
    header('Location: ' . PUBLIC_URL . '/app/auth/presentation/login.php');
    exit;
}

$get_id = filter_var($_GET['get_id'] ?? '', FILTER_VALIDATE_INT);
if (!$get_id) {
    header('Location: ' . PUBLIC_URL . '/index.php');
    exit;
}

// Hardcode Google Translate API key (consider moving to environment variable in production)
$apiKey = $_ENV['GOOGLE_TRANSLATE_API_KEY'] ?? 'AIzaSyDtKz7XS7a0qGgeP3DHbg84DQQrXOH3Zw4';

// Define the URL path for uploaded files
define('UPLOADED_FILES_URL', '/uploaded_files');

// Function to call Google Translate API
function translateText($text, $apiKey, $target = 'en', $source = 'it') {
    if (empty($text) || empty($apiKey)) {
        error_log("Translation skipped: Empty text or API key");
        return false;
    }

    $maxLength = 5000;
    if (strlen($text) > $maxLength) {
        $chunks = str_split($text, $maxLength);
        $translatedChunks = [];
        foreach ($chunks as $chunk) {
            $translatedChunk = translateText($chunk, $apiKey, $target, $source);
            if ($translatedChunk === false) {
                error_log("Failed to translate chunk: $chunk");
                return false;
            }
            $translatedChunks[] = $translatedChunk;
        }
        return implode('', $translatedChunks);
    }

    $url = 'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($apiKey);
    $postData = [
        'q' => $text,
        'source' => $source,
        'target' => $target,
        'format' => 'text',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("cURL error during translation: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Translation API returned HTTP code: $httpCode, Response: $response");
        return false;
    }

    $result = json_decode($response, true);
    if (isset($result['data']['translations'][0]['translatedText'])) {
        return $result['data']['translations'][0]['translatedText'];
    }

    error_log("Translation API error: " . json_encode($result));
    return false;
}

// Function to generate unique ID for images
function create_unique_id() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < 20; $i++) {
        $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$success_msg = [];
$warning_msg = [];

// Fetch current property details for comparison
$current_property = $conn->prepare("SELECT status, price, property_name FROM property WHERE id = ? AND user_id = ?");
$current_property->execute([$get_id, $user_id]);
$property_data = $current_property->fetch(PDO::FETCH_ASSOC);
if (!$property_data) {
    $warning_msg[] = 'Property not found or you do not have permission to edit it.';
    error_log("Property not found or unauthorized: property_id=$get_id, user_id=$user_id");
}

if (isset($_POST['update'])) {
    $update_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
    $property_name = htmlspecialchars(trim($_POST['property_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $price = filter_var($_POST['price'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $deposite = filter_var($_POST['deposite'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $address = htmlspecialchars(trim($_POST['address'] ?? ''), ENT_QUOTES, 'UTF-8');
    $place_id = htmlspecialchars(trim($_POST['place_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $latitude = filter_var($_POST['latitude'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $longitude = filter_var($_POST['longitude'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $offer = htmlspecialchars(trim($_POST['offer'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars(trim($_POST['type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars(trim($_POST['status'] ?? ''), ENT_QUOTES, 'UTF-8');
    $furnished = htmlspecialchars(trim($_POST['furnished'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bhk = filter_var($_POST['bhk'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $bedroom = filter_var($_POST['bedroom'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $bathroom = filter_var($_POST['bathroom'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $balcony = filter_var($_POST['balcony'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $carpet = filter_var($_POST['carpet'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $age = filter_var($_POST['age'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $total_floors = filter_var($_POST['total_floors'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $room_floor = filter_var($_POST['room_floor'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $loan = htmlspecialchars(trim($_POST['loan'] ?? ''), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Validate inputs
    $numericFields = [
        'price' => ['max' => 9999999.99, 'error' => 'Price must be a valid number (e.g., 500 or 500.99)'],
        'deposite' => ['max' => 9999999.99, 'error' => 'Deposit must be a valid number (e.g., 500 or 500.99)'],
        'bhk' => ['max' => 9, 'error' => 'BHK must be between 1 and 9'],
        'bedroom' => ['max' => 9, 'error' => 'Bedroom count must be between 0 and 9'],
        'bathroom' => ['max' => 9, 'error' => 'Bathroom count must be between 1 and 9'],
        'balcony' => ['max' => 9, 'error' => 'Balcony count must be between 0 and 9'],
        'carpet' => ['max' => 9999999, 'error' => 'Carpet area must be between 1 and 9999999'],
        'age' => ['max' => 99, 'error' => 'Property age must be between 0 and 99'],
        'total_floors' => ['max' => 99, 'error' => 'Total floors must be between 0 and 99'],
        'room_floor' => ['max' => 99, 'error' => 'Room floor must be between 0 and 99']
    ];
    foreach ($numericFields as $field => $config) {
        if (!is_numeric($$field) || $$field < 0 || $$field > $config['max']) {
            $warning_msg[] = $config['error'];
            error_log("Validation failed: Invalid $field = " . ($$field ?? 'null'));
        }
    }

    $requiredFields = [
        'property_name' => 'Property name cannot be empty',
        'address' => 'Address cannot be empty',
        'place_id' => 'Location place ID cannot be empty',
        'type' => 'Property type cannot be empty',
        'offer' => 'Offer type cannot be empty',
        'status' => 'Property status cannot be empty',
        'furnished' => 'Furnished status cannot be empty',
        'loan' => 'Loan availability cannot be empty',
        'description' => 'Description cannot be empty'
    ];
    foreach ($requiredFields as $field => $errorMsg) {
        if (empty($$field)) {
            $warning_msg[] = $errorMsg;
            error_log("Validation failed: $field is empty");
        }
    }

    // Validate select field values
    $validOptions = [
        'type' => ['flat', 'house', 'shop'],
        'offer' => ['sale', 'rent'],
        'status' => ['ready to move', 'under construction', 'rented', 'sold'],
        'furnished' => ['furnished', 'semi-furnished', 'unfurnished'],
        'loan' => ['available', 'not available']
    ];
    foreach ($validOptions as $field => $options) {
        if (!empty($$field) && !in_array($$field, $options)) {
            $warning_msg[] = "Invalid value for $field";
            error_log("Validation failed: Invalid $field = " . ($$field ?? 'null'));
        }
    }

    // Translate property name and description
    if (empty($warning_msg)) {
        $translated_property_name = translateText($property_name, $apiKey, 'en', 'it');
        if ($translated_property_name !== false) {
            $property_name = $translated_property_name;
        } else {
            $warning_msg[] = 'Failed to translate property name to English. Using original text.';
            error_log("Translation failed for property_name: $property_name");
        }

        $translated_description = translateText($description, $apiKey, 'en', 'it');
        if ($translated_description !== false) {
            $description = $translated_description;
        } else {
            $warning_msg[] = 'Failed to translate description to English. Using original text.';
            error_log("Translation failed for description: $description");
        }

        // Handle facilities
        $facilities = [
            'lift', 'security_guard', 'play_ground', 'garden',
            'water_supply', 'power_backup', 'parking_area', 'gym',
            'shopping_mall', 'hospital', 'school', 'market_area'
        ];
        foreach ($facilities as $facility) {
            $$facility = isset($_POST[$facility]) ? 'yes' : 'no';
        }

        // Handle image uploads
        for ($i = 1; $i <= 5; $i++) {
            $image_key = "image_0$i";
            $old_image = htmlspecialchars(trim($_POST["old_$image_key"] ?? ''), ENT_QUOTES, 'UTF-8');
            $image = $_FILES[$image_key]['name'] ?? '';
            if (!empty($image)) {
                $image_ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($image_ext, $allowed_extensions)) {
                    $warning_msg[] = "Invalid image type for Image 0$i. Allowed types: jpg, jpeg, png, gif.";
                    error_log("Invalid image type for $image_key: $image_ext");
                    continue;
                }
                $new_image_name = create_unique_id() . '.' . $image_ext;
                $image_tmp = $_FILES[$image_key]['tmp_name'];
                $image_size = $_FILES[$image_key]['size'];
                $image_folder = ROOT_DIR . "/uploaded_files/$new_image_name";

                if ($image_size > 2000000) {
                    $warning_msg[] = "Image 0$i size is too large (max 2MB).";
                    error_log("Image too large for $image_key: $image_size bytes");
                } else {
                    if (move_uploaded_file($image_tmp, $image_folder)) {
                        $update_image = $conn->prepare("UPDATE `property` SET $image_key = ? WHERE id = ?");
                        $update_image->execute([$new_image_name, $update_id]);
                        if (!empty($old_image) && file_exists(ROOT_DIR . "/uploaded_files/$old_image")) {
                            unlink(ROOT_DIR . "/uploaded_files/$old_image");
                        }
                    } else {
                        $warning_msg[] = "Failed to upload Image 0$i.";
                        error_log("Failed to upload $image_key to $image_folder");
                    }
                }
            }
        }

        // Update property details
        $conn->beginTransaction();
        try {
            $update_listing = $conn->prepare("
                UPDATE `property` SET 
                    property_name = ?, address = ?, location_place_id = ?, location_lat = ?, location_lng = ?,
                    price = ?, type = ?, offer = ?, status = ?, furnished = ?, bhk = ?, deposite = ?,
                    bedroom = ?, bathroom = ?, balcony = ?, carpet = ?, age = ?, total_floors = ?,
                    room_floor = ?, loan = ?, lift = ?, security_guard = ?, play_ground = ?, garden = ?,
                    water_supply = ?, power_backup = ?, parking_area = ?, gym = ?, shopping_mall = ?,
                    hospital = ?, school = ?, market_area = ?, description = ?
                WHERE id = ? AND user_id = ?
            ");
            $success = $update_listing->execute([
                $property_name, $address, $place_id, $latitude, $longitude, $price, $type, $offer,
                $status, $furnished, $bhk, $deposite, $bedroom, $bathroom, $balcony, $carpet,
                $age, $total_floors, $room_floor, $loan, $lift, $security_guard, $play_ground,
                $garden, $water_supply, $power_backup, $parking_area, $gym, $shopping_mall,
                $hospital, $school, $market_area, $description, $update_id, $user_id
            ]);

            if ($success && $update_listing->rowCount() > 0) {
                // Run scam detection if key fields changed
                $key_fields = ['price', 'description', 'carpet', 'image_01', 'image_02', 'image_03', 'image_04', 'image_05'];
                $stmt = $conn->prepare("SELECT " . implode(',', $key_fields) . " FROM property WHERE id = ?");
                $stmt->execute([$update_id]);
                $old_property = $stmt->fetch(PDO::FETCH_ASSOC);
                $fields_changed = $old_property['price'] != $price || $old_property['description'] != $description || $old_property['carpet'] != $carpet;
                for ($i = 1; $i <= 5; $i++) {
                    $image_key = "image_0$i";
                    if (!empty($_FILES[$image_key]['name']) && $_FILES[$image_key]['size'] > 0) {
                        $fields_changed = true;
                        break;
                    }
                }
                if ($fields_changed) {
                    $scamLogic = new ScamLogic($conn);
                    $scam_result = $scamLogic->runScamDetection($update_id, 'update');
                    if (!$scam_result['success']) {
                        error_log("Scam detection failed on update: property_id=$update_id, error=" . $scam_result['message']);
                    } else {
                        error_log("Scam detection succeeded for property_id=$update_id: score=" . ($scam_result['score'] ?? 'N/A'));
                    }
                }

                // Notify landlord if status changed
                if ($property_data['status'] != $status) {
                    $notification = new SendNotification($conn);
                    $message_text = "Status update: Your property <b>{$property_data['property_name']}</b> is now <b>$status</b>. <a href='http://rentalweb" . PUBLIC_URL . "/app/property/presentation/view_property.php?get_id=$update_id'>View Property</a>";
                    $notification->sendTelegramNotification($user_id, $message_text, $update_id);
                }

                $conn->commit();
                $success_msg[] = 'Property updated successfully!';
                error_log("Property updated successfully: property_id=$update_id, user_id=$user_id");
            } else {
                $conn->rollBack();
                $warning_msg[] = 'Failed to update property or no changes made.';
                error_log("Update failed or no changes: property_id=$update_id, user_id=$user_id");
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $warning_msg[] = 'Database error: ' . htmlspecialchars($e->getMessage());
            error_log("Database error in update_property.php: " . $e->getMessage());
        }
    }
}

// Handle image deletion
for ($i = 2; $i <= 5; $i++) {
    if (isset($_POST["delete_image_0$i"])) {
        $old_image = htmlspecialchars(trim($_POST["old_image_0$i"] ?? ''), ENT_QUOTES, 'UTF-8');
        $update_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
        
        if (empty($update_id)) {
            $warning_msg[] = "Cannot delete Image 0$i: Invalid property ID.";
            error_log("Image deletion failed: Invalid property_id for image_0$i, user_id=$user_id");
            continue;
        }

        if (empty($old_image)) {
            $warning_msg[] = "Cannot delete Image 0$i: No image specified.";
            error_log("Image deletion failed: No old_image for image_0$i, property_id=$update_id");
            continue;
        }

        $update = $conn->prepare("UPDATE `property` SET image_0$i = '' WHERE id = ? AND user_id = ?");
        try {
            $success = $update->execute([$update_id, $user_id]);
            if ($success && $update->rowCount() > 0) {
                $image_path = ROOT_DIR . "/uploaded_files/$old_image";
                if (file_exists($image_path)) {
                    if (unlink($image_path)) {
                        $success_msg[] = "Image 0$i deleted successfully!";
                        error_log("Image 0$i deleted successfully: property_id=$update_id, user_id=$user_id, file=$image_path");
                    } else {
                        $warning_msg[] = "Image 0$i removed from database but failed to delete file.";
                        error_log("Image deletion failed: Could not unlink file $image_path for image_0$i, property_id=$update_id");
                    }
                } else {
                    $success_msg[] = "Image 0$i removed from database, but file was not found.";
                    error_log("Image deletion: File $image_path not found for image_0$i, property_id=$update_id");
                }

                // Run scam detection
                $scamLogic = new ScamLogic($conn);
                $scam_result = $scamLogic->runScamDetection($update_id, 'update');
                if (!$scam_result['success']) {
                    error_log("Scam detection failed on image delete: property_id=$update_id, error=" . $scam_result['message']);
                } else {
                    error_log("Scam detection succeeded for image_0$i delete: property_id=$update_id, score=" . ($scam_result['score'] ?? 'N/A'));
                }
            } else {
                $warning_msg[] = "Failed to delete Image 0$i from database.";
                error_log("Image deletion failed: No rows affected for image_0$i, property_id=$update_id, user_id=$user_id");
            }
        } catch (PDOException $e) {
            $warning_msg[] = "Database error while deleting Image 0$i: " . htmlspecialchars($e->getMessage());
            error_log("Database error in image_0$i deletion: " . $e->getMessage() . ", property_id=$update_id, user_id=$user_id");
        }
    }
}

$_SESSION['success_msg'] = $success_msg;
$_SESSION['warning_msg'] = $warning_msg;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Property</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .alert { background: #f8d7da; padding: 10px; margin-bottom: 10px; border: 1px solid #dc3545; }
        .alert-success { background: #d4edda; border: 1px solid #28a745; }
        .error { color: #dc3545; font-size: 0.9em; margin-top: 5px; }
        .box { margin-bottom: 15px; }
        .input, select, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        .image { max-width: 100%; height: auto; }
    </style>
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>
<section class="property-form">
    <?php
    $select_properties = $conn->prepare("SELECT * FROM `property` WHERE id = ? AND user_id = ?");
    $select_properties->execute([$get_id, $user_id]);
    if ($select_properties->rowCount() > 0) {
        $fetch_property = $select_properties->fetch(PDO::FETCH_ASSOC);
    ?>
    <form action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="property_id" value="<?= htmlspecialchars($fetch_property['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="old_image_01" value="<?= htmlspecialchars($fetch_property['image_01'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="old_image_02" value="<?= htmlspecialchars($fetch_property['image_02'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="old_image_03" value="<?= htmlspecialchars($fetch_property['image_03'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="old_image_04" value="<?= htmlspecialchars($fetch_property['image_04'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="old_image_05" value="<?= htmlspecialchars($fetch_property['image_05'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <h3>Update Your Property</h3>
        <?php if (!empty($_SESSION['warning_msg'])): ?>
            <div class="alert">
                <?php foreach ($_SESSION['warning_msg'] as $msg): ?>
                    <p><?= htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success_msg'])): ?>
            <div class="alert alert-success">
                <?php foreach ($_SESSION['success_msg'] as $msg): ?>
                    <p><?= htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="box">
            <p>Property Name <span>*</span></p>
            <input type="text" name="property_name" required maxlength="50" placeholder="Enter property name (in Italian)" class="input" value="<?= htmlspecialchars($fetch_property['property_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="flex">
            <div class="box">
                <p>Property Price <span>*</span></p>
                <input type="text" name="price" required placeholder="Enter price (e.g., 500 or 500.99)" class="input" value="<?= htmlspecialchars($fetch_property['price'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="box">
                <p>Deposit Amount <span>*</span></p>
                <input type="text" name="deposite" required placeholder="Enter deposit (e.g., 500 or 500.99)" class="input" value="<?= htmlspecialchars($fetch_property['deposite'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="box">
                <p>Property Address <span>*</span></p>
                <input type="text" name="address" id="autocomplete" required maxlength="100" placeholder="Enter address (Google-powered)" class="input" value="<?= htmlspecialchars($fetch_property['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="place_id" id="place_id" value="<?= htmlspecialchars($fetch_property['location_place_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($fetch_property['location_lat'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($fetch_property['location_lng'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="box">
                <p>Offer Type <span>*</span></p>
                <select name="offer" required class="input">
                    <option value="" disabled>Select offer type</option>
                    <option value="sale" <?= ($fetch_property['offer'] ?? '') === 'sale' ? 'selected' : ''; ?>>For Sale</option>
                    <option value="rent" <?= ($fetch_property['offer'] ?? '') === 'rent' ? 'selected' : ''; ?>>For Rent</option>
                </select>
            </div>
            <div class="box">
                <p>Property Type <span>*</span></p>
                <select name="type" required class="input">
                    <option value="" disabled>Select property type</option>
                    <option value="flat" <?= ($fetch_property['type'] ?? '') === 'flat' ? 'selected' : ''; ?>>Flat</option>
                    <option value="house" <?= ($fetch_property['type'] ?? '') === 'house' ? 'selected' : ''; ?>>House</option>
                    <option value="shop" <?= ($fetch_property['type'] ?? '') === 'shop' ? 'selected' : ''; ?>>Shop</option>
                </select>
            </div>
            <div class="box">
                <p>Property Status <span>*</span></p>
                <select name="status" required class="input">
                    <option value="" disabled>Select property status</option>
                    <option value="ready to move" <?= ($fetch_property['status'] ?? '') === 'ready to move' ? 'selected' : ''; ?>>Ready to Move</option>
                    <option value="under construction" <?= ($fetch_property['status'] ?? '') === 'under construction' ? 'selected' : ''; ?>>Under Construction</option>
                    <option value="rented" <?= ($fetch_property['status'] ?? '') === 'rented' ? 'selected' : ''; ?>>Rented</option>
                    <option value="sold" <?= ($fetch_property['status'] ?? '') === 'sold' ? 'selected' : ''; ?>>Sold</option>
                </select>
            </div>
            <div class="box">
                <p>Furnished Status <span>*</span></p>
                <select name="furnished" required class="input">
                    <option value="" disabled>Select furnished status</option>
                    <option value="furnished" <?= ($fetch_property['furnished'] ?? '') === 'furnished' ? 'selected' : ''; ?>>Fully Furnished</option>
                    <option value="semi-furnished" <?= ($fetch_property['furnished'] ?? '') === 'semi-furnished' ? 'selected' : ''; ?>>Semi-Furnished</option>
                    <option value="unfurnished" <?= ($fetch_property['furnished'] ?? '') === 'unfurnished' ? 'selected' : ''; ?>>Unfurnished</option>
                </select>
            </div>
            <div class="box">
                <p>Number of BHK <span>*</span></p>
                <select name="bhk" required class="input">
                    <option value="" disabled>Select BHK</option>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= ($fetch_property['bhk'] ?? '') == $i ? 'selected' : ''; ?>><?= $i ?> BHK</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="box">
                <p>Number of Bedrooms <span>*</span></p>
                <select name="bedroom" required class="input">
                    <option value="" disabled>Select bedrooms</option>
                    <?php for ($i = 0; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= ($fetch_property['bedroom'] ?? '') == $i ? 'selected' : ''; ?>><?= $i === 0 ? 'No Bedrooms' : "$i Bedroom" . ($i > 1 ? 's' : '') ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="box">
                <p>Number of Bathrooms <span>*</span></p>
                <select name="bathroom" required class="input">
                    <option value="" disabled>Select bathrooms</option>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= ($fetch_property['bathroom'] ?? '') == $i ? 'selected' : ''; ?>><?= $i ?> Bathroom<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="box">
                <p>Number of Balconies <span>*</span></p>
                <select name="balcony" required class="input">
                    <option value="" disabled>Select balconies</option>
                    <?php for ($i = 0; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= ($fetch_property['balcony'] ?? '') == $i ? 'selected' : ''; ?>><?= $i === 0 ? 'No Balcony' : "$i Balcony" . ($i > 1 ? 'ies' : '') ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="box">
                <p>Carpet Area <span>*</span></p>
                <input type="text" name="carpet" required placeholder="Enter carpet area (sq ft, e.g., 1000)" class="input" value="<?= htmlspecialchars($fetch_property['carpet'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="box">
                <p>Property Age <span>*</span></p>
                <input type="text" name="age" required placeholder="Enter property age (years, e.g., 5)" class="input" value="<?= htmlspecialchars($fetch_property['age'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="box">
                <p>Total Floors <span>*</span></p>
                <input type="text" name="total_floors" required placeholder="Enter total floors (e.g., 10)" class="input" value="<?= htmlspecialchars($fetch_property['total_floors'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="box">
                <p>Floor Number <span>*</span></p>
                <input type="text" name="room_floor" required placeholder="Enter floor number (e.g., 3)" class="input" value="<?= htmlspecialchars($fetch_property['room_floor'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="box">
                <p>Loan Availability <span>*</span></p>
                <select name="loan" required class="input">
                    <option value="" disabled>Select loan availability</option>
                    <option value="available" <?= ($fetch_property['loan'] ?? '') === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="not available" <?= ($fetch_property['loan'] ?? '') === 'not available' ? 'selected' : ''; ?>>Not Available</option>
                </select>
            </div>
        </div>
        <div class="box">
            <p>Property Description <span>*</span></p>
            <textarea name="description" maxlength="1000" class="input" required cols="30" rows="10" placeholder="Enter description (in Italian)"><?= htmlspecialchars($fetch_property['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="checkbox">
            <div class="box">
                <?php foreach (['lift' => 'Elevator 🚀', 'security_guard' => 'Security Personnel 🛡️', 'play_ground' => 'Playground ⚽', 'garden' => 'Garden 🌳', 'water_supply' => 'Water Supply 💧', 'power_backup' => 'Power Backup ⚡️'] as $facility => $label): ?>
                    <p><input type="checkbox" name="<?= $facility ?>" value="yes" <?= ($fetch_property[$facility] ?? '') === 'yes' ? 'checked' : ''; ?> /> <?= $label ?></p>
                <?php endforeach; ?>
            </div>
            <div class="box">
                <?php foreach (['parking_area' => 'Parking Area 🚗', 'gym' => 'Gym 💪', 'shopping_mall' => 'Shopping Mall 🛍️', 'hospital' => 'Hospital 🩺', 'school' => 'School 📚', 'market_area' => 'Market Area 🏪'] as $facility => $label): ?>
                    <p><input type="checkbox" name="<?= $facility ?>" value="yes" <?= ($fetch_property[$facility] ?? '') === 'yes' ? 'checked' : ''; ?> /> <?= $label ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="box">
            <p>Primary Image</p>
            <?php if (!empty($fetch_property['image_01'])): ?>
                <img src="<?= UPLOADED_FILES_URL . '/' . htmlspecialchars($fetch_property['image_01'], ENT_QUOTES, 'UTF-8'); ?>" class="image" alt="Primary Image">
            <?php else: ?>
                <p>No primary image available</p>
            <?php endif; ?>
            <input type="file" name="image_01" class="input" accept="image/jpeg,image/jpg,image/png,image/gif">
        </div>
        <div class="flex">
            <?php for ($i = 2; $i <= 5; $i++): ?>
                <div class="box">
                    <?php if (!empty($fetch_property["image_0$i"])): ?>
                        <img src="<?= UPLOADED_FILES_URL . '/' . htmlspecialchars($fetch_property["image_0$i"], ENT_QUOTES, 'UTF-8'); ?>" class="image" alt="Image <?= $i ?>">
                        <input type="hidden" name="old_image_0<?= $i ?>" value="<?= htmlspecialchars($fetch_property["image_0$i"] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="submit" value="Delete Image <?= $i ?>" name="delete_image_0<?= $i ?>" class="inline-btn" onclick="return confirm('Delete Image <?= $i ?>?');">
                    <?php else: ?>
                        <p>No image available</p>
                    <?php endif; ?>
                    <p>Additional Image <?= $i - 1 ?></p>
                    <input type="file" name="image_0<?= $i ?>" class="input" accept="image/jpeg,image/jpg,image/png,image/gif">
                </div>
            <?php endfor; ?>
        </div>
        <input type="submit" value="Update Property" class="btn" name="update">
    </form>
    <?php
    } else {
        echo '<p class="empty">Property not found or you do not have permission to edit it.</p>';
    }
    unset($_SESSION['success_msg'], $_SESSION['warning_msg']);
    ?>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script>
    function initAutocomplete() {
        const autocomplete = new google.maps.places.Autocomplete(document.getElementById('autocomplete'));
        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();
            if (!place.geometry) {
                console.warn('No geometry data for place:', place);
                return;
            }
            document.getElementById('place_id').value = place.place_id || '';
            document.getElementById('latitude').value = place.geometry.location.lat();
            document.getElementById('longitude').value = place.geometry.location.lng();
        });
    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($_ENV['GOOGLE_MAPS_API_KEY'] ?? 'AIzaSyAG_SiDgz9Rp5HZld5PKKlEesaDTEbojWs', ENT_QUOTES, 'UTF-8'); ?>&libraries=places&callback=initAutocomplete" async defer></script>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<script src="<?= PUBLIC_URL ?>/js/script.js"></script>
<?php include ROOT_DIR . '/components/message.php'; ?>
</body>
</html>