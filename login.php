<?php
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'Active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set Session Data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_data'] = $user;

            // Redirect according to role (Attendance is managed exclusively by Admin)
            if ($user['role'] === 'Admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: user/dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid username or password, or account inactive.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Workshop Management Login | Sun Painting Works</title>
  
  <!-- Fonts & FontAwesome -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="assets/images/logo.png">

  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body style="background: radial-gradient(circle at center, #FFFFFF 0%, #F1F5F9 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">

  <div style="width: 100%; max-width: 440px; background: var(--bg-card); border: 1px solid var(--border-gold); border-radius: var(--radius-lg); padding: 45px 35px; box-shadow: 0 15px 40px rgba(0,0,0,0.08); position: relative; overflow: hidden;">
    
    <!-- Top Decorative Gold Line -->
    <div style="position: absolute; top:0; left:0; width: 100%; height: 4px; background: linear-gradient(90deg, var(--gold-dark), var(--gold-light), var(--gold-dark));"></div>

    <div style="text-align: center; margin-bottom: 30px;">
      <a href="index.php">
        <img src="assets/images/logo.png" alt="Sun Painting Works Logo" style="height: 70px; margin-bottom: 15px; filter: drop-shadow(0 2px 8px rgba(212, 175, 55, 0.3));">
      </a>
      <h2 style="font-size: 1.6rem; color: var(--gold-primary);">SUN PAINTING WORKS</h2>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Workshop Management Portal</p>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background: rgba(231, 76, 60, 0.12); border: 1px solid #E74C3C; color: #C0392B; padding: 12px 16px; border-radius: var(--radius-sm); font-size: 0.9rem; margin-bottom: 20px;">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo e($error); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div style="background: rgba(46, 204, 113, 0.12); border: 1px solid #2ECC71; color: #27AE60; padding: 12px 16px; border-radius: var(--radius-sm); font-size: 0.9rem; margin-bottom: 20px;">
        <i class="fa-solid fa-circle-check"></i> <?php echo e($success); ?>
      </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
      <div class="form-group">
        <label for="username" class="form-label"><i class="fa-solid fa-user text-gold"></i> Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required autofocus value="<?php echo e($_POST['username'] ?? ''); ?>">
      </div>

      <div class="form-group" style="position: relative;">
        <label for="password" class="form-label"><i class="fa-solid fa-key text-gold"></i> Password</label>
        <div style="position: relative;">
          <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required style="padding-right: 45px;">
          <button type="button" class="toggle-password-btn" data-target="password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--silver-dark); cursor: pointer; font-size: 1.1rem;">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 10px; padding: 14px; font-size: 1rem;">
        <i class="fa-solid fa-right-to-bracket"></i> LOGIN TO WORKSHOP
      </button>
    </form>

    <div style="margin-top: 30px; text-align: center; font-size: 0.85rem; color: var(--text-muted); border-top: 1px solid var(--border-light); padding-top: 20px;">
      <a href="index.php" style="color: var(--gold-primary); text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Return to Homepage</a>
    </div>

  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
