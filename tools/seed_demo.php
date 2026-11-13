<?php
/**
 * Loads realistic DEMONSTRATION cases for the presentation (last 30 days, mixed statuses,
 * some overdue). All names and phone numbers are fictional. Refuses to run in production.
 *
 *   php tools/seed_demo.php           # add demo cases
 *   php tools/seed_demo.php --reset   # remove previous demo cases first
 *   php tools/seed_demo.php --remove  # remove demo cases only (do this before real use)
 *
 * Demo cases are identifiable by the email domain @demo.invalid.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

if (is_production()) {
    fwrite(STDERR, "Refusing to load demo data while app.env is 'production'.\n");
    exit(1);
}
$pdo = db();

if (in_array('--reset', $argv, true) || in_array('--remove', $argv, true)) {
    $n = $pdo->exec("DELETE FROM reports WHERE citizen_email LIKE '%@demo.invalid'");
    fwrite(STDOUT, "Removed $n demo case(s).\n");
    if (in_array('--remove', $argv, true)) exit(0);
}

mt_srand(2026);
$names = ['Nakato Sarah', 'Ssempijja Joseph', 'Namubiru Grace', 'Kato Ronald', 'Nansubuga Prossy', 'Mugisha Brian', 'Nabirye Esther',
          'Lubega Moses', 'Nalwoga Harriet', 'Ochieng Peter', 'Akello Joan', 'Kiggundu Paul', 'Nanyonga Rebecca', 'Wasswa Isaac',
          'Babirye Annet', 'Tumusiime David', 'Namatovu Florence', 'Kasozi Henry'];
$places = ['along the main road', 'near the trading centre', 'behind the taxi stage', 'off the main road, near the junction',
           'next to the community water point', 'near the health centre', 'by the drainage channel', 'near the primary school'];
// One map centre per configured division, spread around the configured map centre.
$lat0 = (float)cfg('map.lat'); $lng0 = (float)cfg('map.lng');
$offsets = [[0.018, -0.016], [-0.011, 0.018], [-0.012, -0.006], [0.0, 0.03], [0.03, 0.0], [-0.03, 0.0]];
$centres = [];
foreach (array_values(array_diff(array_keys(cfg('divisions')), ['unsure'])) as $i => $key) {
    $o = $offsets[$i % count($offsets)];
    $centres[$key] = [$lat0 + $o[0], $lng0 + $o[1]];
}
$texts = [
    'roads'        => ['Large pothole has opened in the middle of the road. Boda bodas are swerving into oncoming traffic.', 'Road edge has collapsed after the rains and the culvert is exposed.'],
    'drainage'     => ['The drainage channel is completely blocked with silt and plastic. Water flooded three homes during last night\'s rain.', 'A water pipe has burst and has been leaking for four days. The road is turning to mud.'],
    'garbage'      => ['Garbage has not been collected for two weeks. The skip is overflowing and attracting flies and dogs.', 'People are dumping rubbish on the empty plot at night. It is starting to smell.'],
    'streetlights' => ['Five street lights in a row have been off for over a week. The stretch is very dark and residents feel unsafe walking at night.', 'Street light pole was knocked by a truck and is leaning over the walkway.'],
    'noise'        => ['A bar plays very loud music past midnight every weekend. Children cannot sleep and residents have asked them to stop.', 'A metal workshop runs grinding machines from 6am including Sundays.'],
    'building'     => ['A building is going up on the road reserve and is narrowing the road. No approval notice is displayed.', 'Someone is filling in the wetland with murram at night.'],
    'facilities'   => ['The roof of the market shed is leaking badly and vendors\' goods are getting spoilt.', 'Public toilet at the taxi stage has been locked for a month.'],
    'other'        => ['Stray cattle are grazing on the roadside and blocking traffic every morning.'],
];
$statusFlow = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved'];
$publicNotes = [
    'Assigned'    => 'Your report has been passed to the responsible team for inspection.',
    'In Progress' => 'A team has inspected the site and works are scheduled.',
    'Resolved'    => 'Works completed. Thank you for reporting.',
];

$categories = cfg('categories');
$catKeys = array_keys($categories);
$weights = ['roads' => 6, 'drainage' => 6, 'garbage' => 7, 'streetlights' => 4, 'noise' => 4, 'building' => 3, 'facilities' => 2, 'other' => 1];
$pool = [];
foreach ($weights as $k => $w) { for ($i = 0; $i < $w; $i++) $pool[] = $k; }

$insert = $pdo->prepare('INSERT INTO reports (reference_number, citizen_name, citizen_email, phone, category, division, description, location,
    latitude, longitude, priority, assigned_department, due_date, status, channel, created_at, updated_at, resolved_at)
    VALUES (:ref, :name, :email, :phone, :cat, :div, :desc, :loc, :lat, :lng, :pri, :dept, :due, :status, :channel, :created, :updated, :resolved)');
$log = $pdo->prepare('INSERT INTO status_logs (report_id, status, comment, is_public, created_at) VALUES (:id, :status, :comment, :public, :at)');

$count = 48;
for ($i = 0; $i < $count; $i++) {
    $cat = $pool[mt_rand(0, count($pool) - 1)];
    $c = $categories[$cat];
    $divKey = array_keys($centres)[mt_rand(0, count($centres) - 1)];
    $ageDays = mt_rand(0, 29);
    $created = strtotime("-$ageDays days") - mt_rand(0, 9) * 3600;
    if ($created > time()) $created = time() - 600;
    $due = date('Y-m-d', strtotime('+' . $c['sla_days'] . ' days', $created));

    // Older cases are further along; some stall to create realistic overdue work.
    $progress = min(4, (int)floor($ageDays / 4) + mt_rand(-1, 1));
    $progress = max(0, $progress);
    if (mt_rand(1, 10) <= 2 && $progress > 1) $progress = mt_rand(1, 3); // stalled
    $status = $statusFlow[$progress];
    if ($status === 'Resolved' && mt_rand(1, 12) === 1) $status = 'Rejected';

    $lat = $lng = null;
    if (mt_rand(1, 10) <= 7) {
        [$clat, $clng] = $centres[$divKey];
        $lat = round($clat + mt_rand(-80, 80) / 10000, 7);
        $lng = round($clng + mt_rand(-80, 80) / 10000, 7);
    }
    $name = $names[mt_rand(0, count($names) - 1)];
    $timeline = [[ 'Submitted', 'Report received online. Routed to ' . department_label($c['department']) . '.', 1, $created ]];
    $t = $created;
    for ($s = 1; $s <= $progress; $s++) {
        $t += mt_rand(6, 60) * 3600;
        if ($t > time()) { $t = time() - 300; }
        $st = $statusFlow[$s];
        if ($s === 4) $st = $status;
        $note = $st === 'Rejected' ? 'Inspected: the site is private property. The resident has been advised by phone.' : ($publicNotes[$st] ?? '');
        $timeline[] = [$st, $note, $note !== '' ? 1 : 0, $t];
    }
    $resolved = in_array($status, ['Resolved', 'Rejected'], true) ? date('Y-m-d H:i:s', $t) : null;

    $insert->execute([
        ':ref' => new_case_reference(), ':name' => $name,
        ':email' => strtolower(str_replace(' ', '.', $name)) . '@demo.invalid',
        ':phone' => '+2567000' . str_pad((string)mt_rand(0, 99999), 5, '0', STR_PAD_LEFT),
        ':cat' => $cat, ':div' => $divKey, ':desc' => $texts[$cat][mt_rand(0, count($texts[$cat]) - 1)],
        ':loc' => division_label($divKey) . ', ' . $places[mt_rand(0, count($places) - 1)],
        ':lat' => $lat, ':lng' => $lng,
        ':pri' => mt_rand(1, 12) === 1 ? 'Urgent' : $c['priority'],
        ':dept' => $c['department'], ':due' => $due, ':status' => $status,
        ':channel' => ['online', 'online', 'online', 'walk_in', 'phone'][mt_rand(0, 4)],
        ':created' => date('Y-m-d H:i:s', $created), ':updated' => date('Y-m-d H:i:s', $t), ':resolved' => $resolved,
    ]);
    $id = (int)$pdo->lastInsertId();
    foreach ($timeline as [$st, $note, $public, $at]) {
        $log->execute([':id' => $id, ':status' => $st, ':comment' => $note, ':public' => $public, ':at' => date('Y-m-d H:i:s', $at)]);
    }
}
fwrite(STDOUT, "Loaded $count demo cases. Remove them before real use with: php tools/seed_demo.php --remove\n");
