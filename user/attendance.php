<?php
require_once __DIR__ . '/../includes/user_auth.php';

$userId = $_SESSION['user_id'];
$monthFilter = trim($_GET['month'] ?? date('Y-m'));

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ? ORDER BY attendance_date DESC");
$stmt->execute([$userId, $monthFilter]);
$myAttendance = $stmt->fetchAll();
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
      <!-- <li class="sidebar-item active">
        <a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> My Attendance</a>
      </li> -->
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
      <h1 class="top-bar-title"><i class="fa-solid fa-calendar-check text-gold"></i> My Attendance History</h1>
    </header>

    <div class="content-body">
      <!-- Filter Bar -->
      <div class="card-box" style="padding: 20px;">
        <form action="attendance.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
          <div style="flex: 1; max-width: 240px;">
            <label class="form-label" style="font-size: 0.85rem;">Select Month</label>
            <input type="month" name="month" class="form-control" value="<?php echo e($monthFilter); ?>" onchange="this.form.submit();">
          </div>
        </form>
      </div>

      <!-- Attendance Table -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-clock text-gold"></i> Monthly Attendance Log</div>
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
                    <td style="font-weight: 700; color: var(--gold-light);"><?php echo date('d-m-Y (D)', strtotime($ma['attendance_date'])); ?></td>
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

</body>
</html>
