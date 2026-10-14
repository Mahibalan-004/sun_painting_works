<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$error = '';
$success = '';

// Handle Salary Log Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_salary') {
        $user_id      = (int)($_POST['user_id'] ?? 0);
        $amount       = (float)($_POST['amount'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $period_type  = $_POST['period_type'] ?? 'Weekly';
        $start_date   = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date     = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $notes        = trim($_POST['notes'] ?? '');

        if ($user_id <= 0 || $amount <= 0) {
            $error = "Please select an employee and enter a valid salary amount.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO salaries (user_id, amount, payment_date, period_type, start_date, end_date, notes, paid_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $amount, $payment_date, $period_type, $start_date, $end_date, $notes, $_SESSION['user_id']]);
                $success = "Employee salary payout logged successfully!";
            } catch (Exception $e) {
                $error = "Error recording salary payout: " . $e->getMessage();
            }
        }
    }

    if ($action === 'delete_salary') {
        $salary_id = (int)($_POST['salary_id'] ?? 0);
        if ($salary_id > 0) {
            $delStmt = $pdo->prepare("DELETE FROM salaries WHERE id = ?");
            $delStmt->execute([$salary_id]);
            $success = "Salary record deleted.";
        }
    }
}

// Filters
$filter_user_id = (int)($_GET['filter_user_id'] ?? 0);
$filter_period  = $_GET['filter_period'] ?? '';
$filter_month   = $_GET['filter_month'] ?? date('Y-m');

// Fetch Employees (Non-Admin & Active)
$employees = $pdo->query("SELECT id, employee_id, name, phone, role FROM users WHERE status = 'Active' ORDER BY name ASC")->fetchAll();

// Build Query for Salary Records
$sql = "SELECT s.*, u.name AS employee_name, u.employee_id AS emp_code, payer.name AS payer_name FROM salaries s JOIN users u ON s.user_id = u.id LEFT JOIN users payer ON s.paid_by = payer.id WHERE 1=1";
$params = [];

if ($filter_user_id > 0) {
    $sql .= " AND s.user_id = ?";
    $params[] = $filter_user_id;
}
if (!empty($filter_period)) {
    $sql .= " AND s.period_type = ?";
    $params[] = $filter_period;
}
if (!empty($filter_month)) {
    $sql .= " AND DATE_FORMAT(s.payment_date, '%Y-%m') = ?";
    $params[] = $filter_month;
}

$sql .= " ORDER BY s.payment_date DESC, s.id DESC";
$stmtSal = $pdo->prepare($sql);
$stmtSal->execute($params);
$salaryRecords = $stmtSal->fetchAll();

// Metrics Calculations
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$currentMonth = date('Y-m');

// This Week Salary Total
$stmtWeek = $pdo->prepare("SELECT SUM(amount) FROM salaries WHERE payment_date BETWEEN ? AND ?");
$stmtWeek->execute([$weekStart, $weekEnd]);
$weeklySalaryTotal = (float)$stmtWeek->fetchColumn();

