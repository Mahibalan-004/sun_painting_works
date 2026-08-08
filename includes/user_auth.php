<?php
// ============================================================
// Sun Painting Works - Employee Protection Middleware
// ============================================================

require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    header("Location: ../login.php?error=" . urlencode("Please log in to access your portal."));
    exit;
}
