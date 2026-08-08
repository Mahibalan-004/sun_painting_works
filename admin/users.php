<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$error = '';
$success = '';

// Handle POST actions (Add, Edit, Toggle Status, Change Password, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $employee_id = trim($_POST['employee_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'User';
        $joining_date = $_POST['joining_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'Active';

        if (empty($employee_id) || empty($name) || empty($phone) || empty($username) || empty($password)) {
            $error = "Please fill in all required user fields.";
        } elseif ($password !== $confirm_password) {
            $error = "Password and confirm password do not match.";
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR employee_id = ?");
            $chk->execute([$username, $employee_id]);
            if ($chk->fetch()) {
                $error = "Username or Employee ID already exists in the system.";
            } else {
                $hashedPass = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO users (employee_id, name, phone, username, password, role, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$employee_id, $name, $phone, $username, $hashedPass, $role, $joining_date, $status]);
                $success = "User '{$name}' created successfully!";
            }
        }
    }

    if ($action === 'toggle_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $curStatus = $_POST['current_status'] ?? 'Active';
        $newStatus = $curStatus === 'Active' ? 'Inactive' : 'Active';
        $upd = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $upd->execute([$newStatus, $user_id]);
        $success = "User status set to {$newStatus}.";
    }

    if ($action === 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            $error = "Invalid user ID.";
        } elseif ($user_id === (int)$_SESSION['user_id']) {
            $error = "You cannot delete your own admin account while logged in.";
        } else {
            $usrStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
            $usrStmt->execute([$user_id]);
            $targetUser = $usrStmt->fetch();

            if (!$targetUser) {
                $error = "Target staff user not found.";
            } else {
                // Check related records safely
                $attCnt = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id = {$user_id}")->fetchColumn();
                $carsCnt = (int)$pdo->query("SELECT COUNT(*) FROM cars WHERE created_by = {$user_id}")->fetchColumn();
                $histCnt = (int)$pdo->query("SELECT COUNT(*) FROM work_history WHERE updated_by = {$user_id}")->fetchColumn();
                $photoCnt = (int)$pdo->query("SELECT COUNT(*) FROM car_photos WHERE uploaded_by = {$user_id}")->fetchColumn();

                if ($attCnt > 0 || $carsCnt > 0 || $histCnt > 0 || $photoCnt > 0) {
                    // Safe deactivation to prevent foreign key errors
                    $deactStmt = $pdo->prepare("UPDATE users SET status = 'Inactive' WHERE id = ?");
                    $deactStmt->execute([$user_id]);
                    $success = "Staff member '{$targetUser['name']}' has historical attendance/work logs. Account safely set to Inactive.";
                } else {
                    // Safe permanent deletion
                    $delStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $delStmt->execute([$user_id]);
                    $success = "Staff member '{$targetUser['name']}' successfully deleted.";
                }
            }
        }
    }
}

// Fetch All Users
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User & Employee Management | Sun Painting Works</title>
  
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
      <li class="sidebar-item active">
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
      <h1 class="top-bar-title"><i class="fa-solid fa-users-gear text-gold"></i> Employee & User Management</h1>
    </header>

    <div class="content-body">
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

      <!-- Add New User Card -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-user-plus text-gold"></i> Register New Workshop Employee</div>
        </div>

        <form action="users.php" method="POST">
          <input type="hidden" name="action" value="add_user">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Employee ID *</label>
              <input type="text" name="employee_id" class="form-control" placeholder="e.g. EMP003" required>
            </div>
            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" class="form-control" placeholder="e.g. John Painter" required>
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number *</label>
              <input type="text" name="phone" class="form-control" placeholder="e.g. 9842299079" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Username *</label>
              <input type="text" name="username" class="form-control" placeholder="e.g. john" required>
            </div>
            <div class="form-group">
              <label class="form-label">Password *</label>
              <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>
            <div class="form-group">
              <label class="form-label">Confirm Password *</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">System Role *</label>
              <select name="role" class="form-control">
                <option value="User">User (Workshop Employee)</option>
                <option value="Admin">Admin (Full Access)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Joining Date</label>
              <input type="date" name="joining_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Account Status</label>
              <select name="status" class="form-control">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
          </div>

          <div style="text-align: right;">
            <button type="submit" class="btn btn-gold"><i class="fa-solid fa-user-plus"></i> CREATE EMPLOYEE ACCOUNT</button>
          </div>
        </form>
      </div>

      <!-- Workshop Staff Directory -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-address-book text-gold"></i> Workshop Staff Directory</div>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Emp ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Username</th>
                <th>Role</th>
                <th>Joining Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td style="font-weight: 800; color: var(--gold-primary);"><?php echo e($u['employee_id']); ?></td>
                  <td style="font-weight: 700;"><?php echo e($u['name']); ?></td>
                  <td><?php echo e($u['phone']); ?></td>
                  <td><code><?php echo e($u['username']); ?></code></td>
                  <td>
                    <span class="badge <?php echo $u['role'] === 'Admin' ? 'badge-pay-Paid' : 'badge-status-Inspection'; ?>">
                      <?php echo e($u['role']); ?>
                    </span>
                  </td>
                  <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo $u['joining_date'] ? date('d-m-Y', strtotime($u['joining_date'])) : 'N/A'; ?></td>
                  <td>
                    <span class="badge badge-<?php echo e($u['status']); ?>"><?php echo e($u['status']); ?></span>
                  </td>
                  <td>
                    <div style="display: flex; gap: 8px;">
                      <!-- Toggle Status Form -->
                      <form action="users.php" method="POST">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="current_status" value="<?php echo $u['status']; ?>">
                        <button type="submit" class="btn btn-silver" style="padding: 6px 10px; font-size: 0.8rem;" title="Toggle Status">
                          <i class="fa-solid fa-power-off"></i> <?php echo $u['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                        </button>
                      </form>

                      <!-- Delete Form -->
                      <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                          <button type="submit" class="btn btn-silver" style="padding: 6px 12px; font-size: 0.8rem; color: #E74C3C;" title="Delete Staff Member">
                            <i class="fa-solid fa-trash"></i> Delete
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
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
