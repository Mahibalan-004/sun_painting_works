<?php
// ============================================================
// Sun Painting Works - Admin Profile & Account Settings
// ============================================================

require_once __DIR__ . '/../includes/admin_auth.php';

// Ensure consistent local timezone
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
}

$adminId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';

// Load profile data from the authenticated administrator's database record
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'Admin'");
$stmt->execute([$adminId]);
$adminProfile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminProfile) {
    header("Location: ../logout.php");
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Token Verification
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || !verifyCSRFToken($csrfToken)) {
        $error = "CSRF validation failed (invalid or missing security token). Request rejected.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            // Edit permitted profile fields
            $name  = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($name)) {
                $error = "Display name is required.";
            } elseif (strlen($name) < 2 || strlen($name) > 100) {
                $error = "Display name must be between 2 and 100 characters.";
            } elseif (empty($phone)) {
                $error = "Phone number is required.";
            } elseif (!preg_match('/^[0-9\s\+\-]{7,15}$/', $phone)) {
                $error = "Please enter a valid phone number (digits only, 7-15 characters).";
            } else {
                // Update ONLY permitted profile fields for the authenticated administrator
                // Never trust account IDs from POST/GET; strictly bind session admin ID
                $updStmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ? AND role = 'Admin'");
                $updStmt->execute([$name, $phone, $adminId]);

                // Update session values so navigation immediately reflects the new name
                $_SESSION['user_name'] = $name;
                if (isset($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
                    $_SESSION['user_data']['name'] = $name;
                    $_SESSION['user_data']['phone'] = $phone;
                }

                // Refresh local data
                $adminProfile['name'] = $name;
                $adminProfile['phone'] = $phone;

                $success = "Profile information updated successfully!";
            }
        } elseif ($action === 'change_password') {
            // Change Password Verification & Update
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword     = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = "Please fill in all required password fields.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "New password and confirm password do not match.";
            } elseif (strlen($newPassword) < 6) {
                $error = "New password must be at least 6 characters in length.";
            } elseif (!password_verify($currentPassword, $adminProfile['password'])) {
                // Generic and safe error message
                $error = "Incorrect current password. Please try again.";
            } else {
                // Secure password hashing with PASSWORD_DEFAULT
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updPwd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'Admin'");
                $updPwd->execute([$hashedPassword, $adminId]);

                // Update cached password hash in local record and session
                $adminProfile['password'] = $hashedPassword;
                if (isset($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
                    $_SESSION['user_data']['password'] = $hashedPassword;
                }

                // Regenerate session id safely without disrupting the administrator workflow
                session_regenerate_id(true);

                $success = "Password changed successfully!";
            }
        } else {
            $error = "Invalid action requested.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Profile & Account Settings | Sun Painting Works</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
  <link rel="shortcut icon" href="../assets/images/favicon.ico">
  <link rel="apple-touch-icon" href="../assets/images/apple-touch-icon.png">

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
      <div style="flex-grow: 1;">
        <div class="sidebar-brand-name">SUN PAINTING</div>
        <span class="sidebar-role-badge">ADMIN PORTAL</span>
      </div>
      <button type="button" class="sidebar-close-btn" aria-label="Close navigation menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
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
      <li class="sidebar-item">
        <a href="salary.php"><i class="fa-solid fa-money-bill-wave"></i> Employee Salary</a>
      </li>
      <li class="sidebar-item active">
        <a href="profile.php"><i class="fa-solid fa-user-shield"></i> Profile & Security</a>
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
        <button type="button" class="sidebar-toggle-btn" aria-label="Open navigation menu" aria-expanded="false">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="top-bar-title"><i class="fa-solid fa-user-shield text-gold"></i> Profile & Security Settings</h1>
      </div>

      <div class="user-profile-menu">
        <div class="user-avatar"><?php echo strtoupper(substr($adminProfile['name'], 0, 2)); ?></div>
        <div>
          <div style="font-weight: 700; font-size: 0.95rem;"><?php echo e($adminProfile['name']); ?></div>
          <div style="font-size: 0.75rem; color: var(--gold-dark);"><i class="fa-solid fa-shield"></i> Administrator</div>
        </div>
      </div>
    </header>

    <div class="content-body">
      <!-- Error & Success Messages -->
      <?php if (!empty($error)): ?>
        <div style="background: rgba(231, 76, 60, 0.12); border: 1px solid #E74C3C; color: #C0392B; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-triangle-exclamation"></i> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div style="background: rgba(46, 204, 113, 0.12); border: 1px solid #2ECC71; color: #27AE60; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-circle-check"></i> <?php echo e($success); ?>
        </div>
      <?php endif; ?>

      <!-- Administrator Overview Header Card -->
      <div class="card-box" style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.08), rgba(255, 255, 255, 0.95)); border: 1px solid var(--gold-primary); margin-bottom: 25px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
          <div style="display: flex; align-items: center; gap: 20px;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark)); color: #000; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);">
              <?php echo strtoupper(substr($adminProfile['name'], 0, 2)); ?>
            </div>
            <div>
              <div style="font-size: 1.4rem; font-weight: 800; color: #1E293B;">
                <?php echo e($adminProfile['name']); ?>
              </div>
              <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px; flex-wrap: wrap;">
                <span class="badge" style="background: rgba(212, 175, 55, 0.18); color: var(--gold-dark); border: 1px solid var(--gold-primary);">
                  <i class="fa-solid fa-shield-halved"></i> <?php echo e($adminProfile['role']); ?>
                </span>
                <span class="badge badge-Active">
                  <i class="fa-solid fa-circle-check"></i> <?php echo e($adminProfile['status']); ?>
                </span>
                <span style="font-size: 0.85rem; color: var(--text-muted);">
                  Employee Code: <code><?php echo e($adminProfile['employee_id']); ?></code>
                </span>
              </div>
            </div>
          </div>

          <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="text-align: center; padding: 5px 15px; border-right: 1px solid #E2E8F0;">
              <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Username</div>
              <div style="font-size: 1.1rem; font-weight: 700; color: #1E293B;"><?php echo e($adminProfile['username']); ?></div>
            </div>
            <div style="text-align: center; padding: 5px 15px; border-right: 1px solid #E2E8F0;">
              <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Joining Date</div>
              <div style="font-size: 1.1rem; font-weight: 700; color: #1E293B;">
                <?php echo !empty($adminProfile['joining_date']) ? date('d M Y', strtotime($adminProfile['joining_date'])) : 'N/A'; ?>
              </div>
            </div>
            <div style="text-align: center; padding: 5px 15px;">
              <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Account Created</div>
              <div style="font-size: 1.1rem; font-weight: 700; color: #1E293B;">
                <?php echo !empty($adminProfile['created_at']) ? date('d M Y', strtotime($adminProfile['created_at'])) : 'N/A'; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Forms Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px;">

        <!-- 1. Edit Profile Form -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title">
              <i class="fa-solid fa-user-pen text-gold"></i> Edit Profile Information
            </div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Personal contact & display details</span>
          </div>

          <form action="profile.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
              <label class="form-label">Display Name *</label>
              <input type="text" name="name" class="form-control" value="<?php echo e($adminProfile['name']); ?>" required maxlength="100">
              <small style="color: var(--text-muted); font-size: 0.75rem;">Your official name as displayed across workshop logs.</small>
            </div>

            <div class="form-group">
              <label class="form-label">Phone Number *</label>
              <input type="text" name="phone" class="form-control" value="<?php echo e($adminProfile['phone']); ?>" required maxlength="20">
              <small style="color: var(--text-muted); font-size: 0.75rem;">Primary contact number for workshop notifications.</small>
            </div>

            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" value="<?php echo e($adminProfile['username']); ?>" disabled style="background: rgba(0,0,0,0.04); cursor: not-allowed;">
              <small style="color: var(--text-muted); font-size: 0.75rem;">Account username cannot be altered.</small>
            </div>

            <div class="form-group">
              <label class="form-label">Employee ID</label>
              <input type="text" class="form-control" value="<?php echo e($adminProfile['employee_id']); ?>" disabled style="background: rgba(0,0,0,0.04); cursor: not-allowed;">
              <small style="color: var(--text-muted); font-size: 0.75rem;">Fixed organizational identifier.</small>
            </div>

            <div style="text-align: right; margin-top: 20px;">
              <button type="submit" class="btn btn-gold" style="padding: 12px 24px;">
                <i class="fa-solid fa-floppy-disk"></i> SAVE PROFILE CHANGES
              </button>
            </div>
          </form>
        </div>

        <!-- 2. Change Password Form -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title">
              <i class="fa-solid fa-key text-gold"></i> Change / Reset Password
            </div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Current password verification required</span>
          </div>

          <form action="profile.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
              <label class="form-label">Current Password *</label>
              <input type="password" name="current_password" class="form-control" required autocomplete="current-password" placeholder="Enter current password">
              <small style="color: var(--text-muted); font-size: 0.75rem;">Verification required before setting a new password.</small>
            </div>

            <div class="form-group">
              <label class="form-label">New Password *</label>
              <input type="password" name="new_password" class="form-control" required autocomplete="new-password" minlength="6" placeholder="Enter new password (min. 6 chars)">
              <small style="color: var(--text-muted); font-size: 0.75rem;">Must be at least 6 characters in length.</small>
            </div>

            <div class="form-group">
              <label class="form-label">Confirm New Password *</label>
              <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password" minlength="6" placeholder="Re-type new password">
              <small style="color: var(--text-muted); font-size: 0.75rem;">Must match new password exactly.</small>
            </div>

            <div style="background: rgba(212, 175, 55, 0.08); border-left: 3px solid var(--gold-primary); padding: 12px 14px; border-radius: var(--radius-sm); margin-bottom: 20px;">
              <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.5;">
                <strong style="color: #1E293B;"><i class="fa-solid fa-shield"></i> Security Notice:</strong> Passwords are cryptographically hashed using standard PHP password algorithms. Never share your administrative credentials.
              </div>
            </div>

            <div style="text-align: right;">
              <button type="submit" class="btn btn-gold" style="padding: 12px 24px; width: 100%;">
                <i class="fa-solid fa-lock"></i> UPDATE PASSWORD
              </button>
            </div>
          </form>
        </div>

      </div>

    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
