<?php

$host     = '127.0.0.1';
$dbname   = 'APEX_Learning_Hub';
$username = 'root';
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    try {
        $tempPdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `APEX_Learning_Hub` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $ex) {
        die("Database Connection Error: " . $ex->getMessage());
    }
}