// This Month Salary Total
$stmtMonth = $pdo->prepare("SELECT SUM(amount) FROM salaries WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
$stmtMonth->execute([$currentMonth]);
$monthlySalaryTotal = (float)$stmtMonth->fetchColumn();

// Total Count
$totalPayoutsCount = count($salaryRecords);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Salary & Payroll | Sun Painting Works</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
  <link rel="shortcut icon" href="../assets/images/favicon.ico">
  <link rel="apple-touch-icon" href="../assets/images/apple-touch-icon.png">

  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="../assets/js/theme.js"></script>

  <style>
    @media print {
      .sidebar, .top-bar, .no-print, .btn, form { display: none !important; }
      .main-content { margin-left: 0 !important; }
      body { background: #FFF !important; color: #000 !important; }
      .card-box { border: 1px solid #CCC !important; }
    }
  </style>
</head>
<body>

<div class="app-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <img src="../assets/images/logo.png" alt="Logo" class="sidebar-logo">
      <div style="flex-grow: 1;">
        <div class="sidebar-brand-name">SUN PAINTING</div>
        <span class="sidebar-role-badge">ADMIN PORTAL</span>
      </div>
      <button type="button" class="sidebar-close-btn" aria-label="Close navigation menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <ul class="sidebar-menu">
      <li class="sidebar-menu-category">Main Navigation</li>
      <li class="sidebar-item">
        <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      </li>
      <li class="sidebar-item">
        <a href="add_car.php"><i class="fa-solid fa-car-tunnel"></i> Add Car / Job</a>
      </li>
      <li class="sidebar-item">
        <a href="car_list.php"><i class="fa-solid fa-list-check"></i> Car List</a>
      </li>
      <li class="sidebar-item">
        <a href="mechanics.php"><i class="fa-solid fa-screwdriver-wrench"></i> Mechanics & Workshops</a>
      </li>
      
      <li class="sidebar-menu-category">Management</li>
      <li class="sidebar-item">
        <a href="users.php"><i class="fa-solid fa-users-gear"></i> Users & Staff</a>
      </li>
      <li class="sidebar-item">
        <a href="attendance.php"><i class="fa-solid fa-clipboard-user"></i> Attendance</a>
      </li>
      <li class="sidebar-item active">
        <a href="salary.php"><i class="fa-solid fa-money-bill-wave"></i> Employee Salary</a>
      </li>
      <li class="sidebar-item">
        <a href="profile.php"><i class="fa-solid fa-user-shield"></i> Profile & Security</a>
      </li>

      <li class="sidebar-menu-category">Public Site</li>
      <li class="sidebar-item">
        <a href="../index.php" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a>
      </li>
      <li class="sidebar-item">
        <a href="../logout.php"><i class="fa-solid fa-right-from-bracket text-gold"></i> Logout</a>
      </li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="top-bar no-print">
      <div style="display: flex; align-items: center; gap: 15px;">
        <button type="button" class="sidebar-toggle-btn" aria-label="Open navigation menu" aria-expanded="false">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="top-bar-title"><i class="fa-solid fa-money-bill-wave text-gold"></i> Employee Salary & Payroll Management</h1>
      </div>

      <div style="display: flex; gap: 10px; align-items: center;">
        <button onclick="window.print();" class="btn btn-outline-gold" style="padding: 8px 16px; font-size: 0.9rem;"><i class="fa-solid fa-print"></i> Print Report</button>
      </div>
    </header>

    <div class="content-body">
      
      <?php if (!empty($error)): ?>
        <div style="background: rgba(231, 76, 60, 0.15); border: 1px solid #E74C3C; color: #FF6B6B; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-triangle-exclamation"></i> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div style="background: rgba(46, 204, 113, 0.15); border: 1px solid #2ECC71; color: #2ECC71; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-circle-check"></i> <?php echo e($success); ?>
        </div>
      <?php endif; ?>

      <!-- Salary Summary Metrics Grid -->
      <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 25px;">
        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">This Week Payouts (Weekly)</div>
            <div class="metric-value" style="color: var(--gold-primary);"><?php echo formatRupee($weeklySalaryTotal); ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?php echo date('d M', strtotime($weekStart)); ?> – <?php echo date('d M Y', strtotime($weekEnd)); ?></div>
          </div>
          <div class="metric-icon-box metric-icon-gold">
            <i class="fa-solid fa-calendar-week"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">This Month Payouts (Monthly)</div>
            <div class="metric-value" style="color: #2ECC71;"><?php echo formatRupee($monthlySalaryTotal); ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?php echo date('F Y', strtotime($currentMonth . '-01')); ?></div>
          </div>
          <div class="metric-icon-box metric-icon-green">
            <i class="fa-solid fa-calendar-days"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Filtered Salary Records</div>
            <div class="metric-value"><?php echo $totalPayoutsCount; ?> Entries</div>
          </div>
          <div class="metric-icon-box metric-icon-silver">
            <i class="fa-solid fa-file-invoice-dollar"></i>
          </div>
        </div>
      </div>

      <!-- Log New Salary Payout Form -->
      <div class="card-box no-print" style="border-color: var(--border-gold);">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-plus-circle text-gold"></i> Log Employee Salary Payout</div>
        </div>

        <form action="salary.php" method="POST">
          <input type="hidden" name="action" value="add_salary">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Select Employee / Staff *</label>
              <select name="user_id" class="form-control" required style="border-color: var(--border-gold); font-weight: 700;">
                <option value="">-- Choose Employee --</option>
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo $emp['id']; ?>">
                    <?php echo e($emp['name']); ?> (<?php echo e($emp['employee_id']); ?> - <?php echo e($emp['role']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Salary Amount <span style="font-weight:400; font-size:0.85rem; color:var(--text-muted);">(e.g. ₹4,500)</span> *</label>
              <div style="position: relative;">
                <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gold-primary); font-weight: 700; font-size: 1rem;">₹</span>
                <input type="number" step="1" name="amount" class="form-control" style="padding-left: 32px;" placeholder="Enter the Amount" value="" required>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Payout Date *</label>
              <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label">Salary Period Type *</label>
              <select name="period_type" class="form-control" required>
                <option value="Weekly" selected>Weekly Wise Payout</option>
                <option value="Monthly">Monthly Wise Salary</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Work Period Start Date (Optional)</label>
              <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-6 days')); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Work Period End Date (Optional)</label>
              <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group" style="flex: 2;">
              <label class="form-label">Notes / Payment Method / Remarks</label>
              <input type="text" name="notes" class="form-control" placeholder="e.g. Weekly wages paid via UPI / Cash / Full Month Salary">
            </div>
          </div>

          <div style="text-align: right; margin-top: 15px;">
            <button type="submit" class="btn btn-gold" style="padding: 12px 30px; font-size: 0.95rem;">
              <i class="fa-solid fa-circle-check"></i> RECORD SALARY PAYOUT
            </button>
          </div>
        </form>
      </div>

      <!-- Salary Records Table & Filter -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-list-check text-gold"></i> Salary Payout History & Reports</div>

          <!-- Filter Form -->
          <form action="salary.php" method="GET" class="no-print" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div>
              <select name="filter_user_id" class="form-control" style="padding: 6px 12px; font-size: 0.85rem;" onchange="this.form.submit()">
                <option value="">All Workers</option>
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo $emp['id']; ?>" <?php echo $filter_user_id == $emp['id'] ? 'selected' : ''; ?>>
                    <?php echo e($emp['name']); ?> (<?php echo e($emp['employee_id']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <select name="filter_period" class="form-control" style="padding: 6px 12px; font-size: 0.85rem;" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="Weekly" <?php echo $filter_period === 'Weekly' ? 'selected' : ''; ?>>Weekly Wise</option>
                <option value="Monthly" <?php echo $filter_period === 'Monthly' ? 'selected' : ''; ?>>Monthly Wise</option>
              </select>
            </div>

            <div>
              <input type="month" name="filter_month" class="form-control" style="padding: 6px 12px; font-size: 0.85rem;" value="<?php echo e($filter_month); ?>" onchange="this.form.submit()">
            </div>

            <?php if (!empty($filter_user_id) || !empty($filter_period) || $filter_month !== date('Y-m')): ?>
              <a href="salary.php" class="btn btn-silver" style="padding: 6px 12px; font-size: 0.85rem;"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
            <?php endif; ?>
          </form>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Employee / Worker</th>
                <th>Paid Amount</th>
                <th>Payout Date</th>
                <th>Period Type</th>
                <th>Covered Period Range</th>
                <th>Notes / Method</th>
                <th>Recorded By</th>
                <th class="no-print">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($salaryRecords)): ?>
                <tr>
                  <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                    No salary payout records found for the selected criteria.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($salaryRecords as $sr): ?>
                  <tr>
                    <td>
                      <div style="font-weight: 700; color: var(--gold-dark);"><?php echo e($sr['employee_name']); ?></div>
                      <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($sr['emp_code']); ?></div>
                    </td>
                    <td style="font-weight: 800; font-size: 1.05rem; color: #27AE60;">
                      <?php echo formatRupee($sr['amount']); ?>
                    </td>
                    <td style="font-weight: 600;">
                      <?php echo date('d-m-Y', strtotime($sr['payment_date'])); ?>
                    </td>
                    <td>
                      <span class="badge <?php echo $sr['period_type'] === 'Weekly' ? 'badge-status-New' : 'badge-status-Completed'; ?>">
                        <?php echo e($sr['period_type']); ?> Payout
                      </span>
                    </td>
                    <td style="font-size: 0.85rem; color: var(--text-muted);">
                      <?php if (!empty($sr['start_date']) && !empty($sr['end_date'])): ?>
                        <?php echo date('d M', strtotime($sr['start_date'])); ?> to <?php echo date('d M Y', strtotime($sr['end_date'])); ?>
                      <?php else: ?>
                        N/A
                      <?php endif; ?>
                    </td>
                    <td style="font-size: 0.88rem;">
                      <?php echo e($sr['notes'] ?: 'N/A'); ?>
                    </td>
                    <td style="font-size: 0.82rem; color: var(--text-muted);">
                      <?php echo e($sr['payer_name'] ?? 'Admin'); ?>
                    </td>
                    <td class="no-print">
                      <form action="salary.php" method="POST" onsubmit="return confirm('Delete this salary record?');" style="display: inline;">
                        <input type="hidden" name="action" value="delete_salary">
                        <input type="hidden" name="salary_id" value="<?php echo $sr['id']; ?>">
                        <button type="submit" class="btn btn-silver" style="padding: 4px 10px; font-size: 0.8rem; color: #E74C3C;">
                          <i class="fa-solid fa-trash"></i> Delete
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
