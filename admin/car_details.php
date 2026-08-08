<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$car_id = (int)($_GET['id'] ?? 0);

if (!$car_id) {
    header("Location: car_list.php");
    exit;
}

// Fetch Car & Customer Details
$stmt = $pdo->prepare("SELECT c.*, cust.customer_name, cust.customer_no FROM cars c JOIN customers cust ON c.customer_id = cust.customer_id WHERE c.id = ?");
$stmt->execute([$car_id]);
$car = $stmt->fetch();

if (!$car) {
    header("Location: car_list.php");
    exit;
}

// Fetch Damage Photos
$stmtDamage = $pdo->prepare("SELECT * FROM car_photos WHERE car_id = ? AND photo_type = 'damage'");
$stmtDamage->execute([$car_id]);
$damagePhotos = $stmtDamage->fetchAll();

// Fetch After Paint Photos
$stmtAfter = $pdo->prepare("SELECT * FROM car_photos WHERE car_id = ? AND photo_type = 'after_paint'");
$stmtAfter->execute([$car_id]);
$afterPhotos = $stmtAfter->fetchAll();

// Fetch Extra Work Items
$stmtExtra = $pdo->prepare("SELECT * FROM extra_work WHERE car_id = ? ORDER BY id ASC");
$stmtExtra->execute([$car_id]);
$extraWorks = $stmtExtra->fetchAll();

