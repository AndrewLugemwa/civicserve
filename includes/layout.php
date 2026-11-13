<?php
/** Shared page chrome for the resident portal and the staff workspace. */

function render_flashes(): string
{
    $html = '';
    foreach (take_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'error', 'info', 'warning'], true) ? $flash['type'] : 'info';
        $role = $type === 'error' ? 'alert' : 'status';
        $html .= '<div class="alert alert-' . $type . '" role="' . $role . '">' . e($flash['message']) . '</div>';
    }
    return $html;
}

function brand_block(string $href = 'index.php', bool $light = false): string
{
    return '<a class="brand' . ($light ? ' brand-light' : '') . '" href="' . e($href) . '">'
        . '<img class="brand-mark" src="' . asset((string)cfg('council.logo')) . '" alt="" width="44" height="44">'
        . '<span class="brand-text"><span class="brand-council">' . e(cfg('app.product')) . '</span>'
        . '<span class="brand-product">' . e(cfg('app.case_study') ? 'Case study: ' . cfg('app.case_study') : cfg('app.tagline')) . '</span></span></a>';
}

function pilot_banner(): string
{
    if (is_production()) {
        return '';
    }
    $text = cfg('app.case_study')
        ? 'Based on a field study in ' . cfg('app.case_study') . '. Not an official Council service; cases shown are sample data.'
        : 'Cases shown are sample data for demonstration.';
    return '<div class="pilot-banner" role="note"><div class="container"><strong>' . e(cfg('app.pilot_label')) . '.</strong> '
        . e($text) . '</div></div>';
}

function page_head(string $title, string $bodyClass = ''): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d2b45">
    <meta name="robots" content="<?= str_contains($bodyClass, 'staff') ? 'noindex, nofollow' : 'index, follow' ?>">
    <title><?= e($title) ?> · <?= e(cfg('app.product')) ?></title>
    <link rel="icon" href="<?= asset((string)cfg('council.logo')) ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to main content</a>
<?php
}

function public_header(string $title, string $active = ''): void
{
    page_head($title, 'public');
    $nav = ['report' => ['index.php#report', 'Report an issue'], 'track' => ['track.php', 'Track a report'], 'about' => ['about.php', 'About']];
    echo pilot_banner();
    ?>
<header class="site-header">
    <div class="container header-inner">
        <?= brand_block() ?>
        <nav class="site-nav" aria-label="Main">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="nav-staff" href="<?= current_user() ? 'admin.php' : 'login.php' ?>"><?= current_user() ? 'Staff workspace' : 'Staff sign in' ?></a>
        </nav>
    </div>
</header>
<main id="main" tabindex="-1">
<?php
}

function public_footer(): void
{
    ?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <p class="footer-council"><?= e(cfg('app.product')) ?></p>
            <p><?= e(cfg('app.tagline')) ?></p>
            <?php if (cfg('app.case_study')): ?><p>Academic case study: <?= e(cfg('app.case_study')) ?><?= cfg('council.district') ? ', ' . e(cfg('council.district')) : '' ?></p><?php endif; ?>
        </div>
        <div>
            <p class="footer-heading">Contact</p>
            <?php if (cfg('council.contact_phone')): ?><p><?= e(cfg('council.contact_phone')) ?></p><?php endif; ?>
            <?php if (cfg('council.contact_email')): ?><p><?= e(cfg('council.contact_email')) ?></p><?php endif; ?>
            <p><?= e(cfg('council.office_hours')) ?></p>
        </div>
        <div>
            <p class="footer-heading">Emergencies</p>
            <p>CivicServe is not an emergency service. For fire, crime or medical emergencies call <strong>999</strong> or <strong>112</strong>.</p>
        </div>
    </div>
    <div class="container footer-legal">
        <span>Your personal details are used only to handle your report and are visible only to authorised Council staff.</span>
        <span><?= e(cfg('app.product')) ?> · Group work by <a href="<?= e(cfg('app.vendor_url')) ?>"><?= e(cfg('app.vendor_name')) ?></a></span>
    </div>
</footer>
</body>
</html>
<?php
}

function staff_header(string $title, string $active = ''): array
{
    $user = require_login();
    page_head($title, 'staff');
    $links = [
        'overview' => ['admin.php', 'Overview', 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z'],
        'cases'    => ['cases.php', 'Case queue', 'M4 6h16M4 12h16M4 18h10'],
        'intake'   => ['intake.php', 'Log a complaint', 'M12 5v14M5 12h14'],
    ];
    if (user_can('manage_staff', $user)) {
        $links['staff'] = ['staff.php', 'Staff accounts', 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21a8 8 0 0 1 16 0'];
    }
    $links['account'] = ['account.php', 'My account', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 9a7 7 0 0 1 14 0'];
    echo pilot_banner();
    ?>
<div class="staff-shell">
    <aside class="staff-sidebar" aria-label="Staff navigation">
        <?= brand_block('admin.php', true) ?>
        <nav class="staff-nav">
            <?php foreach ($links as $key => [$href, $label, $icon]): ?>
                <a href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= e($icon) ?>"/></svg><?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="staff-user">
            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            <span class="staff-user-text"><strong><?= e($user['name']) ?></strong><small><?= e(cfg('roles.' . $user['role'], $user['role'])) ?><?= $user['department'] ? ' · ' . e(department_label($user['department'])) : '' ?></small></span>
            <form method="post" action="logout.php"><?= csrf_field() ?><button class="link-button" type="submit">Sign out</button></form>
        </div>
    </aside>
    <div class="staff-main">
        <main id="main" tabindex="-1" class="staff-content">
            <?= render_flashes() ?>
<?php
    return $user;
}

function staff_footer(array $scripts = []): void
{
    $scripts = array_values(array_unique(array_merge($scripts, ['assets/js/staff.js'])));
    ?>
        </main>
        <footer class="staff-footer"><?= cfg('app.case_study') ? 'Case study: ' . e(cfg('app.case_study')) . ' · ' : '' ?><?= e(cfg('app.product')) ?> <?= e(cfg('app.version')) ?> · Group work by <a href="<?= e(cfg('app.vendor_url')) ?>"><?= e(cfg('app.vendor_name')) ?></a></footer>
    </div>
</div>
<?php foreach ($scripts as $src): ?><script src="<?= asset($src) ?>" defer></script><?php endforeach; ?>
</body>
</html>
<?php
}

function render_error_page(int $code, string $title, string $message): void
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    page_head($title, 'public');
    ?>
<main id="main" class="error-page">
    <div class="card error-card">
        <img src="<?= asset((string)cfg('council.logo')) ?>" alt="" width="56" height="56">
        <p class="eyebrow">Error <?= (int)$code ?></p>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <div class="button-row"><a class="button button-primary" href="index.php">Go to the portal</a><a class="button button-secondary" href="track.php">Track a report</a></div>
    </div>
</main>
</body>
</html>
<?php
    exit;
}
