<?php
require_once __DIR__ . "/config/database.php";

$db = Database::getConnection();

if ($db) {
    echo "Database connected successfully!";
}
