<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$error = '';
$success = '';

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
        $success = "Attendance record updated for " . $att_date;
    }
}

// Search & Filter Parameters
$empFilter = (int)($_GET['employee_id'] ?? 0);
$dateFilter = trim($_GET['date'] ?? '');
$monthFilter = trim($_GET['month'] ?? '');
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

if (!empty($monthFilter)) {
    $where[] = "DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
    $params[] = $monthFilter;
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

// Fetch Active Users for Filter Dropdown
$allUsers = $pdo->query("SELECT id, name, employee_id FROM users WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Attendance | Sun Painting Works</title>
  
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
      <h1 class="top-bar-title"><i class="fa-solid fa-clipboard-user text-gold"></i> Workshop Attendance Management</h1>
    </header>

    <div class="content-body">
      <?php if (!empty($success)): ?>
        <div style="background: rgba(46, 204, 113, 0.15); border: 1px solid #2ECC71; color: #2ECC71; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-circle-check"></i> <?php echo e($success); ?>
        </div>
      <?php endif; ?>

      <!-- Quick Manual Attendance Entry Modal/Card -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-user-pen text-gold"></i> Record / Override Attendance</div>
        </div>

        <form action="attendance.php" method="POST">
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

          <div style="text-align: right;">
            <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> SAVE ATTENDANCE</button>
          </div>
        </form>
      </div>

      <!-- Filters Form -->
      <div class="card-box" style="padding: 20px;">
        <form action="attendance.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
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
            <input type="month" name="month" class="form-control" value="<?php echo e($monthFilter); ?>">
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

          <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-gold" style="padding: 11px 20px;"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="attendance.php" class="btn btn-silver" style="padding: 11px 16px;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
          </div>
        </form>
      </div>

      <!-- Attendance Table -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-clipboard-check text-gold"></i> Attendance History Log</div>
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
                    <td style="font-weight: 700; color: var(--gold-light);"><?php echo date('d-m-Y (D)', strtotime($att['attendance_date'])); ?></td>
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

    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
