<?php
require_once __DIR__ . '/../includes/user_auth.php';

$userId = $_SESSION['user_id'];
$car_id = (int)($_GET['car_id'] ?? 0);
$error = '';
$success = '';

// Handle Status & Photo Upload Updates by Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_car_id = (int)($_POST['car_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status' && $target_car_id > 0) {
        $new_status = $_POST['status'] ?? '';
        $work_desc = trim($_POST['work_description'] ?? '');

        // Fetch old status
        $cur = $pdo->prepare("SELECT status FROM cars WHERE id = ?");
        $cur->execute([$target_car_id]);
        $old_st = $cur->fetchColumn();

        $upd = $pdo->prepare("UPDATE cars SET status = ? WHERE id = ?");
        $upd->execute([$new_status, $target_car_id]);

        $logMsg = !empty($work_desc) ? $work_desc : "Status updated from {$old_st} to {$new_status}";
        logWorkHistory($pdo, $target_car_id, $new_status, $logMsg, $userId);

        $success = "Car work status updated successfully!";
    }

    if ($action === 'upload_work_photo' && $target_car_id > 0) {
        $photo_type = $_POST['photo_type'] ?? 'after_paint';
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $uploadDir = __DIR__ . '/../uploads/' . ($photo_type === 'damage' ? 'damage/' : 'after_paint/');

        if (!empty($_FILES['work_photo']['name'])) {
            $fileName = $_FILES['work_photo']['name'];
            $fileTmp = $_FILES['work_photo']['tmp_name'];
            $fileSize = $_FILES['work_photo']['size'];
            $fileError = $_FILES['work_photo']['error'];

            if ($fileError === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts) && $fileSize <= 5 * 1024 * 1024) {
                    $prefix = $photo_type === 'damage' ? 'dmg_' : 'after_';
                    $uniqueName = $prefix . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $targetPath = $uploadDir . $uniqueName;

                    if (move_uploaded_file($fileTmp, $targetPath)) {
                        $relPath = 'uploads/' . ($photo_type === 'damage' ? 'damage/' : 'after_paint/') . $uniqueName;
                        $stmtPhoto = $pdo->prepare("INSERT INTO car_photos (car_id, photo_type, photo_path, uploaded_by) VALUES (?, ?, ?, ?)");
                        $stmtPhoto->execute([$target_car_id, $photo_type, $relPath, $userId]);

                        logWorkHistory($pdo, $target_car_id, 'Photo Upload', "Uploaded {$photo_type} photo.", $userId);
                        $success = "Work photo uploaded successfully!";
                    }
                } else {
                    $error = "Invalid file type or size exceeds 5 MB.";
                }
            }
        }
    }
}

// Fetch Cars
$cars = $pdo->query("SELECT c.*, cust.customer_name, cust.customer_no FROM cars c JOIN customers cust ON c.customer_id = cust.customer_id ORDER BY c.id DESC")->fetchAll();

