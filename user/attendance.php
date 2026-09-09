<?php
require_once __DIR__ . '/../includes/user_auth.php';

// Attendance details are restricted to Admin only per system security requirements
header("Location: dashboard.php");
exit;
