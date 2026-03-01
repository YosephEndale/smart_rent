<?php
ob_start(); // Start output buffering to prevent headers-already-sent errors
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/env.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/app/property/data/PropertyData.php';
require_once ROOT_DIR . '/app/property/logic/PropertyLogic.php';
require_once ROOT_DIR . '/app/notifications/logic/SendNotification.php';
require_once ROOT_DIR . '/app/scam/logic/ScamLogic.php';
require_once ROOT_DIR . '/components/save_send.php';

use App\Property\Data\PropertyData;
use App\Property\Logic\PropertyLogic;
use App\Notifications\Logic\SendNotification;
use App\Scam\Logic\ScamLogic;

$conn = get_db_connection();
$propertyLogic = new PropertyLogic($conn);
$propertyData = new PropertyData($conn);
$sendNotification = new SendNotification($conn);
$scamLogic = new ScamLogic($conn);

$user_id = $propertyLogic->validateUser($_SESSION['user_id'] ?? '');
$warning_msg = [];
$success_msg = [];
$field_errors = [];
$formData = [];

// Run automatic batch scam detection and expiration check
$lastRunFile = ROOT_DIR . '/cache/scam_detection_last_run.txt';
$runInterval = 3600; // Run every hour (3600 seconds)
$shouldRun = false;

if (!file_exists($lastRunFile) || (time() - filemtime($lastRunFile)) > $runInterval) {
    $shouldRun = true;
    if (!is_dir(ROOT_DIR . '/cache')) {
        mkdir(ROOT_DIR . '/cache', 0755, true);
    }
    file_put_contents($lastRunFile, time());
}