// Fetch Work History
$stmtHistory = $pdo->prepare("SELECT wh.*, u.name AS updater_name FROM work_history wh LEFT JOIN users u ON wh.updated_by = u.id WHERE wh.car_id = ? ORDER BY wh.id ASC");
$stmtHistory->execute([$car_id]);
$workHistory = $stmtHistory->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Sheet & Invoice #<?php echo e($car['customer_id']); ?> | Sun Painting Works</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  
  <style>
    @media print {
      .sidebar, .top-bar, .no-print { display: none !important; }
      .main-content { margin-left: 0 !important; }
      body { background: #FFF !important; color: #000 !important; }
      .card-box { border: 1px solid #CCC !important; background: #FFF !important; color: #000 !important; }
      .text-gold, .form-label, h1, h2, h3, h4, th, td { color: #000 !important; }
    }
  </style>
</head>
<body>

<div class="app-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar no-print">
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
      <li class="sidebar-item active">
        <a href="car_list.php"><i class="fa-solid fa-list-check"></i> Car List</a>
      </li>
      
      <li class="sidebar-menu-category">Management</li>
      <li class="sidebar-item">
        <a href="users.php"><i class="fa-solid fa-users-gear"></i> Users & Staff</a>
      </li>
      <li class="sidebar-item">
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
    <header class="top-bar no-print">
      <h1 class="top-bar-title"><i class="fa-solid fa-file-invoice text-gold"></i> Car Job Sheet & Invoice Details</h1>
      <div style="display: flex; gap: 10px;">
        <button onclick="window.print();" class="btn btn-gold" style="padding: 8px 16px; font-size: 0.9rem;"><i class="fa-solid fa-print"></i> Print Invoice / Job Card</button>
        <a href="update_car.php?id=<?php echo $car_id; ?>" class="btn btn-outline-gold" style="padding: 8px 16px; font-size: 0.9rem;"><i class="fa-solid fa-pen"></i> Edit Job</a>
        <a href="car_list.php" class="btn btn-silver" style="padding: 8px 16px; font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Car List</a>
      </div>
    </header>

    <div class="content-body">

      <!-- Official Header for Print & Display -->
      <div class="card-box" style="border-color: var(--border-gold);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-gold); padding-bottom: 20px; margin-bottom: 25px; flex-wrap: wrap; gap: 20px;">
          <div style="display: flex; align-items: center; gap: 15px;">
            <img src="../assets/images/logo.png" alt="Logo" style="height: 60px;">
            <div>
              <h2 style="color: var(--gold-primary); font-size: 1.8rem; margin: 0;">SUN PAINTING WORKS</h2>
              <p style="color: var(--silver-light); font-size: 0.85rem; margin: 0;">Kullampalayam Pirivu, Gobichettipalayam, Erode – 638453 | Phone: 94423 99079, 98422 99079</p>
            </div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 1.4rem; font-weight: 800; color: var(--gold-light);">JOB CARD / INVOICE</div>
            <div style="font-size: 0.9rem; color: var(--text-muted);">Customer ID: <strong><?php echo e($car['customer_id']); ?></strong></div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Date: <?php echo date('d-m-Y h:i A', strtotime($car['created_at'])); ?></div>
          </div>
        </div>

        <!-- Customer & Car Two-Column Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
          <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
            <h4 style="color: var(--gold-primary); margin-bottom: 12px; border-bottom: 1px solid var(--border-light); padding-bottom: 6px;"><i class="fa-solid fa-user"></i> Customer Information</h4>
            <table style="width: 100%; font-size: 0.95rem; line-height: 1.8;">
              <tr><td style="color: var(--text-muted); width: 40%;">Customer ID:</td><td style="font-weight: 700; color: var(--gold-light);"><?php echo e($car['customer_id']); ?></td></tr>
              <tr><td style="color: var(--text-muted);">Customer Name:</td><td style="font-weight: 700;"><?php echo e($car['customer_name']); ?></td></tr>
              <tr><td style="color: var(--text-muted);">Phone Number:</td><td style="font-weight: 700;"><?php echo e($car['customer_no']); ?></td></tr>
              <?php if (!empty($car['alternate_phone'])): ?>
                <tr><td style="color: var(--text-muted);">Alt Phone:</td><td><?php echo e($car['alternate_phone']); ?></td></tr>
              <?php endif; ?>
              <?php if (!empty($car['city'])): ?>
                <tr><td style="color: var(--text-muted);">City:</td><td><?php echo e($car['city']); ?></td></tr>
              <?php endif; ?>
            </table>
          </div>

          <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
            <h4 style="color: var(--gold-primary); margin-bottom: 12px; border-bottom: 1px solid var(--border-light); padding-bottom: 6px;"><i class="fa-solid fa-car"></i> Vehicle Specifications</h4>
            <table style="width: 100%; font-size: 0.95rem; line-height: 1.8;">
              <tr><td style="color: var(--text-muted); width: 40%;">Car Number:</td><td style="font-weight: 800; font-size: 1.1rem;"><?php echo e($car['car_number']); ?></td></tr>
              <tr><td style="color: var(--text-muted);">Vehicle Model:</td><td style="font-weight: 700;"><?php echo e($car['car_name']); ?></td></tr>
              <tr><td style="color: var(--text-muted);">Color:</td><td><?php echo e($car['car_color']); ?></td></tr>
              <tr><td style="color: var(--text-muted);">Current Status:</td><td><span class="badge badge-status-<?php echo e($car['status']); ?>"><?php echo e($car['status']); ?></span></td></tr>
            </table>
          </div>
        </div>

        <!-- Extra Work & Financial Table -->
        <h4 style="color: var(--gold-primary); margin-bottom: 15px;"><i class="fa-solid fa-file-invoice-dollar"></i> Work Charges & Financial Summary</h4>
        <div class="table-responsive" style="margin-bottom: 25px;">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount (₹)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Initial Work Estimate (Painting / Body Denting)</td>
                <td style="text-align: right; font-weight: 700;"><?php echo formatRupee($car['estimate_amount']); ?></td>
              </tr>
              <?php foreach ($extraWorks as $ew): ?>
                <tr>
                  <td>Extra Work: <?php echo e($ew['description']); ?></td>
                  <td style="text-align: right; font-weight: 700; color: #E67E22;"><?php echo formatRupee($ew['amount']); ?></td>
                </tr>
              <?php endforeach; ?>
              <tr style="background: rgba(212, 175, 55, 0.08); font-size: 1.1rem;">
                <td style="font-weight: 800; color: var(--gold-primary);">TOTAL AMOUNT</td>
                <td style="text-align: right; font-weight: 800; color: var(--gold-primary);"><?php echo formatRupee($car['total_amount']); ?></td>
              </tr>
              <tr>
                <td style="font-weight: 700; color: #2ECC71;">Final Amount Received / Paid</td>
                <td style="text-align: right; font-weight: 800; color: #2ECC71;"><?php echo formatRupee($car['final_amount']); ?></td>
              </tr>
              <tr style="font-size: 1.1rem;">
                <td style="font-weight: 800; color: #E74C3C;">BALANCE DUE AMOUNT</td>
                <td style="text-align: right; font-weight: 800; color: #E74C3C;"><?php echo formatRupee($car['balance_amount']); ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
          <div>
            <strong>Payment Status:</strong> 
            <span class="badge badge-pay-<?php echo e($car['payment_status']); ?>"><?php echo e($car['payment_status']); ?></span>
          </div>
          <div style="font-size: 0.85rem; color: var(--text-muted);">
            Last Updated: <?php echo date('d-m-Y h:i A', strtotime($car['updated_at'])); ?>
          </div>
        </div>

        <!-- Photos Section -->
        <?php if (!empty($damagePhotos) || !empty($afterPhotos)): ?>
          <div style="border-top: 1px solid var(--border-light); padding-top: 25px; margin-top: 25px;">
            <h4 style="color: var(--gold-primary); margin-bottom: 15px;"><i class="fa-solid fa-images"></i> Vehicle Inspection & Completion Photos</h4>
            
            <?php if (!empty($damagePhotos)): ?>
              <h5 style="color: var(--silver-light); margin: 15px 0 10px;">Initial Damage Photos:</h5>
              <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <?php foreach ($damagePhotos as $dp): ?>
                  <img src="../<?php echo e($dp['photo_path']); ?>" alt="Damage" style="width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($afterPhotos)): ?>
              <h5 style="color: var(--gold-light); margin: 15px 0 10px;">After-Paint Finished Photos:</h5>
              <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px;">
                <?php foreach ($afterPhotos as $ap): ?>
                  <img src="../<?php echo e($ap['photo_path']); ?>" alt="After Paint" style="width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-gold);">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Work History Log -->
        <div style="border-top: 1px solid var(--border-light); padding-top: 25px; margin-top: 25px;">
          <h4 style="color: var(--gold-primary); margin-bottom: 15px;"><i class="fa-solid fa-clock-rotate-left"></i> Work Progress Audit Timeline</h4>
          <div class="timeline">
            <?php foreach ($workHistory as $wh): ?>
              <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                  <div class="timeline-date"><?php echo date('d-m-Y h:i A', strtotime($wh['created_at'])); ?></div>
                  <div class="timeline-title">Stage: <span class="badge badge-status-<?php echo e($wh['status']); ?>"><?php echo e($wh['status']); ?></span></div>
                  <div style="color: var(--text-muted); font-size: 0.9rem; margin-top: 2px;"><?php echo e($wh['description']); ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

</body>
</html>
