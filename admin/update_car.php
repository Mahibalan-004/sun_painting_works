<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$car_id = (int)($_GET['id'] ?? 0);

if (!$car_id) {
    header("Location: car_list.php");
    exit;
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Handle Actions (Update Status/Amounts, Add Extra Work, Upload Photos, Delete Photos)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_car_details') {
        $car_number = strtoupper(trim($_POST['car_number'] ?? ''));
        $car_name = trim($_POST['car_name'] ?? '');
        $car_color = trim($_POST['car_color'] ?? '');
        $new_status = $_POST['status'] ?? '';
        $work_remarks = trim($_POST['work_remarks'] ?? '');
        
        $estimate_amount = (float)($_POST['estimate_amount'] ?? 0);
        $final_amount = (float)($_POST['final_amount'] ?? 0);

        // Fetch current status
        $curCar = $pdo->prepare("SELECT status FROM cars WHERE id = ?");
        $curCar->execute([$car_id]);
        $old_status = $curCar->fetchColumn();

        $updateStmt = $pdo->prepare("UPDATE cars SET car_number = ?, car_name = ?, car_color = ?, status = ?, estimate_amount = ?, final_amount = ? WHERE id = ?");
        $updateStmt->execute([$car_number, $car_name, $car_color, $new_status, $estimate_amount, $final_amount, $car_id]);

        // If status changed or remarks provided, add to work history
        if ($new_status !== $old_status || !empty($work_remarks)) {
            $desc = !empty($work_remarks) ? $work_remarks : "Status updated to " . $new_status;
            logWorkHistory($pdo, $car_id, $new_status, $desc, $_SESSION['user_id']);
        }

        // Recalculate totals
        recalculateCarAmounts($pdo, $car_id);

        header("Location: update_car.php?id={$car_id}&success=" . urlencode("Vehicle details updated successfully."));
        exit;
    }

    if ($action === 'add_extra_work') {
        $extra_desc = trim($_POST['extra_description'] ?? '');
        $extra_amount = (float)($_POST['extra_amount'] ?? 0);

        if (!empty($extra_desc) && $extra_amount > 0) {
            $insExtra = $pdo->prepare("INSERT INTO extra_work (car_id, description, amount, added_by) VALUES (?, ?, ?, ?)");
            $insExtra->execute([$car_id, $extra_desc, $extra_amount, $_SESSION['user_id']]);

            logWorkHistory($pdo, $car_id, 'Extra Work', "Added extra work: {$extra_desc} (" . formatRupee($extra_amount) . ")", $_SESSION['user_id']);
            
            recalculateCarAmounts($pdo, $car_id);

            header("Location: update_car.php?id={$car_id}&success=" . urlencode("Extra work added successfully."));
            exit;
        } else {
            $error = "Please enter description and valid amount for extra work.";
        }
    }

    if ($action === 'upload_after_paint_photos') {
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $uploadDir = __DIR__ . '/../uploads/after_paint/';

        if (!empty($_FILES['after_paint_photos']['name'][0])) {
            $filesCount = count($_FILES['after_paint_photos']['name']);
            for ($i = 0; $i < $filesCount; $i++) {
                $fileName = $_FILES['after_paint_photos']['name'][$i];
                $fileTmp = $_FILES['after_paint_photos']['tmp_name'][$i];
                $fileSize = $_FILES['after_paint_photos']['size'][$i];
                $fileError = $_FILES['after_paint_photos']['error'][$i];

                if ($fileError === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExts) && $fileSize <= 5 * 1024 * 1024) {
                        $uniqueName = 'after_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                        $targetPath = $uploadDir . $uniqueName;

                        if (move_uploaded_file($fileTmp, $targetPath)) {
                            $stmtPhoto = $pdo->prepare("INSERT INTO car_photos (car_id, photo_type, photo_path, uploaded_by) VALUES (?, 'after_paint', ?, ?)");
                            $stmtPhoto->execute([$car_id, 'uploads/after_paint/' . $uniqueName, $_SESSION['user_id']]);
                        }
                    }
                }
            }
            logWorkHistory($pdo, $car_id, 'After Paint Photos', 'Uploaded after-paint completion photos.', $_SESSION['user_id']);
            header("Location: update_car.php?id={$car_id}&success=" . urlencode("After paint photos uploaded successfully."));
            exit;
        }
    }

    if ($action === 'delete_photo') {
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        $pStmt = $pdo->prepare("SELECT photo_path FROM car_photos WHERE id = ? AND car_id = ?");
        $pStmt->execute([$photo_id, $car_id]);
        $photo = $pStmt->fetch();
        if ($photo) {
            $fullPath = __DIR__ . '/../' . $photo['photo_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $delStmt = $pdo->prepare("DELETE FROM car_photos WHERE id = ?");
            $delStmt->execute([$photo_id]);
        }
        header("Location: update_car.php?id={$car_id}&success=" . urlencode("Photo deleted."));
        exit;
    }

    if ($action === 'delete_extra_work') {
        $extra_id = (int)($_POST['extra_id'] ?? 0);
        $delEx = $pdo->prepare("DELETE FROM extra_work WHERE id = ? AND car_id = ?");
        $delEx->execute([$extra_id, $car_id]);
        recalculateCarAmounts($pdo, $car_id);
        header("Location: update_car.php?id={$car_id}&success=" . urlencode("Extra work removed."));
        exit;
    }
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
$stmtHistory = $pdo->prepare("SELECT wh.*, u.name AS updater_name FROM work_history wh LEFT JOIN users u ON wh.updated_by = u.id WHERE wh.car_id = ? ORDER BY wh.id DESC");
$stmtHistory->execute([$car_id]);
$workHistory = $stmtHistory->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Update Car Work | Sun Painting Works</title>
  
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
      <h1 class="top-bar-title"><i class="fa-solid fa-pen-to-square text-gold"></i> Update Car Job: <?php echo e($car['car_number']); ?></h1>
      <div style="display: flex; gap: 10px;">
        <a href="car_details.php?id=<?php echo $car_id; ?>" class="btn btn-gold" style="padding: 8px 16px; font-size: 0.9rem;"><i class="fa-solid fa-eye"></i> View Invoice Sheet</a>
        <a href="car_list.php" class="btn btn-silver" style="padding: 8px 16px; font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Car List</a>
      </div>
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

      <!-- 1. Customer & Vehicle Primary Update Form -->
      <form action="update_car.php?id=<?php echo $car_id; ?>" method="POST">
        <input type="hidden" name="action" value="update_car_details">

        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-car text-gold"></i> Customer & Car Information</div>
            <span class="badge badge-status-<?php echo e($car['status']); ?>"><?php echo e($car['status']); ?></span>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Customer ID</label>
              <input type="text" class="form-control" value="<?php echo e($car['customer_id']); ?>" readonly style="background: rgba(212,175,55,0.1); color: var(--gold-light); font-weight: 700;">
            </div>

            <div class="form-group">
              <label class="form-label">Customer Name</label>
              <input type="text" class="form-control" value="<?php echo e($car['customer_name']); ?>" readonly>
            </div>

            <div class="form-group">
              <label class="form-label">Customer Phone</label>
              <input type="text" class="form-control" value="<?php echo e($car['customer_no']); ?>" readonly>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Car Number *</label>
              <input type="text" name="car_number" class="form-control" value="<?php echo e($car['car_number']); ?>" required style="text-transform: uppercase;">
            </div>

            <div class="form-group">
              <label class="form-label">Car Name / Model *</label>
              <input type="text" name="car_name" class="form-control" value="<?php echo e($car['car_name']); ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label">Car Color</label>
              <input type="text" name="car_color" class="form-control" value="<?php echo e($car['car_color']); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Work Stage / Status *</label>
              <select name="status" class="form-control" style="border-color: var(--border-gold); font-weight: 700;">
                <?php 
                $allStatuses = ['New', 'Inspection', 'Denting', 'Painting', 'Polishing', 'Extra Work', 'Completed', 'Delivered'];
                foreach ($allStatuses as $st): 
                ?>
                  <option value="<?php echo $st; ?>" <?php echo $car['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Estimate Amount (₹)</label>
              <input type="number" step="0.01" id="estimate_amount" name="estimate_amount" class="form-control" value="<?php echo e($car['estimate_amount']); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Final Amount Received (₹)</label>
              <input type="number" step="0.01" id="final_amount" name="final_amount" class="form-control" value="<?php echo e($car['final_amount']); ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Work Progress Log Remarks (Optional)</label>
            <input type="text" name="work_remarks" class="form-control" placeholder="Add optional log entry note for this update...">
          </div>

          <div style="text-align: right; margin-top: 10px;">
            <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> SAVE CAR CHANGES</button>
          </div>
        </div>
      </form>

      <!-- 2. Financial Breakdown Display -->
      <div class="card-box" style="background: rgba(212, 175, 55, 0.04); border-color: var(--border-gold);">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-wallet text-gold"></i> Live Payment & Total Breakdown</div>
          <span class="badge badge-pay-<?php echo e($car['payment_status']); ?>"><?php echo e($car['payment_status']); ?></span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; text-align: center;">
          <div style="background: var(--bg-card); padding: 15px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
            <div style="font-size: 0.8rem; color: var(--text-muted);">Estimate Amount</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: var(--silver-light);"><?php echo formatRupee($car['estimate_amount']); ?></div>
          </div>

          <div style="background: var(--bg-card); padding: 15px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
            <div style="font-size: 0.8rem; color: var(--text-muted);">Extra Work Total</div>
            <?php 
            $exSum = 0;
            foreach ($extraWorks as $ew) $exSum += (float)$ew['amount'];
            ?>
            <div style="font-size: 1.3rem; font-weight: 700; color: #E67E22;"><?php echo formatRupee($exSum); ?></div>
          </div>

          <div style="background: var(--bg-card); padding: 15px; border-radius: var(--radius-sm); border: 1px solid var(--border-gold);">
            <div style="font-size: 0.8rem; color: var(--text-muted);">Total Amount</div>
            <div style="font-size: 1.4rem; font-weight: 800; color: var(--gold-primary);"><?php echo formatRupee($car['total_amount']); ?></div>
          </div>

          <div style="background: var(--bg-card); padding: 15px; border-radius: var(--radius-sm); border: 1px solid rgba(46,204,113,0.3);">
            <div style="font-size: 0.8rem; color: var(--text-muted);">Final Received</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #2ECC71;"><?php echo formatRupee($car['final_amount']); ?></div>
          </div>

          <div style="background: var(--bg-card); padding: 15px; border-radius: var(--radius-sm); border: 1px solid rgba(231,76,60,0.3);">
            <div style="font-size: 0.8rem; color: var(--text-muted);">Balance Due</div>
            <div style="font-size: 1.4rem; font-weight: 800; color: #E74C3C;"><?php echo formatRupee($car['balance_amount']); ?></div>
          </div>
        </div>
      </div>

      <!-- 3. EXTRA WORK SECTION -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-list-plus text-gold"></i> Extra Work Records</div>
        </div>

        <!-- Add Extra Work Form -->
        <form action="update_car.php?id=<?php echo $car_id; ?>" method="POST" style="margin-bottom: 20px;">
          <input type="hidden" name="action" value="add_extra_work">
          <div class="form-row" style="align-items: flex-end;">
            <div class="form-group" style="flex: 2; margin-bottom: 0;">
              <label class="form-label">Extra Work Description</label>
              <input type="text" name="extra_description" class="form-control" placeholder="e.g. Scratch Removal - Door, Ceramic Polish, Bumper Dent" required>
            </div>
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
              <label class="form-label">Amount (₹)</label>
              <input type="number" step="0.01" name="extra_amount" class="form-control extra-work-amount-val" placeholder="0.00" required>
            </div>
            <div style="margin-bottom: 0;">
              <button type="submit" class="btn btn-gold"><i class="fa-solid fa-plus"></i> Add Extra Work</button>
            </div>
          </div>
        </form>

        <!-- Extra Work Table -->
        <div class="table-responsive">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Description</th>
                <th>Amount</th>
                <th>Date Added</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($extraWorks)): ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No extra work added yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($extraWorks as $ew): ?>
                  <tr>
                    <td><?php echo e($ew['description']); ?></td>
                    <td style="font-weight: 700; color: var(--gold-light);"><?php echo formatRupee($ew['amount']); ?></td>
                    <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('d-m-Y H:i', strtotime($ew['created_at'])); ?></td>
                    <td>
                      <form action="update_car.php?id=<?php echo $car_id; ?>" method="POST" onsubmit="return confirm('Remove this extra work item?');">
                        <input type="hidden" name="action" value="delete_extra_work">
                        <input type="hidden" name="extra_id" value="<?php echo $ew['id']; ?>">
                        <button type="submit" class="btn btn-silver" style="padding: 4px 10px; font-size: 0.8rem; color: #E74C3C;"><i class="fa-solid fa-trash"></i> Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 4. AFTER PAINT PHOTOS SECTION -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-wand-magic-sparkles text-gold"></i> After Paint Photos Gallery</div>
        </div>

        <form action="update_car.php?id=<?php echo $car_id; ?>" method="POST" enctype="multipart/form-data" style="margin-bottom: 25px;">
          <input type="hidden" name="action" value="upload_after_paint_photos">
          
          <div class="upload-dropzone" onclick="document.getElementById('after-paint-input').click();">
            <i class="fa-solid fa-camera-retro" style="font-size: 2.2rem; color: var(--gold-primary); margin-bottom: 8px;"></i>
            <h4 style="color: var(--silver-light);">Click to Select After-Paint Finished Photos</h4>
            <p style="color: var(--text-muted); font-size: 0.85rem;">Upload pristine completed photos of the vehicle</p>
            <input type="file" id="after-paint-input" name="after_paint_photos[]" multiple accept="image/*" style="display: none;" onchange="this.form.submit();">
          </div>
        </form>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px;">
          <?php if (empty($afterPhotos)): ?>
            <div style="grid-column: 1 / -1; color: var(--text-muted); text-align: center; padding: 20px;">No after-paint photos uploaded yet.</div>
          <?php else: ?>
            <?php foreach ($afterPhotos as $ap): ?>
              <div style="position: relative; border: 1px solid var(--border-gold); border-radius: var(--radius-sm); overflow: hidden; height: 140px;">
                <img src="../<?php echo e($ap['photo_path']); ?>" alt="After Paint" style="width: 100%; height: 100%; object-fit: cover;">
                <form action="update_car.php?id=<?php echo $car_id; ?>" method="POST" style="position: absolute; top: 6px; right: 6px;" onsubmit="return confirm('Delete this photo?');">
                  <input type="hidden" name="action" value="delete_photo">
                  <input type="hidden" name="photo_id" value="<?php echo $ap['id']; ?>">
                  <button type="submit" class="preview-remove-btn"><i class="fa-solid fa-xmark"></i></button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- 5. INITIAL DAMAGE PHOTOS GALLERY -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-triangle-exclamation text-gold"></i> Initial Damage Photos</div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px;">
          <?php if (empty($damagePhotos)): ?>
            <div style="grid-column: 1 / -1; color: var(--text-muted); text-align: center; padding: 20px;">No initial damage photos uploaded.</div>
          <?php else: ?>
            <?php foreach ($damagePhotos as $dp): ?>
              <div style="position: relative; border: 1px solid var(--border-light); border-radius: var(--radius-sm); overflow: hidden; height: 140px;">
                <img src="../<?php echo e($dp['photo_path']); ?>" alt="Damage Photo" style="width: 100%; height: 100%; object-fit: cover;">
                <form action="update_car.php?id=<?php echo $car_id; ?>" method="POST" style="position: absolute; top: 6px; right: 6px;" onsubmit="return confirm('Delete this photo?');">
                  <input type="hidden" name="action" value="delete_photo">
                  <input type="hidden" name="photo_id" value="<?php echo $dp['id']; ?>">
                  <button type="submit" class="preview-remove-btn"><i class="fa-solid fa-xmark"></i></button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- 6. WORK HISTORY TIMELINE -->
      <div class="card-box">
        <div class="card-box-header">
          <div class="card-box-title"><i class="fa-solid fa-clock-rotate-left text-gold"></i> Vehicle Work History Timeline</div>
        </div>

        <div class="timeline">
          <?php foreach ($workHistory as $wh): ?>
            <div class="timeline-item">
              <div class="timeline-marker"></div>
              <div class="timeline-content">
                <div class="timeline-date"><?php echo date('d-m-Y h:i A', strtotime($wh['created_at'])); ?></div>
                <div class="timeline-title">Stage: <span class="badge badge-status-<?php echo e($wh['status']); ?>"><?php echo e($wh['status']); ?></span></div>
                <div style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;"><?php echo e($wh['description']); ?></div>
                <div style="font-size: 0.75rem; color: var(--silver-dark); margin-top: 4px;">Updated by: <?php echo e($wh['updater_name'] ?? 'System'); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
