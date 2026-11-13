<?php
/** Supervisors and administrators: export the filtered case queue as CSV (opens in Excel). */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/case_query.php';
require_ability('export');

$f = case_filters_from_request($_GET);
[$where, $params] = case_where($f);
$stmt = db()->prepare("SELECT reference_number, created_at, channel, category, division, location, latitude, longitude, priority, status,
                              assigned_department, due_date, resolved_at, citizen_name, phone, citizen_email
                       FROM reports WHERE $where ORDER BY " . case_order_by($f['sort']) . ' LIMIT 20000');
$stmt->execute($params);

/** Neutralise spreadsheet formula injection (cells starting with = + - @). */
$cell = function ($v): string {
    $v = (string)($v ?? '');
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
};

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="civicserve-cases-' . date('Y-m-d-His') . '.csv"');
header('Cache-Control: private, no-store');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly
fputcsv($out, ['Reference', 'Received', 'Channel', 'Issue', area_label(), 'Location', 'Latitude', 'Longitude', 'Priority', 'Status',
               'Department', 'Target date', 'Closed', 'Overdue', 'Complainant', 'Phone', 'Email']);
while ($r = $stmt->fetch()) {
    $row = array_map($cell, [
        $r['reference_number'], $r['created_at'], cfg('channels.' . $r['channel'], $r['channel']), category_label($r['category']),
        division_label($r['division']), $r['location'], $r['latitude'], $r['longitude'], $r['priority'], $r['status'],
        department_label($r['assigned_department']), $r['due_date'], $r['resolved_at'], is_overdue($r) ? 'Yes' : 'No',
        $r['citizen_name'], '', $r['citizen_email'],
    ]);
    // Phone numbers are stored in a validated +256XXXXXXXXX format, so they are safe to write as-is.
    $row[15] = preg_match('/^\+256\d{9}$/', (string)$r['phone']) ? $r['phone'] : $cell($r['phone']);
    fputcsv($out, $row);
}
fclose($out);
