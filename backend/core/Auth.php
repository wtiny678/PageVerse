<?php
// backend/core/Auth.php

require_once __DIR__ . "/Response.php";

class Auth
{

    private static $secret_key = "MY_SUPER_SECRET_KEY_12345";

    // ========================
    // GENERATE JWT
    // ========================
    public static function generateToken($user)
    {
        $header = json_encode(["typ" => "JWT", "alg" => "HS256"]);

        $payload = json_encode([
            "id"    => $user["id"],
            "email" => $user["email"],
            "role"  => $user["role"],
            "iat"   => time(),
            "exp"   => time() + (60 * 60 * 24) // 1 day
        ]);

        $base64Header  = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payload);

        $signature = hash_hmac(
            "sha256",
            $base64Header . "." . $base64Payload,
            self::$secret_key,
            true
        );

        $base64Signature = self::base64UrlEncode($signature);

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    // ========================
    // VERIFY JWT
    // ========================
    public static function verifyToken($token)
    {
        $parts = explode(".", $token);
        if (count($parts) !== 3) return false;

        [$header, $payload, $signature] = $parts;

        $expected = self::base64UrlEncode(
            hash_hmac("sha256", $header . "." . $payload, self::$secret_key, true)
        );

        if (!hash_equals($expected, $signature)) return false;

        $data = json_decode(self::base64UrlDecode($payload), true);

        if (!$data || $data["exp"] < time()) return false;

        return $data;
    }

    // ========================
    // READ TOKEN
    // ========================
    public static function getBearerToken()
    {

        // 1. Try Apache header
        if (!empty($_SERVER["HTTP_AUTHORIZATION"])) {
            $auth = $_SERVER["HTTP_AUTHORIZATION"];
        }
        // 2. Try Nginx/redirect
        elseif (!empty($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) {
            $auth = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
        }
        // 3. Try getallheaders
        elseif (function_exists("getallheaders")) {
            $headers = getallheaders();
            $auth = $headers["Authorization"] ?? $headers["authorization"] ?? null;
        } else {
            $auth = null;
        }

        if (!$auth) return null;

        if (preg_match("/Bearer\s(\S+)/", $auth, $matches)) {
            return $matches[1];
        }

        return null;
    }

    // ========================
    // CHECK LOGIN
    // ========================
public static function check($tokenFromUrl = null){

    $headers = getallheaders();

    // PRIORITY 1 → Bearer header
    if(isset($headers["Authorization"])){

        $token = str_replace("Bearer ", "", $headers["Authorization"]);

    }
    // PRIORITY 2 → token from iframe URL
    else if($tokenFromUrl){

        $token = $tokenFromUrl;

    }
    else{
        Response::unauthorized("Token missing");
    }

    return self::verifyToken($token);
}

    // ========================
    // BASE64 HELPERS
    // ========================
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, "-_", "+/"));
    }
}
