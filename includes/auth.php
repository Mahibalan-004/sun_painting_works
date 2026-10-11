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
    if ($amount === null || $amount === '') return '₹0';
    return '₹' . number_format(round((float)$amount));
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

/**
 * Ensures database schema has necessary tables and columns
 */
function ensureCarsTableSchema($pdo) {
    try {
        $pdo->exec("ALTER TABLE cars ADD COLUMN selected_parts TEXT NULL AFTER status");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE cars MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'New'");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE car_photos MODIFY COLUMN photo_type VARCHAR(50) NOT NULL DEFAULT 'damage'");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `salaries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `payment_date` DATE NOT NULL,
            `period_type` ENUM('Weekly', 'Monthly') NOT NULL DEFAULT 'Weekly',
            `start_date` DATE NULL,
            `end_date` DATE NULL,
            `notes` VARCHAR(255) NULL,
            `paid_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_salary_user_id` (`user_id`),
            INDEX `idx_salary_payment_date` (`payment_date`),
            CONSTRAINT `fk_salaries_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `form_submissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `submission_token` CHAR(64) NOT NULL,
            `user_id` INT NOT NULL,
            `form_type` VARCHAR(50) NOT NULL DEFAULT 'add_car',
            `status` ENUM('processing','success','failed') NOT NULL DEFAULT 'processing',
            `result_car_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_submission_token` (`submission_token`),
            INDEX `idx_fs_user_id` (`user_id`),
            INDEX `idx_fs_status` (`status`),
            INDEX `idx_fs_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {}
}
ensureCarsTableSchema($pdo);

/**
 * Returns existing customers array for live search & auto-fill
 */
function getAllCustomersList($pdo) {
    try {
        $stmt = $pdo->query("SELECT customer_id, customer_no, customer_name, alternate_phone, city FROM customers ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Generates or retrieves the current form idempotency token for Add Car
 */
function getOrCreateAddCarToken() {
    if (empty($_SESSION['current_add_car_token']) || strlen($_SESSION['current_add_car_token']) !== 64 || !ctype_xdigit($_SESSION['current_add_car_token'])) {
        $_SESSION['current_add_car_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['current_add_car_token'];
}

/**
 * Resets the Add Car token in session after a successful submission or when requesting a genuinely new form
 */
function resetAddCarToken() {
    $_SESSION['current_add_car_token'] = bin2hex(random_bytes(32));
    return $_SESSION['current_add_car_token'];
}

/**
 * Checks idempotency state of a submission token
 */
function checkIdempotencyToken($pdo, $token, $userId, $formType = 'add_car') {
    if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
        return ['status' => 'invalid', 'error' => 'Invalid submission token format.'];
    }

    $stmt = $pdo->prepare("SELECT id, user_id, status, result_car_id, created_at FROM form_submissions WHERE submission_token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if ($row) {
        if ((int)$row['user_id'] !== (int)$userId && !isAdmin()) {
            return ['status' => 'unauthorized', 'error' => 'Security check: submission token belongs to another session.'];
        }

        if ($row['status'] === 'success' && !empty($row['result_car_id'])) {
            return ['status' => 'already_success', 'result_car_id' => (int)$row['result_car_id']];
        }

        if ($row['status'] === 'processing') {
            return ['status' => 'processing', 'row' => $row];
        }

        if ($row['status'] === 'failed') {
            return ['status' => 'failed', 'row' => $row];
        }
    }

    return ['status' => 'new'];
}

/**
 * Atomically claims the submission token inside a transaction
 */
function claimIdempotencyToken($pdo, $token, $userId, $formType = 'add_car') {
    $stmt = $pdo->prepare("SELECT id, user_id, status, result_car_id FROM form_submissions WHERE submission_token = ? FOR UPDATE");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if ($row) {
        if ($row['status'] === 'success') {
            return ['claimed' => false, 'already_success' => true, 'result_car_id' => (int)$row['result_car_id']];
        }
        if ($row['status'] === 'processing') {
            return ['claimed' => false, 'concurrent_processing' => true];
        }
        if ($row['status'] === 'failed') {
            $update = $pdo->prepare("UPDATE form_submissions SET status = 'processing', user_id = ?, form_type = ?, updated_at = NOW() WHERE submission_token = ?");
            $update->execute([$userId, $formType, $token]);
            return ['claimed' => true, 'resumed' => true];
        }
    }

    try {
        $insert = $pdo->prepare("INSERT INTO form_submissions (submission_token, user_id, form_type, status, created_at) VALUES (?, ?, ?, 'processing', NOW())");
        $insert->execute([$token, $userId, $formType]);
        return ['claimed' => true, 'new' => true];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
            $check = $pdo->prepare("SELECT status, result_car_id FROM form_submissions WHERE submission_token = ?");
            $check->execute([$token]);
            $concurrentRow = $check->fetch();
            if ($concurrentRow && $concurrentRow['status'] === 'success') {
                return ['claimed' => false, 'already_success' => true, 'result_car_id' => (int)$concurrentRow['result_car_id']];
            }
            return ['claimed' => false, 'concurrent_processing' => true];
        }
        throw $e;
    }
}

/**
 * Marks the submission token as successfully completed with the resulting car ID
 */
function completeIdempotencyToken($pdo, $token, $resultCarId) {
    $stmt = $pdo->prepare("UPDATE form_submissions SET status = 'success', result_car_id = ?, updated_at = NOW() WHERE submission_token = ?");
    $stmt->execute([$resultCarId, $token]);
}

/**
 * Marks the submission token as failed to allow user to retry with corrected form
 */
function failIdempotencyToken($pdo, $token, $userId = null) {
    try {
        if (!$userId && isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }
        $userId = $userId ?: 1;
        $stmt = $pdo->prepare("INSERT INTO form_submissions (submission_token, user_id, form_type, status, created_at) 
            VALUES (?, ?, 'add_car', 'failed', NOW()) 
            ON DUPLICATE KEY UPDATE status = 'failed', updated_at = NOW()");
        $stmt->execute([$token, $userId]);
    } catch (Exception $e) {}
}



