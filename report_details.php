<?php
/** Legacy URL from CivicServe 2.x. Case pages now live at case.php. */
require __DIR__ . '/includes/bootstrap.php';
require_login();
$id = $_GET['id'] ?? '';
redirect((is_string($id) && ctype_digit($id)) ? 'case.php?id=' . (int)$id : 'cases.php');
