<?php
/**
 * CivicServe 2.x → 3.0 database upgrade. Safe to re-run: every step checks first.
 *
 *   php tools/migrate.php            # show what would change (no changes made)
 *   php tools/migrate.php --apply    # apply the changes
 *
 * BACK UP THE DATABASE FIRST (phpMyAdmin → Export).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$pdo = db();
$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$steps = 0;

function say(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
function table_exists(PDO $pdo, string $db, string $t): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :d AND TABLE_NAME = :t');
    $s->execute([':d' => $db, ':t' => $t]);
    return (int)$s->fetchColumn() > 0;
}
function column_exists(PDO $pdo, string $db, string $t, string $c): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :d AND TABLE_NAME = :t AND COLUMN_NAME = :c');
    $s->execute([':d' => $db, ':t' => $t, ':c' => $c]);
    return (int)$s->fetchColumn() > 0;
}
function index_exists(PDO $pdo, string $db, string $t, string $i): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :d AND TABLE_NAME = :t AND INDEX_NAME = :i');
    $s->execute([':d' => $db, ':t' => $t, ':i' => $i]);
    return (int)$s->fetchColumn() > 0;
}
function step(PDO $pdo, bool $apply, string $label, string $sql, array $params = [], bool $tolerant = false): void {
    global $steps;
    $steps++;
    say(($apply ? '  → ' : '  • ') . $label);
    if (!$apply) return;
    try {
        $s = $pdo->prepare($sql);
        $s->execute($params);
    } catch (PDOException $e) {
        if (!$tolerant) throw $e;
        say('    ! Skipped: ' . $e->getMessage());
    }
}

say("CivicServe 3.0 database upgrade — database '$dbName'" . ($apply ? '' : ' (preview only; add --apply to make changes)'));
foreach (['reports', 'users', 'status_logs'] as $t) {
    if (!table_exists($pdo, $dbName, $t)) {
        say("Table '$t' is missing. For a new installation, import database/schema.sql instead.");
        exit(1);
    }
}

/* ---------- 1. reports: columns ---------- */
say("\n1. Report table columns");
$add = [
    'latitude'            => 'DECIMAL(10,7) NULL',
    'longitude'           => 'DECIMAL(10,7) NULL',
    'priority'            => "VARCHAR(10) NOT NULL DEFAULT 'Normal'",
    'photo_path'          => 'VARCHAR(255) NULL',
    'assigned_department' => 'VARCHAR(40) NULL',
    'due_date'            => 'DATE NULL',
    'division'            => 'VARCHAR(20) NULL',
    'channel'             => "VARCHAR(10) NOT NULL DEFAULT 'online'",
    'created_by'          => 'INT UNSIGNED NULL',
    'updated_at'          => 'DATETIME NULL',
    'resolved_at'         => 'DATETIME NULL',
];
foreach ($add as $col => $def) {
    if (!column_exists($pdo, $dbName, 'reports', $col)) {
        step($pdo, $apply, "Add reports.$col", "ALTER TABLE reports ADD COLUMN $col $def");
    }
}
step($pdo, $apply, 'Widen reference, category, status and contact columns; make email optional',
    "ALTER TABLE reports
        MODIFY reference_number VARCHAR(24) NOT NULL,
        MODIFY category VARCHAR(40) NOT NULL,
        MODIFY status VARCHAR(20) NOT NULL DEFAULT 'Submitted',
        MODIFY citizen_email VARCHAR(190) NULL,
        MODIFY phone VARCHAR(20) NULL,
        MODIFY priority VARCHAR(10) NOT NULL DEFAULT 'Normal',
        MODIFY assigned_department VARCHAR(40) NULL");

/* ---------- 2. reports: data ---------- */
say("\n2. Convert existing report data to the CivicServe 3.0 categories and departments");
$categoryMap = [
    'Road/Pothole' => 'roads', 'Streetlight' => 'streetlights', 'Drainage' => 'drainage', 'Waste' => 'garbage',
    'Public Facility' => 'facilities', 'Community Noise & Nuisance' => 'noise', 'Other' => 'other',
];
foreach ($categoryMap as $old => $new) {
    step($pdo, $apply, "Category '$old' → '$new'", 'UPDATE reports SET category = :new WHERE category = :old', [':new' => $new, ':old' => $old]);
}
$known = array_keys(cfg('categories'));
step($pdo, $apply, 'Any other unknown category → other',
    'UPDATE reports SET category = \'other\' WHERE category NOT IN (' . implode(',', array_map(fn($k) => $pdo->quote($k), $known)) . ')');

$deptMap = [
    'Roads & Infrastructure' => 'works', 'Drainage & Water' => 'works', 'Public Lighting' => 'works',
    'Waste Management' => 'health', 'Noise & Nuisance' => 'health',
    'Community Safety' => 'admin', 'Public Facilities' => 'admin', 'General Services' => 'admin',
];
foreach ($deptMap as $old => $new) {
    step($pdo, $apply, "Department '$old' → '$new'", 'UPDATE reports SET assigned_department = :new WHERE assigned_department = :old', [':new' => $new, ':old' => $old]);
}
foreach (cfg('categories') as $key => $cat) {
    step($pdo, $apply, "Route unassigned '$key' cases to {$cat['department']}",
        'UPDATE reports SET assigned_department = :d WHERE assigned_department IS NULL AND category = :c', [':d' => $cat['department'], ':c' => $key]);
    step($pdo, $apply, "Set missing target dates on open '$key' cases (+{$cat['sla_days']} days from receipt)",
        "UPDATE reports SET due_date = DATE_ADD(DATE(created_at), INTERVAL {$cat['sla_days']} DAY)
         WHERE due_date IS NULL AND category = :c AND status NOT IN ('Resolved', 'Rejected')", [':c' => $key]);
}
step($pdo, $apply, 'Unknown division on older reports → Not sure', "UPDATE reports SET division = 'unsure' WHERE division IS NULL OR division = ''");
step($pdo, $apply, 'Fill updated_at', 'UPDATE reports SET updated_at = created_at WHERE updated_at IS NULL');
step($pdo, $apply, 'Make updated_at required', 'ALTER TABLE reports MODIFY updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
step($pdo, $apply, 'Fill resolved_at for closed cases from their history',
    "UPDATE reports r SET resolved_at = COALESCE(
        (SELECT MAX(l.created_at) FROM status_logs l WHERE l.report_id = r.id AND l.status = r.status), r.updated_at)
     WHERE r.status IN ('Resolved', 'Rejected') AND r.resolved_at IS NULL");
step($pdo, $apply, 'Store photo paths as file names only', "UPDATE reports SET photo_path = SUBSTRING(photo_path, 9) WHERE photo_path LIKE 'uploads/%'");

// Normalise phone numbers in PHP (same rule the form uses).
$rows = $pdo->query("SELECT id, phone FROM reports WHERE phone IS NOT NULL AND phone <> '' AND phone NOT LIKE '+256%'")->fetchAll();
$fixable = 0;
foreach ($rows as $r) {
    if (normalise_ug_phone((string)$r['phone']) !== null) $fixable++;
}
if ($fixable) {
    $steps++;
    say(($apply ? '  → ' : '  • ') . "Normalise $fixable phone number(s) to +256 format");
    if ($apply) {
        $u = $pdo->prepare('UPDATE reports SET phone = :p WHERE id = :id');
        foreach ($rows as $r) {
            $p = normalise_ug_phone((string)$r['phone']);
            if ($p !== null) $u->execute([':p' => $p, ':id' => $r['id']]);
        }
    }
}

/* ---------- 3. Indexes ---------- */
say("\n3. Indexes");
$indexes = [
    'uq_reports_reference'        => 'CREATE UNIQUE INDEX uq_reports_reference ON reports (reference_number)',
    'idx_reports_status_due'      => 'CREATE INDEX idx_reports_status_due ON reports (status, due_date)',
    'idx_reports_dept_status'     => 'CREATE INDEX idx_reports_dept_status ON reports (assigned_department, status)',
    'idx_reports_category_status' => 'CREATE INDEX idx_reports_category_status ON reports (category, status)',
    'idx_reports_division_status' => 'CREATE INDEX idx_reports_division_status ON reports (division, status)',
    'idx_reports_created'         => 'CREATE INDEX idx_reports_created ON reports (created_at)',
    'idx_reports_resolved'        => 'CREATE INDEX idx_reports_resolved ON reports (resolved_at)',
];
foreach ($indexes as $name => $sql) {
    if (!index_exists($pdo, $dbName, 'reports', $name)) {
        step($pdo, $apply, "Add index $name", $sql, [], true);
    }
}
// Remove indexes that only served the retired Noise & Nuisance Mapper / older filters.
foreach (['idx_reports_category_coordinates', 'idx_reports_priority_status', 'idx_reports_department', 'idx_reports_due_date'] as $old) {
    if (index_exists($pdo, $dbName, 'reports', $old)) {
        step($pdo, $apply, "Drop superseded index $old", "DROP INDEX $old ON reports", [], true);
    }
}

/* ---------- 4. status_logs ---------- */
say("\n4. Case history");
if (!column_exists($pdo, $dbName, 'status_logs', 'is_public')) {
    step($pdo, $apply, 'Add status_logs.is_public (existing staff notes stay internal)', 'ALTER TABLE status_logs ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0');
}
step($pdo, $apply, 'Mark original submission entries as public', "UPDATE status_logs SET is_public = 1 WHERE comment = 'Report submitted by citizen.'");
if (!index_exists($pdo, $dbName, 'status_logs', 'idx_logs_report')) {
    step($pdo, $apply, 'Add index idx_logs_report', 'CREATE INDEX idx_logs_report ON status_logs (report_id, created_at)', [], true);
}

/* ---------- 5. users ---------- */
say("\n5. Staff accounts");
$userCols = [
    'department'    => 'VARCHAR(40) NULL',
    'is_active'     => 'TINYINT(1) NOT NULL DEFAULT 1',
    'last_login_at' => 'DATETIME NULL',
    'created_at'    => 'DATETIME NULL',
];
foreach ($userCols as $col => $def) {
    if (!column_exists($pdo, $dbName, 'users', $col)) {
        step($pdo, $apply, "Add users.$col", "ALTER TABLE users ADD COLUMN $col $def");
    }
}
step($pdo, $apply, 'Widen users.role', "ALTER TABLE users MODIFY role VARCHAR(20) NOT NULL DEFAULT 'officer'");
step($pdo, $apply, 'Existing accounts with an unrecognised role become administrators (review them afterwards)',
    "UPDATE users SET role = 'admin' WHERE role NOT IN ('admin', 'supervisor', 'officer') OR role IS NULL");
step($pdo, $apply, 'Lower-case staff emails', 'UPDATE users SET email = LOWER(TRIM(email))');
if (!index_exists($pdo, $dbName, 'users', 'uq_users_email')) {
    step($pdo, $apply, 'Add unique index on users.email', 'CREATE UNIQUE INDEX uq_users_email ON users (email)', [], true);
}

/* ---------- 6. New tables ---------- */
say("\n6. New tables");
if (!table_exists($pdo, $dbName, 'rate_limits')) {
    step($pdo, $apply, 'Create rate_limits', "CREATE TABLE rate_limits (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, action VARCHAR(20) NOT NULL, key_hash CHAR(64) NOT NULL, attempted_at DATETIME NOT NULL,
        PRIMARY KEY (id), KEY idx_rate (action, key_hash, attempted_at), KEY idx_rate_time (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!table_exists($pdo, $dbName, 'schema_migrations')) {
    step($pdo, $apply, 'Create schema_migrations', "CREATE TABLE schema_migrations (
        version VARCHAR(40) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if ($apply) {
    $pdo->prepare('INSERT IGNORE INTO schema_migrations (version, applied_at) VALUES (:v, :at)')->execute([':v' => '3.0.0', ':at' => date('Y-m-d H:i:s')]);
}

say("\n" . ($apply ? "Done. $steps step(s) applied or verified." : "$steps step(s) planned. Back up the database, then run again with --apply."));
