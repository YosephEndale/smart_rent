<?php
namespace App\Admin\Data;

use PDO;

class MessageData {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getMessages($search = '') {
        if ($search) {
            $stmt = $this->conn->prepare("SELECT * FROM `messages` WHERE name LIKE ? OR email LIKE ? OR message LIKE ?");
            $stmt->execute(["%$search%", "%$search%", "%$search%"]);
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM `messages`");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteMessage($message_id) {
        $stmt = $this->conn->prepare("SELECT * FROM `messages` WHERE id = ?");
        $stmt->execute([$message_id]);
        if ($stmt->rowCount() > 0) {
            $stmt = $this->conn->prepare("DELETE FROM `messages` WHERE id = ?");
            return $stmt->execute([$message_id]);
        }
        return false;
    }

    public function insertMessage($id, $name, $email, $number, $message) {
        $stmt = $this->conn->prepare("INSERT INTO `messages` (id, name, email, number, message) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$id, $name, $email, $number, $message]);
    }
}