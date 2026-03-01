<?php
namespace App\Property\Data;

use PDO;
use PDOException;

class PropertyData {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getPropertyById($property_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*, 
                       CASE WHEN ps.is_suspicious = 1 THEN 1 ELSE 0 END AS is_suspicious
                FROM property p 
                LEFT JOIN property_suspicion ps ON p.id = ps.property_id 
                WHERE p.id = ? 
                LIMIT 1
            ");
            $stmt->execute([$property_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("getPropertyById: propId=$property_id, found=" . ($result ? 'true' : 'false'));
            return $result;
        } catch (PDOException $e) {
            error_log("getPropertyById failed for propId=$property_id: " . $e->getMessage());
            return false;
        }
    }

    public function isPropertyExpired($property_id) {
        try {
            $stmt = $this->conn->prepare("SELECT status FROM property WHERE id = ? AND status = 'expired'");
            $stmt->execute([$property_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("isPropertyExpired: propId=$property_id, expired=" . ($result ? 'true' : 'false'));
            return $result !== false;
        } catch (PDOException $e) {
            error_log("isPropertyExpired failed for propId=$property_id: " . $e->getMessage());
            return false;
        }
    }

    public function isPropertySuspicious($property_id) {
        try {
            $stmt = $this->conn->prepare("SELECT is_suspicious FROM property_suspicion WHERE property_id = ? AND is_suspicious = 1");
            $stmt->execute([$property_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("isPropertySuspicious: propId=$property_id, suspicious=" . ($result ? 'true' : 'false'));
            return $result !== false;
        } catch (PDOException $e) {
            error_log("isPropertySuspicious failed for propId=$property_id: " . $e->getMessage());
            return false;
        }
    }

    public function getPropertyOwnerId($property_id) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id FROM property WHERE id = ?");
            $stmt->execute([$property_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("getPropertyOwnerId: propId=$property_id, user_id=" . ($data['user_id'] ?? 'none'));
            return $data['user_id'] ?? '';
        } catch (PDOException $e) {
            error_log("getPropertyOwnerId failed for propId=$property_id: " . $e->getMessage());
            return '';
        }
    }

    public function logUserView($user_id, $property_id) {
        try {
            $check_view = $this->conn->prepare("SELECT id FROM user_activity WHERE user_id = ? AND property_id = ? AND action = 'view' AND created_at > NOW() - INTERVAL 30 MINUTE");
            $check_view->execute([$user_id, $property_id]);
            if ($check_view->rowCount() == 0) {
                $insert_view = $this->conn->prepare("INSERT INTO user_activity (user_id, property_id, action) VALUES (?, ?, 'view')");
                $insert_view->execute([$user_id, $property_id]);
                error_log("logUserView: Logged view for user_id=$user_id, propId=$property_id");
            }
        } catch (PDOException $e) {
            error_log("logUserView failed for user_id=$user_id, propId=$property_id: " . $e->getMessage());
        }
    }

    public function logUserInquiry($user_id, $property_id) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO user_activity (user_id, property_id, action) VALUES (?, ?, 'chat')");
            $success = $stmt->execute([$user_id, $property_id]);
            error_log("logUserInquiry: Logged inquiry for user_id=$user_id, propId=$property_id, success=" . ($success ? 'true' : 'false'));
            return $success;
        } catch (PDOException $e) {
            error_log("logUserInquiry failed for user_id=$user_id, propId=$property_id: " . $e->getMessage());
            return false;
        }
    }

    public function getUserById($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT name, number FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("getUserById: user_id=$user_id, found=" . ($result ? 'true' : 'false'));
            return $result;
        } catch (PDOException $e) {
            error_log("getUserById failed for user_id=$user_id: " . $e->getMessage());
            return false;
        }
    }

    public function getLandlordMetrics($user_id, $property_id) {
        try {
            $stmt = $this->conn->prepare("SELECT reputation_score, review_count FROM landlord_reviews WHERE user_id = ? AND property_id = ? AND review_text IS NULL LIMIT 1");
            $stmt->execute([$user_id, $property_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("getLandlordMetrics: user_id=$user_id, propId=$property_id, found=" . ($result ? 'true' : 'false'));
            return $result;
        } catch (PDOException $e) {
            error_log("getLandlordMetrics failed for user_id=$user_id, propId=$property_id: " . $e->getMessage());
            return false;
        }
    }

    public function isPropertySaved($property_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM saved WHERE property_id = ? AND user_id = ?");
            $stmt->execute([$property_id, $user_id]);
            $isSaved = $stmt->rowCount() > 0;
            error_log("isPropertySaved: propId=$property_id, user_id=$user_id, saved=" . ($isSaved ? 'true' : 'false'));
            return $isSaved;
        } catch (PDOException $e) {
            error_log("isPropertySaved failed for propId=$property_id, user_id=$user_id: " . $e->getMessage());
            return false;
        }
    }

    public function getLandlordReviews($user_id, $property_id) {
        try {
            $stmt = $this->conn->prepare("SELECT rating, review_text, created_at FROM landlord_reviews WHERE user_id = ? AND property_id = ? AND review_text IS NOT NULL ORDER BY created_at DESC LIMIT 3");
            $stmt->execute([$user_id, $property_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getLandlordReviews: user_id=$user_id, propId=$property_id, found=" . count($results) . " reviews");
            return $results;
        } catch (PDOException $e) {
            error_log("getLandlordReviews failed for user_id=$user_id, propId=$property_id: " . $e->getMessage());
            return [];
        }
    }

    public function getPropertyForUpdate($property_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM property WHERE id = ? AND user_id = ?");
            $stmt->execute([$property_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("getPropertyForUpdate: propId=$property_id, user_id=$user_id, found=" . ($result ? 'true' : 'false'));
            return $result;
        } catch (PDOException $e) {
            error_log("getPropertyForUpdate failed for propId=$property_id, user_id=$user_id: " . $e->getMessage());
            return false;
        }
    }

 public function updateProperty($formData, $update_id, $user_id) {
    error_log("updateProperty: Starting for property_id=$update_id, user_id=$user_id, formData=" . json_encode($formData));
    try {
        $checkStmt = $this->conn->prepare("SELECT id FROM property WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$update_id, (int)$user_id]);
        if (!$checkStmt->fetch()) {
            error_log("updateProperty: Property not found or unauthorized for propId=$update_id, user_id=$user_id");
            return ['success' => false, 'message' => 'Property not found or you are not authorized to update it.'];
        }

        $stmt = $this->conn->prepare("
            UPDATE `property` SET 
                property_name = ?, address = ?, location_place_id = ?, location_lat = ?, location_lng = ?,
                price = ?, type = ?, offer = ?, status = ?, furnished = ?, bhk = ?,
                deposite = ?, bedroom = ?, bathroom = ?, balcony = ?, carpet = ?,
                age = ?, total_floors = ?, room_floor = ?, loan = ?, lift = ?,
                security_guard = ?, play_ground = ?, garden = ?, water_supply = ?,
                power_backup = ?, parking_area = ?, gym = ?, shopping_mall = ?,
                hospital = ?, school = ?, market_area = ?, description = ?,
                image_01 = ?, image_02 = ?, image_03 = ?, image_04 = ?, image_05 = ?
            WHERE id = ? AND user_id = ?
        ");

        $params = [
            $formData['property_name'] ?? '',
            $formData['address'] ?? '',
            $formData['location_place_id'] ?? '',
            !empty($formData['latitude']) ? sprintf("%.8f", floatval($formData['latitude'])) : null,
            !empty($formData['longitude']) ? sprintf("%.8f", floatval($formData['longitude'])) : null,
            !empty($formData['price']) ? sprintf("%.2f", floatval($formData['price'])) : '0.00',
            $formData['type'] ?? '',
            $formData['offer'] ?? '',
            $formData['status'] ?? '',
            $formData['furnished'] ?? '',
            !empty($formData['bhk']) ? (string)$formData['bhk'] : '0',
            !empty($formData['deposite']) ? sprintf("%.2f", floatval($formData['deposite'])) : '0.00',
            !empty($formData['bedroom']) ? (string)$formData['bedroom'] : '0',
            !empty($formData['bathroom']) ? (string)$formData['bathroom'] : '0',
            !empty($formData['balcony']) ? (string)$formData['balcony'] : '0',
            !empty($formData['carpet']) ? (string)$formData['carpet'] : '0',
            !empty($formData['age']) ? (string)$formData['age'] : '0',
            !empty($formData['total_floors']) ? (string)$formData['total_floors'] : '0',
            !empty($formData['room_floor']) ? (string)$formData['room_floor'] : '0',
            $formData['loan'] ?? '',
            $formData['lift'] ?? 'no',
            $formData['security_guard'] ?? 'no',
            $formData['play_ground'] ?? 'no',
            $formData['garden'] ?? 'no',
            $formData['water_supply'] ?? 'no',
            $formData['power_backup'] ?? 'no',
            $formData['parking_area'] ?? 'no',
            $formData['gym'] ?? 'no',
            $formData['shopping_mall'] ?? 'no',
            $formData['hospital'] ?? 'no',
            $formData['school'] ?? 'no',
            $formData['market_area'] ?? 'no',
            $formData['description'] ?? '',
            $formData['images'][0] ?? '',
            $formData['images'][1],
            $formData['images'][2],
            $formData['images'][3],
            $formData['images'][4],
            $update_id,
            (int)$user_id
        ];

        error_log("updateProperty: Executing query with params=" . json_encode($params));
        $stmt->execute($params);
        $rowCount = $stmt->rowCount();
        error_log("updateProperty: Query executed, propId=$update_id, user_id=$user_id, rows_affected=$rowCount");

        if ($rowCount > 0) {
            return [
                'success' => true,
                'message' => 'Property updated successfully!',
                'rows_affected' => $rowCount
            ];
        } else {
            return [
                'success' => true, // Consider it successful even if no changes, but inform user
                'message' => 'No changes made to the property (data identical).',
                'rows_affected' => $rowCount
            ];
        }
    } catch (PDOException $e) {
        error_log("updateProperty: Database error for propId=$update_id, user_id=$user_id: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
    public function deleteImage($image_num, $update_id, $user_id, $old_image) {
        try {
            if (!empty($old_image) && !preg_match('/^[A-Za-z0-9\-\_\.]+\.(jpg|jpeg|png|gif|webp)$/', $old_image)) {
                error_log("deleteImage Invalid image file name for propId=$update_id, user_id=$user_id, old_image=$old_image");
                return ['success' => false, 'message' => "Invalid image file name for Image 0$image_num"];
            }

            $stmt = $this->conn->prepare("UPDATE `property` SET image_0$image_num = NULL WHERE id = ? AND user_id = ?");
            $stmt->execute([$update_id, $user_id]);

            if ($stmt->rowCount() > 0 && !empty($old_image)) {
                $file_path = ROOT_DIR . "uploaded_files/" . $old_image;
                if (file_exists($file_path) && is_file($file_path)) {
                    if (unlink($file_path)) {
                        error_log("deleteImage: Deleted image_0$image_num for propId=$update_id, user_id=$user_id, file=$file_path");
                        return ['success' => true, 'message' => "Image 0$image_num deleted successfully!"];
                    } else {
                        error_log("deleteImage: Failed to unlink file for propId=$update_id, user_id=$user_id, file=$file_path");
                        return ['success' => true, 'message' => "Image 0$image_num removed from database but file could not be deleted"];
                    }
                } else {
                    error_log("deleteImage: File does not exist for propId=$update_id, user_id=$user_id, file=$file_path");
                    return ['success' => true, 'message' => "Image 0$image_num removed from database but file not found"];
                }
            }
            error_log("deleteImage: Failed to delete image_0$image_num for propId=$update_id, user_id=$user_id");
            return ['success' => false, 'message' => "Failed to delete Image 0$image_num"];
        } catch (PDOException $e) {
            error_log("deleteImage failed for propId=$update_id, user_id=$user_id: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function searchProperties($params, $translated_property_name, $address, $simplified_address, $latitude, $longitude, $radius, $min, $max, $sort_by = 'date_desc', $page = 1, $per_page = 10, $keywords = '', $amenities = [], $age_min = null, $age_max = null, $bedroom = null, $bathroom = null) {
        try {
            $sql = "SELECT p.*, 
                       CASE WHEN ps.is_suspicious = 1 THEN 1 ELSE 0 END AS is_suspicious";
            $bindParams = [];

            if ($latitude !== null && $longitude !== null && $radius !== null) {
                $sql .= ", (6371 * acos(
                        cos(radians(:latitude)) * cos(radians(location_lat)) * 
                        cos(radians(location_lng) - radians(:longitude)) + 
                        sin(radians(:latitude)) * sin(radians(location_lat))
                    )) AS distance";
                $bindParams[':latitude'] = sprintf("%.8f", floatval($latitude));
                $bindParams[':longitude'] = sprintf("%.8f", floatval($longitude));
                $bindParams[':radius'] = $radius;
            }

            $sql .= " FROM `property` p 
                      LEFT JOIN property_suspicion ps ON p.id = ps.property_id 
                      WHERE p.status != 'expired'";

            if (!empty($translated_property_name) || !empty($keywords)) {
                $sql .= " AND (p.property_name LIKE :property_name OR p.description LIKE :keywords)";
                $bindParams[':property_name'] = '%' . ($translated_property_name ?: $keywords) . '%';
                $bindParams[':keywords'] = '%' . $keywords . '%';
            }

            if (!empty($address)) {
                $sql .= " AND (p.address LIKE :address OR p.address LIKE :simplified_address)";
                $bindParams[':address'] = "%$address%";
                $bindParams[':simplified_address'] = "%$simplified_address%";
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
                $bindParams[':bhk'] = $params['bhk'];
            }
            if (!empty($params['furnished'])) {
                $sql .= " AND p.furnished = :furnished";
                $bindParams[':furnished'] = $params['furnished'];
            }
            if ($bedroom !== null) {
                $sql .= " AND p.bedroom = :bedroom";
                $bindParams[':bedroom'] = $bedroom;
            }
            if ($bathroom !== null) {
                $sql .= " AND p.bathroom = :bathroom";
                $bindParams[':bathroom'] = $bathroom;
            }
            if ($age_min !== null) {
                $sql .= " AND p.age >= :age_min";
                $bindParams[':age_min'] = $age_min;
            }
            if ($age_max !== null) {
                $sql .= " AND p.age <= :age_max";
                $bindParams[':age_max'] = $age_max;
            }

            $amenities_list = ['lift', 'security_guard', 'play_ground', 'garden', 'water_supply', 'power_backup', 'parking_area', 'gym', 'shopping_mall', 'hospital', 'school', 'market_area'];
            foreach ($amenities as $amenity) {
                if (in_array($amenity, $amenities_list)) {
                    $sql .= " AND p.$amenity = 'yes'";
                }
            }

            if ($latitude !== null && $longitude !== null && $radius !== null) {
                $sql .= " AND p.location_lat IS NOT NULL AND p.location_lng IS NOT NULL";
                $sql .= " AND (6371 * acos(
                        cos(radians(:latitude)) * cos(radians(p.location_lat)) * 
                        cos(radians(p.location_lng) - radians(:longitude)) + 
                        sin(radians(:latitude)) * sin(radians(p.location_lat))
                    )) <= :radius";
            }

            $sql .= " AND p.price BETWEEN :min AND :max";
            $bindParams[':min'] = $min;
            $bindParams[':max'] = $max;

            $sort_options = [
                'distance' => 'distance ASC, p.date DESC',
                'price_asc' => 'p.price ASC, p.date DESC',
                'price_desc' => 'p.price DESC, p.date DESC',
                'date_desc' => 'p.date DESC',
                'date_asc' => 'p.date ASC'
            ];
            $sql .= " ORDER BY " . ($sort_options[$sort_by] ?? 'p.date DESC');

            $offset = ($page - 1) * $per_page;
            $sql .= " LIMIT :per_page OFFSET :offset";
            $bindParams[':per_page'] = $per_page;
            $bindParams[':offset'] = $offset;

            $stmt = $this->conn->prepare($sql);
            foreach ($bindParams as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue($key, $value, $paramType);
            }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countSql = "SELECT COUNT(*) FROM `property` p 
                         LEFT JOIN property_suspicion ps ON p.id = ps.property_id 
                         WHERE p.status != 'expired'";
            if (!empty($translated_property_name) || !empty($keywords)) {
                $countSql .= " AND (p.property_name LIKE :property_name OR p.description LIKE :keywords)";
            }
            if (!empty($address)) {
                $countSql .= " AND (p.address LIKE :address OR p.address LIKE :simplified_address)";
            }
            if (!empty($params['type'])) {
                $countSql .= " AND p.type = :type";
            }
            if (!empty($params['offer'])) {
                $countSql .= " AND p.offer = :offer";
            }
            if ($params['bhk'] !== null) {
                $countSql .= " AND p.bhk = :bhk";
            }
            if (!empty($params['furnished'])) {
                $countSql .= " AND p.furnished = :furnished";
            }
            if ($bedroom !== null) {
                $countSql .= " AND p.bedroom = :bedroom";
            }
            if ($bathroom !== null) {
                $countSql .= " AND p.bathroom = :bathroom";
            }
            if ($age_min !== null) {
                $countSql .= " AND p.age >= :age_min";
            }
            if ($age_max !== null) {
                $countSql .= " AND p.age <= :age_max";
            }
            foreach ($amenities as $amenity) {
                if (in_array($amenity, $amenities_list)) {
                    $countSql .= " AND p.$amenity = 'yes'";
                }
            }
            if ($latitude !== null && $longitude !== null && $radius !== null) {
                $countSql .= " AND p.location_lat IS NOT NULL AND p.location_lng IS NOT NULL";
                $countSql .= " AND (6371 * acos(
                        cos(radians(:latitude)) * cos(radians(p.location_lat)) * 
                        cos(radians(p.location_lng) - radians(:longitude)) + 
                        sin(radians(:latitude)) * sin(radians(p.location_lat))
                    )) <= :radius";
            }
            $countSql .= " AND p.price BETWEEN :min AND :max";

            $countStmt = $this->conn->prepare($countSql);
            foreach ($bindParams as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $countStmt->bindValue($key, $value, $paramType);
            }
            $countStmt->execute();
            $total_results = $countStmt->fetchColumn();

            error_log("searchProperties: Found " . count($results) . " properties, total=$total_results");
            return [
                'results' => $results,
                'total_results' => $total_results,
                'total_pages' => ceil($total_results / $per_page)
            ];
        } catch (PDOException $e) {
            error_log("searchProperties failed: " . $e->getMessage());
            return ['results' => [], 'total_results' => 0, 'total_pages' => 0];
        }
    }

    public function getLatestProperties($limit = 6) {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*, 
                       CASE WHEN ps.is_suspicious = 1 THEN 1 ELSE 0 END AS is_suspicious
                FROM `property` p 
                LEFT JOIN property_suspicion ps ON p.id = ps.property_id 
                WHERE p.status != 'expired'
                ORDER BY p.date DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getLatestProperties: Fetched " . count($results) . " properties");
            return $results;
        } catch (PDOException $e) {
            error_log("getLatestProperties failed: " . $e->getMessage());
            return [];
        }
    }

    public function insertProperty($formData, $user_id) {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO `property` (
                    user_id, property_name, address, location_place_id, location_lat, location_lng,
                    price, type, offer, status, furnished, bhk, deposite, bedroom, bathroom, balcony,
                    carpet, age, total_floors, room_floor, loan, lift, security_guard, play_ground,
                    garden, water_supply, power_backup, parking_area, gym, shopping_mall, hospital,
                    school, market_area, image_01, image_02, image_03, image_04, image_05, description
                ) VALUES (
                    :user_id, :property_name, :address, :location_place_id, :location_lat, :location_lng,
                    :price, :type, :offer, :status, :furnished, :bhk, :deposite, :bedroom, :bathroom, :balcony,
                    :carpet, :age, :total_floors, :room_floor, :loan, :lift, :security_guard, :play_ground,
                    :garden, :water_supply, :power_backup, :parking_area, :gym, :shopping_mall, :hospital,
                    :school, :market_area, :image_01, :image_02, :image_03, :image_04, :image_05, :description
                )
            ");

            $images = array_fill(0, 5, null);
            if (isset($formData['images']) && is_array($formData['images'])) {
                for ($i = 0; $i < min(5, count($formData['images'])); $i++) {
                    $images[$i] = !empty($formData['images'][$i]) ? $formData['images'][$i] : ($i === 0 ? '' : null);
                }
            }

            $params = [
                ':user_id' => !empty($user_id) ? (string)$user_id : '0',
                ':property_name' => !empty($formData['property_name']) ? $formData['property_name'] : '',
                ':address' => !empty($formData['address']) ? $formData['address'] : '',
                ':location_place_id' => !empty($formData['location_place_id']) ? $formData['location_place_id'] : '',
                ':location_lat' => !empty($formData['latitude']) ? sprintf("%.8f", floatval($formData['latitude'])) : '0.00000000',
                ':location_lng' => !empty($formData['longitude']) ? sprintf("%.8f", floatval($formData['longitude'])) : '0.00000000',
                ':price' => !empty($formData['price']) ? sprintf("%.2f", floatval($formData['price'])) : '0.00',
                ':type' => !empty($formData['type']) ? $formData['type'] : '',
                ':offer' => !empty($formData['offer']) ? $formData['offer'] : '',
                ':status' => !empty($formData['status']) ? $formData['status'] : '',
                ':furnished' => !empty($formData['furnished']) ? $formData['furnished'] : '',
                ':bhk' => !empty($formData['bhk']) ? (string)$formData['bhk'] : '0',
                ':deposite' => !empty($formData['deposite']) ? sprintf("%.2f", floatval($formData['deposite'])) : '0.00',
                ':bedroom' => !empty($formData['bedroom']) ? (string)$formData['bedroom'] : '0',
                ':bathroom' => !empty($formData['bathroom']) ? (string)$formData['bathroom'] : '0',
                ':balcony' => !empty($formData['balcony']) ? (string)$formData['balcony'] : '0',
                ':carpet' => !empty($formData['carpet']) ? (string)$formData['carpet'] : '0',
                ':age' => !empty($formData['age']) ? (string)$formData['age'] : '0',
                ':total_floors' => !empty($formData['total_floors']) ? (string)$formData['total_floors'] : '0',
                ':room_floor' => !empty($formData['room_floor']) ? (string)$formData['room_floor'] : '0',
                ':loan' => !empty($formData['loan']) ? $formData['loan'] : '',
                ':lift' => !empty($formData['lift']) ? $formData['lift'] : 'no',
                ':security_guard' => !empty($formData['security_guard']) ? $formData['security_guard'] : 'no',
                ':play_ground' => !empty($formData['play_ground']) ? $formData['play_ground'] : 'no',
                ':garden' => !empty($formData['garden']) ? $formData['garden'] : 'no',
                ':water_supply' => !empty($formData['water_supply']) ? $formData['water_supply'] : 'no',
                ':power_backup' => !empty($formData['power_backup']) ? $formData['power_backup'] : 'no',
                ':parking_area' => !empty($formData['parking_area']) ? $formData['parking_area'] : 'no',
                ':gym' => !empty($formData['gym']) ? $formData['gym'] : 'no',
                ':shopping_mall' => !empty($formData['shopping_mall']) ? $formData['shopping_mall'] : 'no',
                ':hospital' => !empty($formData['hospital']) ? $formData['hospital'] : 'no',
                ':school' => !empty($formData['school']) ? $formData['school'] : 'no',
                ':market_area' => !empty($formData['market_area']) ? $formData['market_area'] : 'no',
                ':image_01' => $images[0] ?? '',
                ':image_02' => $images[1],
                ':image_03' => $images[2],
                ':image_04' => $images[3],
                ':image_05' => $images[4],
                ':description' => !empty($formData['description']) ? $formData['description'] : ''
            ];

            foreach ($params as $key => $value) {
                $paramType = PDO::PARAM_STR;
                if ($value === null) {
                    $paramType = PDO::PARAM_NULL;
                }
                $stmt->bindValue($key, $value, $paramType);
            }

            $success = $stmt->execute();

            if ($success && $stmt->rowCount() > 0) {
                $property_id = $this->conn->lastInsertId();
                $this->conn->commit();
                error_log("insertProperty: Inserted propId=$property_id for user_id=$user_id");
                return ['success' => true, 'property_id' => $property_id, 'message' => 'Property posted successfully!'];
            }

            $this->conn->rollBack();
            error_log("insertProperty: Failed to insert property for user_id=$user_id");
            return ['success' => false, 'message' => 'Failed to insert property into database.'];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("insertProperty failed for user_id=$user_id: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function saveUserPreferences($user_id, $params, $latitude, $longitude, $radius) {
        try {
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $this->conn->prepare("
                INSERT INTO user_preferences 
                (user_id, offer_type, property_type, bhk, min_budget, max_budget, status, furnished, location_place_id, location_lat, location_lng, radius, created_at) 
                VALUES 
                (:user_id, :offer_type, :property_type, :bhk, :min_budget, :max_budget, :status, :furnished, :location_place_id, :location_lat, :location_lng, :radius, NOW())
            ");
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':offer_type', $params['offer'] ?: null, PDO::PARAM_STR);
            $stmt->bindValue(':property_type', $params['type'] ?: null, PDO::PARAM_STR);
            $stmt->bindValue(':bhk', $params['bhk'] !== null ? $params['bhk'] : null, $params['bhk'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':min_budget', $params['min'] > 0 ? $params['min'] : null, $params['min'] > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':max_budget', $params['max'] < PHP_INT_MAX ? $params['max'] : null, $params['max'] < PHP_INT_MAX ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':status', 'active', PDO::PARAM_STR);
            $stmt->bindValue(':furnished', $params['furnished'] ?: null, PDO::PARAM_STR);
            $stmt->bindValue(':location_place_id', $params['place_id'] ?: null, PDO::PARAM_STR);
            $stmt->bindValue(':location_lat', $latitude !== null ? $latitude : null, $latitude !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':location_lng', $longitude !== null ? $longitude : null, $longitude !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':radius', $radius !== null ? $radius : null, $radius !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->execute();
            error_log("saveUserPreferences: Saved preferences for user_id=$user_id");
            return ['success' => true, 'message' => 'Preferences saved successfully!'];
        } catch (PDOException $e) {
            error_log("saveUserPreferences failed for user_id=$user_id: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
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

        // Match offer type
        if (!empty($formData['offer'])) {
            $sql .= " AND (up.offer_type = :offer OR up.offer_type IS NULL)";
            $bindParams[':offer'] = $formData['offer'];
        }

        // Match property type
        if (!empty($formData['type'])) {
            $sql .= " AND (up.property_type = :type OR up.property_type IS NULL)";
            $bindParams[':type'] = $formData['type'];
        }

        // Match BHK
        if (!empty($formData['bhk'])) {
            $sql .= " AND (up.bhk = :bhk OR up.bhk IS NULL)";
            $bindParams[':bhk'] = (int)$formData['bhk'];
        }

        // Match furnished status
        if (!empty($formData['furnished'])) {
            $sql .= " AND (up.furnished = :furnished OR up.furnished IS NULL)";
            $bindParams[':furnished'] = $formData['furnished'];
        }

        // Match price within budget range
        if (!empty($formData['price'])) {
            $sql .= " AND (up.min_budget <= :price AND up.max_budget >= :price OR up.min_budget IS NULL OR up.max_budget IS NULL)";
            $bindParams[':price'] = floatval($formData['price']);
        }

        // Match location within radius (if latitude and longitude are provided)
        if (!empty($formData['latitude']) && !empty($formData['longitude'])) {
            $sql .= " AND up.location_lat IS NOT NULL AND up.location_lng IS NOT NULL";
            $sql .= " AND (6371 * acos(
                cos(radians(:latitude)) * cos(radians(up.location_lat)) * 
                cos(radians(up.location_lng) - radians(:longitude)) + 
                sin(radians(:latitude)) * sin(radians(up.location_lat))
            )) <= COALESCE(up.radius, 10)";
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