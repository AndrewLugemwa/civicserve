<?php
/** Any signed-in staff member: change own password. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$minLen = (int)cfg('security.min_password_length', 12);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_with_csrf('account.php');
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    $stmt = db()->prepare('SELECT password FROM users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $hash = (string)$stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        flash('error', 'Your current password is not correct.');
    } elseif (mb_strlen($new) < $minLen) {
        flash('error', "Choose a new password of at least $minLen characters.");
    } elseif ($new !== $confirm) {
        flash('error', 'The new passwords do not match.');
    } elseif (password_verify($new, $hash)) {
        flash('error', 'Choose a password different from your current one.');
    } else {
        db()->prepare('UPDATE users SET password = :p WHERE id = :id')->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['id']]);
        session_regenerate_id(true);
        flash('success', 'Your password has been changed.');
    }
    redirect('account.php');
}

staff_header('My account', 'account');
?>
<div class="page-head"><div><h1>My account</h1><p><?= e($user['name']) ?> · <?= e(cfg('roles.' . $user['role'])) ?></p></div></div>
<section class="panel" style="max-width:520px">
    <h2>Change password</h2>
    <p class="panel-sub">Use at least <?= $minLen ?> characters. A short sentence is easier to remember and harder to guess.</p>
    <form method="post" action="account.php" class="stack">
        <?= csrf_field() ?>
        <div class="field"><label for="cur">Current password</label><input id="cur" name="current_password" type="password" required autocomplete="current-password"></div>
        <div class="field"><label for="np">New password</label><div class="password-wrap"><input id="np" name="new_password" type="password" required minlength="<?= $minLen ?>" autocomplete="new-password"><button class="password-toggle" type="button" data-toggle-password="np" aria-pressed="false">Show</button></div></div>
        <div class="field"><label for="cp">Confirm new password</label><input id="cp" name="confirm_password" type="password" required minlength="<?= $minLen ?>" autocomplete="new-password"></div>
        <button class="button button-primary" type="submit">Change password</button>
    </form>
</section>
<?php staff_footer(); ?>
