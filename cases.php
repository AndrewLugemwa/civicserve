<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/case_query.php';
$user = require_login();

$f = case_filters_from_request($_GET);
// Officers start on their own department's queue unless they chose otherwise.
if ($user['role'] === 'officer' && $user['department'] && !isset($_GET['department']) && !isset($_GET['q'])) {
    $f['department'] = $user['department'];
}
[$where, $params] = case_where($f);

$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = db()->prepare("SELECT COUNT(*) FROM reports WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("SELECT id, reference_number, citizen_name, phone, category, division, location, priority, status,
                              assigned_department, due_date, channel, created_at
                       FROM reports WHERE $where ORDER BY " . case_order_by($f['sort']) . " LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$cases = $stmt->fetchAll();

$activeFilters = array_filter($f, fn($v, $k) => $v !== '' && $k !== 'sort', ARRAY_FILTER_USE_BOTH);

staff_header('Case queue', 'cases');
?>
<div class="page-head">
    <div>
        <h1>Case queue</h1>
        <p><?= number_format($total) ?> case<?= $total === 1 ? '' : 's' ?><?= $activeFilters ? ($total === 1 ? ' matches' : ' match') . ' these filters' : ' in total' ?></p>
    </div>
    <div class="button-row">
        <?php if (user_can('export', $user)): ?>
            <a class="button button-secondary" href="export.php<?= e(query_with($f)) ?>">Export to spreadsheet (CSV)</a>
        <?php endif; ?>
        <a class="button button-dark" href="intake.php">Log a complaint</a>
    </div>
</div>

<form class="filters" method="get" action="cases.php" aria-label="Filter cases">
    <div class="field field-search">
        <label for="q">Search</label>
        <input id="q" type="search" name="q" maxlength="80" value="<?= e($f['q']) ?>" placeholder="Reference, name, phone or place">
    </div>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">Any status</option>
            <option value="open"<?= $f['status'] === 'open' ? ' selected' : '' ?>>All open</option>
            <?php foreach (array_keys(cfg('statuses')) as $s): ?><option value="<?= e($s) ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="category">Issue</label>
        <select id="category" name="category">
            <option value="">Any issue</option>
            <?php foreach (cfg('categories') as $k => $c): ?><option value="<?= e($k) ?>"<?= $f['category'] === $k ? ' selected' : '' ?>><?= e($c['label']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="division">Division</label>
        <select id="division" name="division">
            <option value="">Any division</option>
            <?php foreach (cfg('divisions') as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['division'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="department">Department</label>
        <select id="department" name="department">
            <option value="">Any department</option>
            <?php foreach (cfg('departments') as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['department'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            <option value="none"<?= $f['department'] === 'none' ? ' selected' : '' ?>>Unassigned</option>
        </select>
    </div>
    <div class="field">
        <label for="priority">Priority</label>
        <select id="priority" name="priority">
            <option value="">Any priority</option>
            <?php foreach (cfg('priorities') as $p): ?><option value="<?= e($p) ?>"<?= $f['priority'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="period">Submitted</label>
        <select id="period" name="period">
            <?php foreach (['' => 'Any time', 'today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'year' => 'This year'] as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $f['period'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="sort">Sort by</label>
        <select id="sort" name="sort">
            <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'due' => 'Target date', 'priority' => 'Priority'] as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filters-actions">
        <label class="checkbox"><input type="checkbox" name="overdue" value="1"<?= $f['overdue'] ? ' checked' : '' ?>> Overdue only</label>
        <div class="button-row">
            <a class="button button-secondary button-small" href="cases.php?department=">Clear filters</a>
            <button class="button button-primary button-small" type="submit">Apply filters</button>
        </div>
    </div>
</form>

<div class="table-wrap">
    <?php if (!$cases): ?>
        <div class="empty-state"><h3>No cases found</h3><p>Try removing a filter or searching for a different reference.</p></div>
    <?php else: ?>
    <table class="data-table responsive">
        <thead><tr><th>Reference</th><th>Issue &amp; place</th><th>Complainant</th><th>Department</th><th>Priority</th><th>Status</th><th>Target date</th></tr></thead>
        <tbody>
        <?php foreach ($cases as $c): $late = is_overdue($c); ?>
            <tr class="<?= $late ? 'row-overdue' : '' ?>">
                <td data-label="Reference"><a class="ref" href="case.php?id=<?= (int)$c['id'] ?>"><?= e($c['reference_number']) ?></a><span class="sub"><?= e(fmt_date($c['created_at'])) ?><?= $c['channel'] !== 'online' ? ' · ' . e(cfg('channels.' . $c['channel'], '')) : '' ?></span></td>
                <td data-label="Issue"><?= e(category_label($c['category'])) ?><span class="sub"><?= e(str_limit((string)$c['location'], 48)) ?>, <?= e(division_label($c['division'])) ?></span></td>
                <td data-label="Complainant"><?= e($c['citizen_name']) ?><span class="sub" style="white-space:nowrap"><?= e(mask_phone($c['phone'])) ?></span></td>
                <td data-label="Department"><?= e(department_label($c['assigned_department'])) ?></td>
                <td data-label="Priority"><?= priority_badge($c['priority']) ?></td>
                <td data-label="Status"><?= status_badge($c['status']) ?></td>
                <td data-label="Target date"><?= e(fmt_date($c['due_date'])) ?><?php if (!is_closed_status($c['status']) && $c['due_date']): ?><span class="sub <?= $late ? 'overdue-flag' : '' ?>"><?= e(fmt_relative_days($c['due_date'])) ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <div class="table-footer">
        <span>Showing <?= $total ? ($offset + 1) . '–' . min($offset + $perPage, $total) : 0 ?> of <?= number_format($total) ?></span>
        <nav class="pagination" aria-label="Pages">
            <?php if ($page > 1): ?><a class="button button-secondary button-small" href="cases.php<?= e(query_with($f, ['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
            <span style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="button button-secondary button-small" href="cases.php<?= e(query_with($f, ['page' => $page + 1])) ?>">Next</a><?php endif; ?>
        </nav>
    </div>
</div>
<?php staff_footer(); ?>
