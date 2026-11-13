<?php
require __DIR__ . '/includes/bootstrap.php';

$last = $_SESSION['last_reference'] ?? null;
if (!is_array($last)) {
    redirect('index.php');
}

public_header('Report received', 'report');
?>
<section class="section">
    <div class="container narrow">
        <div class="card confirm-card">
            <div class="confirm-icon" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
            <p class="eyebrow">Report received</p>
            <h1 style="font-size:1.8rem">Thank you. The Council has your report.</h1>
            <p class="muted">Keep this reference number. You will need it to check progress.</p>

            <div class="reference-box">
                <span>Your reference number</span>
                <strong id="reference"><?= e($last['reference']) ?></strong>
            </div>

            <dl class="summary-list" style="max-width:520px;margin:0 auto 28px">
                <dt>Issue</dt><dd><?= e(category_label($last['category'])) ?></dd>
                <dt>Location</dt><dd><?= e($last['location']) ?>, <?= e(division_label($last['division'])) ?></dd>
                <dt>Handled by</dt><dd><?= e(department_label($last['department'])) ?></dd>
                <dt>Target date</dt><dd><?= e(fmt_date($last['due_date'])) ?></dd>
            </dl>

            <div class="button-row" style="justify-content:center">
                <a class="button button-primary" href="track.php?ref=<?= urlencode($last['reference']) ?>">Track this report</a>
                <?php if (!empty($last['staff']) && current_user()): ?>
                    <a class="button button-secondary" href="case.php?id=<?= (int)$last['case_id'] ?>">Open the case</a>
                    <a class="button button-secondary" href="intake.php">Log another complaint</a>
                <?php else: ?>
                    <a class="button button-secondary" href="index.php#report">Report another issue</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card" style="margin-top:20px">
            <h3>What happens next</h3>
            <ul>
                <li>The department reviews your report and may call you to confirm the location.</li>
                <li>The target date is the Council's service standard for this type of issue. Complex cases can take longer, and you will see the reason on the tracking page.</li>
                <li>Write the reference number down or take a screenshot of this page.</li>
            </ul>
        </div>
    </div>
</section>
<?php public_footer(); ?>
