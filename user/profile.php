<?php
require_once __DIR__ . '/../includes/user_auth.php';

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch Profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userProfile = $stmt->fetch();

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password)) {
        $error = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } elseif (!password_verify($current_password, $userProfile['password'])) {
        $error = "Incorrect current password.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$hashed, $userId]);
        $success = "Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile | Sun Painting Works</title>
  
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
      <li class="sidebar-item">
        <a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> My Attendance</a>
      </li>
      <li class="sidebar-item active">
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
      <h1 class="top-bar-title"><i class="fa-solid fa-id-card text-gold"></i> My Employee Profile</h1>
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

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Employee Profile Info Card -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-user text-gold"></i> Employee Details</div>
          </div>

          <table style="width: 100%; font-size: 1rem; line-height: 2.2;">
            <tr><td style="color: var(--text-muted); width: 40%;">Employee ID:</td><td style="font-weight: 800; color: var(--gold-primary);"><?php echo e($userProfile['employee_id']); ?></td></tr>
            <tr><td style="color: var(--text-muted);">Full Name:</td><td style="font-weight: 700;"><?php echo e($userProfile['name']); ?></td></tr>
            <tr><td style="color: var(--text-muted);">Phone Number:</td><td><?php echo e($userProfile['phone']); ?></td></tr>
            <tr><td style="color: var(--text-muted);">Username:</td><td><code><?php echo e($userProfile['username']); ?></code></td></tr>
            <tr><td style="color: var(--text-muted);">Role:</td><td><span class="badge badge-pay-Paid"><?php echo e($userProfile['role']); ?></span></td></tr>
            <tr><td style="color: var(--text-muted);">Joining Date:</td><td><?php echo $userProfile['joining_date'] ? date('d-m-Y', strtotime($userProfile['joining_date'])) : 'N/A'; ?></td></tr>
            <tr><td style="color: var(--text-muted);">Account Status:</td><td><span class="badge badge-Active"><?php echo e($userProfile['status']); ?></span></td></tr>
          </table>
        </div>

        <!-- Change Password Form -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-key text-gold"></i> Change Password</div>
          </div>

          <form action="profile.php" method="POST">
            <div class="form-group">
              <label class="form-label">Current Password *</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
              <label class="form-label">New Password *</label>
              <input type="password" name="new_password" class="form-control" required>
            </div>

            <div class="form-group">
              <label class="form-label">Confirm New Password *</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 10px;"><i class="fa-solid fa-lock"></i> UPDATE PASSWORD</button>
          </form>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>
