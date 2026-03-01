<?php
namespace App\Admin\Data;

use PDO;

class PropertyData {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getPropertyById($property_id) {
        $stmt = $this->conn->prepare("SELECT * FROM `property` WHERE id = ? LIMIT 1");
        $stmt->execute([$property_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getProperties($search = '') {
        if ($search) {
            $stmt = $this->conn->prepare("
                SELECT p.*
                FROM `property` p
                LEFT JOIN `property_suspicion` ps ON p.id = ps.property_id
                WHERE (ps.is_suspicious IS NULL OR ps.is_suspicious = 0 OR ps.admin_review_status = 'approved')
                AND (p.property_name LIKE ? OR p.address LIKE ?)
                ORDER BY p.date DESC
            ");
            $stmt->execute(["%$search%", "%$search%"]);
        } else {
            $stmt = $this->conn->prepare("
                SELECT p.*
                FROM `property` p
                LEFT JOIN `property_suspicion` ps ON p.id = ps.property_id
                WHERE (ps.is_suspicious IS NULL OR ps.is_suspicious = 0 OR ps.admin_review_status = 'approved')
                ORDER BY p.date DESC
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSuspiciousProperties($search = '') {
        if ($search) {
            $stmt = $this->conn->prepare("
                SELECT p.*, ps.suspicion_score, ps.admin_review_status
                FROM `property` p
                INNER JOIN `property_suspicion` ps ON p.id = ps.property_id
                WHERE ps.is_suspicious = 1 AND ps.admin_review_status != 'approved'
                AND (p.property_name LIKE ? OR p.address LIKE ?)
                ORDER BY p.date DESC
            ");
            $stmt->execute(["%$search%", "%$search%"]);
        } else {
            $stmt = $this->conn->prepare("
                SELECT p.*, ps.suspicion_score, ps.admin_review_status
                FROM `property` p
                INNER JOIN `property_suspicion` ps ON p.id = ps.property_id
                WHERE ps.is_suspicious = 1 AND ps.admin_review_status != 'approved'
                ORDER BY p.date DESC
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteProperty($property_id) {
        $stmt = $this->conn->prepare("SELECT image_01, image_02, image_03, image_04, image_05 FROM `property` WHERE id = ?");
        $stmt->execute([$property_id]);
        $images = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($images) {
            foreach (['image_01', 'image_02', 'image_03', 'image_04', 'image_05'] as $img_field) {
                if (!empty($images[$img_field]) && file_exists('../uploaded_files/' . $images[$img_field])) {
                    unlink('../uploaded_files/' . $images[$img_field]);
                }
            }
            $stmt = $this->conn->prepare("DELETE FROM `property` WHERE id = ?");
            return $stmt->execute([$property_id]);
        }
        return false;
    }

    public function getSuspicionData($property_id) {
        $stmt = $this->conn->prepare("SELECT suspicion_score, is_suspicious, flagged_at, admin_review_status FROM `property_suspicion` WHERE property_id = ?");
        $stmt->execute([$property_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserReports($property_id) {
        $stmt = $this->conn->prepare("SELECT created_at, reason AS report_reason FROM `property_reports` WHERE property_id = ? ORDER BY created_at DESC");
        $stmt->execute([$property_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateSuspicionStatus($property_id, $status, $is_suspicious, $admin_id) {
        $stmt = $this->conn->prepare("
            UPDATE `property_suspicion`
            SET admin_review_status = ?, is_suspicious = ?, reviewed_at = NOW(), admin_id = ?
            WHERE property_id = ?
        ");
        return $stmt->execute([$status, $is_suspicious, $admin_id, $property_id]);
    }
}