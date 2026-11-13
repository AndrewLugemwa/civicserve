<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$pdo = db();

$today = date('Y-m-d');
$closed = "('Resolved', 'Rejected')";

$q = function (string $sql, array $params = []) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
};

$open      = (int)$q("SELECT COUNT(*) FROM reports WHERE status NOT IN $closed")->fetchColumn();
$overdue   = (int)$q("SELECT COUNT(*) FROM reports WHERE status NOT IN $closed AND due_date < :today", [':today' => $today])->fetchColumn();
$newToday  = (int)$q('SELECT COUNT(*) FROM reports WHERE created_at >= :start', [':start' => $today . ' 00:00:00'])->fetchColumn();
$resolved30 = (int)$q("SELECT COUNT(*) FROM reports WHERE status = 'Resolved' AND resolved_at >= :since", [':since' => date('Y-m-d H:i:s', strtotime('-30 days'))])->fetchColumn();
$perf = $q("SELECT COUNT(*) AS total,
                   SUM(CASE WHEN due_date IS NULL OR DATE(resolved_at) <= due_date THEN 1 ELSE 0 END) AS on_time,
                   AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
            FROM reports WHERE status = 'Resolved' AND resolved_at >= :since", [':since' => date('Y-m-d H:i:s', strtotime('-90 days'))])->fetch();
$onTimeRate = ($perf && (int)$perf['total'] > 0) ? round(100 * (int)$perf['on_time'] / (int)$perf['total']) : null;
$avgDays = ($perf && $perf['avg_hours'] !== null) ? round((float)$perf['avg_hours'] / 24, 1) : null;

// 14-day intake trend
$trendRows = $q('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM reports WHERE created_at >= :since GROUP BY DATE(created_at)',
    [':since' => date('Y-m-d 00:00:00', strtotime('-13 days'))])->fetchAll();
$trendMap = [];
foreach ($trendRows as $r) { $trendMap[(string)$r['d']] = (int)$r['n']; }
$trend = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trend[] = ['date' => $d, 'n' => $trendMap[$d] ?? 0];
}
$trendMax = max(1, max(array_column($trend, 'n')));

