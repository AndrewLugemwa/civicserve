<?php
/** Small, dependency-free helpers shared by all pages. */

/** Escape for HTML text and attribute contexts. Use on EVERY dynamic value printed into HTML. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect to a same-site relative path only (prevents open redirects). */
function redirect(string $path): void
{
    if (!preg_match('#^[a-zA-Z0-9_\-./?=&%\#]+$#', $path) || str_starts_with($path, '//') || preg_match('#^[a-z]+:#i', $path)) {
        $path = 'index.php';
    }
    header('Location: ' . $path, true, 303);
    exit;
}

function safe_return_path(?string $path, string $fallback): string
{
    if (!is_string($path) || $path === '') {
        return $fallback;
    }
    $path = ltrim(basename(parse_url($path, PHP_URL_PATH) ?: ''), '/') . (parse_url($path, PHP_URL_QUERY) ? '?' . parse_url($path, PHP_URL_QUERY) : '');
    return preg_match('#^[a-z_]+\.php(\?[a-zA-Z0-9_\-=&%.]*)?$#', $path) ? $path : $fallback;
}

/* ---------- Flash messages and old input (Post/Redirect/Get) ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function remember_form(string $form, array $input, array $errors): void
{
    $_SESSION['form_state'][$form] = ['input' => $input, 'errors' => $errors];
}

function take_form_state(string $form): array
{
    $state = $_SESSION['form_state'][$form] ?? ['input' => [], 'errors' => []];
    unset($_SESSION['form_state'][$form]);
    return $state;
}

/* ---------- Domain labels ---------- */

function category_label(?string $key): string
{
    return cfg('categories.' . $key . '.label') ?? ($key ? ucfirst((string)$key) : '—');
}

function department_label(?string $key): string
{
    if ($key === null || $key === '') {
        return 'Unassigned';
    }
    return cfg('departments.' . $key) ?? $key;
}

function division_label(?string $key): string
{
    if ($key === null || $key === '') {
        return '—';
    }
    return cfg('divisions.' . $key) ?? $key;
}

function public_status_label(string $status): string
{
    return cfg('statuses')[$status]['public'] ?? $status;
}

function status_tone(string $status): string
{
    return cfg('statuses')[$status]['tone'] ?? 'info';
}

function status_badge(string $status, bool $public = false): string
{
    $label = $public ? public_status_label($status) : $status;
    return '<span class="badge badge-' . e(status_tone($status)) . '">' . e($label) . '</span>';
}

function priority_badge(string $priority): string
{
    $tone = ['Urgent' => 'critical', 'High' => 'warning', 'Normal' => 'neutral', 'Low' => 'muted'][$priority] ?? 'neutral';
    return '<span class="badge badge-' . $tone . '">' . e($priority) . '</span>';
}

function is_closed_status(string $status): bool
{
    return in_array($status, cfg('closed_statuses', []), true);
}

function is_overdue(array $case): bool
{
    return !empty($case['due_date']) && !is_closed_status((string)$case['status']) && $case['due_date'] < date('Y-m-d');
}

/* ---------- Formatting ---------- */

function fmt_date(?string $value, bool $withTime = false): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }
    return date($withTime ? 'j M Y, g:ia' : 'j M Y', $ts);
}

function fmt_relative_days(?string $date): string
{
    if (!$date) {
        return '';
    }
    $days = (int)floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days === 0) return 'due today';
    if ($days === 1) return 'due tomorrow';
    if ($days > 1) return 'due in ' . $days . ' days';
    if ($days === -1) return '1 day overdue';
    return abs($days) . ' days overdue';
}

/** Normalise a Ugandan phone number to +256XXXXXXXXX, or return null if invalid. */
function normalise_ug_phone(string $raw): ?string
{
    $digits = preg_replace('/[\s\-().]/', '', $raw);
    if (preg_match('/^(?:\+?256|0)([1-9]\d{8})$/', $digits, $m)) {
        return '+256' . $m[1];
    }
    return null;
}

/** Mask a phone number for lists: +256 7•• ••• 123 */
function mask_phone(?string $phone): string
{
    if (!$phone) {
        return '—';
    }
    return substr($phone, 0, 5) . '•• ••• ' . substr($phone, -3);
}

/** Case reference: CS-26-7KQ4XZ (31-character alphabet without look-alikes 0/O, 1/I/L). */
function new_case_reference(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return cfg('council.abbreviation', 'CS') . '-' . date('y') . '-' . $code;
}

function normalise_reference(string $raw): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($raw)));
}

function asset(string $path): string
{
    $file = CIVIC_ROOT . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string)filemtime($file) : cfg('app.version');
    return e($path . '?v=' . $version);
}

function str_limit(string $text, int $length): string
{
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

/** Build a query string from the current filters, overriding some keys. */
function query_with(array $base, array $overrides = []): string
{
    $q = array_filter(array_merge($base, $overrides), fn($v) => $v !== '' && $v !== null && $v !== false);
    return $q ? '?' . http_build_query($q) : '';
}
