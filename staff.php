<?php
/** Administrator-only: manage staff accounts. */
require __DIR__ . '/includes/bootstrap.php';
$admin = require_ability('manage_staff');
$pdo = db();
$minLen = (int)cfg('security.min_password_length', 12);

function active_admin_count(PDO $pdo, int $excludeId = 0): int
{
    $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> :id");
    $s->execute([':id' => $excludeId]);
    return (int)$s->fetchColumn();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_with_csrf('staff.php');
    $action = (string)($_POST['action'] ?? '');
    $str = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
    $role = $str('role');
    $dept = $str('department');
    $deptValue = ($dept !== '' && array_key_exists($dept, cfg('departments'))) ? $dept : null;

    if ($action === 'create') {
        $name = $str('name');
        $email = strtolower($str('email'));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = 'Enter the staff member\'s full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (!array_key_exists($role, cfg('roles'))) $errors[] = 'Choose a role.';
        if (mb_strlen($password) < $minLen) $errors[] = "The temporary password must be at least $minLen characters.";
        if (!$errors) {
            $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :e');
            $exists->execute([':e' => $email]);
            if ($exists->fetchColumn()) $errors[] = 'An account with that email already exists.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            $pdo->prepare('INSERT INTO users (name, email, password, role, department, is_active, created_at) VALUES (:n, :e, :p, :r, :d, 1, :at)')
                ->execute([':n' => $name, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT), ':r' => $role, ':d' => $deptValue, ':at' => date('Y-m-d H:i:s')]);
            flash('success', "Account created for $name. Share the temporary password privately and ask them to change it after signing in.");
        }
        redirect('staff.php');
    }

    $id = (int)($_POST['user_id'] ?? 0);
    $target = $pdo->prepare('SELECT id, name, role, is_active FROM users WHERE id = :id');
    $target->execute([':id' => $id]);
    $target = $target->fetch();
    if (!$target) {
        flash('error', 'That account no longer exists.');
        redirect('staff.php');
    }

    if ($action === 'update') {
        $active = $str('is_active') === '1' ? 1 : 0;
        if (!array_key_exists($role, cfg('roles'))) {
            flash('error', 'Choose a valid role.');
        } elseif (($role !== 'admin' || !$active) && $target['role'] === 'admin' && active_admin_count($pdo, $id) === 0) {
            flash('error', 'At least one active administrator must remain.');
        } else {
            $pdo->prepare('UPDATE users SET role = :r, department = :d, is_active = :a WHERE id = :id')
                ->execute([':r' => $role, ':d' => $deptValue, ':a' => $active, ':id' => $id]);
            flash('success', 'Account updated for ' . $target['name'] . '. Changes apply at their next sign-in.');
        }
    } elseif ($action === 'reset') {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (mb_strlen($password) < $minLen) {
            flash('error', "The temporary password must be at least $minLen characters.");
        } else {
            $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
            flash('success', 'Password reset for ' . $target['name'] . '. Share it privately and ask them to change it.');
        }
    }
    redirect('staff.php');
}

$users = $pdo->query('SELECT id, name, email, role, department, is_active, last_login_at FROM users ORDER BY is_active DESC, name ASC')->fetchAll();

staff_header('Staff accounts', 'staff');
?>
<div class="page-head">
    <div><h1>Staff accounts</h1><p>Administrators create accounts and set what each person can do.</p></div>
</div>

<div class="panel-grid">
    <section class="panel span-8">
        <h2>Accounts</h2>
        <p class="panel-sub"><strong>Administrator</strong>: everything, including accounts. <strong>Supervisor</strong>: all cases, assignment and export. <strong>Case officer</strong>: updates cases in their department.</p>
        <div class="table-wrap">
            <table class="data-table responsive">
                <thead><tr><th>Name</th><th>Role &amp; department</th><th>Last sign-in</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td data-label="Name"><strong><?= e($u['name']) ?></strong><span class="sub"><?= e($u['email']) ?></span><?php if (!(int)$u['is_active']): ?><span class="badge badge-muted">Inactive</span><?php endif; ?></td>
                        <td data-label="Role">
                            <form method="post" action="staff.php" class="stack" style="gap:8px">
                                <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <select name="role" aria-label="Role for <?= e($u['name']) ?>"><?php foreach (cfg('roles') as $k => $label): ?><option value="<?= e($k) ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                <select name="department" aria-label="Department for <?= e($u['name']) ?>"><option value="">All departments</option><?php foreach (cfg('departments') as $k => $label): ?><option value="<?= e($k) ?>"<?= $u['department'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                <label class="checkbox"><input type="checkbox" name="is_active" value="1"<?= (int)$u['is_active'] ? ' checked' : '' ?><?= (int)$u['id'] === $admin['id'] ? ' disabled' : '' ?>> Active</label>
                                <?php if ((int)$u['id'] === $admin['id']): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
                                <button class="button button-secondary button-small" type="submit">Save</button>
                            </form>
                        </td>
                        <td data-label="Last sign-in"><?= e(fmt_date($u['last_login_at'], true)) ?></td>
                        <td data-label="Password">
                            <form method="post" action="staff.php" class="stack" style="gap:8px" data-confirm="Reset this person's password?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="text" name="password" minlength="<?= $minLen ?>" placeholder="New temporary password" aria-label="New temporary password for <?= e($u['name']) ?>" autocomplete="new-password">
                                <button class="button button-danger button-small" type="submit">Reset password</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel span-4">
        <h2>Add a staff member</h2>
        <p class="panel-sub">They sign in with this email and the temporary password.</p>
        <form method="post" action="staff.php" class="stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="field"><label for="n-name">Full name</label><input id="n-name" name="name" type="text" required maxlength="120"></div>
            <div class="field"><label for="n-email">Council email</label><input id="n-email" name="email" type="email" required maxlength="190"></div>
            <div class="field"><label for="n-role">Role</label><select id="n-role" name="role"><?php foreach (cfg('roles') as $k => $label): ?><option value="<?= e($k) ?>"<?= $k === 'officer' ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="n-dept">Department</label><select id="n-dept" name="department"><option value="">All departments</option><?php foreach (cfg('departments') as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="n-pass">Temporary password</label><input id="n-pass" name="password" type="text" required minlength="<?= $minLen ?>" autocomplete="new-password"><p class="hint">At least <?= $minLen ?> characters.</p></div>
            <button class="button button-primary" type="submit">Create account</button>
        </form>
    </section>
</div>
<?php staff_footer(); ?>
