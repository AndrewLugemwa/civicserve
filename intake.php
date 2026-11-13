<?php
/** Staff intake: record walk-in and phone complaints in the same queue as online reports. */
require __DIR__ . '/includes/bootstrap.php';
require_ability('log_intake');

$formState = take_form_state('report');
$staffMode = true;

staff_header('Log a complaint', 'intake');
?>
<div class="page-head">
    <div>
        <h1>Log a complaint</h1>
        <p>For residents who report at the front desk or by phone. The case joins the same queue, with the same reference number and target date.</p>
    </div>
</div>
<div class="panel" style="max-width:860px">
    <?php require __DIR__ . '/includes/report_form.php'; ?>
</div>
<?php staff_footer(['assets/js/map.js', 'assets/js/report-form.js']); ?>
