

<?php
// Define the create_unique_id function
function create_unique_id() {
    return uniqid('id_', true); // Prefix with 'id_' and add entropy for uniqueness
}

if (isset($_POST['save'])) {
    if ($user_id != '') {

        $save_id = create_unique_id();
        $property_id = $_POST['property_id'];
        // Replaced FILTER_SANITIZE_STRING
        $property_id = htmlspecialchars($property_id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Check if the property is already saved by the user
        $verify_saved = $conn->prepare("SELECT * FROM `saved` WHERE property_id = ? and user_id = ?");
        $verify_saved->execute([$property_id, $user_id]);

        if ($verify_saved->rowCount() > 0) {
            // Remove the saved property if it already exists
            $remove_saved = $conn->prepare("DELETE FROM `saved` WHERE property_id = ? AND user_id = ?");
            $remove_saved->execute([$property_id, $user_id]);
            $success_msg[] = 'removed from saved!';
        } else {
            // Save the property to the database
            $insert_saved = $conn->prepare("INSERT INTO `saved`(id, property_id, user_id) VALUES(?,?,?)");
            $insert_saved->execute([$save_id, $property_id, $user_id]);
            $success_msg[] = 'listing saved!';
        }

    } else {
        $warning_msg[] = 'please login first!';
    }
}

if (isset($_POST['send'])) {
    if ($user_id != '') {

        $request_id = create_unique_id();
        $property_id = $_POST['property_id'];
        // Replaced FILTER_SANITIZE_STRING
        $property_id = htmlspecialchars($property_id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Get the user ID of the property owner (receiver)
        $select_receiver = $conn->prepare("SELECT user_id FROM `property` WHERE id = ? LIMIT 1");
        $select_receiver->execute([$property_id]);
        $fetch_receiver = $select_receiver->fetch(PDO::FETCH_ASSOC);
        $receiver = $fetch_receiver['user_id'];

        // Check if a request has already been sent
        $verify_request = $conn->prepare("SELECT * FROM `requests` WHERE property_id = ? AND sender = ? AND receiver = ?");
        $verify_request->execute([$property_id, $user_id, $receiver]);

        if ($verify_request->rowCount() > 0) {
            $warning_msg[] = 'request sent already!';
        } else {
            // Send a new request
            $send_request = $conn->prepare("INSERT INTO `requests`(id, property_id, sender, receiver) VALUES(?,?,?,?)");
            $send_request->execute([$request_id, $property_id, $user_id, $receiver]);
            $success_msg[] = 'request sent successfully!';
        }

    } else {
        $warning_msg[] = 'please login first!';
    }
}

?>