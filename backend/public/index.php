<?php
// backend/public/index.php

// ============================
// DEBUG (REMOVE IN PRODUCTION)
// ============================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================
// CORS (VERY IMPORTANT)
// ============================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight instantly
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// ============================
// AUTOLOADER
// ============================
spl_autoload_register(function ($class) {

    $paths = [
        __DIR__ . "/../core/",
        __DIR__ . "/../config/",
        __DIR__ . "/../modules/auth/",
        __DIR__ . "/../modules/books/",
        __DIR__ . "/../modules/chapters/",
        __DIR__ . "/../modules/comments/",
        __DIR__ . "/../modules/wallet/",
        __DIR__ . "/../modules/payments/",
        __DIR__ . "/../modules/bankdetails/",
        __DIR__ . "/../modules/earnings/",
        __DIR__ . "/../modules/search/",
        __DIR__ . "/../modules/dashboard/",
        __DIR__ . "/../modules/purchases/",
        __DIR__ . "/../modules/library/",
        __DIR__ . "/../modules/read/",
        __DIR__ . "/../modules/access/",
        __DIR__ . "/../modules/bookmarks/"
    ];

    foreach ($paths as $path) {
        $file = $path . $class . ".php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ============================
// CORE FILES
// ============================
require_once __DIR__ . "/../core/Router.php";
require_once __DIR__ . "/../core/Response.php";
require_once __DIR__ . "/../core/Auth.php";

// ============================
// CLEAN REQUEST URI (FINAL FIX)
// ============================
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

/*
    Example incoming URLs:

    /ebook-platform/backend/public/chapters
    /ebook-platform/backend/public/index.php/chapters
    /index.php/chapters

    We normalize all to:

    /chapters
*/

// remove project base path
$base = "/ebook-platform/backend/public";
if (strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}

// remove index.php if present
$uri = str_replace("/index.php", "", $uri);

// normalize
$uri = "/" . trim($uri, "/");

if ($uri === "//" || $uri === "") {
    $uri = "/";
}

$_SERVER["REQUEST_URI"] = $uri;

// ============================
// INIT ROUTER
// ============================
$router = new Router();


// ============================
// AUTH
// ============================
$router->add("POST", "/auth/register", "AuthController@register");
$router->add("POST", "/auth/login", "AuthController@login");
$router->add("GET",  "/auth/profile", "AuthController@profile");


// ============================
// BOOKS
// ============================
$router->add("POST", "/books/create", "BookController@create");
$router->add("GET",  "/books/list", "BookController@list");
$router->add("GET",  "/books/my", "BookController@myBooks");
$router->add("GET",  "/books/view", "BookController@view");
$router->add("POST", "/books/publish", "BookController@publish");


// ============================
// CHAPTERS
// ============================
$router->add("POST", "/chapters", "ChapterController@create");
$router->add("GET",  "/chapters/view", "ChapterController@view");
$router->add("GET",  "/chapters/single", "ChapterController@single");
$router->add("GET",  "/chapters/my", "ChapterController@myChapters");


// ============================
// WALLET
// ============================
$router->add("GET",  "/wallet/balance", "WalletController@balance");
$router->add("POST", "/wallet/add", "WalletController@addMoney");
$router->add("GET",  "/wallet/history", "WalletController@history");
$router->add("POST", "/wallet/fake-add", "WalletController@fakeAddCoins");


// ============================
// PURCHASE
// ============================
$router->add("POST", "/purchase/chapter", "PurchaseController@buyChapter");
$router->add("POST", "/purchase/book", "PurchaseController@buyUploadedBook");

$router->add("POST", "/wallet/buy-book", "AccessController@buyWithWallet");
// ============================
// READ
// ============================
$router->add("GET", "/read/chapter", "ReadController@readChapter");
$router->add("GET", "/read/file", "ReadController@readFile");

$router->add("GET", "/books/file", "BookController@getFileByBook");

// ============================
// COMMENTS
// ============================
$router->add("POST", "/comments/add", "CommentController@add");
$router->add("GET",  "/comments/book", "CommentController@bookComments");
$router->add("GET",  "/comments/chapter", "CommentController@chapterComments");
$router->add("DELETE", "/comments/delete", "CommentController@delete");


// ============================
// ACCESS
// ============================
$router->add("GET", "/access/chapter", "AccessController@checkChapterAccess");


// ============================
// LIBRARY
// ============================
$router->add("GET", "/library", "LibraryController@myLibrary");


// ============================
// DASHBOARD
// ============================
$router->add("GET",  "/dashboard/load", "DashboardController@load");
$router->add("POST", "/dashboard/switch-mode", "DashboardController@switchMode");
$router->add("GET",  "/dashboard/library", "DashboardController@library");


// ============================
// BOOKMARKS
// ============================
$router->add("POST", "/bookmarks/save", "BookmarkController@save");
$router->add("GET",  "/bookmarks/get", "BookmarkController@get");
$router->add("GET",  "/bookmarks/library", "BookmarkController@myLibrary");
$router->add("POST", "/bookmarks/remove", "BookmarkController@remove");
$router->add("POST","/books/delete","BookController@delete");

// ============================
// SEARCH
// ============================
// ============================
// SEARCH
// ============================
$router->add("GET", "/search/books", "SearchController@books");
$router->add("GET", "/search/chapters", "SearchController@chapters");
$router->add("GET", "/search/authors", "SearchController@authors");
$router->add("GET", "/search/type", "SearchController@filterByType");

$router->add("POST","/payments/fake","RazorpayController@fakePayment");
$router->add("POST","/payments/create-order","RazorpayController@createOrder");
$router->add("POST","/payments/verify","RazorpayController@verifyPayment");


// ============================
// EARNINGS
// ============================
$router->add("GET",  "/earnings", "EarningsController@index");       // combined summary + sales
$router->add("GET",  "/earnings/history", "EarningsController@history"); // full list
$router->add("POST", "/earnings/withdraw", "EarningsController@withdraw");

// ============================
// DISPATCH
// ============================
try {
    $router->dispatch();
} catch (Throwable $e) {
    Response::serverError("Server crash: " . $e->getMessage());
}
