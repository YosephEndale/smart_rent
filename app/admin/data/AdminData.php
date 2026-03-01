<?php
namespace App\Admin\Data;

use PDO;

class AdminData {
    private $conn;

    public function __construct($conn) {
        if (!$conn instanceof \PDO) {
            throw new \InvalidArgumentException('Invalid PDO connection provided');
        }
        $this->conn = $conn;
    }

    public function getAdminById($admin_id) {
        $stmt = $this->conn->prepare("SELECT * FROM `admins` WHERE id = ? LIMIT 1");
        $stmt->execute([$admin_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAdmins($search = '') {
        if ($search) {
            $stmt = $this->conn->prepare("SELECT * FROM `admins` WHERE name LIKE ?");
            $stmt->execute(["%$search%"]);
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM `admins`");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdminByName($name) {
        $stmt = $this->conn->prepare("SELECT * FROM `admins` WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insertAdmin($id, $name, $hashed_pass) {
        $stmt = $this->conn->prepare("INSERT INTO `admins`(id, name, password) VALUES(?, ?, ?)");
        return $stmt->execute([$id, $name, $hashed_pass]);
    }

    public function updateAdminName($admin_id, $name) {
        $stmt = $this->conn->prepare("UPDATE `admins` SET name = ? WHERE id = ?");
        return $stmt->execute([$name, $admin_id]);
    }

    public function updateAdminPassword($admin_id, $hashed_pass) {
        $stmt = $this->conn->prepare("UPDATE `admins` SET password = ? WHERE id = ?");
        return $stmt->execute([$hashed_pass, $admin_id]);
    }

    public function deleteAdmin($admin_id) {
        $stmt = $this->conn->prepare("SELECT * FROM `admins` WHERE id = ?");
        $stmt->execute([$admin_id]);
        if ($stmt->rowCount() > 0) {
            $stmt = $this->conn->prepare("DELETE FROM `admins` WHERE id = ?");
            return $stmt->execute([$admin_id]);
        }
        return false;
    }
}