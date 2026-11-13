<?php
/** Handles report submissions from residents (index.php) and staff intake (intake.php). */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/uploads.php';

$user = current_user();
$staffMode = $user !== null && ($_POST['channel'] ?? 'online') !== 'online';
$returnTo = $staffMode ? 'intake.php' : 'index.php#report';

require_post_with_csrf($returnTo);

$str = fn(string $key): string => is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';

$input = [
    'category'      => $str('category'),
    'description'   => $str('description'),
    'division'      => $str('division'),
    'location'      => $str('location'),
    'latitude'      => $str('latitude'),
    'longitude'     => $str('longitude'),
    'citizen_name'  => preg_replace('/\s+/', ' ', $str('citizen_name')),
    'phone'         => $str('phone'),
    'citizen_email' => $str('citizen_email'),
    'consent'       => $str('consent') === '1',
    'channel'       => $staffMode ? $str('channel') : 'online',
];

/* ---------- Validation ---------- */
$errors = [];
$categories = cfg('categories');

if (!isset($categories[$input['category']])) {
    $errors['category'] = 'Choose the category that best describes the issue.';
}
$descLen = mb_strlen($input['description']);
if ($descLen < 15) {
    $errors['description'] = 'Describe the problem in at least 15 characters.';
} elseif ($descLen > 2000) {
    $errors['description'] = 'Keep the description under 2,000 characters.';
}
if (!array_key_exists($input['division'], cfg('divisions'))) {
    $errors['division'] = 'Choose the division where the issue is.';
}
$locLen = mb_strlen($input['location']);
if ($locLen < 3 || $locLen > 255) {
    $errors['location'] = 'Enter a street, village or landmark so staff can find the issue.';
}

$lat = $lng = null;
if ($input['latitude'] !== '' || $input['longitude'] !== '') {
    if (!is_numeric($input['latitude']) || !is_numeric($input['longitude'])
        || abs((float)$input['latitude']) > 90 || abs((float)$input['longitude']) > 180) {
        $errors['coordinates'] = 'The map position is not valid. Place the pin again or remove it.';
    } else {
        $lat = round((float)$input['latitude'], 7);
        $lng = round((float)$input['longitude'], 7);
    }
}

$nameLen = mb_strlen($input['citizen_name']);
if ($nameLen < 2 || $nameLen > 120) {
    $errors['name'] = 'Enter the full name of the person reporting.';
}
$phone = normalise_ug_phone($input['phone']);
if ($phone === null) {
    $errors['phone'] = 'Enter a valid Ugandan phone number, e.g. 0772 123 456.';
}
if ($input['citizen_email'] !== '' && (!filter_var($input['citizen_email'], FILTER_VALIDATE_EMAIL) || mb_strlen($input['citizen_email']) > 190)) {
    $errors['email'] = 'Enter a valid email address, or leave it blank.';
}
if (!$input['consent']) {
    $errors['consent'] = 'Please confirm the details and agree to the Council using them for this report.';
}
if ($staffMode && !in_array($input['channel'], ['walk_in', 'phone'], true)) {
    $errors['channel'] = 'Choose how the complaint was received.';
}

if ($errors) {
    remember_form('report', $input, $errors);
    redirect($returnTo);
}

/* ---------- Abuse controls ---------- */
if (!$staffMode && !rate_limit_allow('submit', rate_key('submit', client_ip()), (int)cfg('security.submit_max_per_hour'), 3600)) {
    remember_form('report', $input, []);
    flash('error', 'Too many reports have been sent from this connection in the last hour. Please try again later, or visit a Council office.');
    redirect($returnTo);
}
if (!form_once_consume('report')) {
    // The same form was submitted twice (double-click or browser refresh).
    if (!empty($_SESSION['last_reference'])) {
        redirect('report_submitted.php');
    }
    flash('error', 'This form has expired. Please submit it again.');
    remember_form('report', $input, []);
    redirect($returnTo);
}

/* ---------- Photo ---------- */
$photo = store_evidence_photo($_FILES['issue_photo'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
if ($photo['error'] !== null) {
    remember_form('report', $input, ['photo' => $photo['error']]);
    redirect($returnTo);
}

/* ---------- Save ---------- */
$category = $categories[$input['category']];
$department = $category['department'];
$priority = $category['priority'];
$dueDate = date('Y-m-d', strtotime('+' . (int)$category['sla_days'] . ' days'));
$now = date('Y-m-d H:i:s');

$pdo = db();
$reference = null;
try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        'INSERT INTO reports
            (reference_number, citizen_name, citizen_email, phone, category, division, description, location,
             latitude, longitude, priority, photo_path, assigned_department, due_date, status, channel, created_by, created_at, updated_at)
         VALUES
            (:reference, :name, :email, :phone, :category, :division, :description, :location,
             :lat, :lng, :priority, :photo, :department, :due, \'Submitted\', :channel, :created_by, :created_at, :updated_at)'
    );

    // The reference column is UNIQUE; retry on the (very unlikely) collision.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $reference = new_case_reference();
        try {
            $insert->execute([
                ':reference' => $reference, ':name' => $input['citizen_name'],
                ':email' => $input['citizen_email'] !== '' ? $input['citizen_email'] : null,
                ':phone' => $phone, ':category' => $input['category'], ':division' => $input['division'],
                ':description' => $input['description'], ':location' => $input['location'],
                ':lat' => $lat, ':lng' => $lng, ':priority' => $priority,
                ':photo' => $photo['path'], ':department' => $department, ':due' => $dueDate,
                ':channel' => $input['channel'], ':created_by' => $staffMode ? $user['id'] : null,
                ':created_at' => $now, ':updated_at' => $now,
            ]);
            break;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] ?? null) {
                if ((int)$e->errorInfo[1] === 1062 && $attempt < 4) {
                    continue; // duplicate reference
                }
            }
            throw $e;
        }
    }
    $reportId = (int)$pdo->lastInsertId();

    $note = $staffMode
        ? 'Complaint logged by staff (' . cfg('channels.' . $input['channel']) . '). Routed to ' . department_label($department) . '.'
        : 'Report received online. Routed to ' . department_label($department) . '.';
    $pdo->prepare(
        'INSERT INTO status_logs (report_id, status, comment, is_public, updated_by, created_at)
         VALUES (:id, \'Submitted\', :comment, 1, :by, :at)'
    )->execute([':id' => $reportId, ':comment' => $note, ':by' => $staffMode ? $user['id'] : null, ':at' => $now]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    delete_evidence_photo($photo['path']);
    civic_log_error('Report submission failed', $e);
    remember_form('report', $input, []);
    flash('error', 'We could not save your report just now. Nothing was lost on your side; please press Submit again in a minute.');
    redirect($returnTo);
}

$_SESSION['last_reference'] = [
    'reference' => $reference,
    'category'  => $input['category'],
    'division'  => $input['division'],
    'location'  => $input['location'],
    'department'=> $department,
    'due_date'  => $dueDate,
    'staff'     => $staffMode,
    'case_id'   => $reportId,
];
redirect('report_submitted.php');
