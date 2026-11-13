<?php
/**
 * Copy this file to includes/config.local.php on each server and fill in real values.
 * config.local.php is environment-specific and must never be shared or committed.
 */
return [
    'app' => [
        'env'      => 'production',             // 'demo' for the presentation laptop
        'base_url' => 'https://civicserve.example.go.ug',
    ],
    'db' => [
        'host'     => 'localhost',
        'name'     => 'civic_eservice',
        'user'     => 'civicserve_app',          // a dedicated user with only SELECT/INSERT/UPDATE/DELETE
        'password' => 'CHANGE-ME-long-random-password',
    ],
    'council' => [
        'contact_phone' => '',
        'contact_email' => '',
    ],
    'uploads' => [
        'dir' => '/var/civicserve-private/uploads',  // outside the web root in production
    ],
];
