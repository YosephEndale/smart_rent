<?php
namespace App\Property\Logic;

use Exception;
use PDO;
use PDOException;
use App\Scam\Logic\ScamLogic;
use App\Property\Data\PropertyData;

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', '/');
}

require_once ROOT_DIR . '/app/scam/logic/ScamLogic.php';
require_once ROOT_DIR . '/app/property/data/PropertyData.php';

class PropertyLogic {
    private $apiKey;
    private $scamLogic;
    private $propertyData;
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->apiKey = getenv('GOOGLE_API_KEY');
        if (!$this->apiKey) {
            error_log('GOOGLE_API_KEY not set in .env file');
        }
        $this->scamLogic = new ScamLogic($conn);
        $this->propertyData = new PropertyData($conn);
    }

    public function validatePropertyId($get_id) {
    if (empty($get_id) || !filter_var($get_id, FILTER_VALIDATE_INT)) {
        error_log("Invalid or missing property ID: $get_id");
        $_SESSION['warning_msg'] = ['Invalid or missing property ID. Please select a property to update.'];
        return false; 
    }
    return (int)$get_id;
}

    public function validateUser($user_id) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($user_id) || !filter_var($user_id, FILTER_VALIDATE_INT)) {
            if (isset($_SESSION['user_id']) && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT)) {
                error_log("Using session user_id: {$_SESSION['user_id']}");
                return (int)$_SESSION['user_id'];
            }

            $login_path = ROOT_DIR . '/app/auth/presentation/login.php';
            if (!file_exists($login_path)) {
                error_log("Login page not found at: $login_path, redirecting to index.php");
                header('Location: ' . WEB_ROOT . 'index.php?error=login_page_missing');
                exit;
            }

            error_log("Invalid or missing user_id: $user_id, redirecting to login.php");
            header('Location: ' . WEB_ROOT . 'app/auth/presentation/login.php');
            exit;
        }
        return (int)$user_id;
    }

    public function validateUpdateForm($postData, $files) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $formData = [
        'property_name' => htmlspecialchars(trim($postData['property_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'price' => filter_var($postData['price'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
        'deposite' => filter_var($postData['deposite'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
        'address' => htmlspecialchars(trim($postData['address'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'location_place_id' => htmlspecialchars(trim($postData['place_id'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'latitude' => filter_var($postData['latitude'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
        'longitude' => filter_var($postData['longitude'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
        'offer' => htmlspecialchars(trim($postData['offer'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'type' => htmlspecialchars(trim($postData['type'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'status' => htmlspecialchars(trim($postData['status'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'furnished' => htmlspecialchars(trim($postData['furnished'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'bhk' => filter_var($postData['bhk'] ?? '', FILTER_SANITIZE_NUMBER_INT),
        'bedroom' => filter_var($postData['bedroom'] ?? '', FILTER_SANITIZE_NUMBER_INT),
        'bathroom' => filter_var($postData['bathroom'] ?? '', FILTER_SANITIZE_NUMBER_INT),
        'balcony' => filter_var($postData['balcony'] ?? '', FILTER_SANITIZE_NUMBER_INT),
        'carpet' => filter_var($postData['carpet'] ?? '', FILTER_SANITIZE_NUMBER_INT),
        'age' => filter_var($postData['age'] ?? '', FILTER_SANITIZE_NUMBER_INT),
        'total_floors' => filter_var($postData['total_floors'] ?? '', FILTER_SANITIZE_NUMBER_INT),
        'room_floor' => filter_var($postData['room_floor'] ?? '', FILTER_SANITIZE_NUMBER_INT),
        'loan' => htmlspecialchars(trim($postData['loan'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'description' => htmlspecialchars(trim($postData['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'images' => array_fill(0, 5, null)
    ];

    $facilities = ['lift', 'security_guard', 'play_ground', 'garden', 'water_supply', 'power_backup', 'parking_area', 'gym', 'shopping_mall', 'hospital', 'school', 'market_area'];
    foreach ($facilities as $facility) {
        $formData[$facility] = isset($postData[$facility]) && $postData[$facility] === 'yes' ? 'yes' : 'no';
    }

    $field_errors = [];

    if (empty($_SESSION['user_id']) || !filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT)) {
        $field_errors['user_id'] = 'You must be logged in to update a property';
    }

    $requiredFields = [
        'property_name' => ['max_length' => 50, 'error' => 'Property name is required and must be 50 characters or less'],
        'address' => ['max_length' => 100, 'error' => 'Address is required and must be 100 characters or less'],
        'description' => ['max_length' => 1000, 'error' => 'Description is required and must be 1000 characters or less']
    ];

    foreach ($requiredFields as $field => $rules) {
        if (empty($formData[$field])) {
            $field_errors[$field] = $rules['error'];
        } elseif (strlen($formData[$field]) > $rules['max_length']) {
            $field_errors[$field] = $rules['error'];
        }
    }

    $numericFields = [
        'price' => ['min' => 0.01, 'max' => 9999999.99, 'error' => 'Price must be between 0.01 and 9999999.99'],
        'deposite' => ['min' => 0, 'max' => 9999999.99, 'error' => 'Deposit must be between 0 and 9999999.99'],
        'bhk' => ['min' => 1, 'max' => 9, 'error' => 'BHK must be between 1 and 9'],
        'bedroom' => ['min' => 0, 'max' => 9, 'error' => 'Bedroom count must be between 0 and 9'],
        'bathroom' => ['min' => 1, 'max' => 9, 'error' => 'Bathroom count must be between 1 and 9'],
        'balcony' => ['min' => 0, 'max' => 9, 'error' => 'Balcony count must be between 0 and 9'],
        'carpet' => ['min' => 1, 'max' => 999999, 'error' => 'Carpet area must be between 1 and 999999'],
        'age' => ['min' => 0, 'max' => 100, 'error' => 'Property age must be between 0 and 100 years'],
        'total_floors' => ['min' => 0, 'max' => 100, 'error' => 'Total floors must be between 0 and 100'],
        'room_floor' => ['min' => 0, 'max' => 100, 'error' => 'Room floor must be between 0 and 100']
    ];

    foreach ($numericFields as $field => $rules) {
        if ($formData[$field] === '' || !is_numeric($formData[$field])) {
            $field_errors[$field] = $rules['error'];
        } elseif ($formData[$field] < $rules['min'] || $formData[$field] > $rules['max']) {
            $field_errors[$field] = $rules['error'];
        } else {
            $formData[$field] = $field === 'price' || $field === 'deposite' ? sprintf("%.2f", floatval($formData[$field])) : (string)$formData[$field];
        }
    }

    $selectFields = [
        'offer' => ['sale', 'rent'],
        'type' => ['flat', 'house', 'shop'],
        'status' => ['ready to move', 'under construction', 'rented', 'sold'],
        'furnished' => ['furnished', 'semi-furnished', 'unfurnished'],
        'loan' => ['available', 'not available']
    ];

    foreach ($selectFields as $field => $validOptions) {
        if (empty($formData[$field]) || !in_array($formData[$field], $validOptions)) {
            $field_errors[$field] = ucfirst($field) . ' must be a valid option';
        }
    }

    // Relaxed location validation: only validate if new location data is provided
    if (!empty($formData['latitude']) && !empty($formData['longitude'])) {
        if (!is_numeric($formData['latitude']) || $formData['latitude'] < -90 || $formData['latitude'] > 90) {
            $field_errors['latitude'] = 'Valid latitude is required (-90 to 90)';
        } else {
            $formData['latitude'] = sprintf("%.8f", floatval($formData['latitude']));
        }
        if (!is_numeric($formData['longitude']) || $formData['longitude'] < -180 || $formData['longitude'] > 180) {
            $field_errors['longitude'] = 'Valid longitude is required (-180 to 180)';
        } else {
            $formData['longitude'] = sprintf("%.8f", floatval($formData['longitude']));
        }
    } else {
        $formData['latitude'] = '';
        $formData['longitude'] = '';
    }

    if (!empty($formData['location_place_id']) && strlen($formData['location_place_id']) > 255) {
        $field_errors['location_place_id'] = 'Invalid place ID';
    }

    // Image handling
    $upload_dir = ROOT_DIR . '/uploaded_files/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    if (!is_writable($upload_dir)) {
        $field_errors['upload_dir'] = 'Upload directory is not writable';
    } else {
        for ($i = 1; $i <= 5; $i++) {
            $key = "image_0$i";
            if (isset($files[$key]) && $files[$key]['size'] > 0 && $files[$key]['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                if (!in_array($files[$key]['type'], $allowed_types)) {
                    $field_errors[$key] = "Image $i must be a valid image (JPEG, PNG, GIF)";
                } elseif ($files[$key]['size'] > $max_size) {
                    $field_errors[$key] = "Image $i must be less than 2MB";
                } else {
                    $ext = pathinfo($files[$key]['name'], PATHINFO_EXTENSION);
                    $filename = $this->createUniqueId() . '.' . $ext;
                    $destination = $upload_dir . $filename;
                    if (move_uploaded_file($files[$key]['tmp_name'], $destination)) {
                        $formData['images'][$i-1] = $filename;
                    } else {
                        $field_errors[$key] = "Failed to upload image $i";
                    }
                }
            } elseif (isset($postData["old_image_0$i"]) && !empty($postData["old_image_0$i"])) {
                $formData['images'][$i-1] = htmlspecialchars(trim($postData["old_image_0$i"]));
            }
        }
    }

    // Ensure image_01 is present
    if (empty($formData['images'][0]) && empty($postData['old_image_01'])) {
        $field_errors['image_01'] = 'At least one image (Image 1) is required';
    }

    if (!empty($field_errors)) {
        error_log("validateUpdateForm: Validation failed, errors=" . json_encode($field_errors));
        return ['success' => false, 'formData' => $formData, 'field_errors' => $field_errors, 'warning_msg' => ['Please correct the errors in the form']];
    }

    error_log("validateUpdateForm: Validation succeeded, formData=" . json_encode($formData));
    return ['success' => true, 'formData' => $formData];
}

public function handleUpdateProperty($dataLayer, $postData, $files, $update_id, $user_id) {
    error_log("handleUpdateProperty: Starting for property_id=$update_id, user_id=$user_id, postData=" . json_encode($postData));
    $validation = $this->validateUpdateForm($postData, $files);
    if (!$validation['success']) {
        error_log("handleUpdateProperty: Validation failed: " . json_encode($validation['field_errors']));
        return [
            'success' => false,
            'formData' => $validation['formData'],
            'warning_msg' => $validation['warning_msg'] ?? ['Form validation failed.'],
            'field_errors' => $validation['field_errors'] ?? []
        ];
    }

    $formData = $validation['formData'];
    $existing_property = $dataLayer->getPropertyForUpdate($update_id, $user_id);
    if (!$existing_property) {
        error_log("handleUpdateProperty: Property not found or unauthorized: property_id=$update_id, user_id=$user_id");
        return [
            'success' => false,
            'formData' => $formData,
            'warning_msg' => ['Property not found or you are not authorized to update it'],
            'field_errors' => []
        ];
    }

    // Preserve existing images if no new ones are uploaded
    for ($i = 0; $i < 5; $i++) {
        $image_field = "image_0" . ($i + 1);
        if (empty($formData['images'][$i]) && !empty($existing_property[$image_field])) {
            $formData['images'][$i] = $existing_property[$image_field];
        }
    }

    $result = $dataLayer->updateProperty($formData, $update_id, $user_id);
    error_log("handleUpdateProperty: Update result=" . json_encode($result));

    if ($result['success']) {
        $scamResult = $this->scamLogic->checkSingleProperty($update_id);
        if ($scamResult['is_suspicious']) {
            $stmt = $this->conn->prepare("UPDATE property SET status = 'pending_review' WHERE id = ?");
            $stmt->execute([$update_id]);
            error_log("handleUpdateProperty: Property flagged as suspicious, propId=$update_id, score={$scamResult['suspicion_score']}");
            return [
                'success' => true,
                'formData' => $formData,
                'success_msg' => ['Property updated but flagged for review due to suspicious content.'],
                'warning_msg' => [],
                'field_errors' => []
            ];
        }
        return [
            'success' => true,
            'formData' => $formData,
            'success_msg' => [$result['message']],
            'warning_msg' => [],
            'field_errors' => []
        ];
    }

    return [
        'success' => false,
        'formData' => $formData,
        'warning_msg' => [$result['message']],
        'field_errors' => []
    ];
}
    public function handleDeleteImage($dataLayer, $image_num, $property_id, $user_id) {
        error_log("handleDeleteImage: Starting for image_num=$image_num, property_id=$property_id, user_id=$user_id");
        if ($image_num < 1 || $image_num > 5) {
            error_log("Invalid image number: $image_num for property_id=$property_id, user_id=$user_id");
            return ['success' => false, 'message' => 'Invalid image number'];
        }

        $property = $dataLayer->getPropertyForUpdate($property_id, $user_id);
        if (!$property) {
            error_log("Property not found or user not authorized: property_id=$property_id, user_id=$user_id");
            return ['success' => false, 'message' => 'Property not found or you are not authorized'];
        }

        $image_field = "image_0$image_num";
        $old_image = $property[$image_field];
        if (empty($old_image) && $image_num !== 1) {
            error_log("No image to delete for $image_field, property_id=$property_id, user_id=$user_id");
            return ['success' => false, 'message' => 'No image to delete'];
        }
        if ($image_num === 1 && empty($old_image)) {
            error_log("Cannot delete image_01 as it is required: property_id=$property_id, user_id=$user_id");
            return ['success' => false, 'message' => 'Image 1 is required and cannot be deleted without replacement'];
        }

        return $dataLayer->deleteImage($image_num, $property_id, $user_id, $old_image);
    }

    public function translateText($text, $target = 'en', $source = 'it') {
        if (empty($text)) {
            error_log("Empty text provided for translation");
            return false;
        }

        $maxLength = 5000;
        if (strlen($text) > $maxLength) {
            $chunks = str_split($text, $maxLength);
            $translatedChunks = [];
            foreach ($chunks as $chunk) {
                $translatedChunk = $this->translateText($chunk, $target, $source);
                if ($translatedChunk === false) {
                    error_log("Failed to translate chunk: $chunk");
                    return false;
                }
                $translatedChunks[] = $translatedChunk;
            }
            return implode('', $translatedChunks);
        }

        $url = 'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($this->apiKey);
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

    public function createUniqueId() {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 20; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function handlePostProperty($dataLayer, $postData, $files, $user_id, $sendNotificationCallback) {
        $validation = $this->validateUpdateForm($postData, $files);
        if (!$validation['success']) {
            return $validation;
        }

        $formData = $validation['formData'];
        $result = $dataLayer->insertProperty($formData, $user_id);
        if (!$result['success']) {
            return array_merge($result, ['formData' => $formData]);
        }

        $property_id = $result['property_id'];
        $matching_users = $dataLayer->getMatchingPreferences($formData, $property_id);
        foreach ($matching_users as $user) {
            call_user_func($sendNotificationCallback, $user['user_id'], $user['name'], $property_id, $formData['property_name']);
        }

        return [
            'success' => true,
            'property_id' => $property_id,
            'formData' => $formData,
            'success_msg' => ['Property posted successfully!']
        ];
    }

    public function validateSearchForm($postData) {
        $params = [
            'property_name' => htmlspecialchars(trim($postData['property_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'address' => htmlspecialchars(trim($postData['address'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'place_id' => htmlspecialchars(trim($postData['place_id'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'latitude' => filter_var($postData['latitude'] ?? null, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => -90, 'max_range' => 90]]) ?: null,
            'longitude' => filter_var($postData['longitude'] ?? null, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => -180, 'max_range' => 180]]) ?: null,
            'radius' => filter_var($postData['radius'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
            'offer' => htmlspecialchars(trim($postData['offer'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'type' => htmlspecialchars(trim($postData['type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'furnished' => htmlspecialchars(trim($postData['furnished'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'bhk' => filter_var($postData['bhk'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: null,
            'min_select' => htmlspecialchars(trim($postData['min_select'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'max_select' => htmlspecialchars(trim($postData['max_select'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'min' => filter_var($postData['min'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 0,
            'max' => filter_var($postData['max'] ?? PHP_INT_MAX, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: PHP_INT_MAX
        ];

        $warning_msg = [];
        $field_errors = [];

        if (!empty($params['property_name']) && strlen($params['property_name']) > 50) {
            $field_errors['property_name'] = 'Property name must be 50 characters or less';
        }
        if (!empty($params['address']) && strlen($params['address']) > 100) {
            $field_errors['address'] = 'Address must be 100 characters or less';
        }
        if (!empty($params['place_id']) && strlen($params['place_id']) > 255) {
            $field_errors['place_id'] = 'Invalid place ID';
        }
        if ($params['latitude'] !== null && ($params['latitude'] < -90 || $params['latitude'] > 90)) {
            $field_errors['latitude'] = 'Latitude must be between -90 and 90';
        } else {
            $params['latitude'] = $params['latitude'] !== null ? sprintf("%.8f", floatval($params['latitude'])) : null;
        }
        if ($params['longitude'] !== null && ($params['longitude'] < -180 || $params['longitude'] > 180)) {
            $field_errors['longitude'] = 'Longitude must be between -180 and 180';
        } else {
            $params['longitude'] = $params['longitude'] !== null ? sprintf("%.8f", floatval($params['longitude'])) : null;
        }
        if ($params['radius'] !== null && !in_array($params['radius'], [1, 5, 10, 25, 50])) {
            $field_errors['radius'] = 'Radius must be 1, 5, 10, 25, or 50 km';
        }

        $selectFields = [
            'offer' => ['sale', 'rent', 'resale'],
            'type' => ['flat', 'house', 'shop'],
            'furnished' => ['furnished', 'semi-furnished', 'unfurnished']
        ];
        foreach ($selectFields as $field => $valid_values) {
            if (!empty($params[$field]) && !in_array($params[$field], $valid_values)) {
                $field_errors[$field] = "Invalid $field selected";
            }
        }

        if ($params['bhk'] !== null && ($params['bhk'] < 1 || $params['bhk'] > 9)) {
            $field_errors['bhk'] = 'BHK must be between 1 and 9';
        }

        if ($params['min_select'] === 'custom' && $params['min'] < 0) {
            $field_errors['min'] = 'Minimum budget must be 0 or greater';
        }
        if ($params['max_select'] === 'custom' && $params['max'] < $params['min']) {
            $field_errors['max'] = 'Maximum budget must be greater than or equal to minimum budget';
        }

        $translated_property_name = $params['property_name'];
        if (!empty($params['property_name'])) {
            $translated_property_name = $this->translateText($params['property_name']);
            if ($translated_property_name === false) {
                $warning_msg[] = 'Failed to translate property name to English. Searching with original text.';
                $translated_property_name = $params['property_name'];
            }
        }

        $simplified_address = $params['address'];
        if (stripos($params['address'], 'metropolitan city of') !== false) {
            $simplified_address = trim(str_ireplace('metropolitan city of', '', $params['address']));
            $simplified_address = explode(',', $simplified_address)[0];
        } elseif (stripos($params['address'], 'university of') !== false) {
            $simplified_address = trim(str_ireplace('university of', '', $params['address']));
            $simplified_address = explode(',', $simplified_address)[0];
        }

        if (!empty($field_errors)) {
            error_log("validateSearchForm: Validation failed, errors=" . json_encode($field_errors));
            return [
                'success' => false,
                'warning_msg' => ['Please correct the errors in the form'],
                'field_errors' => $field_errors,
                'params' => $params,
                'translated_property_name' => $translated_property_name,
                'simplified_address' => $simplified_address
            ];
        }

        return [
            'success' => true,
            'params' => $params,
            'translated_property_name' => $translated_property_name,
            'simplified_address' => $simplified_address,
            'warning_msg' => $warning_msg
        ];
    }

    public function handleViewProperty($dataLayer, $user_id, $property_id) {
        error_log("handleViewProperty: Starting for property_id=$property_id, user_id=$user_id");

        $property_id = $this->validatePropertyId($property_id);

        $property = $dataLayer->getPropertyById($property_id);
        if (!$property) {
            error_log("handleViewProperty: Property not found for property_id=$property_id");
            return ['success' => false, 'warning_msg' => ['Property not found']];
        }

        $owner_id = $dataLayer->getPropertyOwnerId($property_id);
        if (empty($owner_id)) {
            error_log("handleViewProperty: Owner not found for property_id=$property_id");
            return ['success' => false, 'warning_msg' => ['Property owner not found']];
        }

        $user = $dataLayer->getUserById($owner_id);
        if (!$user) {
            error_log("handleViewProperty: User details not found for owner_id=$owner_id");
            return ['success' => false, 'warning_msg' => ['Owner details not found']];
        }

        $is_saved = $user_id ? $dataLayer->isPropertySaved($property_id, $user_id) : false;

        if ($user_id) {
            $dataLayer->logUserView($user_id, $property_id);
        }

        error_log("handleViewProperty: Successfully fetched property_id=$property_id, owner_id=$owner_id");

        return [
            'success' => true,
            'property' => $property,
            'user' => $user,
            'owner_id' => $owner_id,
            'is_saved' => $is_saved
        ];
    }

    public function handleInquiry($dataLayer, $user_id, $property_id) {
        error_log("handleInquiry: Starting for property_id=$property_id, user_id=$user_id");

        if (empty($user_id) || !filter_var($user_id, FILTER_VALIDATE_INT)) {
            error_log("handleInquiry: Invalid or missing user_id=$user_id");
            return ['success' => false, 'warning_msg' => ['You must be logged in to send an inquiry']];
        }
        $property_id = $this->validatePropertyId($property_id);

        $property = $dataLayer->getPropertyById($property_id);
        if (!$property) {
            error_log("handleInquiry: Property not found for property_id=$property_id");
            return ['success' => false, 'warning_msg' => ['Property not found']];
        }

        $owner_id = $dataLayer->getPropertyOwnerId($property_id);
        if (empty($owner_id)) {
            error_log("handleInquiry: Owner not found for property_id=$property_id");
            return ['success' => false, 'warning_msg' => ['Property owner not found']];
        }

        if ($user_id == $owner_id) {
            error_log("handleInquiry: User cannot inquire about their own property, user_id=$user_id, property_id=$property_id");
            return ['success' => false, 'warning_msg' => ['You cannot send an inquiry for your own property']];
        }

        $success = $dataLayer->logUserInquiry($user_id, $property_id);
        if ($success) {
            error_log("handleInquiry: Inquiry logged successfully for user_id=$user_id, property_id=$property_id");
            return ['success' => true, 'success_msg' => ['Inquiry sent successfully']];
        } else {
            error_log("handleInquiry: Failed to log inquiry for user_id=$user_id, property_id=$property_id");
            return ['success' => false, 'warning_msg' => ['Failed to send inquiry']];
        }
    }

    public function searchProperties($params, $translated_property_name, $address, $simplified_address, $latitude, $longitude, $radius, $min, $max, $sort_by = 'date_desc', $page = 1, $per_page = 10) {
        try {
            $sql = "SELECT p.*";
            $bindParams = [];

            if ($latitude !== null && $longitude !== null && $radius !== null && $latitude != 0 && $longitude != 0) {
                $sql .= ", (6371 * acos(
                        cos(radians(:latitude)) * cos(radians(p.location_lat)) * 
                        cos(radians(p.location_lng) - radians(:longitude)) + 
                        sin(radians(:latitude)) * sin(radians(p.location_lat))
                    )) AS distance";
                $bindParams[':latitude'] = sprintf("%.8f", floatval($latitude));
                $bindParams[':longitude'] = sprintf("%.8f", floatval($longitude));
            }

            $sql .= " FROM `property` p WHERE 1=1";

            if (!empty($translated_property_name)) {
                $sql .= " AND p.property_name LIKE :property_name";
                $bindParams[':property_name'] = '%' . $translated_property_name . '%';
            }

            if (!empty($address) && $radius === null) {
                $sql .= " AND (p.address LIKE :address OR p.address LIKE :simplified_address)";
                $bindParams[':address'] = '%' . ($address ?: '') . '%';
                $bindParams[':simplified_address'] = '%' . ($simplified_address ?: '') . '%';
            }

            if (!empty($params['type'])) {
                $sql .= " AND p.type = :type";
                $bindParams[':type'] = $params['type'];
            }
            if (!empty($params['offer'])) {
                $sql .= " AND p.offer = :offer";
                $bindParams[':offer'] = $params['offer'];
            }
            if ($params['bhk'] !== null) {
                $sql .= " AND p.bhk = :bhk";
                $bindParams[':bhk'] = (int)$params['bhk'];
            }
            if (!empty($params['furnished'])) {
                $sql .= " AND p.furnished = :furnished";
                $bindParams[':furnished'] = $params['furnished'];
            }

            if ($latitude !== null && $longitude !== null && $radius !== null && $latitude != 0 && $longitude != 0) {
                $sql .= " AND p.location_lat != 0 AND p.location_lng != 0";
                $sql .= " AND (6371 * acos(
                        cos(radians(:latitude)) * cos(radians(p.location_lat)) * 
                        cos(radians(p.location_lng) - radians(:longitude)) + 
                        sin(radians(:latitude)) * sin(radians(p.location_lat))
                    )) <= :radius";
                $bindParams[':radius'] = (int)$radius;
            }

            $sql .= " AND p.price BETWEEN :min AND :max";
            $bindParams[':min'] = floatval($min);
            $bindParams[':max'] = floatval($max);

            if ($latitude !== null && $longitude !== null && $radius !== null && $latitude != 0 && $longitude != 0) {
                $sql .= " ORDER BY distance ASC, p.date DESC";
            } else {
                $sql .= " ORDER BY p.date DESC";
            }

            $offset = ($page - 1) * $per_page;
            $sql .= " LIMIT :per_page OFFSET :offset";
            $bindParams[':per_page'] = (int)$per_page;
            $bindParams[':offset'] = (int)$offset;

            error_log("searchProperties: Executing query=$sql, params=" . json_encode($bindParams));
            $stmt = $this->conn->prepare($sql);
            foreach ($bindParams as $key => $value) {
                if ($value === null) {
                    $stmt->bindValue($key, null, PDO::PARAM_NULL);
                } elseif (in_array($key, [':latitude', ':longitude', ':radius', ':address', ':simplified_address', ':property_name', ':type', ':offer', ':furnished'])) {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                } else {
                    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countSql = "SELECT COUNT(*) FROM `property` p";
            if (strpos($sql, 'WHERE') !== false) {
                $countSql .= " WHERE " . substr($sql, strpos($sql, 'WHERE') + 6, strpos($sql, 'ORDER BY') - strpos($sql, 'WHERE') - 6);
            }
            $countStmt = $this->conn->prepare($countSql);
            foreach ($bindParams as $key => $value) {
                if (in_array($key, [':per_page', ':offset'])) {
                    continue;
                }
                if ($value === null) {
                    $countStmt->bindValue($key, null, PDO::PARAM_NULL);
                } elseif (in_array($key, [':latitude', ':longitude', ':radius', ':address', ':simplified_address', ':property_name', ':type', ':offer', ':furnished'])) {
                    $countStmt->bindValue($key, $value, PDO::PARAM_STR);
                } else {
                    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
            }
            $countStmt->execute();
            $total_results = $countStmt->fetchColumn();

            error_log("searchProperties: Found " . count($results) . " properties, total=$total_results");
            return [
                'results' => $results,
                'total_results' => (int)$total_results,
                'total_pages' => ceil($total_results / $per_page)
            ];
        } catch (PDOException $e) {
            error_log("searchProperties failed: " . $e->getMessage() . ", query=$sql, params=" . json_encode($bindParams));
            return ['results' => [], 'total_results' => 0, 'total_pages' => 0, 'error' => $e->getMessage()];
        }
    }

    private function simplifyAddress($address) {
        if (stripos($address, 'metropolitan city of') !== false) {
            $address = trim(str_ireplace('metropolitan city of', '', $address));
            $address = explode(',', $address)[0];
        } elseif (stripos($address, 'university of') !== false) {
            $address = trim(str_ireplace('university of', '', $address));
            $address = explode(',', $address)[0];
        }
        return $address;
    }

    public function getMatchingPreferences($formData, $property_id) {
        try {
            $sql = "
                SELECT DISTINCT u.user_id, u.name
                FROM user_preferences up
                JOIN users u ON up.user_id = u.user_id
                WHERE up.status = 'active'
            ";

            $bindParams = [];

            if (!empty($formData['offer'])) {
                $sql .= " AND (up.offer_type = :offer OR up.offer_type IS NULL)";
                $bindParams[':offer'] = $formData['offer'];
            }

            if (!empty($formData['type'])) {
                $sql .= " AND (up.property_type = :type OR up.property_type IS NULL)";
                $bindParams[':type'] = $formData['type'];
            }

            if (!empty($formData['bhk'])) {
                $sql .= " AND (up.bhk = :bhk OR up.bhk IS NULL)";
                $bindParams[':bhk'] = (int)$formData['bhk'];
            }

            if (!empty($formData['furnished'])) {
                $sql .= " AND (up.furnished = :furnished OR up.furnished IS NULL)";
                $bindParams[':furnished'] = $formData['furnished'];
            }

            if (!empty($formData['price'])) {
                $sql .= " AND (up.min_budget <= :price AND up.max_budget >= :price OR up.min_budget IS NULL OR up.max_budget IS NULL)";
                $bindParams[':price'] = floatval($formData['price']);
            }

            if (!empty($formData['latitude']) && !empty($formData['longitude']) && !empty($formData['radius'])) {
                $sql .= " AND up.location_lat IS NOT NULL AND up.location_lng IS NOT NULL";
                $sql .= " AND (6371 * acos(
                    cos(radians(:latitude)) * cos(radians(up.location_lat)) * 
                    cos(radians(up.location_lng) - radians(:longitude)) + 
                    sin(radians(:latitude)) * sin(radians(up.location_lat))
                )) <= up.radius";
                $bindParams[':latitude'] = sprintf("%.8f", floatval($formData['latitude']));
                $bindParams[':longitude'] = sprintf("%.8f", floatval($formData['longitude']));
            }

            $stmt = $this->conn->prepare($sql);
            foreach ($bindParams as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue($key, $value, $paramType);
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getMatchingPreferences: Found " . count($results) . " matching users for property_id=$property_id");
            return $results;
        } catch (PDOException $e) {
            error_log("getMatchingPreferences failed for property_id=$property_id: " . $e->getMessage());
            return [];
        }
    }
}