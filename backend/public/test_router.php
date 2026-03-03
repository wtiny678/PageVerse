<?php
// backend/public/test_router.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "PHP version: " . phpversion() . "<br>\n";

$routerFile = __DIR__ . "/../core/Router.php";
echo "Expect Router at: $routerFile <br>\n";

if (!file_exists($routerFile)) {
    echo "Router.php: NOT FOUND<br>";
    exit;
}

require_once $routerFile;

echo "Router.php included.<br>";
echo "class Router exists? ";
var_dump(class_exists('Router'));
