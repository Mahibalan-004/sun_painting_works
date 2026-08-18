<?php
require_once __DIR__ . '/../includes/admin_auth.php';

// Calculate Operational Metrics
$totalCars = $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

// Status Breakdown Data for Operational Cards
$statusCounts = [];
$statuses = ['New', 'Inspection', 'Denting', 'Painting', 'Polishing', 'Extra Work', 'Completed', 'Delivered'];
foreach ($statuses as $st) {
    $stStmt = $pdo->prepare("SELECT COUNT(*) FROM cars WHERE status = ?");
    $stStmt->execute([$st]);
    $statusCounts[$st] = (int)$stStmt->fetchColumn();
}

// Financial Totals (FOR BOTTOM SECTION ONLY)
$totalsRow = $pdo->query("SELECT SUM(estimate_amount) AS est, SUM(total_amount) AS tot, SUM(final_amount) AS fin, SUM(balance_amount) AS bal FROM cars")->fetch();
$totalEstimate = (float)($totalsRow['est'] ?? 0);
$totalAmount = (float)($totalsRow['tot'] ?? 0);
$finalReceived = (float)($totalsRow['fin'] ?? 0);
$pendingBalance = (float)($totalsRow['bal'] ?? 0);

$totalExtraWorkAmount = (float)$pdo->query("SELECT SUM(amount) FROM extra_work")->fetchColumn();

// Today's Attendance Summary
$today = date('Y-m-d');
$todayAtt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'Present'");
$todayAtt->execute([$today]);
$presentToday = $todayAtt->fetchColumn();
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Active'")->fetchColumn();

// Recent Car Jobs
$recentCars = $pdo->query("SELECT c.*, cust.customer_name, cust.customer_no FROM cars c JOIN customers cust ON c.customer_id = cust.customer_id ORDER BY c.id DESC LIMIT 5")->fetchAll();

