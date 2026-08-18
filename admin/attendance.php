<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'daily'; // 'daily', 'weekly', 'monthly'

// Fetch Active Users for Dropdowns & Reports
$allUsers = $pdo->query("SELECT id, name, employee_id FROM users WHERE status = 'Active' ORDER BY name ASC")->fetchAll();

// Handle Manual Attendance Entry/Update by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $att_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'Present';
    $login_time = !empty($_POST['login_time']) ? $_POST['login_time'] : null;
    $logout_time = !empty($_POST['logout_time']) ? $_POST['logout_time'] : null;
    $remarks = trim($_POST['remarks'] ?? '');

    if ($user_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, attendance_date, login_time, logout_time, status, remarks) 
                               VALUES (?, ?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE login_time = VALUES(login_time), logout_time = VALUES(logout_time), status = VALUES(status), remarks = VALUES(remarks)");
        $stmt->execute([$user_id, $att_date, $login_time, $logout_time, $status, $remarks]);
        $success = "Attendance record updated for " . date('d-m-Y', strtotime($att_date));
    }
}

// -------------------------------------------------------------
// 1. DATA FOR DAILY LOG VIEW
// -------------------------------------------------------------
$empFilter = (int)($_GET['employee_id'] ?? 0);
$dateFilter = trim($_GET['date'] ?? '');
$monthFilterDaily = trim($_GET['month'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];

if ($empFilter > 0) {
    $where[] = "a.user_id = ?";
    $params[] = $empFilter;
}

if (!empty($dateFilter)) {
    $where[] = "a.attendance_date = ?";
    $params[] = $dateFilter;
}

if (!empty($monthFilterDaily)) {
    $where[] = "DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
    $params[] = $monthFilterDaily;
}

if (!empty($statusFilter)) {
    $where[] = "a.status = ?";
    $params[] = $statusFilter;
}

$whereSql = implode(" AND ", $where);
$query = "SELECT a.*, u.name AS employee_name, u.employee_id AS emp_code, u.role 
          FROM attendance a 
          JOIN users u ON a.user_id = u.id 
          WHERE {$whereSql} 
          ORDER BY a.attendance_date DESC, a.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendanceList = $stmt->fetchAll();

// -------------------------------------------------------------
// 2. DATA FOR WEEKLY ATTENDANCE REPORT
// -------------------------------------------------------------
$weekDate = trim($_GET['week_date'] ?? date('Y-m-d'));
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($weekDate)));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($weekDate)));

$weeklyStaffStats = [];
$totalWeeklyPresent = 0;
$totalWeeklyAbsent = 0;
$totalWeeklyLeave = 0;

foreach ($allUsers as $u) {
    $uId = $u['id'];
    $wStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE user_id = ? AND attendance_date BETWEEN ? AND ? GROUP BY status");
    $wStmt->execute([$uId, $weekStart, $weekEnd]);
    $counts = ['Present' => 0, 'Absent' => 0, 'Half Day' => 0, 'Leave' => 0];
    while ($row = $wStmt->fetch()) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
    $effectiveDays = $counts['Present'] + ($counts['Half Day'] * 0.5);
    $percent = round(($effectiveDays / 6) * 100);
    $percent = min(100, $percent);

    $totalWeeklyPresent += $counts['Present'];
    $totalWeeklyAbsent += $counts['Absent'];
    $totalWeeklyLeave += ($counts['Half Day'] + $counts['Leave']);

    $weeklyStaffStats[] = [
        'id' => $u['id'],
        'name' => $u['name'],
        'emp_code' => $u['employee_id'],
        'present' => $counts['Present'],
        'absent' => $counts['Absent'],
        'half_day' => $counts['Half Day'],
        'leave' => $counts['Leave'],
        'percent' => $percent
    ];
}

