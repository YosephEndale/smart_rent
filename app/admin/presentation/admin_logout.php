<?php

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3)); 
}

require_once ROOT_DIR . '/components/connect.php';

session_start(); 

unset($_SESSION['admin_id']); 

session_destroy();

header('location:login.php'); 
exit();
?>