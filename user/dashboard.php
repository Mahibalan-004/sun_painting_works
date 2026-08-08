<?php
require_once __DIR__ . '/../includes/user_auth.php';

$userId = $_SESSION['user_id'];
$today = date('Y-m-d');
$error = '';
$success = '';

// Handle Attendance Punch In / Punch Out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nowTime = date('H:i:s');

    if ($action === 'punch_in') {
        $ins = $pdo->prepare("INSERT INTO attendance (user_id, attendance_date, login_time, status, remarks) 
                              VALUES (?, ?, ?, 'Present', 'Self Punch-In') 
                              ON DUPLICATE KEY UPDATE login_time = IFNULL(login_time, VALUES(login_time)), status = 'Present'");
        $ins->execute([$userId, $today, $nowTime]);
        $success = "Punched IN successfully at " . date('h:i A');
    }

    if ($action === 'punch_out') {
        $upd = $pdo->prepare("UPDATE attendance SET logout_time = ? WHERE user_id = ? AND attendance_date = ?");
        $upd->execute([$nowTime, $userId, $today]);
        $success = "Punched OUT successfully at " . date('h:i A');
    }
}

// Fetch Today's Attendance for Current User
$attTodayStmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?");
$attTodayStmt->execute([$userId, $today]);
$myTodayAtt = $attTodayStmt->fetch();

// Fetch Active Car Works
$activeCars = $pdo->query("SELECT c.*, cust.customer_name FROM cars c JOIN customers cust ON c.customer_id = cust.customer_id WHERE c.status NOT IN ('Completed', 'Delivered') ORDER BY c.id DESC LIMIT 6")->fetchAll();

// Fetch Completed Car Works
$completedCarsCount = $pdo->query("SELECT COUNT(*) FROM cars WHERE status IN ('Completed', 'Delivered')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Portal | Sun Painting Works</title>
  
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
      <li class="sidebar-item active">
        <a href="dashboard.php"><i class="fa-solid fa-house"></i> My Dashboard</a>
      </li>
      <li class="sidebar-item">
        <a href="work.php"><i class="fa-solid fa-spray-can"></i> Car Work</a>
      </li>
      <li class="sidebar-item">
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
      <h1 class="top-bar-title">Welcome, <span class="text-gold"><?php echo e($_SESSION['user_name']); ?></span></h1>
      <div class="user-profile-menu">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?></div>
        <div>
          <div style="font-weight: 700; font-size: 0.95rem;"><?php echo e($_SESSION['user_name']); ?></div>
          <div style="font-size: 0.75rem; color: var(--silver-light);">Workshop Technician</div>
        </div>
      </div>
    </header>

    <div class="content-body">

      <?php if (!empty($success)): ?>
        <div style="background: rgba(46, 204, 113, 0.15); border: 1px solid #2ECC71; color: #2ECC71; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-circle-check"></i> <?php echo e($success); ?>
        </div>
      <?php endif; ?>

      <!-- Attendance Punch Card -->
      <div class="card-box" style="background: radial-gradient(circle at top right, rgba(212, 175, 55, 0.1), var(--bg-card)); border-color: var(--border-gold);">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-clock text-gold"></i> Today's Attendance Clock</div>
          <div style="font-size: 0.9rem; color: var(--gold-light); font-weight: 700;"><?php echo date('d F Y (l)'); ?></div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
          <div>
            <div style="font-size: 0.9rem; color: var(--text-muted);">Current Status:</div>
            <?php if ($myTodayAtt): ?>
              <span class="badge badge-att-<?php echo e($myTodayAtt['status']); ?>" style="font-size: 1.1rem; padding: 8px 16px; margin-top: 5px;">
                <i class="fa-solid fa-circle-check"></i> <?php echo e($myTodayAtt['status']); ?>
              </span>
              <div style="font-size: 0.85rem; color: var(--silver-light); margin-top: 8px;">
                Punched In: <strong><?php echo date('h:i A', strtotime($myTodayAtt['login_time'])); ?></strong>
                <?php if ($myTodayAtt['logout_time']): ?>
                  | Punched Out: <strong><?php echo date('h:i A', strtotime($myTodayAtt['logout_time'])); ?></strong>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span class="badge badge-att-Absent" style="font-size: 1.1rem; padding: 8px 16px; margin-top: 5px;">Not Punched In Yet</span>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 15px;">
            <form action="dashboard.php" method="POST">
              <input type="hidden" name="action" value="punch_in">
              <button type="submit" class="btn btn-gold" <?php echo ($myTodayAtt && $myTodayAtt['login_time']) ? 'disabled style="opacity:0.5;"' : ''; ?>>
                <i class="fa-solid fa-right-to-bracket"></i> PUNCH IN
              </button>
            </form>

            <form action="dashboard.php" method="POST">
              <input type="hidden" name="action" value="punch_out">
              <button type="submit" class="btn btn-silver" <?php echo (!$myTodayAtt || $myTodayAtt['logout_time']) ? 'disabled style="opacity:0.5;"' : ''; ?>>
                <i class="fa-solid fa-right-from-bracket"></i> PUNCH OUT
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Quick Work Metrics -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Active Workshop Cars</div>
            <div class="metric-value"><?php echo count($activeCars); ?></div>
          </div>
          <div class="metric-icon-box metric-icon-gold">
            <i class="fa-solid fa-car"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Completed Jobs</div>
            <div class="metric-value"><?php echo $completedCarsCount; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-green">
            <i class="fa-solid fa-circle-check"></i>
          </div>
        </div>
      </div>

      <!-- Active Cars Table -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-spray-can text-gold"></i> Current Car Work Pipeline</div>
          <a href="work.php" class="btn btn-outline-gold" style="padding: 6px 12px; font-size: 0.85rem;">Manage All Car Work</a>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Customer ID</th>
                <th>Car Number</th>
                <th>Car Name</th>
                <th>Current Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($activeCars)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No active car work entries.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($activeCars as $ac): ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--gold-primary);"><?php echo e($ac['customer_id']); ?></td>
                    <td style="font-weight: 700;"><?php echo e($ac['car_number']); ?></td>
                    <td><?php echo e($ac['car_name']); ?> (<?php echo e($ac['car_color']); ?>)</td>
                    <td><span class="badge badge-status-<?php echo e($ac['status']); ?>"><?php echo e($ac['status']); ?></span></td>
                    <td>
                      <a href="work.php?car_id=<?php echo $ac['id']; ?>" class="btn btn-gold" style="padding: 5px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pen-to-square"></i> Update Progress</a>
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

</body>
</html>
