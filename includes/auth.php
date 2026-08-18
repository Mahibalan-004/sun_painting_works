<?php
// ============================================================
// Sun Painting Works - Core Session & Auth Helper
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Escapes HTML output safely
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Formats Indian Currency (INR)
 */
function formatRupee($amount) {
    return '₹' . number_format((float)$amount, 2);
}

/**
 * Returns current logged-in user array or null
 */
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_data'] ?? null;
    }
    return null;
}

/**
 * Checks if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Checks if logged in user is Admin
 */
function isAdmin() {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'Admin');
}

/**
 * CSRF Token Generator
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Automatically Generate Next Unique Customer ID (SPW000001, SPW000002, etc.)
 */
function generateNextCustomerID($pdo) {
    $stmt = $pdo->query("SELECT customer_id FROM customers WHERE customer_id LIKE 'SPW%' ORDER BY id DESC LIMIT 1");
    $lastId = $stmt->fetchColumn();
    if ($lastId) {
        $num = (int)substr($lastId, 3);
        $nextNum = $num + 1;
    } else {
        $nextNum = 1;
    }
    return 'SPW' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

/**
 * Log work history entry
 */
function logWorkHistory($pdo, $car_id, $status, $description = '', $updated_by = null) {
    if (!$updated_by && isset($_SESSION['user_id'])) {
        $updated_by = $_SESSION['user_id'];
    }
    $stmt = $pdo->prepare("INSERT INTO work_history (car_id, status, description, updated_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$car_id, $status, $description, $updated_by]);
}

/**
 * Recalculate Car Financial Amounts (Estimate + Extra Work = Total, Balance = Total - Final Amount)
 */
function recalculateCarAmounts($pdo, $car_id) {
    // Get car estimate, final_amount, advance & mechanic amounts
    $stmt = $pdo->prepare("SELECT estimate_amount, final_amount, advance_amount, mechanic_total_amount, mechanic_given_amount FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch();
    if (!$car) return;

    $estimate = (float)$car['estimate_amount'];
    $final_amount = (float)$car['final_amount'];
    $advance_amount = (float)($car['advance_amount'] > 0 ? $car['advance_amount'] : $final_amount);

    $mech_total = (float)($car['mechanic_total_amount'] ?? 0);
    $mech_given = (float)($car['mechanic_given_amount'] ?? 0);
    $mech_balance = max(0, $mech_total - $mech_given);

    // Get total extra work amount
    $stmtExtra = $pdo->prepare("SELECT SUM(amount) AS extra_total FROM extra_work WHERE car_id = ?");
    $stmtExtra->execute([$car_id]);
    $extra_row = $stmtExtra->fetch();
    $extra_total = (float)($extra_row['extra_total'] ?? 0);

    $total_amount = $estimate + $extra_total;
    $balance_amount = max(0, $total_amount - $final_amount);

    if ($final_amount >= $total_amount && $total_amount > 0) {
        $payment_status = 'Paid';
        $balance_amount = 0.00;
    } elseif ($final_amount > 0) {
        $payment_status = 'Partial';
    } else {
        $payment_status = 'Pending';
    }

    $updateStmt = $pdo->prepare("UPDATE cars SET total_amount = ?, advance_amount = ?, balance_amount = ?, mechanic_balance_amount = ?, payment_status = ? WHERE id = ?");
    $updateStmt->execute([$total_amount, $advance_amount, $balance_amount, $mech_balance, $payment_status, $car_id]);
}
