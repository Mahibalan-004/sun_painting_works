<?php
require_once __DIR__ . '/../includes/admin_auth.php';

// Search & Filter Query Parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$paymentFilter = trim($_GET['payment'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Where Clauses Build
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(c.customer_id LIKE ? OR cust.customer_no LIKE ? OR cust.customer_name LIKE ? OR c.car_number LIKE ? OR c.car_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($statusFilter)) {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

if (!empty($paymentFilter)) {
    $where[] = "c.payment_status = ?";
    $params[] = $paymentFilter;
}

if (!empty($dateFilter)) {
    $where[] = "DATE(c.created_at) = ?";
    $params[] = $dateFilter;
}

$whereSql = implode(" AND ", $where);

// Count Total Filtered Records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cars c JOIN customers cust ON c.customer_id = cust.customer_id WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch Paginated Cars
$query = "SELECT c.*, cust.customer_name, cust.customer_no 
          FROM cars c 
          JOIN customers cust ON c.customer_id = cust.customer_id 
          WHERE {$whereSql} 
          ORDER BY c.id DESC 
          LIMIT {$limit} OFFSET {$offset}";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$cars = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Car Work List | Sun Painting Works</title>
  
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
    <header class="top-bar">
      <h1 class="top-bar-title"><i class="fa-solid fa-list-check text-gold"></i> Workshop Car Management List</h1>
      <a href="add_car.php" class="btn btn-gold"><i class="fa-solid fa-plus-circle"></i> Add New Car Entry</a>
    </header>

    <div class="content-body">
      
      <!-- Search & Filters Toolbar -->
      <div class="card-box" style="padding: 20px; margin-bottom: 25px;">
        <form action="car_list.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
          <div style="flex: 1.5; min-width: 220px;">
            <label class="form-label" style="font-size: 0.8rem;"><i class="fa-solid fa-search text-gold"></i> Search</label>
            <input type="text" name="search" class="form-control" placeholder="Search ID, Phone, Name, Car No..." value="<?php echo e($search); ?>">
          </div>

          <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.8rem;">Work Status</label>
            <select name="status" class="form-control">
              <option value="">All Statuses</option>
              <?php 
              $allStatuses = ['New', 'Inspection', 'Denting', 'Painting', 'Polishing', 'Extra Work', 'Completed', 'Delivered'];
              foreach ($allStatuses as $st): 
              ?>
                <option value="<?php echo $st; ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.8rem;">Payment Status</label>
            <select name="payment" class="form-control">
              <option value="">All Payments</option>
              <option value="Pending" <?php echo $paymentFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="Partial" <?php echo $paymentFilter === 'Partial' ? 'selected' : ''; ?>>Partial</option>
              <option value="Paid" <?php echo $paymentFilter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
          </div>

          <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.8rem;">Date</label>
            <input type="date" name="date" class="form-control" value="<?php echo e($dateFilter); ?>">
          </div>

          <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-gold" style="padding: 11px 20px;"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="car_list.php" class="btn btn-silver" style="padding: 11px 16px;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
          </div>
        </form>
      </div>

      <!-- Main Car Table -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title">
            <i class="fa-solid fa-car text-gold"></i> Registered Vehicles 
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;">(Total: <?php echo $totalRecords; ?> cars)</span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Customer ID</th>
                <th>Customer No</th>
                <th>Customer Name</th>
                <th>Car Number</th>
                <th>Car Name</th>
                <th>Total Amount</th>
                <th>Final Amount</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($cars)): ?>
                <tr>
                  <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 40px;">No vehicle job records match your search query.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($cars as $c): ?>
                  <tr>
                    <!-- REQUIRED COLUMNS FIRST -->
                    <td style="font-weight: 800; color: var(--gold-primary);"><?php echo e($c['customer_id']); ?></td>
                    <td style="font-weight: 600;"><?php echo e($c['customer_no']); ?></td>
                    <td><?php echo e($c['customer_name']); ?></td>
                    <td style="font-weight: 800;"><?php echo e($c['car_number']); ?></td>
                    <td><?php echo e($c['car_name']); ?> <small style="color: var(--text-muted);">(<?php echo e($c['car_color']); ?>)</small></td>
                    <td style="font-weight: 700; color: var(--gold-light);"><?php echo formatRupee($c['total_amount']); ?></td>
                    <td style="font-weight: 700; color: #2ECC71;"><?php echo formatRupee($c['final_amount']); ?></td>
                    <td style="font-weight: 700; color: #E74C3C;"><?php echo formatRupee($c['balance_amount']); ?></td>
                    <td>
                      <span class="badge badge-status-<?php echo e($c['status']); ?>"><?php echo e($c['status']); ?></span>
                      <br>
                      <span class="badge badge-pay-<?php echo e($c['payment_status']); ?>" style="margin-top: 4px; font-size: 0.65rem;"><?php echo e($c['payment_status']); ?></span>
                    </td>
                    <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('d-m-Y', strtotime($c['created_at'])); ?></td>
                    <td>
                      <div style="display: flex; gap: 6px;">
                        <a href="car_details.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-gold" style="padding: 5px 10px; font-size: 0.8rem;" title="View Complete Details"><i class="fa-solid fa-eye"></i> View</a>
                        <a href="update_car.php?id=<?php echo $c['id']; ?>" class="btn btn-gold" style="padding: 5px 10px; font-size: 0.8rem;" title="Edit & Update Work"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
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
                <a href="car_list.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="btn btn-silver" style="padding: 6px 12px; font-size: 0.85rem;">Previous</a>
              <?php endif; ?>

              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="car_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="btn <?php echo $i === $page ? 'btn-gold' : 'btn-silver'; ?>" style="padding: 6px 12px; font-size: 0.85rem;"><?php echo $i; ?></a>
              <?php endfor; ?>

              <?php if ($page < $totalPages): ?>
                <a href="car_list.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="btn btn-silver" style="padding: 6px 12px; font-size: 0.85rem;">Next</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