// If specific car selected for details
$selectedCar = null;
$selectedPhotos = [];
$selectedHistory = [];
if ($car_id > 0) {
    $selStmt = $pdo->prepare("SELECT c.*, cust.customer_name, cust.customer_no FROM cars c JOIN customers cust ON c.customer_id = cust.customer_id WHERE c.id = ?");
    $selStmt->execute([$car_id]);
    $selectedCar = $selStmt->fetch();

    if ($selectedCar) {
        $pStmt = $pdo->prepare("SELECT * FROM car_photos WHERE car_id = ? ORDER BY id DESC");
        $pStmt->execute([$car_id]);
        $selectedPhotos = $pStmt->fetchAll();

        $hStmt = $pdo->prepare("SELECT wh.*, u.name AS updater_name FROM work_history wh LEFT JOIN users u ON wh.updated_by = u.id WHERE wh.car_id = ? ORDER BY wh.id DESC");
        $hStmt->execute([$car_id]);
        $selectedHistory = $hStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Car Work Management | Sun Painting Works</title>
  
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
      <li class="sidebar-item active">
        <a href="work.php"><i class="fa-solid fa-spray-can"></i> Car Work</a>
      </li>
      <!-- <li class="sidebar-item">
        <a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> My Attendance</a>
      </li> -->
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
      <h1 class="top-bar-title"><i class="fa-solid fa-spray-can text-gold"></i> Workshop Car Work Queue</h1>
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

      <!-- Selected Car Work Update Panel -->
      <?php if ($selectedCar): ?>
        <div class="card-box" style="border-color: var(--border-gold);">
          <div class="card-box-header">
            <div class="card-box-title">
              <i class="fa-solid fa-car text-gold"></i> Update Work: <?php echo e($selectedCar['car_number']); ?> (<?php echo e($selectedCar['car_name']); ?>)
            </div>
            <a href="work.php" class="btn btn-silver" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-xmark"></i> Close Panel</a>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 25px;">
            <!-- Update Status Form -->
            <form action="work.php?car_id=<?php echo $selectedCar['id']; ?>" method="POST" style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="car_id" value="<?php echo $selectedCar['id']; ?>">

              <h4 style="color: var(--gold-primary); margin-bottom: 15px;">Update Work Stage</h4>

              <div class="form-group">
                <label class="form-label">Current Work Status *</label>
                <select name="status" class="form-control" required style="border-color: var(--border-gold); font-weight: 700;">
                  <?php 
                  $allStatuses = ['New', 'Inspection', 'Denting', 'Painting', 'Polishing', 'Extra Work', 'Completed', 'Delivered'];
                  foreach ($allStatuses as $st): 
                  ?>
                    <option value="<?php echo $st; ?>" <?php echo $selectedCar['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Work Progress Description / Log Remarks</label>
                <textarea name="work_description" class="form-control" rows="3" placeholder="e.g. Applied primer coat, completed panel beating..."></textarea>
              </div>

              <button type="submit" class="btn btn-gold" style="width: 100%;"><i class="fa-solid fa-floppy-disk"></i> UPDATE WORK STATUS</button>
            </form>

            <!-- Upload Photo Form -->
            <form action="work.php?car_id=<?php echo $selectedCar['id']; ?>" method="POST" enctype="multipart/form-data" style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
              <input type="hidden" name="action" value="upload_work_photo">
              <input type="hidden" name="car_id" value="<?php echo $selectedCar['id']; ?>">

              <h4 style="color: var(--gold-primary); margin-bottom: 15px;">Upload Work Photo</h4>

              <div class="form-group">
                <label class="form-label">Photo Category *</label>
                <select name="photo_type" class="form-control" required>
                  <option value="after_paint">After Paint / Completed Photo</option>
                  <option value="damage">Damage Photo</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Select Photo Source *</label>
                <div style="display: flex; gap: 10px;">
                  <button type="button" class="btn btn-gold" style="flex: 1; padding: 10px; font-size: 0.85rem;" onclick="document.getElementById('user-photo-file').click();">
                    <i class="fa-solid fa-folder-open"></i> Upload File
                  </button>
                  <button type="button" class="btn btn-outline-gold" style="flex: 1; padding: 10px; font-size: 0.85rem;" onclick="document.getElementById('user-photo-camera').click();">
                    <i class="fa-solid fa-camera"></i> 📷 Take Photo
                  </button>
                </div>
                <input type="file" id="user-photo-file" name="work_photo" accept="image/*" style="display: none;" onchange="this.form.submit();">
                <input type="file" id="user-photo-camera" name="work_photo" accept="image/*" capture="environment" style="display: none;" onchange="this.form.submit();">
              </div>

              <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 15px;"><i class="fa-solid fa-cloud-arrow-up"></i> SUBMIT PHOTO</button>
            </form>
          </div>

          <!-- Existing Photos Gallery for this car -->
          <h4 style="color: var(--gold-primary); margin-bottom: 12px;"><i class="fa-solid fa-images"></i> Car Photos Gallery</h4>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <?php if (empty($selectedPhotos)): ?>
              <div style="color: var(--text-muted); font-size: 0.9rem;">No photos uploaded for this vehicle yet.</div>
            <?php else: ?>
              <?php foreach ($selectedPhotos as $sp): ?>
                <div style="border: 1px solid var(--border-gold); border-radius: var(--radius-sm); overflow: hidden; height: 110px;">
                  <img src="../<?php echo e($sp['photo_path']); ?>" alt="Car Photo" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Work History Log for selected car -->
          <h4 style="color: var(--gold-primary); margin-bottom: 12px;"><i class="fa-solid fa-clock-rotate-left"></i> Work Progress History</h4>
          <div class="timeline">
            <?php foreach ($selectedHistory as $sh): ?>
              <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                  <div class="timeline-date"><?php echo date('d-m-Y h:i A', strtotime($sh['created_at'])); ?></div>
                  <div class="timeline-title"><span class="badge badge-status-<?php echo e($sh['status']); ?>"><?php echo e($sh['status']); ?></span></div>
                  <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 2px;"><?php echo e($sh['description']); ?> (by <?php echo e($sh['updater_name'] ?? 'Staff'); ?>)</div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- All Workshop Cars Table -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-list-check text-gold"></i> Workshop Vehicle Work List</div>
        </div>

        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Customer ID</th>
                <th>Car Number</th>
                <th>Car Name</th>
                <th>Car Color</th>
                <th>Work Status</th>
                <th>Registered Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cars as $c): ?>
                <tr>
                  <td style="font-weight: 800; color: var(--gold-primary);"><?php echo e($c['customer_id']); ?></td>
                  <td style="font-weight: 800;"><?php echo e($c['car_number']); ?></td>
                  <td><?php echo e($c['car_name']); ?></td>
                  <td><?php echo e($c['car_color']); ?></td>
                  <td><span class="badge badge-status-<?php echo e($c['status']); ?>"><?php echo e($c['status']); ?></span></td>
                  <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('d-m-Y', strtotime($c['created_at'])); ?></td>
                  <td>
                    <a href="work.php?car_id=<?php echo $c['id']; ?>" class="btn btn-gold" style="padding: 5px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pen-to-square"></i> Select & Update</a>
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

</body>
</html>
