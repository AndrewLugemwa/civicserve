<?php
require __DIR__ . '/includes/bootstrap.php';

$formState = take_form_state('report');
$staffMode = false;

public_header('Report a community issue', 'report');
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <p class="eyebrow"><?= e(cfg('app.tagline')) ?></p>
            <h1>Report a problem in your community.</h1>
            <p class="hero-lead">Tell the Council about potholes, blocked drainage, uncollected garbage, faulty street lights, noise and other local issues. Every report gets a reference number, a responsible department and a target date.</p>
            <div class="button-row">
                <a class="button button-primary" href="#report">Report an issue</a>
                <a class="button button-secondary" href="track.php">Track a report</a>
            </div>
        </div>
        <div class="hero-panel">
            <h2>How it works</h2>
            <ol class="hero-steps">
                <li><span class="step-num">1</span><span><strong>Report</strong>Describe the issue, mark the spot and add a photo.</span></li>
                <li><span class="step-num">2</span><span><strong>Get a reference</strong>Keep it to follow progress without calling the office.</span></li>
                <li><span class="step-num">3</span><span><strong>Council acts</strong>The right department is assigned with a target date.</span></li>
            </ol>
            <form class="hero-track" method="get" action="track.php">
                <label class="visually-hidden" for="hero-ref">Reference number</label>
                <input id="hero-ref" name="ref" type="text" placeholder="e.g. CS-26-7KQ4XZ" maxlength="24" autocomplete="off">
                <button class="button button-primary" type="submit">Track</button>
            </form>
        </div>
    </div>
</section>

<section class="section" id="report">
    <div class="container report-layout">
        <div class="card">
            <div class="card-head">
                <div>
                    <h2>Submit a report</h2>
                    <p>It takes about three minutes. Fields marked optional can be left blank.</p>
                </div>
            </div>
            <?= render_flashes() ?>
            <?php require __DIR__ . '/includes/report_form.php'; ?>
        </div>

        <aside class="aside-stack" aria-label="Before you report">
            <div class="info-card info-card-warning">
                <h3>Not for emergencies</h3>
                <p style="margin:0">For fire, crime, road accidents or medical emergencies, call <strong>999</strong> or <strong>112</strong>.</p>
            </div>
            <div class="info-card">
                <h3>Our service standards</h3>
                <ul>
                    <?php foreach (cfg('categories') as $catKey => $cat): if ($catKey === 'other') continue; ?>
                        <li><?= e($cat['label']) ?>: target <?= (int)$cat['sla_days'] ?> days</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="info-card">
                <h3>Your privacy</h3>
                <p style="margin:0">We use your details only to handle this report. They are visible to authorised Council staff and are never shown on the public tracking page.</p>
            </div>
        </aside>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container feature-grid">
        <div class="feature">
            <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/></svg></div>
            <h3>Accountable</h3><p>Every update is recorded with the date and the department responsible.</p>
        </div>
        <div class="feature">
            <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M3 12h4l3 8 4-16 3 8h4"/></svg></div>
            <h3>Trackable</h3><p>Use your reference number at any time to see where your report stands.</p>
        </div>
        <div class="feature">
            <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></div>
            <h3>Local</h3><p>Each report records where it is and goes to the department responsible.</p>
        </div>
    </div>
</section>
<script src="<?= asset('assets/js/map.js') ?>" defer></script>
<script src="<?= asset('assets/js/report-form.js') ?>" defer></script>
<?php public_footer(); ?>
