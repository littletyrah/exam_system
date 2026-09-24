<?php

function getDbConnection() {
    $host = 'localhost';
    $dbname = 'exam_system';
    $username = 'root';
    $password = ''; // default XAMPP MySQL password is empty

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
    }
}