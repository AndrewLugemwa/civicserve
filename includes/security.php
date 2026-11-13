<?php
/**
 * Security primitives: headers, sessions, CSRF, authentication, roles, one-time form tokens.
 */

function civic_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function civic_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    // All scripts are first-party files (no inline JS). Leaflet sets inline styles, hence style 'unsafe-inline'.
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://tile.openstreetmap.fr https://*.basemaps.cartocdn.com",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]);
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (civic_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');
}

function civic_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name((string)cfg('security.session_name', 'CIVICSERVE'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => civic_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Expire idle or very long staff sessions.
    if (!empty($_SESSION['user_id'])) {
        $now = time();
        $idle = (int)cfg('security.session_idle_minutes', 30) * 60;
        $max  = (int)cfg('security.session_max_hours', 10) * 3600;
        $last = (int)($_SESSION['last_activity'] ?? $now);
        $start = (int)($_SESSION['login_at'] ?? $now);
        if (($now - $last) > $idle || ($now - $start) > $max) {
            civic_end_session();
            session_start();
            flash('info', 'You were signed out after a period of inactivity. Please sign in again.');
        } else {
            $_SESSION['last_activity'] = $now;
        }
    }
}

function civic_end_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return is_string($submitted) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $submitted);
}

/** Use at the top of every POST handler. */
function require_post_with_csrf(string $redirectOnFailure): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        redirect($redirectOnFailure);
    }
    if (!csrf_valid()) {
        flash('error', 'Your session expired or the form was opened in another window. Please try again.');
        redirect($redirectOnFailure);
    }
}

/* ---------- One-time form tokens (prevent duplicate submissions on double-click/refresh) ---------- */

function form_once_token(string $form): string
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['form_once'][$form][$token] = time();
    // Keep only the 10 most recent tokens per form.
    $_SESSION['form_once'][$form] = array_slice($_SESSION['form_once'][$form], -10, null, true);
    return $token;
}

function form_once_consume(string $form): bool
{
    $token = $_POST['form_once'] ?? '';
    if (!is_string($token) || !isset($_SESSION['form_once'][$form][$token])) {
        return false;
    }
    unset($_SESSION['form_once'][$form][$token]);
    return true;
}

/* ---------- Authentication and roles ---------- */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'         => (int)$_SESSION['user_id'],
        'name'       => (string)($_SESSION['user_name'] ?? ''),
        'role'       => (string)($_SESSION['user_role'] ?? 'officer'),
        'department' => $_SESSION['user_department'] ?? null,
    ];
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? 'admin.php';
        redirect('login.php');
    }
    return $user;
}

function user_can(string $ability, ?array $user = null, ?array $case = null): bool
{
    $user = $user ?? current_user();
    if ($user === null) {
        return false;
    }
    $role = $user['role'];
    switch ($ability) {
        case 'manage_staff':
            return $role === 'admin';
        case 'assign':          // change department, priority, target date
        case 'export':
            return in_array($role, ['admin', 'supervisor'], true);
        case 'update_case':     // change status and add notes
            if (in_array($role, ['admin', 'supervisor'], true)) {
                return true;
            }
            // Officers work on cases in their own department (or any case if they have no department).
            return empty($user['department']) || ($case !== null && ($case['assigned_department'] ?? null) === $user['department']);
        case 'log_intake':      // record walk-in / phone complaints
            return true;
    }
    return false;
}

function require_ability(string $ability, ?array $case = null): array
{
    $user = require_login();
    if (!user_can($ability, $user, $case)) {
        http_response_code(403);
        render_error_page(403, 'Not permitted', 'Your account does not have permission for this action. Contact a CivicServe administrator if you need access.');
    }
    return $user;
}

/* ---------- Misc ---------- */

function client_ip(): string
{
    // REMOTE_ADDR only: forwarded headers are client-controlled unless a trusted proxy is configured.
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function civic_log_error(string $context, Throwable $error): void
{
    error_log('[CivicServe] ' . $context . ': ' . get_class($error) . ': ' . $error->getMessage() . ' @ ' . $error->getFile() . ':' . $error->getLine());
}
