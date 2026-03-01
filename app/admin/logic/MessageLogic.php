<?php
namespace App\Admin\Logic;

use App\Admin\Data\MessageData;

class MessageLogic {
    private $messageData;

    public function __construct($conn) {
        $this->messageData = new MessageData($conn);
    }

    public function deleteMessage($message_id) {
        if ($this->messageData->deleteMessage($message_id)) {
            return ['success' => true, 'message' => 'Message deleted successfully!'];
        }
        return ['success' => false, 'message' => 'Message not found!'];
    }
}