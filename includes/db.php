<?php
/** Database connection (PDO, native prepared statements, strict SQL mode). */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string)cfg('db.host');
    $port = (string)cfg('db.port');
    $dsn = 'mysql:host=' . $host . ($port !== '' ? ';port=' . $port : '') . ';dbname=' . cfg('db.name') . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, (string)cfg('db.user'), (string)cfg('db.password'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements: each named marker must be unique
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        // Keep MySQL's clock aligned with PHP's (Africa/Kampala, UTC+3, no daylight saving).
        try {
            $pdo->exec("SET time_zone = '+03:00'");
        } catch (PDOException $e) {
            // Some managed hosts restrict this; the app still works with server time.
        }
    } catch (PDOException $e) {
        civic_log_error('Database connection failed', $e);
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Database connection failed: " . $e->getMessage() . PHP_EOL);
            exit(1);
        }
        http_response_code(503);
        render_error_page(503, 'Service temporarily unavailable', 'CivicServe cannot reach its database right now. Please try again shortly.');
    }

    return $pdo;
}
