<?php
namespace App\User;

use PDOException;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language'])) {
    $language = trim($_POST['language']);
    $user_id = $_SESSION['user_id'] ?? '';
    $allowed_languages = ['en', 'it', 'am'];

    error_log("save_language.php called with language: $language, user_id: " . ($user_id ?: 'not set'));

    if (!in_array($language, $allowed_languages)) {
        $response['error'] = 'Invalid language code';
        error_log("Invalid language code: $language");
        echo json_encode($response);
        exit;
    }

    // Update session
    $_SESSION['language'] = $language;
    error_log("Session language updated to: $language");

    // Update database if user is logged in
    if (!empty($user_id)) {
        try {
            $conn = get_db_connection();
            $stmt = $conn->prepare("UPDATE users SET language = ? WHERE user_id = ?");
            $stmt->execute([$language, $user_id]);

            if ($stmt->rowCount() > 0) {
                error_log("Successfully updated language to $language for user_id: $user_id");
                $response['success'] = true;
            } else {
                error_log("No rows updated for user_id: $user_id, language: $language");
                $response['error'] = 'No rows updated, user may not exist';
            }
        } catch (PDOException $e) {
            error_log("Database error for user_id: $user_id - Error: " . $e->getMessage());
            $response['error'] = 'Database error: ' . $e->getMessage();
        }
    } else {
        error_log("No user_id, language set in session only: $language");
        $response['success'] = true;
    }
} else {
    $response['error'] = 'Invalid request';
    error_log("Invalid request or missing language parameter");
}

echo json_encode($response);