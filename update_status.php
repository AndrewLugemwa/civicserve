<?php
/** Applies a staff update to a case and records it in the audit history. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id = $_POST['report_id'] ?? '';
$back = (is_string($id) && ctype_digit($id)) ? 'case.php?id=' . (int)$id : 'cases.php';
require_post_with_csrf($back);

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM reports WHERE id = :id LIMIT 1');
$stmt->execute([':id' => (int)$id]);
$case = $stmt->fetch();
if (!$case) {
    flash('error', 'That case no longer exists.');
    redirect('cases.php');
}
if (!user_can('update_case', $user, $case)) {
    flash('error', 'You do not have permission to update this case.');
    redirect($back);
}

$str = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
$status = $str('status');
$comment = mb_substr($str('comment'), 0, 1000);
$isPublic = $str('is_public') === '1' ? 1 : 0;

$department = $case['assigned_department'];
$priority = $case['priority'];
$dueDate = $case['due_date'];

$errors = [];
if (!array_key_exists($status, cfg('statuses'))) {
    $errors[] = 'Choose a valid status.';
}
if (user_can('assign', $user)) {
    $department = $str('assigned_department');
    if ($department !== '' && !array_key_exists($department, cfg('departments'))) {
        $errors[] = 'Choose a valid department.';
    }
    $department = $department === '' ? null : $department;

    $priority = $str('priority');
    if (!in_array($priority, cfg('priorities'), true)) {
        $errors[] = 'Choose a valid priority.';
    }
    $due = $str('due_date');
    if ($due === '') {
        $dueDate = null;
    } else {
        $parsed = DateTime::createFromFormat('!Y-m-d', $due);
        if (!$parsed || $parsed->format('Y-m-d') !== $due) {
            $errors[] = 'Enter a valid target date.';
        } else {
            $dueDate = $due;
        }
    }
}
if (is_closed_status($status) && !is_closed_status($case['status']) && $comment === '') {
    $errors[] = 'Add a note explaining the outcome before closing the case.';
}
if ($status === $case['status'] && $department === $case['assigned_department'] && $priority === $case['priority']
    && $dueDate === $case['due_date'] && $comment === '') {
    $errors[] = 'Nothing changed. Change a field or add a note.';
}
// Optimistic locking: refuse to overwrite someone else's update made after this page was opened.
if ($str('expected_updated_at') !== '' && $str('expected_updated_at') !== (string)$case['updated_at']) {
    $errors[] = 'Someone else updated this case while you had it open. Review the latest history below, then try again.';
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect($back);
}

// Describe what changed, for the audit history.
$changes = [];
if ($department !== $case['assigned_department']) $changes[] = 'Department: ' . department_label($case['assigned_department']) . ' → ' . department_label($department);
if ($priority !== $case['priority']) $changes[] = 'Priority: ' . $case['priority'] . ' → ' . $priority;
if ($dueDate !== $case['due_date']) $changes[] = 'Target date: ' . fmt_date($case['due_date']) . ' → ' . fmt_date($dueDate);

$now = date('Y-m-d H:i:s');
$resolvedAt = $case['resolved_at'];
if (is_closed_status($status) && !is_closed_status($case['status'])) {
    $resolvedAt = $now;
} elseif (!is_closed_status($status)) {
    $resolvedAt = null; // reopened
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE reports SET status = :status, assigned_department = :dept, priority = :priority, due_date = :due,
                          resolved_at = :resolved, updated_at = :now WHERE id = :id')
        ->execute([':status' => $status, ':dept' => $department, ':priority' => $priority, ':due' => $dueDate,
                   ':resolved' => $resolvedAt, ':now' => $now, ':id' => (int)$case['id']]);

    // Internal assignment details are logged separately so a public note never leaks them.
    if ($changes) {
        $pdo->prepare('INSERT INTO status_logs (report_id, status, comment, is_public, updated_by, created_at) VALUES (:id, :status, :comment, 0, :by, :at)')
            ->execute([':id' => (int)$case['id'], ':status' => $status, ':comment' => implode("\n", $changes), ':by' => $user['id'], ':at' => $now]);
    }
    if ($comment !== '' || $status !== $case['status']) {
        $pdo->prepare('INSERT INTO status_logs (report_id, status, comment, is_public, updated_by, created_at) VALUES (:id, :status, :comment, :public, :by, :at)')
            ->execute([':id' => (int)$case['id'], ':status' => $status, ':comment' => $comment, ':public' => $comment === '' ? 1 : $isPublic, ':by' => $user['id'], ':at' => $now]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    civic_log_error('Case update failed', $e);
    flash('error', 'The update could not be saved. Please try again.');
    redirect($back);
}

flash('success', 'Case ' . $case['reference_number'] . ' updated.');
redirect($back);
