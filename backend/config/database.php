<?php
// backend/config/database.php

class Database
{

    private static $host;
    private static $db_name;
    private static $username;
    private static $password;
    private static $conn = null;

    private static function init()
    {
        // Load config if not already loaded
        if (!class_exists('Config')) {
            require_once __DIR__ . '/../core/Config.php';
        }

        self::$host = Config::getDBHost();
        self::$db_name = Config::getDBName();
        self::$username = Config::getDBUser();
        self::$password = Config::getDBPass();
    }

    public static function getConnection()
    {

        if (self::$conn !== null) {
            return self::$conn;
        }

        // Initialize config values
        self::init();

        try {
            self::$conn = new PDO(
                "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=utf8mb4",
                self::$username,
                self::$password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Database connection failed",
                "data" => null
            ]);
            exit;
        }

        return self::$conn;
    }
}