// 7-day daily breakdown for Weekly Bar Chart
$weekDaysData = [];
for ($i = 0; $i < 7; $i++) {
    $curDay = date('Y-m-d', strtotime("+{$i} days", strtotime($weekStart)));
    $dayLabel = date('D (d M)', strtotime($curDay));
    $dStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE attendance_date = ? GROUP BY status");
    $dStmt->execute([$curDay]);
    $dCounts = ['Present' => 0, 'Absent' => 0, 'Half Day' => 0, 'Leave' => 0];
    while ($dRow = $dStmt->fetch()) {
        $dCounts[$dRow['status']] = (int)$dRow['cnt'];
    }
    $weekDaysData[] = [
        'date' => $curDay,
        'label' => $dayLabel,
        'present' => $dCounts['Present'],
        'absent' => $dCounts['Absent'],
        'leave' => $dCounts['Leave'] + $dCounts['Half Day']
    ];
}

// -------------------------------------------------------------
// 3. DATA FOR MONTHLY ATTENDANCE REPORT
// -------------------------------------------------------------
$selectedMonth = trim($_GET['report_month'] ?? date('Y-m'));
$daysInMonth = (int)date('t', strtotime($selectedMonth . '-01'));

$monthlyStaffStats = [];
$monthlyStatusTotals = ['Present' => 0, 'Absent' => 0, 'Half Day' => 0, 'Leave' => 0];