// Open workload by department (with overdue share)
$deptRows = $q("SELECT assigned_department AS dept, COUNT(*) AS open_n,
                       SUM(CASE WHEN due_date < :today THEN 1 ELSE 0 END) AS overdue_n
                FROM reports WHERE status NOT IN $closed GROUP BY assigned_department ORDER BY open_n DESC", [':today' => $today])->fetchAll();
$deptMax = max(1, ...array_map(fn($r) => (int)$r['open_n'], $deptRows ?: [['open_n' => 1]]));

$catRows = $q("SELECT category, COUNT(*) AS n FROM reports WHERE status NOT IN $closed GROUP BY category ORDER BY n DESC")->fetchAll();
$catMax = max(1, ...array_map(fn($r) => (int)$r['n'], $catRows ?: [['n' => 1]]));

$divRows = $q("SELECT division, COUNT(*) AS n FROM reports WHERE status NOT IN $closed GROUP BY division ORDER BY n DESC")->fetchAll();
$divMax = max(1, ...array_map(fn($r) => (int)$r['n'], $divRows ?: [['n' => 1]]));

$attention = $q("SELECT id, reference_number, category, division, assigned_department, priority, status, due_date
                 FROM reports
                 WHERE status NOT IN $closed AND (due_date < :today OR priority = 'Urgent')
                 ORDER BY due_date IS NULL, due_date ASC LIMIT 8", [':today' => $today])->fetchAll();

staff_header('Overview', 'overview');
?>
<div class="page-head">
    <div>
        <h1>Overview</h1>
        <p><?= e(date('l j F Y')) ?> · Council-wide figures across all divisions</p>
    </div>
    <div class="button-row">
        <a class="button button-secondary" href="intake.php">Log a complaint</a>
        <a class="button button-dark" href="cases.php?status=open&amp;sort=due">Open case queue</a>
    </div>
</div>

<section class="kpi-grid" aria-label="Key figures">
    <a class="kpi" href="cases.php?status=open"><div class="kpi-label">Open cases</div><div class="kpi-value"><?= number_format($open) ?></div><div class="kpi-note">Not yet resolved or closed</div></a>
    <a class="kpi<?= $overdue > 0 ? ' kpi-alert' : '' ?>" href="cases.php?overdue=1&amp;sort=due"><div class="kpi-label">Overdue</div><div class="kpi-value"><?= number_format($overdue) ?></div><div class="kpi-note">Past their target date</div></a>
    <a class="kpi" href="cases.php?period=today"><div class="kpi-label">New today</div><div class="kpi-value"><?= number_format($newToday) ?></div><div class="kpi-note">Received since midnight</div></a>
    <a class="kpi" href="cases.php?status=Resolved&amp;period=30"><div class="kpi-label">Resolved, 30 days</div><div class="kpi-value"><?= number_format($resolved30) ?></div><div class="kpi-note">Cases closed as resolved</div></a>
    <div class="kpi"><div class="kpi-label">Resolved on time</div><div class="kpi-value"><?= $onTimeRate === null ? '—' : $onTimeRate . '%' ?></div><div class="kpi-note"><?= $avgDays === null ? 'No resolved cases in 90 days' : 'Last 90 days · avg ' . e($avgDays) . ' days' ?></div></div>
</section>

<div class="panel-grid">
    <section class="panel span-7" aria-labelledby="trend-h">
        <h2 id="trend-h">Reports received</h2>
        <p class="panel-sub">Last 14 days, all channels</p>
        <div class="bars" role="img" aria-label="Reports received per day over the last 14 days">
            <?php foreach ($trend as $t): ?>
                <div class="bar-col<?= $t['date'] === $today ? ' today' : '' ?>">
                    <span class="bar-value"><?= $t['n'] ?: '' ?></span>
                    <div class="bar" style="height:<?= round($t['n'] / $trendMax * 100) ?>%" title="<?= e(fmt_date($t['date'])) ?>: <?= (int)$t['n'] ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="bar-labels" aria-hidden="true">
            <?php foreach ($trend as $i => $t): ?><span><?= $i % 2 === 1 || $t['date'] === $today ? e(date('j M', strtotime($t['date']))) : '' ?></span><?php endforeach; ?>
        </div>
    </section>

    <section class="panel span-5" aria-labelledby="dept-h">
        <h2 id="dept-h">Open workload by department</h2>
        <p class="panel-sub">Overdue share shown in red</p>
        <div class="legend"><span><i style="background:var(--navy-700)"></i>Within target</span><span><i style="background:var(--red-700)"></i>Overdue</span></div>
        <?php if (!$deptRows): ?><p class="muted">No open cases.</p><?php endif; ?>
        <ul class="hbar-list">
            <?php foreach ($deptRows as $r): $n = (int)$r['open_n']; $o = (int)$r['overdue_n']; ?>
                <li class="hbar-row">
                    <a href="cases.php?status=open&amp;department=<?= e($r['dept'] ?? 'none') ?>"><?= e(department_label($r['dept'])) ?></a>
                    <span class="count"><?= $n ?><?= $o ? ' <span class="overdue-flag">· ' . $o . ' overdue</span>' : '' ?></span>
                    <span class="hbar-track"><span class="hbar-fill" style="width:<?= round(($n - $o) / $deptMax * 100) ?>%"></span><span class="hbar-fill overdue" style="width:<?= round($o / $deptMax * 100) ?>%"></span></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel span-6" aria-labelledby="cat-h">
        <h2 id="cat-h">Open cases by issue</h2>
        <p class="panel-sub">Where demand is concentrated</p>
        <ul class="hbar-list">
            <?php foreach ($catRows as $r): ?>
                <li class="hbar-row">
                    <a href="cases.php?status=open&amp;category=<?= e($r['category']) ?>"><?= e(category_label($r['category'])) ?></a>
                    <span class="count"><?= (int)$r['n'] ?></span>
                    <span class="hbar-track"><span class="hbar-fill" style="width:<?= round((int)$r['n'] / $catMax * 100) ?>%"></span></span>
                </li>
            <?php endforeach; ?>
            <?php if (!$catRows): ?><li class="muted">No open cases.</li><?php endif; ?>
        </ul>
    </section>

    <section class="panel span-6" aria-labelledby="div-h">
        <h2 id="div-h">Open cases by division</h2>
        <p class="panel-sub">Across all divisions</p>
        <ul class="hbar-list">
            <?php foreach ($divRows as $r): ?>
                <li class="hbar-row">
                    <a href="cases.php?status=open&amp;division=<?= e($r['division']) ?>"><?= e(division_label($r['division'])) ?></a>
                    <span class="count"><?= (int)$r['n'] ?></span>
                    <span class="hbar-track"><span class="hbar-fill" style="width:<?= round((int)$r['n'] / $divMax * 100) ?>%"></span></span>
                </li>
            <?php endforeach; ?>
            <?php if (!$divRows): ?><li class="muted">No open cases.</li><?php endif; ?>
        </ul>
    </section>

    <section class="panel span-12" aria-labelledby="attn-h">
        <div class="card-head" style="margin-bottom:12px">
            <div><h2 id="attn-h">Needs attention</h2><p class="panel-sub" style="margin:0">Overdue or urgent cases, oldest target date first</p></div>
            <a class="button button-secondary button-small" href="cases.php?overdue=1&amp;sort=due">See all overdue</a>
        </div>
        <?php if (!$attention): ?>
            <div class="empty-state"><h3>Nothing overdue</h3><p>All open cases are within their target dates.</p></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table responsive">
                <thead><tr><th>Reference</th><th>Issue</th><th>Division</th><th>Department</th><th>Priority</th><th>Target date</th></tr></thead>
                <tbody>
                <?php foreach ($attention as $c): ?>
                    <tr class="<?= is_overdue($c) ? 'row-overdue' : '' ?>">
                        <td data-label="Reference"><a class="ref" href="case.php?id=<?= (int)$c['id'] ?>"><?= e($c['reference_number']) ?></a></td>
                        <td data-label="Issue"><?= e(category_label($c['category'])) ?></td>
                        <td data-label="Division"><?= e(division_label($c['division'])) ?></td>
                        <td data-label="Department"><?= e(department_label($c['assigned_department'])) ?></td>
                        <td data-label="Priority"><?= priority_badge($c['priority']) ?></td>
                        <td data-label="Target date"><?= e(fmt_date($c['due_date'])) ?> <span class="<?= is_overdue($c) ? 'overdue-flag' : 'due-soon' ?>"><?= e(fmt_relative_days($c['due_date'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php staff_footer(); ?>
