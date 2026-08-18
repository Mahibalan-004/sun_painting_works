<?php
require_once __DIR__ . '/../includes/user_auth.php';

$userId = $_SESSION['user_id'];
$monthFilter = trim($_GET['month'] ?? date('Y-m'));

// Fetch Monthly Attendance for Current User
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ? ORDER BY attendance_date DESC");
$stmt->execute([$userId, $monthFilter]);
$myAttendance = $stmt->fetchAll();

// 1. Calculate Monthly Stats for current user
$monthlyCounts = ['Present' => 0, 'Absent' => 0, 'Half Day' => 0, 'Leave' => 0];
foreach ($myAttendance as $row) {
    if (isset($monthlyCounts[$row['status']])) {
        $monthlyCounts[$row['status']]++;
    }
}
$monthEffective = $monthlyCounts['Present'] + ($monthlyCounts['Half Day'] * 0.5);
$monthPercent = min(100, round(($monthEffective / 26) * 100));

// 2. Calculate Weekly Stats for current user (Current week Mon-Sun)
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($today)));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($today)));

$wStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE user_id = ? AND attendance_date BETWEEN ? AND ? GROUP BY status");
$wStmt->execute([$userId, $weekStart, $weekEnd]);
$weeklyCounts = ['Present' => 0, 'Absent' => 0, 'Half Day' => 0, 'Leave' => 0];
while ($wRow = $wStmt->fetch()) {
    $weeklyCounts[$wRow['status']] = (int)$wRow['cnt'];
}
$weekEffective = $weeklyCounts['Present'] + ($weeklyCounts['Half Day'] * 0.5);
$weekPercent = min(100, round(($weekEffective / 6) * 100));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Attendance | Sun Painting Works</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="app-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <img src="../assets/images/logo.png" alt="Logo" class="sidebar-logo">
      <div>
        <div class="sidebar-brand-name">SUN PAINTING</div>
        <span class="sidebar-role-badge">STAFF PORTAL</span>
      </div>
    </div>

    <ul class="sidebar-menu">
      <li class="sidebar-menu-category">Staff Navigation</li>
      <li class="sidebar-item">
        <a href="dashboard.php"><i class="fa-solid fa-house"></i> My Dashboard</a>
      </li>
      <li class="sidebar-item">
        <a href="work.php"><i class="fa-solid fa-spray-can"></i> Car Work</a>
      </li>
      <li class="sidebar-item active">
        <a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> My Attendance</a>
      </li>
      <li class="sidebar-item">
        <a href="profile.php"><i class="fa-solid fa-user-circle"></i> My Profile</a>
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
        <h1 class="top-bar-title"><i class="fa-solid fa-calendar-check text-gold"></i> My Attendance & Reports</h1>
      </div>
      <div class="user-profile-menu">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?></div>
        <div>
          <div style="font-weight: 700; font-size: 0.95rem;"><?php echo e($_SESSION['user_name']); ?></div>
          <div style="font-size: 0.75rem; color: var(--silver-primary);">Technician</div>
        </div>
      </div>
    </header>

    <div class="content-body">

      <!-- Weekly & Monthly Attendance Summary Overview Cards -->
      <div class="metrics-grid">
        <!-- Weekly Card -->
        <div class="metric-card" style="border-left: 4px solid var(--gold-primary);">
          <div class="metric-info" style="width: 100%;">
            <div class="metric-label" style="display: flex; justify-content: space-between;">
              <span>This Week (<?php echo date('d M', strtotime($weekStart)); ?> - <?php echo date('d M', strtotime($weekEnd)); ?>)</span>
              <strong style="color: var(--gold-dark);"><?php echo $weekPercent; ?>%</strong>
            </div>
            <div class="metric-value" style="font-size: 1.3rem; margin: 6px 0;">
              <span style="color: #27AE60;"><?php echo $weeklyCounts['Present']; ?> Present</span>
              <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;"> / <?php echo $weeklyCounts['Absent']; ?> Absent</span>
            </div>
            <div style="background: #E2E8F0; height: 6px; border-radius: 3px; overflow: hidden; margin-top: 6px;">
              <div style="width: <?php echo $weekPercent; ?>%; background: <?php echo $weekPercent >= 80 ? '#27AE60' : ($weekPercent >= 50 ? '#F1C40F' : '#E74C3C'); ?>; height: 100%;"></div>
            </div>
          </div>
        </div>

        <!-- Monthly Card -->
        <div class="metric-card" style="border-left: 4px solid #27AE60;">
          <div class="metric-info" style="width: 100%;">
            <div class="metric-label" style="display: flex; justify-content: space-between;">
              <span>This Month (<?php echo date('F Y', strtotime($monthFilter . '-01')); ?>)</span>
              <strong style="color: #27AE60;"><?php echo $monthPercent; ?>%</strong>
            </div>
            <div class="metric-value" style="font-size: 1.3rem; margin: 6px 0;">
              <span style="color: #27AE60;"><?php echo $monthlyCounts['Present']; ?> Present</span>
              <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;"> / <?php echo $monthlyCounts['Absent']; ?> Absent / <?php echo $monthlyCounts['Leave'] + $monthlyCounts['Half Day']; ?> Leave</span>
            </div>
            <div style="background: #E2E8F0; height: 6px; border-radius: 3px; overflow: hidden; margin-top: 6px;">
              <div style="width: <?php echo $monthPercent; ?>%; background: <?php echo $monthPercent >= 80 ? '#27AE60' : ($monthPercent >= 50 ? '#F1C40F' : '#E74C3C'); ?>; height: 100%;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Month Filter Bar -->
      <div class="card-box" style="padding: 20px;">
        <form action="attendance.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
          <div style="flex: 1; min-width: 220px; max-width: 280px;">
            <label class="form-label" style="font-size: 0.85rem;"><i class="fa-solid fa-calendar-days text-gold"></i> Select Month</label>
            <input type="month" name="month" class="form-control" value="<?php echo e($monthFilter); ?>" onchange="this.form.submit();">
          </div>
          <div>
            <button type="submit" class="btn btn-gold" style="padding: 11px 20px;"><i class="fa-solid fa-filter"></i> View Log</button>
          </div>
        </form>
      </div>

      <!-- Attendance Table -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-clock text-gold"></i> Monthly Attendance Log (Marked by Admin)</div>
          <span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo count($myAttendance); ?> entries recorded</span>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Punch In Time</th>
                <th>Punch Out Time</th>
                <th>Status</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($myAttendance)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No attendance records found for this month.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($myAttendance as $ma): ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--gold-dark);"><?php echo date('d-m-Y (D)', strtotime($ma['attendance_date'])); ?></td>
                    <td><?php echo $ma['login_time'] ? date('h:i A', strtotime($ma['login_time'])) : '--:--'; ?></td>
                    <td><?php echo $ma['logout_time'] ? date('h:i A', strtotime($ma['logout_time'])) : '--:--'; ?></td>
                    <td><span class="badge badge-att-<?php echo e($ma['status']); ?>"><?php echo e($ma['status']); ?></span></td>
                    <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo e($ma['remarks']); ?></td>
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
