<?php
namespace App\Screening\Data;

use PDO;
use PDOException;

class ScreeningData {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getPropertyByIdAndUser($property_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT id, property_name FROM property WHERE id = ? AND user_id = ?");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ScreeningData::getPropertyByIdAndUser error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getScreeningPreferences($property_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT preferences_text FROM screening_preferences WHERE property_id = ? AND user_id = ?");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->fetchColumn() ?: '';
        } catch (PDOException $e) {
            error_log("ScreeningData::getScreeningPreferences error: " . $e->getMessage());
            throw $e;
        }
    }

    public function saveScreeningPreferences($property_id, $user_id, $preferences_text) {
        try {
            $check_stmt = $this->conn->prepare("SELECT id FROM screening_preferences WHERE property_id = ? AND user_id = ?");
            $check_stmt->execute([$property_id, $user_id]);
            if ($check_stmt->rowCount() > 0) {
                $stmt = $this->conn->prepare("UPDATE screening_preferences SET preferences_text = ? WHERE property_id = ? AND user_id = ?");
                $stmt->execute([$preferences_text, $property_id, $user_id]);
            } else {
                $stmt = $this->conn->prepare("INSERT INTO screening_preferences (property_id, user_id, preferences_text) VALUES (?, ?, ?)");
                $stmt->execute([$property_id, $user_id, $preferences_text]);
            }
            return true;
        } catch (PDOException $e) {
            error_log("ScreeningData::saveScreeningPreferences error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getScreeningQuestions($property_id) {
        try {
            $stmt = $this->conn->prepare("SELECT id, question_text FROM screening_questions WHERE property_id = ?");
            $stmt->execute([$property_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ScreeningData::getScreeningQuestions error: " . $e->getMessage());
            throw $e;
        }
    }

    public function saveScreeningQuestion($property_id, $question_text, $ai_metadata) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO screening_questions (property_id, question_text, ai_metadata) VALUES (?, ?, ?)");
            $stmt->execute([$property_id, $question_text, $ai_metadata]);
            return true;
        } catch (PDOException $e) {
            error_log("ScreeningData::saveScreeningQuestion error: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateScreeningQuestion($question_id, $property_id, $question_text) {
        try {
            $stmt = $this->conn->prepare("UPDATE screening_questions SET question_text = ? WHERE id = ? AND property_id = ?");
            $stmt->execute([$question_text, $question_id, $property_id]);
            return true;
        } catch (PDOException $e) {
            error_log("ScreeningData::updateScreeningQuestion error: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteScreeningQuestion($question_id, $property_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM screening_questions WHERE id = ? AND property_id = ?");
            $stmt->execute([$question_id, $property_id]);
            return true;
        } catch (PDOException $e) {
            error_log("ScreeningData::deleteScreeningQuestion error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getPropertyDetails($property_id) {
        try {
            $stmt = $this->conn->prepare("SELECT id, property_name, address, price, bhk, furnished, lift, parking_area FROM property WHERE id = ?");
            $stmt->execute([$property_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ScreeningData::getPropertyDetails error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getApplicants($property_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT DISTINCT u.user_id, u.name, u.reputation_score, u.review_count
                FROM tenant_answers ta
                JOIN users u ON ta.user_id = u.user_id
                WHERE ta.property_id = ?
            ");
            $stmt->execute([$property_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ScreeningData::getApplicants error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getTenantAnswers($property_id, $tenant_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT ta.question_id, sq.question_text, ta.answer_text, ta.created_at, sq.created_at AS question_created_at
                FROM tenant_answers ta
                JOIN screening_questions sq ON ta.question_id = sq.id
                WHERE ta.property_id = ? AND ta.user_id = ?
            ");
            $stmt->execute([$property_id, $tenant_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ScreeningData::getTenantAnswers error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getTenantScore($property_id, $tenant_id) {
        try {
            $stmt = $this->conn->prepare("SELECT score, criteria_breakdown FROM tenant_scores WHERE property_id = ? AND user_id = ?");
            $stmt->execute([$property_id, $tenant_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ScreeningData::getTenantScore error: " . $e->getMessage());
            throw $e;
        }
    }

    public function saveTenantScore($property_id, $tenant_id, $score, $criteria_breakdown) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO tenant_scores (property_id, user_id, score, criteria_breakdown)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$property_id, $tenant_id, $score, $criteria_breakdown]);
            return true;
        } catch (PDOException $e) {
            error_log("ScreeningData::saveTenantScore error: " . $e->getMessage());
            throw $e;
        }
    }

    public function hasApplied($property_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM tenant_answers WHERE property_id = ? AND user_id = ?");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("ScreeningData::hasApplied error: " . $e->getMessage());
            throw $e;
        }
    }

    public function saveTenantAnswers($property_id, $user_id, $answers) {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                INSERT INTO tenant_answers (question_id, property_id, user_id, answer_text)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($answers as $question_id => $answer_text) {
                $stmt->execute([$question_id, $property_id, $user_id, $answer_text]);
            }
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("ScreeningData::saveTenantAnswers error: " . $e->getMessage());
            throw $e;
        }
    }
}