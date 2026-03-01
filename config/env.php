<?php
if (!defined('ROOT_DIR')) define('ROOT_DIR', __DIR__ . '/..');
if (!defined('PUBLIC_URL')) define('PUBLIC_URL', '/');

$dotenvFile = ROOT_DIR . '/.env';
if (!file_exists($dotenvFile)) die("Configuration error: .env file not found");

foreach(file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line){
    $line = trim($line);
    if($line === '' || str_starts_with($line,'#')) continue;
    [$name,$value] = explode('=', $line, 2);
    $_ENV[trim($name)] = trim($value);
    putenv("$name=$value");
}
