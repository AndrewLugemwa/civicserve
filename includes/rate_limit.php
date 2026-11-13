<?php
/**
 * Database-backed rate limiting. Keys are hashed so raw IPs and emails are not stored.
 * Table: rate_limits (see database/schema.sql).
 */

function rate_key(string ...$parts): string
{
    return hash('sha256', implode('|', $parts) . '|' . cfg('db.name'));
}

function rate_limit_hits(string $action, string $key, int $windowSeconds): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM rate_limits WHERE action = :action AND key_hash = :key AND attempted_at > :since');
    $stmt->execute([':action' => $action, ':key' => $key, ':since' => date('Y-m-d H:i:s', time() - $windowSeconds)]);
    return (int)$stmt->fetchColumn();
}

function rate_limit_record(string $action, string $key): void
{
    $stmt = db()->prepare('INSERT INTO rate_limits (action, key_hash, attempted_at) VALUES (:action, :key, :at)');
    $stmt->execute([':action' => $action, ':key' => $key, ':at' => date('Y-m-d H:i:s')]);

    // Opportunistic clean-up (about 1 in 50 requests) keeps the table small.
    if (random_int(1, 50) === 1) {
        db()->prepare('DELETE FROM rate_limits WHERE attempted_at < :cutoff')
            ->execute([':cutoff' => date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function rate_limit_clear(string $action, string $key): void
{
    db()->prepare('DELETE FROM rate_limits WHERE action = :action AND key_hash = :key')
        ->execute([':action' => $action, ':key' => $key]);
}

/** Returns true if the action is allowed, and records the attempt. */
function rate_limit_allow(string $action, string $key, int $max, int $windowSeconds): bool
{
    if (rate_limit_hits($action, $key, $windowSeconds) >= $max) {
        return false;
    }
    rate_limit_record($action, $key);
    return true;
}
