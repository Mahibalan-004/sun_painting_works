<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$error = '';
$success = $_GET['success'] ?? '';

// Handle Quick Payout POST Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'quick_payout') {
        $car_id = (int)($_POST['car_id'] ?? 0);
        $payout_amount = (float)($_POST['payout_amount'] ?? 0);
        $payout_mode = $_POST['payout_mode'] ?? 'add'; // 'add' to given amount or 'set' total given
        $payout_note = trim($_POST['payout_note'] ?? '');

        if ($car_id > 0 && $payout_amount >= 0) {
            $stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ? AND is_mechanic_vehicle = 'Yes'");
            $stmt->execute([$car_id]);
            $car = $stmt->fetch();

            if ($car) {
                $curGiven = (float)$car['mechanic_given_amount'];
                $mechTotal = (float)$car['mechanic_total_amount'];

                if ($payout_mode === 'add') {
                    $newGiven = $curGiven + $payout_amount;
                } else {
                    $newGiven = $payout_amount;
                }

                $newBalance = max(0, $mechTotal - $newGiven);

                $updateStmt = $pdo->prepare("UPDATE cars SET mechanic_given_amount = ?, mechanic_balance_amount = ? WHERE id = ?");
                $updateStmt->execute([$newGiven, $newBalance, $car_id]);

                // Log work history
                $noteText = !empty($payout_note) ? " Note: {$payout_note}" : "";
                $logDesc = "Mechanic payment updated. Paid " . formatRupee($payout_amount) . " (Total given: " . formatRupee($newGiven) . ", Balance: " . formatRupee($newBalance) . "). Workshop: " . ($car['workshop_name'] ?: 'Mechanic') . "." . $noteText;
                logWorkHistory($pdo, $car_id, 'Mechanic Payout', $logDesc, $_SESSION['user_id']);

                header("Location: mechanics.php?success=" . urlencode("Mechanic payout updated successfully for Job #{$car['customer_id']} ({$car['car_number']})!"));
                exit;
            } else {
                $error = "Mechanic vehicle record not found.";
            }
        } else {
            $error = "Please provide a valid payout amount.";
        }
    }
}

