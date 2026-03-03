<?php
// backend/core/Response.php

class Response {

    private static function send($status = "success", $message = "", $data = null, $code = 200) {

    // Clear any previous output (important!)
    if (ob_get_length()) {
        ob_clean();
    }

    // DO NOT remove headers here — cors.php already set CORS headers.
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code($code);

    echo json_encode([
        "status"  => $status,
        "message" => $message,
        "data"    => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

    public static function success($message = "Success", $data = null, $code = 200) {
        self::send("success", $message, $data, $code);
    }

    public static function error($message = "Error", $data = null, $code = 400) {
        self::send("error", $message, $data, $code);
    }

    public static function unauthorized($message = "Unauthorized") {
        self::send("error", $message, null, 401);
    }

    public static function notFound($message = "Not Found") {
        self::send("error", $message, null, 404);
    }

    public static function serverError($message = "Server Error") {
        self::send("error", $message, null, 500);
    }
}
