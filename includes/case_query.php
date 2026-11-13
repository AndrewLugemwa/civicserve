<?php
/**
 * Case queue filtering shared by cases.php and export.php.
 * Every filter value is whitelisted or bound as a parameter. Each named marker is
 * used exactly once, which native prepared statements require (the cause of the
 * old dashboard search crash).
 */

function case_filters_from_request(array $get): array
{
    $pick = fn(string $k) => is_string($get[$k] ?? null) ? trim($get[$k]) : '';
    $f = [
        'q'          => mb_substr($pick('q'), 0, 80),
        'status'     => $pick('status'),
        'category'   => $pick('category'),
        'division'   => $pick('division'),
        'department' => $pick('department'),
        'priority'   => $pick('priority'),
        'period'     => $pick('period'),
        'overdue'    => $pick('overdue') === '1' ? '1' : '',
        'sort'       => $pick('sort'),
    ];
    if ($f['status'] !== 'open' && !array_key_exists($f['status'], cfg('statuses'))) $f['status'] = '';
    if (!array_key_exists($f['category'], cfg('categories'))) $f['category'] = '';
    if (!array_key_exists($f['division'], cfg('divisions'))) $f['division'] = '';
    if ($f['department'] !== 'none' && !array_key_exists($f['department'], cfg('departments'))) $f['department'] = '';
    if (!in_array($f['priority'], cfg('priorities'), true)) $f['priority'] = '';
    if (!in_array($f['period'], ['today', '7', '30', '90', 'year'], true)) $f['period'] = '';
    if (!in_array($f['sort'], ['newest', 'oldest', 'due', 'priority'], true)) $f['sort'] = 'newest';
    return $f;
}

/** @return array{0:string,1:array} WHERE clause (without the keyword) and its parameters */
function case_where(array $f): array
{
    $where = ['1=1'];
    $params = [];

    if ($f['q'] !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
        $where[] = '(reference_number LIKE :q_ref OR citizen_name LIKE :q_name OR location LIKE :q_loc OR phone LIKE :q_phone)';
        $params[':q_ref'] = $like;
        $params[':q_name'] = $like;
        $params[':q_loc'] = $like;
        $params[':q_phone'] = '%' . preg_replace('/\D/', '', $f['q']) . '%';
        if (preg_replace('/\D/', '', $f['q']) === '') {
            $params[':q_phone'] = '__no_phone_match__';
        }
    }
    if ($f['status'] === 'open') {
        $where[] = "status NOT IN ('Resolved', 'Rejected')";
    } elseif ($f['status'] !== '') {
        $where[] = 'status = :status';
        $params[':status'] = $f['status'];
    }
    foreach (['category', 'division', 'priority'] as $col) {
        if ($f[$col] !== '') {
            $where[] = "$col = :$col";
            $params[":$col"] = $f[$col];
        }
    }
    if ($f['department'] === 'none') {
        $where[] = 'assigned_department IS NULL';
    } elseif ($f['department'] !== '') {
        $where[] = 'assigned_department = :department';
        $params[':department'] = $f['department'];
    }
    $periodStart = match ($f['period']) {
        'today' => date('Y-m-d 00:00:00'),
        '7'     => date('Y-m-d H:i:s', strtotime('-7 days')),
        '30'    => date('Y-m-d H:i:s', strtotime('-30 days')),
        '90'    => date('Y-m-d H:i:s', strtotime('-90 days')),
        'year'  => date('Y-01-01 00:00:00'),
        default => null,
    };
    if ($periodStart !== null) {
        $where[] = 'created_at >= :period_start';
        $params[':period_start'] = $periodStart;
    }
    if ($f['overdue'] === '1') {
        $where[] = "due_date IS NOT NULL AND due_date < :today AND status NOT IN ('Resolved', 'Rejected')";
        $params[':today'] = date('Y-m-d');
    }
    return [implode(' AND ', $where), $params];
}

function case_order_by(string $sort): string
{
    return match ($sort) {
        'oldest'   => 'created_at ASC, id ASC',
        'due'      => "CASE WHEN status IN ('Resolved','Rejected') THEN 1 ELSE 0 END, due_date IS NULL, due_date ASC, id ASC",
        'priority' => "FIELD(priority, 'Urgent', 'High', 'Normal', 'Low'), due_date IS NULL, due_date ASC",
        default    => 'created_at DESC, id DESC',
    };
}
