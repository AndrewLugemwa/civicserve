<?php
require __DIR__ . '/includes/bootstrap.php';

$ref = normalise_reference((string)($_GET['ref'] ?? ''));
$report = null;
$timeline = [];
$message = null;

if ($ref !== '') {
    if (strlen($ref) > 24) {
        $message = ['error', 'That does not look like a CivicServe reference number.'];
    } elseif (!rate_limit_allow('track', rate_key('track', client_ip()), (int)cfg('security.track_max_lookups'), 600)) {
        http_response_code(429);
        $message = ['error', 'Too many lookups from this connection. Please wait 10 minutes and try again.'];
    } else {
        // Only non-personal fields are selected for the public view.
        $stmt = db()->prepare('SELECT id, reference_number, category, division, status, assigned_department, due_date, created_at, resolved_at
                               FROM reports WHERE reference_number = :ref LIMIT 1');
        $stmt->execute([':ref' => $ref]);
        $report = $stmt->fetch() ?: null;
        if ($report) {
            $log = db()->prepare('SELECT status, comment, is_public, created_at FROM status_logs WHERE report_id = :id ORDER BY created_at ASC, id ASC');
            $log->execute([':id' => $report['id']]);
            $timeline = $log->fetchAll();
        } else {
            $message = ['error', 'We could not find a report with reference ' . $ref . '. Check the number and try again.'];
        }
    }
}

$stages = ['Submitted' => 'Received', 'Assigned' => 'Assigned', 'In Progress' => 'In progress', 'Resolved' => 'Resolved'];
$order = ['Submitted' => 0, 'Under Review' => 0, 'Assigned' => 1, 'In Progress' => 2, 'Resolved' => 3, 'Rejected' => 3];

public_header('Track a report', 'track');
?>
<section class="section">
    <div class="container narrow">
        <p class="eyebrow">Track a report</p>
        <h1>Check the progress of your report</h1>
        <p class="muted">Enter the reference number you received when you submitted your report.</p>

        <div class="card" style="margin:20px 0">
            <form class="track-form" method="get" action="track.php" role="search">
                <label class="visually-hidden" for="ref">Reference number</label>
                <input id="ref" name="ref" type="text" required maxlength="24" placeholder="e.g. CS-26-7KQ4XZ" value="<?= e($ref) ?>" autocomplete="off" spellcheck="false">
                <button class="button button-dark" type="submit">Track report</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= e($message[0]) ?>" role="alert"><?= e($message[1]) ?></div>
        <?php endif; ?>

        <?php if ($report): $step = $order[$report['status']] ?? 0; $stopped = $report['status'] === 'Rejected'; ?>
            <article class="card" aria-labelledby="result-heading">
                <div class="card-head">
                    <div>
                        <p class="muted" style="margin:0;font-size:.85rem">Reference</p>
                        <h2 id="result-heading" class="mono" style="margin:0"><?= e($report['reference_number']) ?></h2>
                    </div>
                    <?= status_badge($report['status'], true) ?>
                </div>

                <ol class="progress-steps" aria-label="Progress">
                    <?php $i = 0; foreach ($stages as $label): ?>
                        <li class="<?= $i <= $step ? ($stopped && $i === 3 ? 'stopped' : 'done') : '' ?>"><?= e($stopped && $i === 3 ? 'Closed' : $label) ?></li>
                    <?php $i++; endforeach; ?>
                </ol>

                <dl class="detail-list">
                    <dt>Issue</dt><dd><?= e(category_label($report['category'])) ?></dd>
                    <dt>Division</dt><dd><?= e(division_label($report['division'])) ?></dd>
                    <dt>Department</dt><dd><?= e(department_label($report['assigned_department'])) ?></dd>
                    <dt>Submitted</dt><dd><?= e(fmt_date($report['created_at'], true)) ?></dd>
                    <?php if ($report['status'] === 'Resolved'): ?>
                        <dt>Resolved</dt><dd><?= e(fmt_date($report['resolved_at'], true)) ?></dd>
                    <?php elseif (!is_closed_status($report['status'])): ?>
                        <dt>Target date</dt><dd><?= e(fmt_date($report['due_date'])) ?></dd>
                    <?php endif; ?>
                </dl>
            </article>

            <section class="card" aria-labelledby="history-heading">
                <h2 id="history-heading">Updates</h2>
                <ol class="timeline">
                    <?php $lastShown = null; foreach ($timeline as $entry):
                        $publicNote = (int)$entry['is_public'] === 1 && trim((string)$entry['comment']) !== '';
                        // Show each status change once, plus any note staff chose to make public.
                        if ($entry['status'] === $lastShown && !$publicNote) continue;
                        $lastShown = $entry['status']; ?>
                        <li>
                            <div class="when"><?= e(fmt_date($entry['created_at'], true)) ?></div>
                            <div class="what"><?= e(public_status_label($entry['status'])) ?></div>
                            <?php if ((int)$entry['is_public'] === 1 && trim((string)$entry['comment']) !== ''): ?>
                                <p class="note"><?= e($entry['comment']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>

        <div class="info-card" style="margin-top:20px">
            <h3>Lost your reference number?</h3>
            <p style="margin:0">Visit or call a Council office with the phone number you used to report. For your privacy, staff will confirm your identity before sharing details.</p>
        </div>
    </div>
</section>
<?php public_footer(); ?>
