<?php
require_once __DIR__ . '/../includes/user_auth.php';

$userId = $_SESSION['user_id'];
$today = date('Y-m-d');
$error = '';
$success = '';

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
  <script src="../assets/js/theme.js"></script>
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
        <a href="add_car.php"><i class="fa-solid fa-car-tunnel"></i> Add New Car Job</a>
      </li>
      <li class="sidebar-item">
        <a href="work.php"><i class="fa-solid fa-spray-can"></i> Car Work</a>
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
        <h1 class="top-bar-title">Welcome, <span class="text-gold"><?php echo e($_SESSION['user_name']); ?></span></h1>
      </div>

      <div class="user-profile-menu">
        <!-- Theme Switcher Control -->
        <div class="theme-switcher-control">
          <i class="fa-solid fa-circle-half-stroke text-gold"></i>
          <select class="theme-select-input" aria-label="Theme Preference">
            <option value="light">☀️ Light</option>
            <option value="dark">🌙 Dark</option>
            <option value="system">💻 System</option>
          </select>
        </div>

        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?></div>
        <div>
          <div style="font-weight: 700; font-size: 0.95rem;"><?php echo e($_SESSION['user_name']); ?></div>
          <div style="font-size: 0.75rem; color: var(--silver-primary);">Workshop Technician</div>
        </div>
      </div>
    </header>

    <div class="content-body">

      <?php if (!empty($success)): ?>
        <div style="background: rgba(46, 204, 113, 0.12); border: 1px solid #2ECC71; color: #27AE60; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-circle-check"></i> <?php echo e($success); ?>
        </div>
      <?php endif; ?>

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
          <div style="display: flex; gap: 10px;">
            <a href="add_car.php" class="btn btn-gold" style="padding: 6px 14px; font-size: 0.85rem;"><i class="fa-solid fa-plus"></i> Add New Car Job</a>
            <a href="work.php" class="btn btn-outline-gold" style="padding: 6px 14px; font-size: 0.85rem;">Manage All Car Work</a>
          </div>
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

<script src="../assets/js/main.js"></script>
</body>
</html>
