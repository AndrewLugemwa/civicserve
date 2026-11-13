<?php
/**
 * Single entry point loaded by every page. Order matters:
 * config → error policy → security headers → session → database → helpers.
 */
declare(strict_types=1);

if (!defined('CIVIC_ROOT')) {
    define('CIVIC_ROOT', dirname(__DIR__));
}

$GLOBALS['civic_config'] = require __DIR__ . '/config.php';

date_default_timezone_set(cfg('app.timezone', 'Africa/Kampala'));

/** Read a config value using dot notation, e.g. cfg('council.name'). */
function cfg(string $path, $default = null)
{
    $value = $GLOBALS['civic_config'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }
        $value = $value[$key];
    }
    return $value;
}

function is_production(): bool
{
    return cfg('app.env') === 'production';
}

// Never show PHP internals to visitors; log them instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/helpers.php';
require __DIR__ . '/security.php';

if (PHP_SAPI !== 'cli') {
    civic_send_security_headers();
    civic_start_session();
}

require __DIR__ . '/db.php';
require __DIR__ . '/rate_limit.php';
require __DIR__ . '/layout.php';

set_exception_handler(function (Throwable $e): void {
    civic_log_error('Unhandled exception', $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    render_error_page(500, 'Something went wrong', 'The service could not complete your request. Please try again in a few minutes.');
});
