<?php
require __DIR__ . '/includes/bootstrap.php';

// Sign-out is POST + CSRF so another site cannot sign staff out with a link or image.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_valid()) {
    civic_end_session();
    session_start();
    flash('success', 'You have signed out.');
    redirect('login.php');
}
redirect(current_user() ? 'admin.php' : 'login.php');
