<?php
/**
 * Create a staff account from the terminal (use this for the first administrator).
 *
 *   php tools/create_staff.php --name="Jane Nakato" --email=jane@example.org --role=admin [--department=works]
 *
 * You will be asked for the password; it is never written to the screen or shell history.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$opts = getopt('', ['name:', 'email:', 'role:', 'department::', 'password-stdin']);
$name = trim((string)($opts['name'] ?? ''));
$email = strtolower(trim((string)($opts['email'] ?? '')));
$role = (string)($opts['role'] ?? 'officer');
$dept = isset($opts['department']) && $opts['department'] !== '' ? (string)$opts['department'] : null;

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !array_key_exists($role, cfg('roles'))
    || ($dept !== null && !array_key_exists($dept, cfg('departments')))) {
    fwrite(STDERR, "Usage: php tools/create_staff.php --name=\"Full Name\" --email=name@example.org --role=admin|supervisor|officer [--department=" . implode('|', array_keys(cfg('departments'))) . "]\n");
    exit(1);
}

function read_secret(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $tty = stream_isatty(STDIN);
    if ($tty) shell_exec('stty -echo');
    $value = rtrim((string)fgets(STDIN), "\r\n");
    if ($tty) { shell_exec('stty echo'); fwrite(STDOUT, PHP_EOL); }
    return $value;
}

$min = (int)cfg('security.min_password_length', 12);
$password = read_secret("Password for $email (min $min characters): ");
if (isset($opts['password-stdin'])) {
    $confirm = $password;
} else {
    $confirm = read_secret('Confirm password: ');
}
if (mb_strlen($password) < $min || $password !== $confirm) {
    fwrite(STDERR, "Passwords must match and be at least $min characters.\n");
    exit(1);
}

$exists = db()->prepare('SELECT id FROM users WHERE email = :e');
$exists->execute([':e' => $email]);
if ($id = $exists->fetchColumn()) {
    db()->prepare('UPDATE users SET name = :n, password = :p, role = :r, department = :d, is_active = 1 WHERE id = :id')
        ->execute([':n' => $name, ':p' => password_hash($password, PASSWORD_DEFAULT), ':r' => $role, ':d' => $dept, ':id' => $id]);
    fwrite(STDOUT, "Updated existing account $email ($role).\n");
} else {
    db()->prepare('INSERT INTO users (name, email, password, role, department, is_active, created_at) VALUES (:n, :e, :p, :r, :d, 1, :at)')
        ->execute([':n' => $name, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT), ':r' => $role, ':d' => $dept, ':at' => date('Y-m-d H:i:s')]);
    fwrite(STDOUT, "Created $email ($role).\n");
}
