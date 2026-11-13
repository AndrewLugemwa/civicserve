<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('admin.php');
}

$error = null;
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $window = (int)cfg('security.login_window_minutes', 15) * 60;
    $accountKey = rate_key('login', $email, client_ip());
    $ipKey = rate_key('login-ip', client_ip());

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try signing in again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email address and password.';
    } elseif (rate_limit_hits('login', $accountKey, $window) >= (int)cfg('security.login_max_failures', 5)
           || rate_limit_hits('login-ip', $ipKey, $window) >= (int)cfg('security.login_ip_max_failures', 25)) {
        $error = 'Too many unsuccessful attempts. For your security, please wait ' . (int)cfg('security.login_window_minutes', 15) . ' minutes before trying again.';
    } else {
        $stmt = db()->prepare('SELECT id, name, email, password, role, department, is_active FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $account = $stmt->fetch();

        // Verify against a dummy hash when the account does not exist, so timing does not reveal valid emails.
        $hash = $account['password'] ?? password_hash(random_bytes(16), PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash) && $account && (int)$account['is_active'] === 1;

        if ($valid) {
            rate_limit_clear('login', $accountKey);
            if (password_needs_rehash($account['password'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password = :p WHERE id = :id')->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $account['id']]);
            }
            db()->prepare('UPDATE users SET last_login_at = :now WHERE id = :id')->execute([':now' => date('Y-m-d H:i:s'), ':id' => $account['id']]);

            $after = safe_return_path($_SESSION['after_login'] ?? null, 'admin.php');
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['user_id'] = (int)$account['id'];
            $_SESSION['user_name'] = $account['name'];
            $_SESSION['user_role'] = array_key_exists($account['role'], cfg('roles')) ? $account['role'] : 'officer';
            $_SESSION['user_department'] = $account['department'] ?: null;
            $_SESSION['login_at'] = time();
            $_SESSION['last_activity'] = time();
            redirect($after);
        }

        rate_limit_record('login', $accountKey);
        rate_limit_record('login-ip', $ipKey);
        $error = 'The email or password is not correct, or the account is not active.';
    }
}

page_head('Staff sign in', 'auth staff');
echo pilot_banner();
?>
<div class="auth-page">
    <section class="auth-aside">
        <?= brand_block('index.php', true) ?>
        <div>
            <p class="eyebrow" style="color:#8fe0bd">Staff workspace</p>
            <h1>Every community report, owned and on time.</h1>
            <ul class="auth-points">
                <li>One queue for every <?= e(strtolower(area_label())) ?> and department.</li>
                <li>Target dates and overdue cases in plain view.</li>
                <li>A complete, timed history of every action.</li>
            </ul>
        </div>
        <p style="font-size:.85rem;margin:0">For authorised council staff only. Activity is logged.<br><?= e(cfg('app.product')) ?> · Group work by <?= e(cfg('app.vendor_name')) ?></p>
    </section>

    <main class="auth-main" id="main" tabindex="-1">
        <div class="auth-card">
            <p class="eyebrow">Sign in</p>
            <h2>Welcome back</h2>
            <p class="muted">Use your Council email address and CivicServe password.</p>

            <?= render_flashes() ?>
            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="login.php" class="stack" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" required autocomplete="username" maxlength="190" value="<?= e($email) ?>" autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="200">
                        <button class="password-toggle" type="button" data-toggle-password="password" aria-pressed="false">Show</button>
                    </div>
                </div>
                <button type="submit" class="button button-dark" style="width:100%">Sign in</button>
            </form>
            <p class="muted" style="font-size:.85rem;margin-top:20px">Forgotten your password? Ask a CivicServe administrator to reset it. <a href="index.php">Back to the resident portal</a></p>
        </div>
    </main>
</div>
<script src="<?= asset('assets/js/staff.js') ?>" defer></script>
</body>
</html>
