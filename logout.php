<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    $nowTime = date('H:i:s');

    // Update logout time in attendance
    $updateAtt = $pdo->prepare("UPDATE attendance SET logout_time = ? WHERE user_id = ? AND attendance_date = ? AND logout_time IS NULL");
    $updateAtt->execute([$nowTime, $userId, $today]);
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: login.php?success=" . urlencode("You have logged out successfully."));
exit;