// Search & Filter Parameters
$search = trim($_GET['search'] ?? '');
$workshopFilter = trim($_GET['workshop'] ?? '');
$settlementFilter = trim($_GET['settlement'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$activeTab = trim($_GET['tab'] ?? 'vehicles'); // 'vehicles' or 'directory'

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Where Clauses Build for Mechanic Vehicles
$where = ["c.is_mechanic_vehicle = 'Yes'"];
$params = [];

if (!empty($search)) {
    $where[] = "(c.workshop_name LIKE ? OR c.mechanic_name LIKE ? OR c.mechanic_contact LIKE ? OR c.customer_contact LIKE ? OR c.mechanic_location LIKE ? OR c.car_number LIKE ? OR c.car_name LIKE ? OR c.customer_id LIKE ? OR cust.customer_name LIKE ? OR cust.customer_no LIKE ?)";
    $searchTerm = "%{$search}%";
    for ($i = 0; $i < 10; $i++) {
        $params[] = $searchTerm;
    }
}

if (!empty($workshopFilter)) {
    $where[] = "c.workshop_name = ?";
    $params[] = $workshopFilter;
}

if (!empty($statusFilter)) {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

if ($settlementFilter === 'settled') {
    $where[] = "(c.mechanic_total_amount > 0 AND c.mechanic_balance_amount <= 0)";
} elseif ($settlementFilter === 'pending') {
    $where[] = "(c.mechanic_balance_amount > 0)";
} elseif ($settlementFilter === 'unpaid') {
    $where[] = "(c.mechanic_given_amount = 0 AND c.mechanic_total_amount > 0)";
}

$whereSql = implode(" AND ", $where);

// Metrics Summary Queries
$totalMechJobs = (int)$pdo->query("SELECT COUNT(*) FROM cars WHERE is_mechanic_vehicle = 'Yes'")->fetchColumn();
$totalWorkshopsCount = (int)$pdo->query("SELECT COUNT(DISTINCT NULLIF(TRIM(workshop_name), '')) FROM cars WHERE is_mechanic_vehicle = 'Yes'")->fetchColumn();

$metricsRow = $pdo->query("SELECT 
    SUM(mechanic_total_amount) AS tot_billing, 
    SUM(mechanic_given_amount) AS tot_given, 
    SUM(mechanic_balance_amount) AS tot_balance,
    SUM(CASE WHEN mechanic_total_amount > 0 AND mechanic_balance_amount <= 0 THEN 1 ELSE 0 END) AS count_settled,
    SUM(CASE WHEN mechanic_balance_amount > 0 THEN 1 ELSE 0 END) AS count_pending
    FROM cars WHERE is_mechanic_vehicle = 'Yes'")->fetch();

$totalMechBilling = (float)($metricsRow['tot_billing'] ?? 0);
$totalMechGiven = (float)($metricsRow['tot_given'] ?? 0);
$totalMechBalance = (float)($metricsRow['tot_balance'] ?? 0);
$settledJobsCount = (int)($metricsRow['count_settled'] ?? 0);
$pendingJobsCount = (int)($metricsRow['count_pending'] ?? 0);

// Distinct Workshops for Dropdown
$workshopList = $pdo->query("SELECT DISTINCT workshop_name FROM cars WHERE is_mechanic_vehicle = 'Yes' AND workshop_name IS NOT NULL AND TRIM(workshop_name) != '' ORDER BY workshop_name ASC")->fetchAll(PDO::FETCH_COLUMN);

// Count Total Filtered Records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cars c JOIN customers cust ON c.customer_id = cust.customer_id WHERE {$whereSql}");
$countStmt->execute($params);
$totalFilteredRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalFilteredRecords / $limit);

// Fetch Paginated Mechanic Vehicles (Tab 1)
$query = "SELECT c.*, cust.customer_name, cust.customer_no, cust.city AS customer_city
          FROM cars c 
          JOIN customers cust ON c.customer_id = cust.customer_id 
          WHERE {$whereSql} 
          ORDER BY c.id DESC 
          LIMIT {$limit} OFFSET {$offset}";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$mechanicCars = $stmt->fetchAll();

// Fetch Workshop Aggregated Directory (Tab 2)
$workshopDirectory = $pdo->query("SELECT 
    workshop_name,
    MAX(mechanic_name) AS mechanic_name,
    MAX(mechanic_contact) AS mechanic_contact,
    MAX(mechanic_location) AS mechanic_location,
    COUNT(id) AS total_jobs,
    SUM(mechanic_total_amount) AS total_billing,
    SUM(mechanic_given_amount) AS total_given,
    SUM(mechanic_balance_amount) AS total_balance,
    MAX(created_at) AS last_job_date
  FROM cars 
  WHERE is_mechanic_vehicle = 'Yes' AND workshop_name IS NOT NULL AND TRIM(workshop_name) != ''
  GROUP BY workshop_name
  ORDER BY total_balance DESC, total_jobs DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mechanics & Workshops Portal | Sun Painting Works</title>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  
  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  
  <style>
    .tab-nav {
      display: flex;
      gap: 12px;
      margin-bottom: 25px;
      border-bottom: 1px solid var(--border-light);
      padding-bottom: 12px;
    }
    .tab-btn {
      background: transparent;
      border: 1px solid var(--border-light);
      color: var(--text-muted);
      padding: 10px 22px;
      border-radius: var(--radius-sm);
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
      text-decoration: none;
    }
    .tab-btn:hover {
      color: var(--gold-light);
      border-color: var(--border-gold);
      background: rgba(212, 175, 55, 0.05);
    }
    .tab-btn.active {
      background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
      color: #0A0B0E;
      border-color: var(--gold-primary);
      box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
      font-weight: 700;
    }
    
    .badge-settled {
      background: rgba(46, 204, 113, 0.15);
      border: 1px solid #2ECC71;
      color: #2ECC71;
    }
    .badge-partial {
      background: rgba(243, 156, 18, 0.15);
      border: 1px solid #F39C12;
      color: #F39C12;
    }
    .badge-unpaid {
      background: rgba(231, 76, 60, 0.15);
      border: 1px solid #E74C3C;
      color: #FF6B6B;
    }

    /* Quick Pay Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(5px);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-overlay.active {
      display: flex;
    }
    .modal-box {
      background: var(--bg-card);
      border: 1px solid var(--border-gold);
      border-radius: var(--radius-md);
      max-width: 520px;
      width: 100%;
      padding: 30px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
      position: relative;
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border-light);
      padding-bottom: 15px;
      margin-bottom: 20px;
    }
    .modal-title {
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--gold-primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .modal-close {
      background: transparent;
      border: none;
      color: var(--text-muted);
      font-size: 1.3rem;
      cursor: pointer;
      transition: var(--transition);
    }
    .modal-close:hover {
      color: #E74C3C;
    }
  </style>
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
      <li class="sidebar-item active">
        <a href="mechanics.php"><i class="fa-solid fa-screwdriver-wrench text-gold"></i> Mechanics & Workshops</a>
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
    <header class="top-bar">
      <div>
        <h1 class="top-bar-title"><i class="fa-solid fa-screwdriver-wrench text-gold"></i> Mechanics & Workshops Hub</h1>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
          Dedicated management for external partner workshops, mechanics, customer vehicles, and payout ledgers.
        </div>
      </div>
      <div style="display: flex; gap: 10px;">
        <a href="add_car.php" class="btn btn-gold"><i class="fa-solid fa-plus-circle"></i> + Add Mechanic Vehicle</a>
      </div>
    </header>

    <div class="content-body">
      
      <!-- Notifications -->
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

      <!-- METRICS OVERVIEW CARDS -->
      <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 30px;">
        
        <div class="metric-card" style="border-left: 3px solid var(--gold-primary);">
          <div class="metric-info">
            <div class="metric-label">Total Mechanic Vehicles</div>
            <div class="metric-value" style="color: var(--gold-primary);"><?php echo $totalMechJobs; ?> Jobs</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Across <?php echo $totalWorkshopsCount; ?> Partner Workshops</div>
          </div>
          <div class="metric-icon-box metric-icon-gold">
            <i class="fa-solid fa-car-side"></i>
          </div>
        </div>

        <div class="metric-card" style="border-left: 3px solid var(--silver-primary);">
          <div class="metric-info">
            <div class="metric-label">Total Mechanic Billing</div>
            <div class="metric-value" style="color: var(--silver-light);"><?php echo formatRupee($totalMechBilling); ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Agreed Total Value</div>
          </div>
          <div class="metric-icon-box metric-icon-silver">
            <i class="fa-solid fa-calculator"></i>
          </div>
        </div>

        <div class="metric-card" style="border-left: 3px solid #2ECC71;">
          <div class="metric-info">
            <div class="metric-label">Total Given / Paid Amount</div>
            <div class="metric-value" style="color: #2ECC71;"><?php echo formatRupee($totalMechGiven); ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?php echo $settledJobsCount; ?> Fully Settled Jobs</div>
          </div>
          <div class="metric-icon-box metric-icon-green">
            <i class="fa-solid fa-hand-holding-dollar"></i>
          </div>
        </div>

        <div class="metric-card" style="border-left: 3px solid #F39C12;">
          <div class="metric-info">
            <div class="metric-label">Total Balance Payable</div>
            <div class="metric-value" style="color: #F39C12;"><?php echo formatRupee($totalMechBalance); ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?php echo $pendingJobsCount; ?> Jobs with Pending Balance</div>
          </div>
          <div class="metric-icon-box metric-icon-silver">
            <i class="fa-solid fa-scale-balanced"></i>
          </div>
        </div>

      </div>

      <!-- TABS NAVIGATION -->
      <div class="tab-nav">
        <a href="mechanics.php?tab=vehicles<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="tab-btn <?php echo $activeTab === 'vehicles' ? 'active' : ''; ?>">
          <i class="fa-solid fa-list-check"></i> Mechanic Vehicles Job Ledger (<?php echo $totalMechJobs; ?>)
        </a>
        <a href="mechanics.php?tab=directory" class="tab-btn <?php echo $activeTab === 'directory' ? 'active' : ''; ?>">
          <i class="fa-solid fa-building"></i> Workshops Directory & Balances (<?php echo count($workshopDirectory); ?>)
        </a>
      </div>

      <?php if ($activeTab === 'vehicles'): ?>
        <!-- ============================================================ -->
        <!-- TAB 1: MECHANIC VEHICLES JOB LEDGER                          -->
        <!-- ============================================================ -->

        <!-- Filters Toolbar -->
        <div class="card-box" style="padding: 20px; margin-bottom: 25px;">
          <form action="mechanics.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="tab" value="vehicles">
            
            <div style="flex: 2; min-width: 220px;">
              <label class="form-label" style="font-size: 0.8rem;"><i class="fa-solid fa-search text-gold"></i> Search</label>
              <input type="text" name="search" class="form-control" placeholder="Search Workshop, Mechanic, Car No, Phone..." value="<?php echo e($search); ?>">
            </div>

            <div style="flex: 1.5; min-width: 170px;">
              <label class="form-label" style="font-size: 0.8rem;">Filter by Workshop</label>
              <select name="workshop" class="form-control">
                <option value="">All Workshops</option>
                <?php foreach ($workshopList as $wsName): ?>
                  <option value="<?php echo e($wsName); ?>" <?php echo $workshopFilter === $wsName ? 'selected' : ''; ?>><?php echo e($wsName); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
              <label class="form-label" style="font-size: 0.8rem;">Settlement Status</label>
              <select name="settlement" class="form-control">
                <option value="">All Settlements</option>
                <option value="pending" <?php echo $settlementFilter === 'pending' ? 'selected' : ''; ?>>Pending Balance</option>
                <option value="settled" <?php echo $settlementFilter === 'settled' ? 'selected' : ''; ?>>Fully Settled</option>
                <option value="unpaid" <?php echo $settlementFilter === 'unpaid' ? 'selected' : ''; ?>>Unpaid (₹0 Given)</option>
              </select>
            </div>

            <div style="flex: 1; min-width: 140px;">
              <label class="form-label" style="font-size: 0.8rem;">Work Status</label>
              <select name="status" class="form-control">
                <option value="">All Stages</option>
                <?php 
                $allStatuses = ['New', 'Inspection', 'Denting', 'Painting', 'Polishing', 'Extra Work', 'Completed', 'Delivered'];
                foreach ($allStatuses as $st): 
                ?>
                  <option value="<?php echo $st; ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="display: flex; gap: 10px;">
              <button type="submit" class="btn btn-gold" style="padding: 11px 20px;"><i class="fa-solid fa-filter"></i> Filter</button>
              <a href="mechanics.php?tab=vehicles" class="btn btn-silver" style="padding: 11px 16px;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            </div>
          </form>
        </div>

        <!-- Mechanic Vehicles Table -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title">
              <i class="fa-solid fa-screwdriver-wrench text-gold"></i> Mechanic & Workshop Vehicles
              <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;">(Showing <?php echo count($mechanicCars); ?> of <?php echo $totalFilteredRecords; ?> total)</span>
            </div>
          </div>

          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Job ID / Date</th>
                  <th>Workshop & Mechanic</th>
                  <th>Contacts (Mech / Customer)</th>
                  <th>Car Details</th>
                  <th>Work Stage</th>
                  <th>Mechanic Agreed</th>
                  <th>Given Amount</th>
                  <th>Balance Payable</th>
                  <th>Settlement</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($mechanicCars)): ?>
                  <tr>
                    <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px;">
                      <i class="fa-solid fa-car-side" style="font-size: 2.5rem; color: var(--border-gold); margin-bottom: 15px; display: block;"></i>
                      No mechanic vehicles found matching your criteria.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($mechanicCars as $c): 
                    $mechTotal = (float)$c['mechanic_total_amount'];
                    $mechGiven = (float)$c['mechanic_given_amount'];
                    $mechBal   = (float)$c['mechanic_balance_amount'];

                    if ($mechBal <= 0 && $mechTotal > 0) {
                      $settleBadge = '<span class="badge badge-settled"><i class="fa-solid fa-check-double"></i> Settled</span>';
                    } elseif ($mechGiven > 0) {
                      $settleBadge = '<span class="badge badge-partial"><i class="fa-solid fa-clock"></i> Partial</span>';
                    } else {
                      $settleBadge = '<span class="badge badge-unpaid"><i class="fa-solid fa-circle-exclamation"></i> Unpaid</span>';
                    }
                  ?>
                    <tr>
                      <td>
                        <div style="font-weight: 800; color: var(--gold-primary);"><?php echo e($c['customer_id']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('d-m-Y', strtotime($c['created_at'])); ?></div>
                      </td>

                      <td>
                        <div style="font-weight: 800; color: var(--gold-light); font-size: 0.95rem;">
                          <i class="fa-solid fa-building"></i> <?php echo e($c['workshop_name'] ?: 'Independent Workshop'); ?>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--silver-light); margin-top: 2px;">
                          <i class="fa-solid fa-user-gear"></i> <?php echo e($c['mechanic_name'] ?: 'Mechanic'); ?>
                        </div>
                        <?php if (!empty($c['mechanic_location'])): ?>
                          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                            <i class="fa-solid fa-location-dot text-gold"></i> <?php echo e($c['mechanic_location']); ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <td>
                        <?php if (!empty($c['mechanic_contact'])): ?>
                          <div>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Mech:</span> 
                            <a href="tel:<?php echo e($c['mechanic_contact']); ?>" style="color: var(--text-main); font-weight: 600; text-decoration: none;">
                              <i class="fa-solid fa-phone text-gold" style="font-size: 0.75rem;"></i> <?php echo e($c['mechanic_contact']); ?>
                            </a>
                          </div>
                        <?php endif; ?>
                        <?php if (!empty($c['customer_contact'])): ?>
                          <div style="margin-top: 3px;">
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Client:</span> 
                            <a href="tel:<?php echo e($c['customer_contact']); ?>" style="color: var(--silver-light); font-size: 0.85rem; text-decoration: none;">
                              <i class="fa-solid fa-user" style="font-size: 0.75rem;"></i> <?php echo e($c['customer_contact']); ?>
                            </a>
                          </div>
                        <?php endif; ?>
                      </td>

                      <td>
                        <div style="font-weight: 800; font-size: 1rem;"><?php echo e($c['car_number']); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo e($c['car_name']); ?> (<?php echo e($c['car_color']); ?>)</div>
                        <div style="font-size: 0.75rem; color: var(--silver-dark); margin-top: 2px;">
                          Job Total: <?php echo formatRupee($c['total_amount']); ?> | Adv: <?php echo formatRupee($c['advance_amount'] > 0 ? $c['advance_amount'] : $c['final_amount']); ?>
                        </div>
                      </td>

                      <td>
                        <span class="badge badge-status-<?php echo e($c['status']); ?>"><?php echo e($c['status']); ?></span>
                      </td>

                      <td style="font-weight: 700; color: var(--gold-light);"><?php echo formatRupee($c['mechanic_total_amount']); ?></td>
                      <td style="font-weight: 700; color: #2ECC71;"><?php echo formatRupee($c['mechanic_given_amount']); ?></td>
                      <td style="font-weight: 800; color: <?php echo $mechBal > 0 ? '#F39C12' : '#2ECC71'; ?>;">
                        <?php echo formatRupee($c['mechanic_balance_amount']); ?>
                      </td>

                      <td>
                        <?php echo $settleBadge; ?>
                      </td>

                      <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                          <button type="button" class="btn btn-gold" style="padding: 5px 9px; font-size: 0.75rem;" 
                            onclick="openPayoutModal(<?php echo $c['id']; ?>, '<?php echo e(addslashes($c['workshop_name'] ?: ($c['mechanic_name'] ?: 'Mechanic'))); ?>', '<?php echo e($c['car_number']); ?>', <?php echo $c['mechanic_total_amount']; ?>, <?php echo $c['mechanic_given_amount']; ?>, <?php echo $c['mechanic_balance_amount']; ?>)" 
                            title="Quick Record Payout">
                            <i class="fa-solid fa-hand-holding-dollar"></i> Pay
                          </button>
                          <a href="car_details.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-gold" style="padding: 5px 9px; font-size: 0.75rem;" title="View Invoice / Job Card">
                            <i class="fa-solid fa-eye"></i>
                          </a>
                          <a href="update_car.php?id=<?php echo $c['id']; ?>" class="btn btn-silver" style="padding: 5px 9px; font-size: 0.75rem;" title="Edit Car Job">
                            <i class="fa-solid fa-pen-to-square"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination Bar -->
          <?php if ($totalPages > 1): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--border-light);">
              <div style="font-size: 0.85rem; color: var(--text-muted);">
                Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
              </div>
              <div style="display: flex; gap: 8px;">
                <?php if ($page > 1): ?>
                  <a href="mechanics.php?tab=vehicles&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&workshop=<?php echo urlencode($workshopFilter); ?>&settlement=<?php echo urlencode($settlementFilter); ?>" class="btn btn-silver" style="padding: 6px 12px; font-size: 0.85rem;">Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <a href="mechanics.php?tab=vehicles&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&workshop=<?php echo urlencode($workshopFilter); ?>&settlement=<?php echo urlencode($settlementFilter); ?>" class="btn <?php echo $i === $page ? 'btn-gold' : 'btn-silver'; ?>" style="padding: 6px 12px; font-size: 0.85rem;"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                  <a href="mechanics.php?tab=vehicles&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&workshop=<?php echo urlencode($workshopFilter); ?>&settlement=<?php echo urlencode($settlementFilter); ?>" class="btn btn-silver" style="padding: 6px 12px; font-size: 0.85rem;">Next</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <!-- ============================================================ -->
        <!-- TAB 2: WORKSHOPS DIRECTORY & BALANCES                        -->
        <!-- ============================================================ -->

        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title">
              <i class="fa-solid fa-building text-gold"></i> Partner Workshops & Mechanics Directory
              <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;">(Total: <?php echo count($workshopDirectory); ?> Workshops)</span>
            </div>
          </div>

          <div class="table-responsive">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Workshop Name</th>
                  <th>Primary Mechanic</th>
                  <th>Contact Number</th>
                  <th>Location</th>
                  <th>Total Jobs</th>
                  <th>Total Agreed Amount</th>
                  <th>Given / Paid Amount</th>
                  <th>Outstanding Balance</th>
                  <th>Last Job Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($workshopDirectory)): ?>
                  <tr>
                    <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px;">No partner workshops registered yet.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($workshopDirectory as $w): 
                    $wBal = (float)$w['total_balance'];
                  ?>
                    <tr>
                      <td style="font-weight: 800; color: var(--gold-primary); font-size: 1rem;">
                        <i class="fa-solid fa-warehouse text-gold"></i> <?php echo e($w['workshop_name']); ?>
                      </td>
                      <td style="font-weight: 600;"><?php echo e($w['mechanic_name'] ?: 'N/A'); ?></td>
                      <td>
                        <?php if (!empty($w['mechanic_contact'])): ?>
                          <a href="tel:<?php echo e($w['mechanic_contact']); ?>" style="color: var(--text-main); text-decoration: none; font-weight: 600;">
                            <i class="fa-solid fa-phone text-gold" style="font-size: 0.75rem;"></i> <?php echo e($w['mechanic_contact']); ?>
                          </a>
                        <?php else: ?>
                          <span style="color: var(--text-muted);">N/A</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo e($w['mechanic_location'] ?: 'N/A'); ?></td>
                      <td style="font-weight: 800; color: var(--silver-light);"><?php echo $w['total_jobs']; ?> Cars</td>
                      <td style="font-weight: 700; color: var(--gold-light);"><?php echo formatRupee($w['total_billing']); ?></td>
                      <td style="font-weight: 700; color: #2ECC71;"><?php echo formatRupee($w['total_given']); ?></td>
                      <td style="font-weight: 800; font-size: 1.05rem; color: <?php echo $wBal > 0 ? '#F39C12' : '#2ECC71'; ?>;">
                        <?php echo formatRupee($w['total_balance']); ?>
                      </td>
                      <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('d-m-Y', strtotime($w['last_job_date'])); ?></td>
                      <td>
                        <a href="mechanics.php?tab=vehicles&workshop=<?php echo urlencode($w['workshop_name']); ?>" class="btn btn-outline-gold" style="padding: 6px 12px; font-size: 0.8rem;">
                          <i class="fa-solid fa-folder-open"></i> View Jobs
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </main>
</div>

<!-- ============================================================ -->
<!-- QUICK PAYOUT MODAL DIALOG                                    -->
<!-- ============================================================ -->
<div id="quickPayoutModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">
        <i class="fa-solid fa-hand-holding-dollar"></i> Record Mechanic Payout
      </div>
      <button type="button" class="modal-close" onclick="closePayoutModal()">&times;</button>
    </div>

    <form action="mechanics.php" method="POST">
      <input type="hidden" name="action" value="quick_payout">
      <input type="hidden" name="car_id" id="modal_car_id" value="">

      <div style="background: rgba(212,175,55,0.06); border: 1px solid var(--border-gold); padding: 15px; border-radius: var(--radius-sm); margin-bottom: 20px;">
        <div style="font-size: 0.85rem; color: var(--text-muted);">Workshop / Mechanic:</div>
        <div id="modal_workshop_display" style="font-size: 1.1rem; font-weight: 800; color: var(--gold-light);"></div>
        <div id="modal_car_display" style="font-size: 0.85rem; color: var(--silver-light); margin-top: 3px;"></div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; text-align: center; margin-bottom: 20px;">
        <div style="background: var(--bg-card); padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
          <div style="font-size: 0.75rem; color: var(--text-muted);">Total Agreed</div>
          <div id="modal_total_display" style="font-weight: 700; color: var(--gold-light); font-size: 1rem;">₹0.00</div>
        </div>
        <div style="background: var(--bg-card); padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
          <div style="font-size: 0.75rem; color: var(--text-muted);">Already Given</div>
          <div id="modal_given_display" style="font-weight: 700; color: #2ECC71; font-size: 1rem;">₹0.00</div>
        </div>
        <div style="background: var(--bg-card); padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
          <div style="font-size: 0.75rem; color: var(--text-muted);">Current Balance</div>
          <div id="modal_balance_display" style="font-weight: 800; color: #F39C12; font-size: 1rem;">₹0.00</div>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 15px;">
        <label class="form-label" style="font-weight: 700;">Payout Mode</label>
        <div style="display: flex; gap: 15px;">
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.9rem;">
            <input type="radio" name="payout_mode" value="add" checked>
            <span>Add to Given Amount</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.9rem;">
            <input type="radio" name="payout_mode" value="set">
            <span>Set New Total Given</span>
          </label>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 15px;">
        <label class="form-label" style="font-weight: 700;">Amount to Pay / Record (₹) *</label>
        <div style="position: relative;">
          <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #2ECC71; font-weight: 700; font-size: 1.1rem;">₹</span>
          <input type="number" step="0.01" name="payout_amount" id="modal_payout_input" class="form-control" style="padding-left: 35px; font-size: 1.1rem; font-weight: 700;" placeholder="0.00" required>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label class="form-label">Payment Note / Reference (Optional)</label>
        <input type="text" name="payout_note" class="form-control" placeholder="e.g. Cash payment / GPay Ref #1234">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-silver" onclick="closePayoutModal()">Cancel</button>
        <button type="submit" class="btn btn-gold" style="padding: 10px 25px;"><i class="fa-solid fa-check"></i> Submit Payout</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function openPayoutModal(carId, workshopName, carNo, totalAmt, givenAmt, balAmt) {
  document.getElementById('modal_car_id').value = carId;
  document.getElementById('modal_workshop_display').textContent = workshopName;
  document.getElementById('modal_car_display').textContent = 'Car Number: ' + carNo;
  document.getElementById('modal_total_display').textContent = '₹' + parseFloat(totalAmt).toLocaleString('en-IN', { minimumFractionDigits: 2 });
  document.getElementById('modal_given_display').textContent = '₹' + parseFloat(givenAmt).toLocaleString('en-IN', { minimumFractionDigits: 2 });
  document.getElementById('modal_balance_display').textContent = '₹' + parseFloat(balAmt).toLocaleString('en-IN', { minimumFractionDigits: 2 });
  
  // Default payout input to remaining balance if any
  document.getElementById('modal_payout_input').value = balAmt > 0 ? balAmt : '';
  
  document.getElementById('quickPayoutModal').classList.add('active');
}

function closePayoutModal() {
  document.getElementById('quickPayoutModal').classList.remove('active');
}

// Close on escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closePayoutModal();
});
</script>
</body>
</html>