if ($shouldRun) {
    try {
        $batchResult = $scamLogic->runBatchScamDetection();
        $expirationResult = $scamLogic->checkExpiredProperties();
        error_log("post_property.php: Automatic batch scam detection and expiration check completed - batch_processed={$batchResult['processed']}, suspicious={$batchResult['suspicious']}, expired={$expirationResult['expired']}");
    } catch (Exception $e) {
        error_log("post_property.php: Automatic batch scam detection failed: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Log the raw property_name from POST
    error_log("post_property.php: Received property_name from POST: " . ($_POST['property_name'] ?? ''));
    
    $result = $propertyLogic->handlePostProperty($propertyData, $_POST, $_FILES, $user_id, [$sendNotification, 'sendTelegramNotification']);
    $formData = $result['formData'] ?? [];
    $warning_msg = $result['warning_msg'] ?? [];
    $success_msg = $result['success_msg'] ?? [];
    $field_errors = $result['field_errors'] ?? [];

    // Log the processed property_name in formData
    error_log("post_property.php: Processed property_name in formData: " . ($formData['property_name'] ?? ''));

    // Check if $result is an array and has 'success' key
    if (is_array($result) && isset($result['success']) && $result['success'] && isset($result['property_id'])) {
        $property_id = $result['property_id'];
        try {
            $scamResult = $scamLogic->checkSingleProperty($property_id);
            if ($scamResult['is_suspicious']) {
                $warning_msg[] = "This property has been flagged as potentially suspicious and is under review.";
                $stmt = $conn->prepare("UPDATE property SET status = 'pending_review' WHERE id = ?");
                $stmt->execute([$property_id]);
                error_log("post_property.php: Property propId=$property_id flagged as suspicious, score={$scamResult['suspicion_score']}");
            } else {
                $success_msg[] = "Property posted and verified as safe.";
                error_log("post_property.php: Property propId=$property_id verified, score={$scamResult['suspicion_score']}");
                $expirationResult = $scamLogic->checkSinglePropertyExpiration($property_id);
                if ($expirationResult['is_expired']) {
                    $warning_msg[] = "This property is marked as expired and cannot be posted.";
                    $stmt = $conn->prepare("UPDATE property SET status = 'expired' WHERE id = ?");
                    $stmt->execute([$property_id]);
                    error_log("post_property.php: Property propId=$property_id marked as expired");
                } else {
                    // Check if user is premium
                    $stmt = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $is_premium = $user['is_premium'] ?? 0;

                    // Redirect based on premium status
                    $redirect_url = $is_premium
                        ? '../../../app/screening/presentation/screening_preferences.php?success=property_posted&id=' . urlencode($property_id)
                        : '../../../index.php?success=property_posted';
                    error_log("post_property.php: Redirecting user_id=$user_id (is_premium=$is_premium) to $redirect_url");
                    header('Location: ' . $redirect_url);
                    exit;
                }
            }
        } catch (Exception $e) {
            $warning_msg[] = "Error checking property: " . $e->getMessage();
            error_log("post_property.php: Error checking propId=$property_id: " . $e->getMessage());
        }
    } elseif (is_array($result) && isset($result['success']) && !$result['success']) {
        $warning_msg[] = $result['message'] ?? 'Failed to post property.';
        error_log("post_property.php: Property posting failed: " . ($result['message'] ?? 'No message provided'));
    } else {
        $warning_msg[] = 'An unexpected error occurred while posting the property.';
        error_log("post_property.php: Unexpected result format: " . json_encode($result));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Property</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <style>
        .alert { background: #f8d7da; padding: 10px; margin-bottom: 10px; border: 1px solid; }
        .alert-success { background: #d4edda; border: 1px solid #28a745; }
        .error { color: #dc3545; font-size: 0.9em; margin-top: 5px; }
        .box { margin-bottom: 15px; }
        .input, select, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
    </style>
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>
<section class="property-form">
    <?php if (!empty($warning_msg)): ?>
        <div class="alert">
            <?php foreach ($warning_msg as $msg): ?>
                <p><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <?php foreach ($success_msg as $msg): ?>
                <p><?= htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form action="" method="POST" enctype="multipart/form-data">
        <h3>Property Details</h3>
        <div class="box">
            <p>Property Name <span>*</span></p>
            <input type="text" name="property_name" required maxlength="50" placeholder="Enter the property name" class="input" value="<?= htmlspecialchars(stripslashes($formData['property_name'] ?? '')); ?>">
            <?php if (isset($field_errors['property_name'])): ?>
                <p class="error"><?= htmlspecialchars($field_errors['property_name']); ?></p>
            <?php endif; ?>
        </div>
        <div class="flex">
            <div class="box">
                <p>Price <span>*</span></p>
                <input type="text" name="price" required placeholder="Enter the price" class="input" value="<?= htmlspecialchars($formData['price'] ?? ''); ?>">
                <?php if (isset($field_errors['price'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['price']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Deposit Amount <span>*</span></p>
                <input type="text" name="deposite" required placeholder="Enter the deposit" class="input" value="<?= htmlspecialchars($formData['deposite'] ?? ''); ?>">
                <?php if (isset($field_errors['deposite'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['deposite']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Address <span>*</span></p>
                <input type="text" name="address" id="autocomplete" required maxlength="100" placeholder="Enter the address (Google-powered)" class="input" value="<?= htmlspecialchars($formData['address'] ?? ''); ?>">
                <input type="hidden" name="place_id" id="place_id" value="<?= htmlspecialchars($formData['location_place_id'] ?? ''); ?>">
                <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($formData['latitude'] ?? ''); ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($formData['longitude'] ?? ''); ?>">
                <?php if (isset($field_errors['address']) || isset($field_errors['latitude'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['address'] ?? $field_errors['latitude']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Property Type <span>*</span></p>
                <select name="type" required class="input">
                    <option value="" disabled <?= empty($formData['type']) ? 'selected' : ''; ?>>Select property type</option>
                    <option value="flat" <?= isset($formData['type']) && $formData['type'] == 'flat' ? 'selected' : ''; ?>>Flat</option>
                    <option value="house" <?= isset($formData['type']) && $formData['type'] == 'house' ? 'selected' : ''; ?>>House</option>
                    <option value="shop" <?= isset($formData['type']) && $formData['type'] == 'shop' ? 'selected' : ''; ?>>Shop</option>
                </select>
                <?php if (isset($field_errors['type'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['type']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Offer Type <span>*</span></p>
                <select name="offer" required class="input">
                    <option value="" disabled <?= empty($formData['offer']) ? 'selected' : ''; ?>>Select offer type</option>
                    <option value="sale" <?= isset($formData['offer']) && $formData['offer'] == 'sale' ? 'selected' : ''; ?>>Sale</option>
                    <option value="rent" <?= isset($formData['offer']) && $formData['offer'] == 'rent' ? 'selected' : ''; ?>>Rent</option>
                </select>
                <?php if (isset($field_errors['offer'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['offer']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Property Status <span>*</span></p>
                <select name="status" required class="input">
                    <option value="" disabled <?= empty($formData['status']) ? 'selected' : ''; ?>>Select property status</option>
                    <option value="ready to move" <?= isset($formData['status']) && $formData['status'] == 'ready to move' ? 'selected' : ''; ?>>Ready to Move</option>
                    <option value="under construction" <?= isset($formData['status']) && $formData['status'] == 'under construction' ? 'selected' : ''; ?>>Under Construction</option>
                </select>
                <?php if (isset($field_errors['status'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['status']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Furnished Status <span>*</span></p>
                <select name="furnished" required class="input">
                    <option value="" disabled <?= empty($formData['furnished']) ? 'selected' : ''; ?>>Select furnished status</option>
                    <option value="furnished" <?= isset($formData['furnished']) && $formData['furnished'] == 'furnished' ? 'selected' : ''; ?>>Furnished</option>
                    <option value="semi-furnished" <?= isset($formData['furnished']) && $formData['furnished'] == 'semi-furnished' ? 'selected' : ''; ?>>Semi-furnished</option>
                    <option value="unfurnished" <?= isset($formData['furnished']) && $formData['furnished'] == 'unfurnished' ? 'selected' : ''; ?>>Unfurnished</option>
                </select>
                <?php if (isset($field_errors['furnished'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['furnished']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>BHK <span>*</span></p>
                <select name="bhk" required class="input">
                    <option value="" disabled <?= empty($formData['bhk']) ? 'selected' : ''; ?>>Select BHK</option>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= isset($formData['bhk']) && $formData['bhk'] == $i ? 'selected' : ''; ?>><?= $i ?> BHK</option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($field_errors['bhk'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['bhk']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Bedrooms <span>*</span></p>
                <select name="bedroom" required class="input">
                    <option value="" disabled <?= empty($formData['bedroom']) ? 'selected' : ''; ?>>Select bedrooms</option>
                    <?php for ($i = 0; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= isset($formData['bedroom']) && $formData['bedroom'] == $i ? 'selected' : ''; ?>><?= $i === 0 ? 'No Bedrooms' : "$i Bedroom" . ($i > 1 ? 's' : '') ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($field_errors['bedroom'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['bedroom']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Bathrooms <span>*</span></p>
                <select name="bathroom" required class="input">
                    <option value="" disabled <?= empty($formData['bathroom']) ? 'selected' : ''; ?>>Select bathrooms</option>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= isset($formData['bathroom']) && $formData['bathroom'] == $i ? 'selected' : ''; ?>><?= $i ?> Bathroom<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($field_errors['bathroom'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['bathroom']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Balconies <span>*</span></p>
                <select name="balcony" required class="input">
                    <option value="" disabled <?= empty($formData['balcony']) ? 'selected' : ''; ?>>Select balconies</option>
                    <?php for ($i = 0; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= isset($formData['balcony']) && $formData['balcony'] == $i ? 'selected' : ''; ?>><?= $i === 0 ? 'No Balcony' : "$i Balcony" . ($i > 1 ? 'ies' : '') ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($field_errors['balcony'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['balcony']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Carpet Area (sqft) <span>*</span></p>
                <input type="text" name="carpet" required placeholder="Enter carpet area" class="input" value="<?= htmlspecialchars($formData['carpet'] ?? ''); ?>">
                <?php if (isset($field_errors['carpet'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['carpet']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Property Age (years) <span>*</span></p>
                <input type="text" name="age" required placeholder="Enter property age" class="input" value="<?= htmlspecialchars($formData['age'] ?? ''); ?>">
                <?php if (isset($field_errors['age'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['age']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Total Floors <span>*</span></p>
                <input type="text" name="total_floors" required placeholder="Enter total floors" class="input" value="<?= htmlspecialchars($formData['total_floors'] ?? ''); ?>">
                <?php if (isset($field_errors['total_floors'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['total_floors']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Room Floor <span>*</span></p>
                <input type="text" name="room_floor" required placeholder="Enter floor number" class="input" value="<?= htmlspecialchars($formData['room_floor'] ?? ''); ?>">
                <?php if (isset($field_errors['room_floor'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['room_floor']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Loan Availability <span>*</span></p>
                <select name="loan" required class="input">
                    <option value="" disabled <?= empty($formData['loan']) ? 'selected' : ''; ?>>Select loan availability</option>
                    <option value="available" <?= isset($formData['loan']) && $formData['loan'] == 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="not available" <?= isset($formData['loan']) && $formData['loan'] == 'not available' ? 'selected' : ''; ?>>Not Available</option>
                </select>
                <?php if (isset($field_errors['loan'])): ?>
                    <p class="error"><?= htmlspecialchars($field_errors['loan']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="box">
            <p>Description <span>*</span></p>
            <textarea name="description" maxlength="1000" required class="input" cols="30" rows="10" placeholder="Enter a detailed description"><?= htmlspecialchars($formData['description'] ?? ''); ?></textarea>
            <?php if (isset($field_errors['description'])): ?>
                <p class="error"><?= htmlspecialchars($field_errors['description']); ?></p>
            <?php endif; ?>
        </div>
        <div class="checkbox">
            <div class="box">
                <?php foreach (['lift' => 'Elevator 🚀', 'security_guard' => 'Security Personnel 🛡️', 'play_ground' => 'Playground ⚽', 'garden' => 'Garden 🌳', 'water_supply' => 'Water Supply 💧', 'power_backup' => 'Power Backup ⚡️'] as $facility => $label): ?>
                    <p><input type="checkbox" name="<?= $facility ?>" value="yes" <?= isset($formData[$facility]) && $formData[$facility] == 'yes' ? 'checked' : ''; ?> /> <?= $label ?></p>
                <?php endforeach; ?>
            </div>
            <div class="box">
                <?php foreach (['parking_area' => 'Parking Area 🚗', 'gym' => 'Gym 💪', 'shopping_mall' => 'Shopping Mall 🛍️', 'hospital' => 'Hospital 🩺', 'school' => 'School 📚', 'market_area' => 'Market Area 🏪'] as $facility => $label): ?>
                    <p><input type="checkbox" name="<?= $facility ?>" value="yes" <?= isset($formData[$facility]) && $formData[$facility] == 'yes' ? 'checked' : ''; ?> /> <?= $label ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="box">
            <p>Primary Image <span>*</span></p>
            <input type="file" name="image_01" class="input" accept="image/jpeg,image/jpg,image/png,image/gif" required>
            <?php if (isset($field_errors['image_01'])): ?>
                <p class="error"><?= htmlspecialchars($field_errors['image_01']); ?></p>
            <?php endif; ?>
        </div>
        <div class="flex">
            <?php for ($i = 2; $i <= 5; $i++): ?>
                <div class="box">
                    <p>Additional Image <?= $i - 1 ?></p>
                    <input type="file" name="image_0<?= $i ?>" class="input" accept="image/jpeg,image/jpg,image/png,image/gif">
                    <?php if (isset($field_errors["image_0$i"])): ?>
                        <p class="error"><?= htmlspecialchars($field_errors["image_0$i"]); ?></p>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        <input type="submit" value="Submit Property Listing" name="submit" class="btn">
    </form>
</section>
<?php include ROOT_DIR . '/components/footer.php'; ?>
<?php include ROOT_DIR . '/components/message.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script>
function initAutocomplete() {
    const autocomplete = new google.maps.places.Autocomplete(document.getElementById('autocomplete'), {
        types: ['geocode', 'establishment'],
        fields: ['place_id', 'geometry', 'name', 'formatted_address']
    });
    autocomplete.addListener('place_changed', function() {
        const place = autocomplete.getPlace();
        if (!place.geometry) return;
        document.getElementById('place_id').value = place.place_id || '';
        document.getElementById('latitude').value = place.geometry.location.lat();
        document.getElementById('longitude').value = place.geometry.location.lng();
        document.getElementById('autocomplete').value = place.formatted_address || place.name;
    });
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(getenv('GOOGLE_API_KEY') ?: getenv('GOOGLE_MAPS_API_KEY') ?: '', ENT_QUOTES, 'UTF-8'); ?>&libraries=places&callback=initAutocomplete" async defer></script>
</body>
</html>
<?php ob_end_flush();  ?>