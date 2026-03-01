<?php
// app/auth/data/otps.php

function getOtpByUserId($user_id) {
    global $conn;
    try {
        $select_otp = $conn->prepare("SELECT * FROM `otps` WHERE user_id = ? AND expires_at > NOW() LIMIT 1");
        $select_otp->execute([$user_id]);
        $otp_data = $select_otp->fetch(PDO::FETCH_ASSOC);
        return $otp_data ?: false;
    } catch (PDOException $e) {
        error_log("Error fetching OTP for user ID $user_id: " . $e->getMessage());
        return false;
    }
}

function deleteOtp($user_id) {
    global $conn;
    try {
        $delete_otp = $conn->prepare("DELETE FROM `otps` WHERE user_id = ?");
        $delete_otp->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Error deleting OTP for user ID $user_id: " . $e->getMessage());
    }
}

function insertOtp($user_id, $hashed_otp, $expires_at) {
    global $conn;
    try {
        $insert_otp = $conn->prepare("INSERT INTO `otps` (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
        $insert_otp->execute([$user_id, $hashed_otp, $expires_at]);
    } catch (PDOException $e) {
        error_log("Error inserting OTP for user ID $user_id: " . $e->getMessage());
        throw $e;
    }
}
?>