foreach ($allUsers as $u) {
    $uId = $u['id'];
    $mStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE user_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ? GROUP BY status");
    $mStmt->execute([$uId, $selectedMonth]);
    $counts = ['Present' => 0, 'Absent' => 0, 'Half Day' => 0, 'Leave' => 0];
    while ($row = $mStmt->fetch()) {
        $counts[$row['status']] = (int)$row['cnt'];
        $monthlyStatusTotals[$row['status']] += (int)$row['cnt'];
    }
    $effectiveDays = $counts['Present'] + ($counts['Half Day'] * 0.5);
    $percent = round(($effectiveDays / 26) * 100);
    $percent = min(100, $percent);

    $monthlyStaffStats[] = [
        'id' => $u['id'],
        'name' => $u['name'],
        'emp_code' => $u['employee_id'],
        'present' => $counts['Present'],
        'absent' => $counts['Absent'],
        'half_day' => $counts['Half Day'],
        'leave' => $counts['Leave'],
        'percent' => $percent
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Workshop Attendance & Reports | Sun Painting Works</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  
  <!-- Chart.js for Attendance Bar Charts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="app-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <img src="../assets/images/logo.png" alt="Logo" class="sidebar-logo">
      <div>
        <div class="sidebar-brand-name">SUN PAINTING</div>
        <span class="sidebar-role-badge">ADMIN PORTAL</span>
      </div>
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
      <li class="sidebar-item active">
        <a href="attendance.php"><i class="fa-solid fa-clipboard-user"></i> Attendance</a>
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
    <header class="top-bar">
      <div style="display: flex; align-items: center; gap: 15px;">
        <button type="button" class="sidebar-toggle-btn" aria-label="Toggle Sidebar Menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="top-bar-title"><i class="fa-solid fa-clipboard-user text-gold"></i> Attendance Management</h1>
      </div>
      <div class="user-profile-menu">
        <div class="user-avatar">AD</div>
        <div>
          <div style="font-weight: 700; font-size: 0.95rem;"><?php echo e($_SESSION['user_name']); ?></div>
          <div style="font-size: 0.75rem; color: var(--gold-dark);"><i class="fa-solid fa-shield"></i> Administrator</div>
        </div>
      </div>
    </header>

    <div class="content-body">
      <?php if (!empty($success)): ?>
        <div style="background: rgba(46, 204, 113, 0.12); border: 1px solid #2ECC71; color: #27AE60; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-circle-check"></i> <?php echo e($success); ?>
        </div>
      <?php endif; ?>

      <!-- Tab Navigation -->
      <div class="report-tabs">
        <a href="attendance.php?tab=daily" class="report-tab-btn <?php echo $activeTab === 'daily' ? 'active' : ''; ?>">
          <i class="fa-solid fa-calendar-day"></i> Daily Log & Entry
        </a>
        <a href="attendance.php?tab=weekly" class="report-tab-btn <?php echo $activeTab === 'weekly' ? 'active' : ''; ?>">
          <i class="fa-solid fa-chart-column"></i> Weekly Report & Bar Chart
        </a>
        <a href="attendance.php?tab=monthly" class="report-tab-btn <?php echo $activeTab === 'monthly' ? 'active' : ''; ?>">
          <i class="fa-solid fa-calendar-days"></i> Monthly Report & Bar Chart
        </a>
      </div>

      <?php if ($activeTab === 'daily'): ?>
        <!-- ================= TAB 1: DAILY ATTENDANCE & MANUAL ENTRY ================= -->

        <!-- Quick Manual Attendance Entry Modal/Card -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-user-pen text-gold"></i> Record / Update Employee Attendance</div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Only Admin can record and override staff attendance</span>
          </div>

          <form action="attendance.php?tab=daily" method="POST">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Employee *</label>
                <select name="user_id" class="form-control" required>
                  <option value="">Select Employee</option>
                  <?php foreach ($allUsers as $u): ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo e($u['name']); ?> (<?php echo e($u['employee_id']); ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Date *</label>
                <input type="date" name="attendance_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Status *</label>
                <select name="status" class="form-control" required>
                  <option value="Present">Present</option>
                  <option value="Absent">Absent</option>
                  <option value="Half Day">Half Day</option>
                  <option value="Leave">Leave</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Login Time</label>
                <input type="time" name="login_time" class="form-control" value="09:00">
              </div>

              <div class="form-group">
                <label class="form-label">Logout Time</label>
                <input type="time" name="logout_time" class="form-control" value="18:00">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Remarks</label>
              <input type="text" name="remarks" class="form-control" placeholder="Optional notes (e.g., On-site job, Overtime, Permission)">
            </div>

            <div style="text-align: right;">
              <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> SAVE ATTENDANCE</button>
            </div>
          </form>
        </div>

        <!-- Filters Form -->
        <div class="card-box" style="padding: 20px;">
          <form action="attendance.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="tab" value="daily">
            <div style="flex: 1; min-width: 160px;">
              <label class="form-label" style="font-size: 0.8rem;">Filter Employee</label>
              <select name="employee_id" class="form-control">
                <option value="0">All Staff</option>
                <?php foreach ($allUsers as $u): ?>
                  <option value="<?php echo $u['id']; ?>" <?php echo $empFilter === (int)$u['id'] ? 'selected' : ''; ?>><?php echo e($u['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="flex: 1; min-width: 140px;">
              <label class="form-label" style="font-size: 0.8rem;">Filter Month</label>
              <input type="month" name="month" class="form-control" value="<?php echo e($monthFilterDaily); ?>">
            </div>

            <div style="flex: 1; min-width: 140px;">
              <label class="form-label" style="font-size: 0.8rem;">Filter Date</label>
              <input type="date" name="date" class="form-control" value="<?php echo e($dateFilter); ?>">
            </div>

            <div style="flex: 1; min-width: 140px;">
              <label class="form-label" style="font-size: 0.8rem;">Filter Status</label>
              <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Present" <?php echo $statusFilter === 'Present' ? 'selected' : ''; ?>>Present</option>
                <option value="Absent" <?php echo $statusFilter === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                <option value="Half Day" <?php echo $statusFilter === 'Half Day' ? 'selected' : ''; ?>>Half Day</option>
                <option value="Leave" <?php echo $statusFilter === 'Leave' ? 'selected' : ''; ?>>Leave</option>
              </select>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              <button type="submit" class="btn btn-gold" style="padding: 11px 20px;"><i class="fa-solid fa-filter"></i> Filter</button>
              <a href="attendance.php?tab=daily" class="btn btn-silver" style="padding: 11px 16px;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            </div>
          </form>
        </div>

        <!-- Attendance Table -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-clipboard-check text-gold"></i> Daily Attendance History Log</div>
            <span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo count($attendanceList); ?> records found</span>
          </div>

          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Employee Code</th>
                  <th>Employee Name</th>
                  <th>Login Time</th>
                  <th>Logout Time</th>
                  <th>Status</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($attendanceList)): ?>
                  <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">No attendance records found matching filters.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($attendanceList as $att): ?>
                    <tr>
                      <td style="font-weight: 700; color: var(--gold-dark);"><?php echo date('d-m-Y (D)', strtotime($att['attendance_date'])); ?></td>
                      <td><code><?php echo e($att['emp_code']); ?></code></td>
                      <td style="font-weight: 700;"><?php echo e($att['employee_name']); ?></td>
                      <td><?php echo $att['login_time'] ? date('h:i A', strtotime($att['login_time'])) : '--:--'; ?></td>
                      <td><?php echo $att['logout_time'] ? date('h:i A', strtotime($att['logout_time'])) : '--:--'; ?></td>
                      <td>
                        <span class="badge badge-att-<?php echo e($att['status']); ?>"><?php echo e($att['status']); ?></span>
                      </td>
                      <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo e($att['remarks']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($activeTab === 'weekly'): ?>
        <!-- ================= TAB 2: WEEKLY ATTENDANCE REPORT & BAR CHART ================= -->

        <!-- Week Filter -->
        <div class="card-box" style="padding: 20px;">
          <form action="attendance.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="weekly">
            <div style="flex: 1; min-width: 220px;">
              <label class="form-label" style="font-size: 0.85rem;"><i class="fa-solid fa-calendar text-gold"></i> Select Any Date in Target Week</label>
              <input type="date" name="week_date" class="form-control" value="<?php echo e($weekDate); ?>" onchange="this.form.submit();">
            </div>
            <div>
              <button type="submit" class="btn btn-gold" style="padding: 11px 20px;"><i class="fa-solid fa-filter"></i> View Week</button>
            </div>
          </form>
        </div>

        <!-- Weekly Summary Metrics -->
        <div class="metrics-grid">
          <div class="metric-card">
            <div class="metric-info">
              <div class="metric-label">Selected Week Range</div>
              <div class="metric-value" style="font-size: 1.1rem; color: var(--gold-dark);">
                <?php echo date('d M', strtotime($weekStart)); ?> - <?php echo date('d M Y', strtotime($weekEnd)); ?>
              </div>
            </div>
            <div class="metric-icon-box metric-icon-gold"><i class="fa-solid fa-calendar-week"></i></div>
          </div>

          <div class="metric-card">
            <div class="metric-info">
              <div class="metric-label">Total Days Present</div>
              <div class="metric-value" style="color: #27AE60;"><?php echo $totalWeeklyPresent; ?></div>
            </div>
            <div class="metric-icon-box metric-icon-green"><i class="fa-solid fa-circle-check"></i></div>
          </div>

          <div class="metric-card">
            <div class="metric-info">
              <div class="metric-label">Total Days Absent</div>
              <div class="metric-value" style="color: #C0392B;"><?php echo $totalWeeklyAbsent; ?></div>
            </div>
            <div class="metric-icon-box metric-icon-red"><i class="fa-solid fa-circle-xmark"></i></div>
          </div>

          <div class="metric-card">
            <div class="metric-info">
              <div class="metric-label">Total Leaves / Half Days</div>
              <div class="metric-value" style="color: #2980B9;"><?php echo $totalWeeklyLeave; ?></div>
            </div>
            <div class="metric-icon-box metric-icon-silver"><i class="fa-solid fa-clock"></i></div>
          </div>
        </div>

        <!-- Weekly Attendance Trends Bar Chart -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-chart-column text-gold"></i> Weekly Daily Attendance Trends (Bar Chart)</div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Daily staff attendance counts across the selected week</span>
          </div>
          <div style="height: 290px; position: relative;">
            <canvas id="weeklyBarChart"></canvas>
          </div>
        </div>

        <!-- Weekly Employee Breakdown Table -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-users text-gold"></i> Staff Weekly Attendance Summary</div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Week: <?php echo date('d-m-Y', strtotime($weekStart)); ?> to <?php echo date('d-m-Y', strtotime($weekEnd)); ?></span>
          </div>

          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Employee Code</th>
                  <th>Employee Name</th>
                  <th>Present Days</th>
                  <th>Absent Days</th>
                  <th>Half Days</th>
                  <th>Leaves</th>
                  <th>Weekly Attendance %</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($weeklyStaffStats as $ws): ?>
                  <tr>
                    <td><code><?php echo e($ws['emp_code']); ?></code></td>
                    <td style="font-weight: 700;"><?php echo e($ws['name']); ?></td>
                    <td style="color: #27AE60; font-weight: 700;"><?php echo $ws['present']; ?> Days</td>
                    <td style="color: #C0392B; font-weight: 700;"><?php echo $ws['absent']; ?> Days</td>
                    <td><?php echo $ws['half_day']; ?></td>
                    <td><?php echo $ws['leave']; ?></td>
                    <td>
                      <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="flex-grow: 1; background: #E2E8F0; height: 8px; border-radius: 4px; overflow: hidden; min-width: 60px;">
                          <div style="width: <?php echo $ws['percent']; ?>%; background: <?php echo $ws['percent'] >= 80 ? '#27AE60' : ($ws['percent'] >= 50 ? '#F1C40F' : '#E74C3C'); ?>; height: 100%;"></div>
                        </div>
                        <span style="font-weight: 800; font-size: 0.9rem; min-width: 45px;"><?php echo $ws['percent']; ?>%</span>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($activeTab === 'monthly'): ?>
        <!-- ================= TAB 3: MONTHLY ATTENDANCE REPORT & BAR CHART ================= -->

        <!-- Month Filter -->
        <div class="card-box" style="padding: 20px;">
          <form action="attendance.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="monthly">
            <div style="flex: 1; min-width: 220px;">
              <label class="form-label" style="font-size: 0.85rem;"><i class="fa-solid fa-calendar-days text-gold"></i> Select Target Month</label>
              <input type="month" name="report_month" class="form-control" value="<?php echo e($selectedMonth); ?>" onchange="this.form.submit();">
            </div>
            <div>
              <button type="submit" class="btn btn-gold" style="padding: 11px 20px;"><i class="fa-solid fa-filter"></i> View Month</button>
            </div>
          </form>
        </div>

        <!-- Monthly Summary Metrics -->
        <div class="metrics-grid">
          <div class="metric-card">
            <div class="metric-info">
              <div class="metric-label">Selected Month</div>
              <div class="metric-value" style="font-size: 1.3rem; color: var(--gold-dark);"><?php echo date('F Y', strtotime($selectedMonth . '-01')); ?></div>
            </div>
            <div class="metric-icon-box metric-icon-gold"><i class="fa-solid fa-calendar-check"></i></div>
          </div>

          <div class="metric-card">
            <div class="metric-info">
              <div class="metric-label">Total Present Recorded</div>
              <div class="metric-value" style="color: #27AE60;"><?php echo $monthlyStatusTotals['Present']; ?></div>
            </div>
            <div class="metric-icon-box metric-icon-green"><i class="fa-solid fa-user-check"></i></div>
          </div>

          <div class="metric-card">
            <div class="metric-info">
              <div class="metric-label">Total Absences</div>
              <div class="metric-value" style="color: #C0392B;"><?php echo $monthlyStatusTotals['Absent']; ?></div>
            </div>
            <div class="metric-icon-box metric-icon-red"><i class="fa-solid fa-user-xmark"></i></div>
          </div>

          <div class="metric-card">
            <div class="metric-info">
              <div class="metric-label">Total Leaves & Half Days</div>
              <div class="metric-value" style="color: #2980B9;"><?php echo $monthlyStatusTotals['Leave'] + $monthlyStatusTotals['Half Day']; ?></div>
            </div>
            <div class="metric-icon-box metric-icon-silver"><i class="fa-solid fa-clock-rotate-left"></i></div>
          </div>
        </div>

        <!-- Monthly Breakdown Bar Chart -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-chart-column text-gold"></i> Monthly Attendance Status Breakdown (Bar Chart)</div>
            <span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('F Y', strtotime($selectedMonth . '-01')); ?> Summary</span>
          </div>
          <div style="height: 290px; position: relative;">
            <canvas id="monthlyBarChart"></canvas>
          </div>
        </div>

        <!-- Monthly Staff Attendance Table -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-users-viewfinder text-gold"></i> Employee Monthly Attendance Log Summary</div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Month: <?php echo date('F Y', strtotime($selectedMonth . '-01')); ?> (<?php echo $daysInMonth; ?> Days)</span>
          </div>

          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Employee Code</th>
                  <th>Employee Name</th>
                  <th>Present</th>
                  <th>Absent</th>
                  <th>Half Day</th>
                  <th>Leave</th>
                  <th>Monthly Attendance %</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($monthlyStaffStats as $ms): ?>
                  <tr>
                    <td><code><?php echo e($ms['emp_code']); ?></code></td>
                    <td style="font-weight: 700;"><?php echo e($ms['name']); ?></td>
                    <td style="color: #27AE60; font-weight: 700;"><?php echo $ms['present']; ?></td>
                    <td style="color: #C0392B; font-weight: 700;"><?php echo $ms['absent']; ?></td>
                    <td><?php echo $ms['half_day']; ?></td>
                    <td><?php echo $ms['leave']; ?></td>
                    <td>
                      <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="flex-grow: 1; background: #E2E8F0; height: 8px; border-radius: 4px; overflow: hidden; min-width: 60px;">
                          <div style="width: <?php echo $ms['percent']; ?>%; background: <?php echo $ms['percent'] >= 80 ? '#27AE60' : ($ms['percent'] >= 50 ? '#F1C40F' : '#E74C3C'); ?>; height: 100%;"></div>
                        </div>
                        <span style="font-weight: 800; font-size: 0.9rem; min-width: 45px;"><?php echo $ms['percent']; ?>%</span>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>

<?php if ($activeTab === 'weekly'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('weeklyBarChart').getContext('2d');
  const labels = <?php echo json_encode(array_column($weekDaysData, 'label')); ?>;
  const presentData = <?php echo json_encode(array_column($weekDaysData, 'present')); ?>;
  const absentData = <?php echo json_encode(array_column($weekDaysData, 'absent')); ?>;
  const leaveData = <?php echo json_encode(array_column($weekDaysData, 'leave')); ?>;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Present',
          data: presentData,
          backgroundColor: '#27AE60',
          borderRadius: 5
        },
        {
          label: 'Absent',
          data: absentData,
          backgroundColor: '#E74C3C',
          borderRadius: 5
        },
        {
          label: 'Leave / Half Day',
          data: leaveData,
          backgroundColor: '#3498DB',
          borderRadius: 5
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: { color: '#1E293B', font: { family: 'Outfit', weight: '600' } }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#1E293B', font: { family: 'Outfit' } }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0, 0, 0, 0.05)' },
          ticks: { precision: 0, color: '#64748B', font: { family: 'Outfit' } }
        }
      }
    }
  });
});
</script>
<?php endif; ?>

<?php if ($activeTab === 'monthly'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('monthlyBarChart').getContext('2d');
  const labels = ['Present', 'Absent', 'Half Day', 'Leave'];
  const data = [
    <?php echo $monthlyStatusTotals['Present']; ?>,
    <?php echo $monthlyStatusTotals['Absent']; ?>,
    <?php echo $monthlyStatusTotals['Half Day']; ?>,
    <?php echo $monthlyStatusTotals['Leave']; ?>
  ];

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Total Staff Count',
        data: data,
        backgroundColor: ['#27AE60', '#E74C3C', '#F1C40F', '#3498DB'],
        borderRadius: 6,
        borderWidth: 1,
        borderColor: 'rgba(0,0,0,0.05)'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.raw} Total Logs`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#1E293B', font: { family: 'Outfit', weight: '700' } }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0, 0, 0, 0.05)' },
          ticks: { precision: 0, color: '#64748B', font: { family: 'Outfit' } }
        }
      }
    }
  });
});
</script>
<?php endif; ?>

</body>
</html>
