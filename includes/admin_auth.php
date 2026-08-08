<?php
// ============================================================
// Sun Painting Works - Admin Protection Middleware
// ============================================================

require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    header("Location: ../login.php?error=" . urlencode("Please log in to access the admin portal."));
    exit;
}

if (!isAdmin()) {
    header("Location: ../user/dashboard.php?error=" . urlencode("Access Denied: Admin privileges required."));
    exit;
}
