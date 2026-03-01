<?php
if (!function_exists('get_db_connection')) {
    function get_db_connection(): PDO {
        // Define ROOT_DIR
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', __DIR__ . '/..');
        }

        // Load environment variables
        $envFile = ROOT_DIR . '/config/env.php';
        if (!file_exists($envFile)) {
            die("Error: env.php not found at $envFile");
        }
        require_once $envFile;

        // Read DB credentials from $_ENV
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'rent_web';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '123456';

        try {
            $conn = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $conn;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection error. Please check your credentials.");
        }
    }
}

// Create connection
$conn = get_db_connection();
