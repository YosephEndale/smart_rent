<?php
function getUserByEmail($email) {
    global $conn;
    $select_users = $conn->prepare("SELECT * FROM `users` WHERE email = ? LIMIT 1");
    $select_users->execute([$email]);
    return $select_users->fetch(PDO::FETCH_ASSOC);
}
function getUserById($user_id) {
    global $conn;
    $select_user = $conn->prepare("SELECT * FROM `users` WHERE user_id = ? LIMIT 1");
    $select_user->execute([$user_id]);
    return $select_user->fetch(PDO::FETCH_ASSOC);
}