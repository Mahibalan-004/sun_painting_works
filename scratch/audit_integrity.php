<?php
require_once __DIR__ . '/../config/database.php';

echo "=== DATABASE INTEGRITY AUDIT ===\n";

// 1. Check orphaned cars
$orphanCars = $pdo->query("SELECT c.id, c.car_number, c.customer_id FROM cars c LEFT JOIN customers cust ON c.customer_id = cust.customer_id WHERE cust.customer_id IS NULL")->fetchAll();
echo "Orphaned Cars without customer: " . count($orphanCars) . "\n";

// 2. Check orphaned car_photos
$orphanPhotos = $pdo->query("SELECT cp.id, cp.car_id FROM car_photos cp LEFT JOIN cars c ON cp.car_id = c.id WHERE c.id IS NULL")->fetchAll();
echo "Orphaned Car Photos: " . count($orphanPhotos) . "\n";

// 3. Check orphaned attendance
$orphanAtt = $pdo->query("SELECT a.id, a.user_id FROM attendance a LEFT JOIN users u ON a.user_id = u.id WHERE u.id IS NULL")->fetchAll();
echo "Orphaned Attendance Records: " . count($orphanAtt) . "\n";

// 4. Check orphaned salaries
$orphanSal = $pdo->query("SELECT s.id, s.user_id FROM salaries s LEFT JOIN users u ON s.user_id = u.id WHERE u.id IS NULL")->fetchAll();
echo "Orphaned Salary Records: " . count($orphanSal) . "\n";

// 5. Check orphaned extra_work
$orphanExtra = $pdo->query("SELECT ew.id, ew.car_id FROM extra_work ew LEFT JOIN cars c ON ew.car_id = c.id WHERE c.id IS NULL")->fetchAll();
echo "Orphaned Extra Work: " . count($orphanExtra) . "\n";

// 6. Check orphaned work_history
$orphanWork = $pdo->query("SELECT wh.id, wh.car_id FROM work_history wh LEFT JOIN cars c ON wh.car_id = c.id WHERE c.id IS NULL")->fetchAll();
echo "Orphaned Work History: " . count($orphanWork) . "\n";

// 7. Verify financial consistency in cars
$cars = $pdo->query("SELECT id, car_number, estimate_amount, total_amount, final_amount, advance_amount, balance_amount, payment_status FROM cars")->fetchAll();
echo "\n=== FINANCIAL CONSISTENCY AUDIT IN CARS ===\n";
$financialDiscrepancies = 0;
foreach ($cars as $c) {
    $carId = $c['id'];
    $extraStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM extra_work WHERE car_id = ?");
    $extraStmt->execute([$carId]);
    $extraAmount = (float)$extraStmt->fetchColumn();

    $expectedTotal = (float)$c['estimate_amount'] + $extraAmount;
    $storedTotal = (float)$c['total_amount'];
    $finalAmt = (float)$c['final_amount'];
    $expectedBalance = max(0, $storedTotal - $finalAmt);
    $storedBalance = (float)$c['balance_amount'];

    $issue = [];
    if (abs($expectedTotal - $storedTotal) > 0.01) {
        $issue[] = "Total mismatch (expected $expectedTotal, stored $storedTotal)";
    }
    if (abs($expectedBalance - $storedBalance) > 0.01) {
        $issue[] = "Balance mismatch (expected $expectedBalance, stored $storedBalance)";
    }
    
    // Check payment status consistency
    $expectedStatus = ($finalAmt >= $storedTotal && $storedTotal > 0) ? 'Paid' : (($finalAmt > 0) ? 'Partial' : 'Pending');
    if ($c['payment_status'] !== $expectedStatus) {
        $issue[] = "Payment status mismatch (expected {$expectedStatus}, stored {$c['payment_status']})";
    }

    if (!empty($issue)) {
        $financialDiscrepancies++;
        echo "Car ID {$c['id']} ({$c['car_number']}): " . implode("; ", $issue) . "\n";
    }
}
if ($financialDiscrepancies === 0) {
    echo "All " . count($cars) . " cars have perfectly consistent financial figures and payment statuses!\n";
}

// 8. Sunday Attendance Rule Check on existing records
echo "\n=== SUNDAY ATTENDANCE INTEGRITY CHECK ===\n";
$sundayRecords = $pdo->query("SELECT id, user_id, attendance_date, status, DAYNAME(attendance_date) as dname FROM attendance WHERE DAYOFWEEK(attendance_date) = 1")->fetchAll();
echo "Total Sunday records in database: " . count($sundayRecords) . "\n";
$invalidSundayCount = 0;
foreach ($sundayRecords as $sr) {
    if ($sr['status'] !== 'Leave') {
        $invalidSundayCount++;
        echo "Sunday record ID {$sr['id']} for user {$sr['user_id']} on {$sr['attendance_date']} has non-Leave status: '{$sr['status']}'\n";
    }
}
if ($invalidSundayCount === 0) {
    echo "All existing Sunday records strictly follow the Sunday Leave rule!\n";
}
