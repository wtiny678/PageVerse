<?php
// backend/core/Config.php
// Environment configuration loader

class Config
{

    private static $loaded = false;
    private static $config = [];

    /**
     * Load environment variables from .env file
     */
    public static function load()
    {
        if (self::$loaded) {
            return;
        }

        $envFile = __DIR__ . '/../.env';

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Remove quotes if present
                    if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
                        $value = $matches[1];
                    }

                    self::$config[$key] = $value;
                    $_ENV[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get configuration value
     */
    public static function get($key, $default = null)
    {
        self::load();

        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }

        // Also check $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        return $default;
    }

    /**
     * Get database host
     */
    public static function getDBHost()
    {
        return self::get('DB_HOST', 'localhost');
    }

    /**
     * Get database name
     */
    public static function getDBName()
    {
        return self::get('DB_NAME', 'ebook_platform');
    }

    /**
     * Get database username
     */
    public static function getDBUser()
    {
        return self::get('DB_USER', 'root');
    }

    /**
     * Get database password
     */
    public static function getDBPass()
    {
        return self::get('DB_PASS', '');
    }

    /**
     * Get JWT secret
     */
    public static function getJWTSecret()
    {
        return self::get('JWT_SECRET', 'DEFAULT_SECRET_KEY_CHANGE_ME');
    }

    /**
     * Get Razorpay Key ID
     */
    public static function getRazorpayKeyId()
    {
        return self::get('RAZORPAY_KEY_ID', '');
    }

    /**
     * Get Razorpay Key Secret
     */
    public static function getRazorpayKeySecret()
    {
        return self::get('RAZORPAY_KEY_SECRET', '');
    }

    /**
     * Check if in development mode
     */
    public static function isDevelopment()
    {
        return self::get('APP_ENV', 'development') === 'development';
    }

    /**
     * Check if debug is enabled
     */
    public static function isDebug()
    {
        return self::get('APP_DEBUG', 'false') === 'true';
    }
}
