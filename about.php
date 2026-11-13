<?php
/** About this project: the academic case study and the Group 5 team. */
require __DIR__ . '/includes/bootstrap.php';

$team = cfg('team', []);
$initials = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $letters .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $letters;
};

public_header('About the project', 'about');
?>
<section class="hero hero-compact">
    <div class="container">
        <p class="eyebrow">About the project</p>
        <h1>CivicServe is group work by <?= e(cfg('app.vendor_name')) ?>.</h1>
        <p class="hero-lead">A civic issue reporting and tracking system designed from a field study<?= cfg('app.case_study') ? ' in ' . e(cfg('app.case_study')) : '' ?>.
            Residents report community problems and follow their progress; council staff assign, manage and resolve them on time.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <p class="eyebrow">The team</p>
            <h2><?= e(cfg('app.vendor_name')) ?></h2>
            <p class="muted">Kampala International University · School of Mathematics and Computing · System Analysis and Design (COS2102), Year 2 Semester 1 · Lecturer: Mr. Byamugisha Peter.</p>
        </div>
        <div class="team-grid">
            <?php foreach ($team as $member): ?>
                <div class="team-card">
                    <span class="team-avatar" aria-hidden="true"><?= e($initials((string)$member['name'])) ?></span>
                    <div>
                        <p class="team-name"><?= e($member['name']) ?></p>
                        <?php if (!empty($member['role'])): ?><p class="team-role"><?= e($member['role']) ?></p><?php endif; ?>
                        <?php if (!empty($member['reg'])): ?><p class="team-reg"><?= e($member['reg']) ?><?= !empty($member['course']) ? ' · ' . e($member['course']) : '' ?></p><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container feature-grid">
        <div class="feature">
            <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></div>
            <h3>The case study</h3>
            <p><?php if (cfg('app.case_study')): ?>We studied how community complaints are reported and handled in <?= e(cfg('app.case_study')) ?><?= cfg('council.district') ? ', ' . e(cfg('council.district')) : '' ?>, covering <?= e(implode(', ', array_values(array_diff_key(cfg('divisions'), ['unsure' => 1])))) ?>.<?php else: ?>CivicServe can be configured for any municipality.<?php endif; ?></p>
        </div>
        <div class="feature">
            <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/></svg></div>
            <h3>What we built</h3>
            <p>A resident portal for reporting and tracking, and a secure staff workspace with a dashboard, case queue, deadlines, case history and role-based access.</p>
        </div>
        <div class="feature">
            <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/></svg></div>
            <h3>Technology</h3>
            <p>PHP, MySQL, HTML, CSS and JavaScript, with interactive maps (Leaflet and OpenStreetMap). Runs locally on MAMP.</p>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container narrow">
        <div class="info-card info-card-warning">
            <h3>Academic project</h3>
            <p style="margin:0">CivicServe is a student project by <?= e(cfg('app.vendor_name')) ?><?= cfg('app.case_study') ? ', based on a field study in ' . e(cfg('app.case_study')) : '' ?>.
                It is not an official government service, and all cases shown are sample data.</p>
        </div>
    </div>
</section>
<?php public_footer(); ?>
