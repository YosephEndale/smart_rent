<?php
namespace App\Admin\Data;

use PDO;

class UserData {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getUsers($search = '') {
        if ($search) {
            $stmt = $this->conn->prepare("SELECT * FROM `users` WHERE name LIKE ? OR number LIKE ? OR email LIKE ?");
            $stmt->execute(["%$search%", "%$search%", "%$search%"]);
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM `users`");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserById($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM `users` WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteUser($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM `users` WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            // Delete associated property images
            $stmt = $this->conn->prepare("SELECT image_01, image_02, image_03, image_04, image_05 FROM `property` WHERE user_id = ?");
            $stmt->execute([$user_id]);
            while ($fetch_images = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $images = [
                    $fetch_images['image_01'],
                    $fetch_images['image_02'],
                    $fetch_images['image_03'],
                    $fetch_images['image_04'],
                    $fetch_images['image_05']
                ];
                foreach ($images as $image) {
                    if (!empty($image) && file_exists('../uploaded_files/' . $image)) {
                        unlink('../uploaded_files/' . $image);
                    }
                }
            }
            // Delete related data
            $this->conn->prepare("DELETE FROM `property` WHERE user_id = ?")->execute([$user_id]);
            $this->conn->prepare("DELETE FROM `saved` WHERE user_id = ?")->execute([$user_id]);
            $stmt = $this->conn->prepare("DELETE FROM `users` WHERE user_id = ?");
            return $stmt->execute([$user_id]);
        }
        return false;
    }
}