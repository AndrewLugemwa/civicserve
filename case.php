<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id = $_GET['id'] ?? '';
if (!is_string($id) || !ctype_digit($id)) {
    render_error_page(404, 'Case not found', 'That case link is not valid.');
}
$stmt = db()->prepare('SELECT r.*, u.name AS created_by_name FROM reports r LEFT JOIN users u ON u.id = r.created_by WHERE r.id = :id LIMIT 1');
$stmt->execute([':id' => (int)$id]);
$case = $stmt->fetch();
if (!$case) {
    render_error_page(404, 'Case not found', 'This case does not exist or has been removed.');
}

$logs = db()->prepare('SELECT l.status, l.comment, l.is_public, l.created_at, u.name AS staff_name
                       FROM status_logs l LEFT JOIN users u ON u.id = l.updated_by
                       WHERE l.report_id = :id ORDER BY l.created_at DESC, l.id DESC');
$logs->execute([':id' => (int)$case['id']]);
$history = $logs->fetchAll();

$canUpdate = user_can('update_case', $user, $case);
$canAssign = user_can('assign', $user);
$late = is_overdue($case);
$hasCoords = $case['latitude'] !== null && $case['longitude'] !== null;

staff_header($case['reference_number'], 'cases');
?>
<div class="page-head">
    <div>
        <p class="muted" style="margin:0"><a href="cases.php">Case queue</a> / Case</p>
        <h1 class="mono"><?= e($case['reference_number']) ?></h1>
        <div class="case-meta">
            <?= status_badge($case['status']) ?>
            <?= priority_badge($case['priority']) ?>
            <?php if ($late): ?><span class="overdue-flag"><?= e(fmt_relative_days($case['due_date'])) ?></span><?php endif; ?>
            <span class="muted" style="font-size:.85rem">Received <?= e(fmt_date($case['created_at'], true)) ?> · <?= e(cfg('channels.' . $case['channel'], 'Online')) ?></span>
        </div>
    </div>
    <div class="button-row no-print">
        <button class="button button-secondary" type="button" data-print>Print case sheet</button>
    </div>
</div>

<div class="case-grid">
    <div class="stack">
        <section class="panel">
            <h2>The issue</h2>
            <dl class="detail-list" style="margin:12px 0 16px">
                <dt>Category</dt><dd><?= e(category_label($case['category'])) ?></dd>
                <dt>Division</dt><dd><?= e(division_label($case['division'])) ?></dd>
                <dt>Location</dt><dd><?= e($case['location']) ?></dd>
                <dt>Map position</dt>
                <dd><?php if ($hasCoords): ?>
                    <?= e(number_format((float)$case['latitude'], 6)) ?>, <?= e(number_format((float)$case['longitude'], 6)) ?>
                    · <a href="https://www.openstreetmap.org/?mlat=<?= e((string)$case['latitude']) ?>&amp;mlon=<?= e((string)$case['longitude']) ?>#map=18/<?= e((string)$case['latitude']) ?>/<?= e((string)$case['longitude']) ?>" target="_blank" rel="noopener noreferrer">Open in map ↗</a>
                <?php else: ?><span class="muted">No pin provided</span><?php endif; ?></dd>
            </dl>
            <p class="description"><?= e($case['description']) ?></p>
            <?php if ($hasCoords): ?>
                <div class="map-frame no-print" style="margin-top:16px"><div class="map-canvas" id="case-map" style="height:260px" data-lat="<?= e((string)$case['latitude']) ?>" data-lng="<?= e((string)$case['longitude']) ?>" role="img" aria-label="Map showing the reported location"></div></div>
            <?php endif; ?>
        </section>

        <?php if (!empty($case['photo_path'])): ?>
        <section class="panel">
            <h2>Photo evidence</h2>
            <p class="panel-sub">Submitted with the report. Visible to signed-in staff only.</p>
            <a href="view_attachment.php?id=<?= (int)$case['id'] ?>" target="_blank" rel="noopener"><img class="evidence-photo" src="view_attachment.php?id=<?= (int)$case['id'] ?>" alt="Photo submitted with case <?= e($case['reference_number']) ?>" loading="lazy"></a>
        </section>
        <?php endif; ?>

        <section class="panel">
            <h2>Case history</h2>
            <p class="panel-sub">Most recent first. Notes marked internal are never shown to the resident.</p>
            <ol class="timeline">
                <?php foreach ($history as $h): ?>
                    <li>
                        <div class="when"><?= e(fmt_date($h['created_at'], true)) ?> · <?= e($h['staff_name'] ?: 'Resident / system') ?></div>
                        <div class="what"><?= e($h['status']) ?><?php if (!(int)$h['is_public']): ?><span class="tag-internal">Internal</span><?php endif; ?></div>
                        <?php if (trim((string)$h['comment']) !== ''): ?>
                            <p class="note<?= (int)$h['is_public'] ? '' : ' note-internal' ?>"><?= e($h['comment']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <div class="stack sticky-panel">
        <section class="panel no-print" aria-labelledby="update-h">
            <h2 id="update-h">Update case</h2>
            <?php if (!$canUpdate): ?>
                <p class="locked-note">This case belongs to <?= e(department_label($case['assigned_department'])) ?>. Only that department or a supervisor can update it.</p>
            <?php else: ?>
            <form method="post" action="update_status.php" class="stack" style="margin-top:12px">
                <?= csrf_field() ?>
                <input type="hidden" name="report_id" value="<?= (int)$case['id'] ?>">
                <input type="hidden" name="expected_updated_at" value="<?= e((string)$case['updated_at']) ?>">
                <div class="field">
                    <label for="u-status">Status</label>
                    <select id="u-status" name="status">
                        <?php foreach (cfg('statuses') as $s => $meta): ?><option value="<?= e($s) ?>"<?= $case['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php if ($canAssign): ?>
                <div class="field">
                    <label for="u-dept">Department</label>
                    <select id="u-dept" name="assigned_department">
                        <option value="">Unassigned</option>
                        <?php foreach (cfg('departments') as $k => $label): ?><option value="<?= e($k) ?>"<?= $case['assigned_department'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="u-priority">Priority</label>
                        <select id="u-priority" name="priority">
                            <?php foreach (cfg('priorities') as $p): ?><option value="<?= e($p) ?>"<?= $case['priority'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="u-due">Target date</label>
                        <input id="u-due" type="date" name="due_date" value="<?= e((string)$case['due_date']) ?>">
                    </div>
                </div>
                <?php else: ?>
                    <p class="locked-note">Department, priority and target date are set by supervisors.</p>
                <?php endif; ?>
                <div class="field">
                    <label for="u-comment">Note <span class="optional">(required when closing)</span></label>
                    <textarea id="u-comment" name="comment" maxlength="1000" style="min-height:96px" placeholder="What was done, or what happens next"></textarea>
                </div>
                <label class="checkbox"><input type="checkbox" name="is_public" value="1"> Show this note to the resident on the tracking page</label>
                <button class="button button-primary" type="submit">Save update</button>
            </form>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Assignment</h2>
            <dl class="detail-list" style="grid-template-columns:110px 1fr;margin-top:12px">
                <dt>Department</dt><dd><?= e(department_label($case['assigned_department'])) ?></dd>
                <dt>Target date</dt><dd><?= e(fmt_date($case['due_date'])) ?><?php if (!is_closed_status($case['status']) && $case['due_date']): ?> <span class="<?= $late ? 'overdue-flag' : 'due-soon' ?>">(<?= e(fmt_relative_days($case['due_date'])) ?>)</span><?php endif; ?></dd>
                <?php if ($case['resolved_at']): ?><dt>Closed</dt><dd><?= e(fmt_date($case['resolved_at'], true)) ?></dd><?php endif; ?>
            </dl>
        </section>

        <section class="panel">
            <h2>Complainant</h2>
            <p class="panel-sub">Personal data. Use only to handle this case.</p>
            <dl class="detail-list" style="grid-template-columns:110px 1fr">
                <dt>Name</dt><dd><?= e($case['citizen_name']) ?></dd>
                <dt>Phone</dt><dd><a href="tel:<?= e($case['phone']) ?>"><?= e($case['phone']) ?></a></dd>
                <dt>Email</dt><dd><?= $case['citizen_email'] ? '<a href="mailto:' . e($case['citizen_email']) . '">' . e($case['citizen_email']) . '</a>' : '<span class="muted">Not given</span>' ?></dd>
                <?php if ($case['created_by_name']): ?><dt>Logged by</dt><dd><?= e($case['created_by_name']) ?></dd><?php endif; ?>
            </dl>
        </section>
    </div>
</div>
<?php staff_footer(['assets/js/map.js', 'assets/js/staff.js']); ?>
