<?php
/**
 * CivicServe — configuration. Change names, divisions, departments and categories here
 * to set CivicServe up for any municipality.
 *
 * Everything the Council may want to change (names, departments, categories,
 * service standards, branding) lives here, so no page template needs editing.
 *
 * Environment-specific secrets (database password, environment name) belong in
 * includes/config.local.php, which is NOT shipped and must not be committed.
 * See config.local.example.php.
 */

$config = [
    'app' => [
        'product'      => 'CivicServe',
        'version'      => '3.0.0',
        // 'demo' shows the demonstration banner and allows the demo seed; 'production' hides debug detail.
        'env'          => 'demo',
        'timezone'     => 'Africa/Kampala',
        'base_url'     => '',          // e.g. https://civicserve.example.go.ug — used for absolute links if set
        'pilot_label'  => 'Academic project by Group 5',
        'tagline'      => 'Civic Issue Reporting & Tracking Portal',
        // Academic case study: set the study area to label the whole system as a field-study project.
        // Leave empty for the general edition.
        'case_study'   => 'Kansanga, Makindye Division (Kampala)',
        // The project team credited in footers and on the About page (about.php).
        'vendor_name'  => 'Group 5',
        'vendor_url'   => 'about.php',
    ],

    'council' => [
        'name'         => 'Makindye Division, Kampala',   // area of the field study
        'short_name'   => 'Makindye Division',
        'abbreviation' => 'CS',                     // reference prefix, e.g. CS-26-7KQ4XZ
        'district'     => '',
        'tagline'      => 'Report it. Track it. See it resolved.',
        // Logo shown in the header. To use a council's approved logo, drop the file into assets/brand/
        // and change this path. Never add national insignia without written authorisation.
        'logo'         => 'assets/brand/council-mark.svg',
        'logo_alt'     => 'CivicServe',
        'contact_phone'=> '',          // e.g. +256 … — leave blank until the Council confirms the public line
        'contact_email'=> '',
        'office_hours' => 'Monday to Friday, 8:00am – 5:00pm',
    ],

    'db' => [
        'host'     => getenv('CIVICSERVE_DB_HOST') ?: 'localhost',
        'port'     => getenv('CIVICSERVE_DB_PORT') ?: '',
        'name'     => getenv('CIVICSERVE_DB_NAME') ?: 'civicserve_kansanga',
        'user'     => getenv('CIVICSERVE_DB_USER') ?: 'root',
        'password' => getenv('CIVICSERVE_DB_PASSWORD') !== false ? getenv('CIVICSERVE_DB_PASSWORD') : 'root', // MAMP default, local only
    ],

    'security' => [
        'session_name'          => 'CIVICSERVE_KANSANGA',
        'session_idle_minutes'  => 30,
        'session_max_hours'     => 10,
        'login_max_failures'    => 5,    // per email + IP, within the window below
        'login_window_minutes'  => 15,
        'login_ip_max_failures' => 25,
        'track_max_lookups'     => 30,   // per IP per 10 minutes
        'submit_max_per_hour'   => 10,   // per IP
        'min_password_length'   => 12,
    ],

    'uploads' => [
        // Absolute path. For production, point this OUTSIDE the public web root.
        'dir'       => dirname(__DIR__) . '/uploads',
        'max_bytes' => 5 * 1024 * 1024,
        'max_px'    => 2400,             // images are re-encoded and resized; this strips GPS/EXIF metadata
    ],

    // Areas covered (neighbourhoods of Makindye Division around Kansanga) and what they are called.
    'area_label'        => 'Area',
    'area_label_plural' => 'Areas',
    // Where the report map opens (default: central Kampala). Change for another area.
    'map' => ['lat' => 0.2872, 'lng' => 32.6078, 'zoom' => 14],   // Kansanga
    // Project team (shown on about.php). Group work by Group 5.
    'team' => [
        ['name' => 'Lugemwa Andrew',        'reg' => '2025-08-43544', 'course' => 'BIT', 'role' => 'Team lead & lead developer'],
        ['name' => 'Najjuma Anisha',        'reg' => '2025-08-39931', 'course' => 'BIT', 'role' => 'Testing & quality assurance'],
        ['name' => 'Ochen Daniel',          'reg' => '2025-08-40657', 'course' => 'BIT', 'role' => 'Back-end development & security'],
        ['name' => 'Ssemujju Arthur Silver','reg' => '2025-08-42879', 'course' => 'BIT', 'role' => 'Systems analyst (field study)'],
        ['name' => 'Nabakka Caroline',      'reg' => '2025-08-41733', 'course' => 'BIT', 'role' => 'User interface design'],
        ['name' => 'Nalukwago Shanitah',    'reg' => '2025-08-42859', 'course' => 'BIT', 'role' => 'Requirements & documentation'],
        ['name' => 'Obanya Julius',         'reg' => '2025-08-44034', 'course' => 'BCS', 'role' => 'Database design'],
        ['name' => 'Aber Mercy Unia',       'reg' => '2025-08-43000', 'course' => 'BIT', 'role' => 'Field research & user interviews'],
        ['name' => 'Muroreukwere Shamirah', 'reg' => '2025-08-42503', 'course' => 'BIT', 'role' => 'Presentation & reporting'],
        ['name' => 'Nyamanyama Essenine',   'reg' => '2025-08-40571', 'course' => 'BIT', 'role' => 'Deployment & technical support'],
    ],

    'divisions' => [
        'kansanga'   => 'Kansanga',
        'kabalagala' => 'Kabalagala',
        'muyenga'    => 'Muyenga',
        'ggaba'      => 'Ggaba',
        'bukasa'     => 'Bukasa',
        'unsure'    => 'Not sure',
    ],

    // Departments that own cases.
    'departments' => [
        'works'     => 'Engineering & Technical Services',
        'health'    => 'Public Health Services & Environment',
        'planning'  => 'Physical Planning',
        'community' => 'Gender, Community Services & Production',
        'admin'     => 'Administration & Human Resource Management',
    ],

    // Report categories. 'department' routes new cases; 'sla_days' sets the target date;
    // 'priority' is the starting priority (staff can change it).
    'categories' => [
        'noise' => [
            'label' => 'Noise & nuisance', 'department' => 'health', 'priority' => 'Normal', 'sla_days' => 7,
            'hint'  => 'Persistent loud music, machinery, events or other public nuisance.',
        ],
        'roads' => [
            'label' => 'Potholes & road damage', 'department' => 'works', 'priority' => 'Normal', 'sla_days' => 14,
            'hint'  => 'Potholes, broken culverts, damaged road edges or walkways.',
        ],
        'drainage' => [
            'label' => 'Drainage, flooding & water leaks', 'department' => 'works', 'priority' => 'High', 'sla_days' => 7,
            'hint'  => 'Blocked channels, flooding, burst pipes or standing water.',
        ],
        'garbage' => [
            'label' => 'Uncollected garbage & sanitation', 'department' => 'health', 'priority' => 'Normal', 'sla_days' => 5,
            'hint'  => 'Missed collection, illegal dumping, overflowing skips or public toilets.',
        ],
        'streetlights' => [
            'label' => 'Streetlighting', 'department' => 'works', 'priority' => 'Normal', 'sla_days' => 10,
            'hint'  => 'Street lights that are off, flickering or damaged.',
        ],
        'building' => [
            'label' => 'Building control & land use', 'department' => 'planning', 'priority' => 'Normal', 'sla_days' => 14,
            'hint'  => 'Construction without approval, encroachment on road reserves or wetlands.',
        ],
        'facilities' => [
            'label' => 'Markets & public facilities', 'department' => 'admin', 'priority' => 'Normal', 'sla_days' => 14,
            'hint'  => 'Markets, taxi parks, public buildings or open spaces in poor condition.',
        ],
        'other' => [
            'label' => 'Other issue', 'department' => 'admin', 'priority' => 'Normal', 'sla_days' => 14,
            'hint'  => 'Anything else the Council should know about.',
        ],
    ],

    // Stored values stay stable for data integrity; residents see the public label.
    'statuses' => [
        'Submitted'    => ['public' => 'Received',               'tone' => 'info'],
        'Under Review' => ['public' => 'Under review',           'tone' => 'info'],
        'Assigned'     => ['public' => 'Assigned to department', 'tone' => 'progress'],
        'In Progress'  => ['public' => 'Work in progress',       'tone' => 'progress'],
        'Resolved'     => ['public' => 'Resolved',               'tone' => 'success'],
        'Rejected'     => ['public' => 'Closed — not actioned',  'tone' => 'muted'],
    ],
    'closed_statuses' => ['Resolved', 'Rejected'],

    'priorities' => ['Low', 'Normal', 'High', 'Urgent'],

    'roles' => [
        'admin'      => 'Administrator',
        'supervisor' => 'Supervisor',
        'officer'    => 'Case officer',
    ],

    'channels' => [
        'online'  => 'Online',
        'walk_in' => 'Walk-in (front desk)',
        'phone'   => 'Phone call',
    ],
];

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $overrides = require $localConfig;
    if (is_array($overrides)) {
        $config = array_replace_recursive($config, $overrides);
    }
}

return $config;
