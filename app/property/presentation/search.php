<?php
namespace App\User\Presentation;

use PDO;
use Exception;
use App\Property\Logic\PropertyLogic;
use App\Property\Data\PropertyData;
use App\User\Logic\UserLogic;

ob_start();

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/components/currency.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '';
$conn = get_db_connection();
$propertyData = new PropertyData($conn);
$propertyLogic = new PropertyLogic($conn);
$userLogic = new UserLogic($conn);
$warning_msg = $_SESSION['warning_msg'] ?? [];
$success_msg = $_SESSION['success_msg'] ?? [];
$_SESSION['warning_msg'] = [];
$_SESSION['success_msg'] = [];

if (isset($_POST['filter_search'])) {
    $result = $propertyLogic->validateSearchForm($_POST);
    if (!$result['success']) {
        $warning_msg = array_merge($warning_msg, $result['warning_msg']);
        $field_errors = $result['field_errors'];
        $properties = $propertyData->getLatestProperties();
    } else {
        $params = $result['params'];
        $translated_property_name = $result['translated_property_name'];
        $simplified_address = $result['simplified_address'];
        error_log("search.php: Form inputs: " . json_encode($params));

        // Ensure required parameters are set with defaults
        $params['sort_by'] = isset($params['sort_by']) ? $params['sort_by'] : 'date_desc';
        $params['page'] = isset($params['page']) ? (int)$params['page'] : 1;
        $params['per_page'] = isset($params['per_page']) ? (int)$params['per_page'] : 10;

        if (!empty($user_id)) {
            $pref_result = $propertyData->saveUserPreferences(
                $user_id,
                $params,
                $params['latitude'],
                $params['longitude'],
                $params['radius']
            );
            if (!$pref_result['success']) {
                $warning_msg[] = $pref_result['message'];
            }
        }

        $search_result = $propertyLogic->searchProperties(
            $params,
            $translated_property_name,
            $params['address'],
            $simplified_address,
            $params['latitude'],
            $params['longitude'],
            $params['radius'],
            $params['min'],
            $params['max'],
            $params['sort_by'],
            $params['page'],
            $params['per_page']
        );
        $properties = $search_result['results'];
        $total_results = $search_result['total_results'];
        $total_pages = $search_result['total_pages'];
        $current_page = $params['page'];
    }
} else {
    $properties = $propertyData->getLatestProperties();
    $total_results = count($properties);
    $total_pages = 1;
    $current_page = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && $user_id) {
    $property_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
    if ($property_id) {
        $save_result = $userLogic->saveProperty($user_id, $property_id);
        if (isset($save_result['success'])) {
            $success_msg[] = $save_result['success'];
        } elseif (isset($save_result['error'])) {
            $warning_msg[] = $save_result['error'];
        }
    } else {
        $warning_msg[] = 'Invalid property ID';
    }
    error_log("search.php: Save property attempt for user_id=$user_id, property_id=$property_id");
    header('Location: /app/property/presentation/search.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send']) && $user_id) {
    $property_id = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT);
    $owner_id = $conn->prepare("SELECT user_id FROM property WHERE id = ?");
    $owner_id->execute([$property_id]);
    $owner_id = $owner_id->fetchColumn();

    if ($owner_id) {
        $check_subscription = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
        $check_subscription->execute([$owner_id]);
        $owner_premium = $check_subscription->fetchColumn();

        $check_preferences = $conn->prepare("SELECT COUNT(*) FROM screening_preferences WHERE user_id = ? AND property_id = ?");
        $check_preferences->execute([$owner_id, $property_id]);
        $has_preferences = $check_preferences->fetchColumn() > 0;

        $check_applied = $conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
        $check_applied->execute([$property_id, $user_id]);
        $has_applied = $check_applied->fetchColumn() > 0;

        $check_passed = $conn->prepare("SELECT COUNT(*) FROM tenant_scores WHERE property_id = ? AND user_id = ? AND score >= 70");
        $check_passed->execute([$property_id, $user_id]);
        $has_passed = $check_passed->fetchColumn() > 0;

        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, property_id, action, created_at) VALUES (?, ?, 'chat', NOW())");
        $stmt->execute([$user_id, $property_id]);

        if ($owner_premium && $has_preferences && !$has_applied) {
            error_log("search.php: Redirecting to apply.php for property_id=$property_id");
            $_SESSION['success_msg'] = ['Please complete the application to chat with the owner!'];
            header("Location: /app/screening/presentation/apply.php?property_id=$property_id");
        } elseif ($has_applied && !$has_passed) {
            error_log("search.php: Application pending for property_id=$property_id");
            $_SESSION['warning_msg'] = ['Your application is pending owner review.'];
            header("Location: /app/property/presentation/search.php");
        } else {
            error_log("search.php: Redirecting to chat.php for property_id=$property_id, other_user_id=$owner_id");
            $_SESSION['success_msg'] = ['Chat opened successfully!'];
            header("Location: /app/chat/presentation/chat.php?property_id=$property_id&other_user_id=$owner_id");
        }
        exit;
    } else {
        error_log("search.php: Property owner not found for property_id=$property_id");
        $_SESSION['warning_msg'] = ['Property owner not found'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <style>
        .alert { background: #f8d7da; padding: 10px; margin-bottom: 10px; border: 0px solid; }
        .alert-success { background: #d4edda; border: 1px solid #28a745; }
        .box { margin-bottom: 15px; }
        .input { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { padding: 10px; margin: 5px; }
        .pagination { text-align: center; margin: 20px 0; }
        .pagination a { margin: 0 5px; padding: 8px 16px; text-decoration: none; color: #007bff; border: 1px solid #ddd; }
        .pagination a.active { background-color: #007bff; color: white; }
        .pagination a:hover { background-color: #ddd; }
    </style>
</head>
<body>
<?php include ROOT_DIR . '/components/user_header.php'; ?>

<section class="filters" style="padding-bottom: 0;">
    <form action="search.php" method="post" id="search-form">
        <div id="close-filter"><i class="fas fa-times"></i></div>
        <h3>Filter Your Search</h3>
        <div class="flex">
            <div class="box">
                <p>Property Name</p>
                <input type="text" name="property_name" maxlength="50" placeholder="Enter property name" class="input" value="<?= isset($_POST['property_name']) ? htmlspecialchars($_POST['property_name']) : '' ?>">
                <?php if (isset($field_errors['property_name'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['property_name']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Enter Location</p>
                <input type="text" name="address" id="autocomplete" maxlength="100" placeholder="Enter address or landmark (Google-powered)" class="input" value="<?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?>">
                <input type="hidden" name="place_id" id="place_id" value="<?= isset($_POST['place_id']) ? htmlspecialchars($_POST['place_id']) : '' ?>">
                <input type="hidden" name="latitude" id="latitude" value="<?= isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : '' ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : '' ?>">
                <?php if (isset($field_errors['address']) || isset($field_errors['place_id']) || isset($field_errors['latitude']) || isset($field_errors['longitude'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['address'] ?? $field_errors['place_id'] ?? $field_errors['latitude'] ?? $field_errors['longitude']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Search Radius (km)</p>
                <select name="radius" class="input">
                    <option value="">Any</option>
                    <option value="1" <?= isset($_POST['radius']) && $_POST['radius'] == '1' ? 'selected' : '' ?>>1 km</option>
                    <option value="5" <?= isset($_POST['radius']) && $_POST['radius'] == '5' ? 'selected' : '' ?>>5 km</option>
                    <option value="10" <?= isset($_POST['radius']) && $_POST['radius'] == '10' ? 'selected' : '' ?>>10 km</option>
                    <option value="25" <?= isset($_POST['radius']) && $_POST['radius'] == '25' ? 'selected' : '' ?>>25 km</option>
                    <option value="50" <?= isset($_POST['radius']) && $_POST['radius'] == '50' ? 'selected' : '' ?>>50 km</option>
                </select>
                <?php if (isset($field_errors['radius'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['radius']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Offer Type</p>
                <select name="offer" class="input">
                    <option value="">Any</option>
                    <option value="sale" <?= isset($_POST['offer']) && $_POST['offer'] == 'sale' ? 'selected' : '' ?>>Sale</option>
                    <option value="resale" <?= isset($_POST['offer']) && $_POST['offer'] == 'resale' ? 'selected' : '' ?>>Resale</option>
                    <option value="rent" <?= isset($_POST['offer']) && $_POST['offer'] == 'rent' ? 'selected' : '' ?>>Rent</option>
                </select>
                <?php if (isset($field_errors['offer'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['offer']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Property Type</p>
                <select name="type" class="input">
                    <option value="">Any</option>
                    <option value="flat" <?= isset($_POST['type']) && $_POST['type'] == 'flat' ? 'selected' : '' ?>>Flat</option>
                    <option value="house" <?= isset($_POST['type']) && $_POST['type'] == 'house' ? 'selected' : '' ?>>House</option>
                    <option value="shop" <?= isset($_POST['type']) && $_POST['type'] == 'shop' ? 'selected' : '' ?>>Shop</option>
                </select>
                <?php if (isset($field_errors['type'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['type']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>How many BHK</p>
                <select name="bhk" class="input">
                    <option value="">Any</option>
                    <option value="1" <?= isset($_POST['bhk']) && $_POST['bhk'] == '1' ? 'selected' : '' ?>>1 BHK</option>
                    <option value="2" <?= isset($_POST['bhk']) && $_POST['bhk'] == '2' ? 'selected' : '' ?>>2 BHK</option>
                    <option value="3" <?= isset($_POST['bhk']) && $_POST['bhk'] == '3' ? 'selected' : '' ?>>3 BHK</option>
                    <option value="4" <?= isset($_POST['bhk']) && $_POST['bhk'] == '4' ? 'selected' : '' ?>>4 BHK</option>
                    <option value="5" <?= isset($_POST['bhk']) && $_POST['bhk'] == '5' ? 'selected' : '' ?>>5 BHK</option>
                    <option value="6" <?= isset($_POST['bhk']) && $_POST['bhk'] == '6' ? 'selected' : '' ?>>6 BHK</option>
                    <option value="7" <?= isset($_POST['bhk']) && $_POST['bhk'] == '7' ? 'selected' : '' ?>>7 BHK</option>
                    <option value="8" <?= isset($_POST['bhk']) && $_POST['bhk'] == '8' ? 'selected' : '' ?>>8 BHK</option>
                    <option value="9" <?= isset($_POST['bhk']) && $_POST['bhk'] == '9' ? 'selected' : '' ?>>9 BHK</option>
                </select>
                <?php if (isset($field_errors['bhk'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['bhk']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Minimum Budget (€)</p>
                <select name="min_select" id="min_select" class="input">
                    <option value="">Any</option>
                    <option value="custom" <?= isset($_POST['min_select']) && $_POST['min_select'] == 'custom' ? 'selected' : '' ?>>Custom</option>
                </select>
                <input type="number" name="min" id="min_budget" min="0" step="1" placeholder="Enter min budget" class="input" style="display: <?= isset($_POST['min_select']) && $_POST['min_select'] == 'custom' ? 'block' : 'none' ?>;" value="<?= isset($_POST['min']) ? htmlspecialchars($_POST['min']) : '' ?>">
                <?php if (isset($field_errors['min'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['min']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Maximum Budget (€)</p>
                <select name="max_select" id="max_select" class="input">
                    <option value="">Any</option>
                    <option value="custom" <?= isset($_POST['max_select']) && $_POST['max_select'] == 'custom' ? 'selected' : '' ?>>Custom</option>
                </select>
                <input type="number" name="max" id="max_budget" min="0" step="1" placeholder="Enter max budget" class="input" style="display: <?= isset($_POST['max_select']) && $_POST['max_select'] == 'custom' ? 'block' : 'none' ?>;" value="<?= isset($_POST['max']) ? htmlspecialchars($_POST['max']) : '' ?>">
                <?php if (isset($field_errors['max'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['max']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Furnished</p>
                <select name="furnished" class="input">
                    <option value="">Any</option>
                    <option value="unfurnished" <?= isset($_POST['furnished']) && $_POST['furnished'] == 'unfurnished' ? 'selected' : '' ?>>Unfurnished</option>
                    <option value="furnished" <?= isset($_POST['furnished']) && $_POST['furnished'] == 'furnished' ? 'selected' : '' ?>>Furnished</option>
                    <option value="semi-d" <?= isset($_POST['furnished']) && $_POST['furnished'] == 'semi-furnished' ? 'selected' : '' ?>>Semi-Furnished</option>
                </select>
                <?php if (isset($field_errors['furnished'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['furnished']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Bedrooms</p>
                <select name="bedroom" class="input">
                    <option value="">Any</option>
                    <?php for ($i = 0; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= isset($_POST['bedroom']) && $_POST['bedroom'] == $i ? 'selected' : '' ?>><?= $i ?> Bedroom<?= $i !== 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($field_errors['bedroom'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['bedroom']); ?></p>
                <?php endif; ?>
            </div>
            <div class="box">
                <p>Bathrooms</p>
                <select name="bathroom" class="input">
                    <option value="">Any</option>
                    <?php for ($i = 0; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= isset($_POST['bathroom']) && $_POST['bathroom'] == $i ? 'selected' : '' ?>><?= $i ?> Bathroom<?= $i !== 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($field_errors['bathroom'])): ?>
                    <p class="alert"><?= htmlspecialchars($field_errors['bathroom']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <input type="hidden" name="page" value="<?= isset($_POST['page']) ? htmlspecialchars($_POST['page']) : 1 ?>">
        <input type="hidden" name="per_page" value="10">
        <input type="submit" value="Search Property" name="filter_search" class="btn">
    </form>
</section>
<div id="filter-btn" class="fas fa-filter"></div>

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

<section class="listings">
    <h1 class="heading"><?php echo isset($_POST['filter_search']) ? 'Search Results' : 'Latest Listings'; ?></h1>
    <div class="box-container">
        <?php if (!empty($properties)): ?>
            <?php foreach ($properties as $fetch_property):
                $select_user = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
                $select_user->execute([$fetch_property['user_id']]);
                $fetch_user = $select_user->fetch(PDO::FETCH_ASSOC);
                $total_images = 1;
                for ($i = 2; $i <= 5; $i++) {
                    if (!empty($fetch_property["image_0$i"])) $total_images++;
                }
                $select_saved = $conn->prepare("SELECT * FROM saved WHERE property_id = ? AND user_id = ?");
                $select_saved->execute([$fetch_property['id'], $user_id]);

                $owner_id = $fetch_property['user_id'];
                $check_subscription = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
                $check_subscription->execute([$owner_id]);
                $owner_premium = $check_subscription->fetchColumn();

                $check_preferences = $conn->prepare("SELECT COUNT(*) FROM screening_preferences WHERE user_id = ? AND property_id = ?");
                $check_preferences->execute([$owner_id, $fetch_property['id']]);
                $has_preferences = $check_preferences->fetchColumn() > 0;

                $check_applied = $conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
                $check_applied->execute([$fetch_property['id'], $user_id]);
                $has_applied = $check_applied->fetchColumn() > 0;

                $check_passed = $conn->prepare("SELECT COUNT(*) FROM tenant_scores WHERE property_id = ? AND user_id = ? AND score >= 70");
                $check_passed->execute([$fetch_property['id'], $user_id]);
                $has_passed = $check_passed->fetchColumn() > 0;

                $chat_button = ($user_id && $user_id != $owner_id) ?
                    (($owner_premium && $has_preferences && !$has_applied) ?
                        '<input type="submit" value="📝 Apply to Chat" name="send" class="btn">' :
                        ($has_applied && $has_passed ?
                            '<input type="submit" value="💬 Chat with the owner" name="send" class="btn">' :
                            (!$owner_premium || !$has_preferences ?
                                '<input type="submit" value="💬 Chat with the owner" name="send" class="btn">' : ''))) : '';
            ?>
            <form action="" method="POST">
                <div class="box">
                    <input type="hidden" name="property_id" value="<?= htmlspecialchars($fetch_property['id']); ?>">
                    <button type="submit" name="save" class="save">
                        <i class="<?= $select_saved->rowCount() > 0 ? 'fas' : 'far'; ?> fa-heart"></i>
                        <span><?= $select_saved->rowCount() > 0 ? 'Saved' : 'Save'; ?></span>
                    </button>
                    <div class="thumb">
                        <p class="total-images"><i class="far fa-image"></i><span><?= $total_images; ?></span></p>
                        <img src="/uploaded_files/<?= htmlspecialchars($fetch_property['image_01']); ?>" alt="">
                    </div>
                    <div class="admin">
                        <h3><?= htmlspecialchars(substr($fetch_user['name'] ?? 'Unknown', 0, 1)); ?></h3>
                        <div>
                            <p><?= htmlspecialchars($fetch_user['name'] ?? 'Unknown User'); ?></p>
                            <span><?= htmlspecialchars($fetch_property['date']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="price"><?= convertCurrencyFromEUR($fetch_property['price'], $rates); ?></div>
                    <h3 class="name"><?= htmlspecialchars($fetch_property['property_name']); ?></h3>
                    <p class="location"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($fetch_property['address']); ?></span></p>
                    <?php if (isset($fetch_property['distance'])): ?>
                        <p class="distance"><i class="fas fa-ruler"></i><span><?= round($fetch_property['distance'], 2); ?> km away</span></p>
                    <?php endif; ?>
                    <div class="flex">
                        <p><i class="fas fa-house"></i><span><?= htmlspecialchars($fetch_property['type']); ?></span></p>
                        <p><i class="fas fa-tag"></i><span><?= htmlspecialchars($fetch_property['offer']); ?></span></p>
                        <p><i class="fas fa-bed"></i><span><?= htmlspecialchars($fetch_property['bhk']); ?> BHK</span></p>
                        <p><i class="fas fa-trowel"></i><span><?= htmlspecialchars($fetch_property['status']); ?></span></p>
                        <p><i class="fas fa-couch"></i><span><?= htmlspecialchars($fetch_property['furnished']); ?></span></p>
                        <p><i class="fas fa-maximize"></i><span><?= htmlspecialchars($fetch_property['carpet']); ?> sqft</span></p>
                    </div>
                    <div class="flex-btn">
                        <a href="/app/property/presentation/view_property.php?get_id=<?= htmlspecialchars($fetch_property['id']); ?>" class="btn">View Property</a>
                        <?php if ($user_id && $user_id != $fetch_property['user_id']): ?>
                            <?= $chat_button; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty">No properties found! Try adjusting your filters.</p>
        <?php endif; ?>
    </div>
    <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $current_page == $i ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_DIR . '/components/footer.php'; ?>
<?php include ROOT_DIR . '/components/message.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>

<?php if (!empty($success_msg)): ?>
<script>
    swal({
        title: "Success!",
        text: "<?= htmlspecialchars($success_msg[0]); ?>",
        icon: "success",
        button: "OK"
    });
</script>
<?php endif; ?>

<?php if (!empty($warning_msg)): ?>
<script>
    swal({
        title: "Warning!",
        text: "<?= htmlspecialchars(implode('\n', $warning_msg)); ?>",
        icon: "warning",
        button: "OK"
    });
</script>
<?php endif; ?>

<script>
document.querySelector('#filter-btn').onclick = () => {
    document.querySelector('.filters').classList.add('active');
};

document.querySelector('#close-filter').onclick = () => {
    document.querySelector('.filters').classList.remove('active');
};

document.getElementById('min_select').addEventListener('change', function() {
    document.getElementById('min_budget').style.display = this.value === 'custom' ? 'block' : 'none';
    if (this.value !== 'custom') document.getElementById('min_budget').value = '';
});

document.getElementById('max_select').addEventListener('change', function() {
    document.getElementById('max_budget').style.display = this.value === 'custom' ? 'block' : 'none';
    if (this.value !== 'custom') document.getElementById('max_budget').value = '';
});
</script>

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
<?php ob_end_flush(); ?>