// Mechanic Vehicle Metrics
$totalMechanicVehicles = (int)$pdo->query("SELECT COUNT(*) FROM cars WHERE is_mechanic_vehicle = 'Yes'")->fetchColumn();
$mechanicTotalsRow = $pdo->query("SELECT SUM(mechanic_total_amount) AS mech_tot, SUM(mechanic_given_amount) AS mech_giv, SUM(mechanic_balance_amount) AS mech_bal FROM cars WHERE is_mechanic_vehicle = 'Yes'")->fetch();
$totalMechanicAmount = (float)($mechanicTotalsRow['mech_tot'] ?? 0);
$totalMechanicGiven = (float)($mechanicTotalsRow['mech_giv'] ?? 0);
$totalMechanicBalance = (float)($mechanicTotalsRow['mech_bal'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Sun Painting Works</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  
  <!-- Chart.js for Status Breakdown -->
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
      <li class="sidebar-item active">
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
    <!-- Top Bar -->
    <header class="top-bar">
      <h1 class="top-bar-title"><i class="fa-solid fa-chart-pie text-gold"></i> Operational Workshop Dashboard</h1>
      <div class="user-profile-menu">
        <div class="user-avatar">AD</div>
        <div>
          <div style="font-weight: 700; font-size: 0.95rem;"><?php echo e($_SESSION['user_name']); ?></div>
          <div style="font-size: 0.75rem; color: var(--gold-primary);"><i class="fa-solid fa-shield"></i> Administrator</div>
        </div>
      </div>
    </header>

    <div class="content-body">

      <!-- TOP SECTION: OPERATIONAL CARDS ONLY -->
      <div style="margin-bottom: 15px; font-size: 1.1rem; font-weight: 700; color: var(--gold-primary); display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-list-check"></i> Operational Status Cards
      </div>

      <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Total Cars</div>
            <div class="metric-value"><?php echo $totalCars; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-gold">
            <i class="fa-solid fa-car"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">New Cars</div>
            <div class="metric-value"><?php echo $statusCounts['New']; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-silver">
            <i class="fa-solid fa-sparkles"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Inspection</div>
            <div class="metric-value"><?php echo $statusCounts['Inspection']; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-silver">
            <i class="fa-solid fa-magnifying-glass"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Denting</div>
            <div class="metric-value"><?php echo $statusCounts['Denting']; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-silver">
            <i class="fa-solid fa-hammer"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Painting</div>
            <div class="metric-value"><?php echo $statusCounts['Painting']; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-gold">
            <i class="fa-solid fa-spray-can"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Polishing</div>
            <div class="metric-value"><?php echo $statusCounts['Polishing']; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-silver">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Extra Work</div>
            <div class="metric-value"><?php echo $statusCounts['Extra Work']; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-silver">
            <i class="fa-solid fa-wrench"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Completed</div>
            <div class="metric-value"><?php echo $statusCounts['Completed']; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-green">
            <i class="fa-solid fa-circle-check"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Delivered</div>
            <div class="metric-value"><?php echo $statusCounts['Delivered']; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-gold">
            <i class="fa-solid fa-truck-ramp-box"></i>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-info">
            <div class="metric-label">Today's Staff Attendance</div>
            <div class="metric-value"><?php echo $presentToday; ?> / <?php echo $totalEmployees; ?></div>
          </div>
          <div class="metric-icon-box metric-icon-gold">
            <i class="fa-solid fa-user-check"></i>
          </div>
        </div>
      </div>

      <!-- Charts & Quick Overview Row -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 35px;">
        <!-- Status Breakdown Chart -->
        <div class="card-box" style="margin-bottom: 0;">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-chart-pie text-gold"></i> Cars by Work Stage</div>
          </div>
          <div style="max-height: 280px; position: relative;">
            <canvas id="statusChart"></canvas>
          </div>
        </div>

        <!-- Workshop Quick Actions -->
        <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-bolt text-gold"></i> Quick Actions</div>
          </div>
          <div style="display: flex; flex-direction: column; gap: 15px;">
            <a href="add_car.php" class="btn btn-gold" style="padding: 16px; justify-content: flex-start;">
              <i class="fa-solid fa-plus-circle" style="font-size: 1.3rem;"></i> 
              <div>
                <strong>Add New Car Job</strong>
                <div style="font-size: 0.75rem; font-weight: normal; opacity: 0.8;">Register customer, vehicle details & damage photos</div>
              </div>
            </a>
            <a href="car_list.php" class="btn btn-outline-gold" style="padding: 16px; justify-content: flex-start;">
              <i class="fa-solid fa-list-check" style="font-size: 1.3rem;"></i>
              <div>
                <strong>View All Workshop Cars</strong>
                <div style="font-size: 0.75rem; font-weight: normal; opacity: 0.8;">Track progress, payments & update status</div>
              </div>
            </a>
            <a href="users.php" class="btn btn-silver" style="padding: 16px; justify-content: flex-start;">
              <i class="fa-solid fa-users-gear" style="font-size: 1.3rem;"></i>
              <div>
                <strong>Manage Staff & Employees</strong>
                <div style="font-size: 0.75rem; font-weight: normal; opacity: 0.8;">Add employees, assign roles & monitor attendance</div>
              </div>
            </a>
          </div>
        </div>
      </div>

      <!-- Recent Cars Table -->
      <div class="card-box" style="margin-bottom: 40px;">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-clock-rotate-left text-gold"></i> Recent Vehicles Registered</div>
          <a href="car_list.php" class="btn btn-outline-gold" style="padding: 6px 14px; font-size: 0.85rem;">View All Cars</a>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Customer ID</th>
                <th>Customer Name</th>
                <th>Car Number</th>
                <th>Car Name</th>
                <th>Total Amount</th>
                <th>Final Amount</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentCars)): ?>
                <tr>
                  <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">No car records found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentCars as $car): ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--gold-primary);"><?php echo e($car['customer_id']); ?></td>
                    <td><?php echo e($car['customer_name']); ?><br><small style="color: var(--text-muted);"><?php echo e($car['customer_no']); ?></small></td>
                    <td style="font-weight: 700;"><?php echo e($car['car_number']); ?></td>
                    <td><?php echo e($car['car_name']); ?> (<?php echo e($car['car_color']); ?>)</td>
                    <td style="font-weight: 700; color: var(--gold-light);"><?php echo formatRupee($car['total_amount']); ?></td>
                    <td style="color: #2ECC71; font-weight: 700;"><?php echo formatRupee($car['final_amount']); ?></td>
                    <td style="color: #E74C3C; font-weight: 700;"><?php echo formatRupee($car['balance_amount']); ?></td>
                    <td><span class="badge badge-status-<?php echo e($car['status']); ?>"><?php echo e($car['status']); ?></span></td>
                    <td>
                      <a href="update_car.php?id=<?php echo $car['id']; ?>" class="btn btn-gold" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pen"></i> Update</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- BOTTOM SECTION: SEPARATE FINANCIAL SUMMARY SECTION -->
      <div class="card-box" style="border: 2px solid var(--border-gold); background: rgba(212, 175, 55, 0.04); margin-top: 20px;">
        <div class="card-box-header" style="border-bottom: 1px solid var(--border-gold); padding-bottom: 15px; margin-bottom: 20px;">
          <div class="card-box-title" style="font-size: 1.4rem; color: var(--gold-primary);">
            <i class="fa-solid fa-coins text-gold"></i> FINANCIAL SUMMARY
          </div>
          <span style="font-size: 0.85rem; color: var(--silver-light);">Total Workshop Billing & Revenue Overview</span>
        </div>

        <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 0;">
          <div class="metric-card" style="background: var(--bg-card);">
            <div class="metric-info">
              <div class="metric-label">Total Estimate Amount</div>
              <div class="metric-value" style="color: var(--silver-light);"><?php echo formatRupee($totalEstimate); ?></div>
            </div>
            <div class="metric-icon-box metric-icon-silver">
              <i class="fa-solid fa-calculator"></i>
            </div>
          </div>

          <div class="metric-card" style="background: var(--bg-card);">
            <div class="metric-info">
              <div class="metric-label">Total Extra Work Amount</div>
              <div class="metric-value" style="color: #E67E22;"><?php echo formatRupee($totalExtraWorkAmount); ?></div>
            </div>
            <div class="metric-icon-box metric-icon-silver">
              <i class="fa-solid fa-wrench"></i>
            </div>
          </div>

          <div class="metric-card" style="background: var(--bg-card);">
            <div class="metric-info">
              <div class="metric-label">Total Amount</div>
              <div class="metric-value" style="color: var(--gold-primary);"><?php echo formatRupee($totalAmount); ?></div>
            </div>
            <div class="metric-icon-box metric-icon-gold">
              <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
          </div>

          <div class="metric-card" style="background: var(--bg-card);">
            <div class="metric-info">
              <div class="metric-label">Total Amount Received</div>
              <div class="metric-value" style="color: #2ECC71;"><?php echo formatRupee($finalReceived); ?></div>
            </div>
            <div class="metric-icon-box metric-icon-green">
              <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
          </div>

          <div class="metric-card" style="background: var(--bg-card);">
            <div class="metric-info">
              <div class="metric-label">Total Outstanding Balance</div>
              <div class="metric-value" style="color: #E74C3C;"><?php echo formatRupee($pendingBalance); ?></div>
            </div>
            <div class="metric-icon-box metric-icon-red">
              <i class="fa-solid fa-scale-unbalanced"></i>
            </div>
          </div>

          <div class="metric-card" style="background: var(--bg-card); border-left: 3px solid var(--gold-primary);">
            <div class="metric-info">
              <div class="metric-label">Mechanic Vehicles Count</div>
              <div class="metric-value" style="color: var(--gold-light);"><?php echo $totalMechanicVehicles; ?> Jobs</div>
            </div>
            <div class="metric-icon-box metric-icon-gold">
              <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
          </div>

          <div class="metric-card" style="background: var(--bg-card); border-left: 3px solid #2ECC71;">
            <div class="metric-info">
              <div class="metric-label">Total Given to Mechanics</div>
              <div class="metric-value" style="color: #2ECC71;"><?php echo formatRupee($totalMechanicGiven); ?></div>
            </div>
            <div class="metric-icon-box metric-icon-green">
              <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
          </div>

          <div class="metric-card" style="background: var(--bg-card); border-left: 3px solid #F39C12;">
            <div class="metric-info">
              <div class="metric-label">Mechanic Balance Payable</div>
              <div class="metric-value" style="color: #F39C12;"><?php echo formatRupee($totalMechanicBalance); ?></div>
            </div>
            <div class="metric-icon-box metric-icon-silver">
              <i class="fa-solid fa-scale-balanced"></i>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('statusChart').getContext('2d');
  const statusData = <?php echo json_encode(array_values($statusCounts)); ?>;
  const statusLabels = <?php echo json_encode(array_keys($statusCounts)); ?>;

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: statusLabels,
      datasets: [{
        data: statusData,
        backgroundColor: [
          '#3498DB', '#9B59B6', '#E67E22', '#F1C40F', 
          '#1ABC9C', '#E74C3C', '#2ECC71', '#D4AF37'
        ],
        borderWidth: 2,
        borderColor: '#181B24'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          labels: { color: '#F5F6F8', font: { family: 'Outfit' } }
        }
      }
    }
  });
});
</script>
</body>
</html>
