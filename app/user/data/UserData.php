<?php
namespace App\User\Data;

use PDO;
use PDOException;

class UserData {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getUserById($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("UserData::getUserById error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function updateUserName($user_id, $name) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET name = ? WHERE user_id = ?");
            $stmt->execute([$name, $user_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("UserData::updateUserName error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function updateUserEmail($user_id, $email) {
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->rowCount() > 0) {
                return ['error' => 'Email already taken'];
            }
            $stmt = $this->db->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->execute([$email, $user_id]);
            return $stmt->rowCount() > 0 ? ['success' => true] : ['error' => 'Failed to update email'];
        } catch (PDOException $e) {
            error_log("UserData::updateUserEmail error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function updateUserNumber($user_id, $number) {
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE number = ? AND user_id != ?");
            $stmt->execute([$number, $user_id]);
            if ($stmt->rowCount() > 0) {
                return ['error' => 'Number already taken'];
            }
            $stmt = $this->db->prepare("UPDATE users SET number = ? WHERE user_id = ?");
            $stmt->execute([$number, $user_id]);
            return $stmt->rowCount() > 0 ? ['success' => true] : ['error' => 'Failed to update number'];
        } catch (PDOException $e) {
            error_log("UserData::updateUserNumber error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function updateUserTelegramId($user_id, $telegram_id, $encrypted_telegram_id) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET telegram_id = ?, encrypted_telegram_id = ? WHERE user_id = ?");
            $stmt->execute([$telegram_id, $encrypted_telegram_id, $user_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("UserData::updateUserTelegramId error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function updateUserPassword($user_id, $hashed_password) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("UserData::updateUserPassword error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getSavedProperties($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.* 
                FROM saved s 
                JOIN property p ON s.property_id = p.id 
                WHERE s.user_id = ? 
                ORDER BY p.date DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("UserData::getSavedProperties error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function saveProperty($user_id, $property_id, $save_id) {
        try {
            $stmt = $this->db->prepare("INSERT INTO saved (id, property_id, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$save_id, $property_id, $user_id]);
            return true;
        } catch (PDOException $e) {
            error_log("UserData::saveProperty error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function removeSavedProperty($user_id, $property_id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM saved WHERE property_id = ? AND user_id = ?");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("UserData::removeSavedProperty error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getUserProperties($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    COALESCE(property_name, 'Unnamed Property') AS property_name,
                    COALESCE(image_01, 'default.jpg') AS image_01,
                    COALESCE(image_02, '') AS image_02,
                    COALESCE(image_03, '') AS image_03,
                    COALESCE(image_04, '') AS image_04,
                    COALESCE(image_05, '') AS image_05,
                    COALESCE(price, 0.00) AS price,
                    COALESCE(address, 'Unknown location') AS address
                FROM property 
                WHERE user_id = ? 
                ORDER BY date DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("UserData::getUserProperties error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getPropertyById($property_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    COALESCE(property_name, 'Unnamed Property') AS property_name,
                    COALESCE(image_01, 'default.jpg') AS image_01,
                    COALESCE(image_02, '') AS image_02,
                    COALESCE(image_03, '') AS image_03,
                    COALESCE(image_04, '') AS image_04,
                    COALESCE(image_05, '') AS image_05,
                    COALESCE(price, 0.00) AS price,
                    COALESCE(address, 'Unknown location') AS address
                FROM property 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("UserData::getPropertyById error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function deleteProperty($property_id) {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT image_01, image_02, image_03, image_04, image_05 FROM property WHERE id = ?");
            $stmt->execute([$property_id]);
            $images = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($images) {
                $upload_dir = ROOT_DIR . '/uploaded_files/';
                foreach (['image_01', 'image_02', 'image_03', 'image_04', 'image_05'] as $image_field) {
                    if (!empty($images[$image_field]) && file_exists($upload_dir . $images[$image_field])) {
                        unlink($upload_dir . $images[$image_field]);
                    } elseif (!empty($images[$image_field])) {
                        error_log("Image not found for deletion: {$upload_dir}{$images[$image_field]}");
                    }
                }
            }

            $this->db->prepare("DELETE FROM saved WHERE property_id = ?")->execute([$property_id]);
            $this->db->prepare("DELETE FROM tenant_answers WHERE property_id = ?")->execute([$property_id]);
            $this->db->prepare("DELETE FROM screening_questions WHERE property_id = ?")->execute([$property_id]);
            $this->db->prepare("DELETE FROM screening_preferences WHERE property_id = ?")->execute([$property_id]);
            $this->db->prepare("DELETE FROM property WHERE id = ?")->execute([$property_id]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("UserData::deleteProperty error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getApplicantCounts($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM property WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $counts = [];
            foreach ($properties as $property) {
                $stmt = $this->db->prepare("SELECT COUNT(DISTINCT user_id) as applicant_count FROM tenant_answers WHERE property_id = ?");
                $stmt->execute([$property['id']]);
                $counts[$property['id']] = $stmt->fetchColumn() ?: 0;
            }
            return $counts;
        } catch (PDOException $e) {
            error_log("UserData::getApplicantCounts error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getApplicants($property_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT u.user_id, u.name
                FROM tenant_answers ta
                JOIN users u ON ta.user_id = u.user_id
                WHERE ta.property_id = ?
            ");
            $stmt->execute([$property_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("UserData::getApplicants error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getApplicantAnswers($property_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT sq.question_text, ta.answer_text, ta.created_at
                FROM tenant_answers ta
                JOIN screening_questions sq ON ta.question_id = sq.id
                WHERE ta.property_id = ? AND ta.user_id = ?
            ");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("UserData::getApplicantAnswers error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getPreferences($property_id, $user_id) {
        try {
            $stmt = $this->db->prepare("SELECT preferences_text FROM screening_preferences WHERE property_id = ? AND user_id = ?");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->fetchColumn() ?: '';
        } catch (PDOException $e) {
            error_log("UserData::getPreferences error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getTenantScore($property_id, $user_id) {
        try {
            $stmt = $this->db->prepare("SELECT score, criteria_breakdown FROM tenant_scores WHERE property_id = ? AND user_id = ?");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("UserData::getTenantScore error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function saveTenantScore($property_id, $user_id, $score, $criteria_breakdown) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO tenant_scores (property_id, user_id, score, criteria_breakdown)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$property_id, $user_id, $score, $criteria_breakdown]);
            return true;
        } catch (PDOException $e) {
            error_log("UserData::saveTenantScore error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getTotalChats($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT property_id) as chat_count
                FROM chat_messages
                WHERE sender_id = ? OR receiver_id = ?
            ");
            $stmt->execute([$user_id, $user_id]);
            return $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            error_log("UserData::getTotalChats error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getTotalSavedProperties($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM saved WHERE user_id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            error_log("UserData::getTotalSavedProperties error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function getPropertyOwner($property_id) {
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM property WHERE id = ? LIMIT 1");
            $stmt->execute([$property_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['user_id'] : null;
        } catch (PDOException $e) {
            error_log("UserData::getPropertyOwner error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function hasExistingRequest($property_id, $sender_id, $receiver_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM chat_messages WHERE property_id = ? AND sender_id = ? AND receiver_id = ?");
            $stmt->execute([$property_id, $sender_id, $receiver_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("UserData::hasExistingRequest error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    public function insertRequest($request_id, $property_id, $sender_id, $receiver_id) {
        try {
            $initial_message = "Enquiry about your property (ID: $property_id)";
            $stmt = $this->db->prepare("
                INSERT INTO chat_messages (id, property_id, sender_id, receiver_id, message, timestamp)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$request_id, $property_id, $sender_id, $receiver_id, $initial_message]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("UserData::insertRequest error: " . $e->getMessage());
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }
}