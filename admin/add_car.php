<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$error = '';
$success = '';

// Generate next Customer ID automatically
$nextCustomerId = generateNextCustomerID($pdo);
$allCustomers = getAllCustomersList($pdo);

// Token management: preserve token on validation error, create fresh one for new forms
$submission_token = trim($_POST['submission_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($submission_token) && strlen($submission_token) === 64 && ctype_xdigit($submission_token)) {
    $submissionToken = $submission_token;
} else {
    $submissionToken = getOrCreateAddCarToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 0. Verify CSRF Token
    $csrf_token = trim($_POST['csrf_token'] ?? '');
    if (!verifyCSRFToken($csrf_token)) {
        $error = "Security validation failed. Please refresh the page and submit again.";
    }

    $customer_id = trim($_POST['customer_id'] ?? '');
    $customer_no = trim($_POST['customer_no'] ?? '');
    $alternate_phone = trim($_POST['alternate_phone'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    
    $car_number = strtoupper(trim($_POST['car_number'] ?? ''));
    $car_name = trim($_POST['car_name'] ?? '');
    $car_color = trim($_POST['car_color'] ?? '');
    
    // Mechanic Vehicle Fields
    $is_mechanic_vehicle = trim($_POST['is_mechanic_vehicle'] ?? 'No');
    $mechanic_name       = trim($_POST['mechanic_name'] ?? '');
    $workshop_name       = trim($_POST['workshop_name'] ?? '');
    $mechanic_contact    = trim($_POST['mechanic_contact'] ?? '');
    $mechanic_location   = trim($_POST['mechanic_location'] ?? '');
    $customer_contact    = trim($_POST['customer_contact'] ?? '');

    // Selected Body Work / Damage Parts
    $selected_parts      = trim($_POST['selected_parts'] ?? '');

    // Financial Amounts
    $estimate_amount        = isset($_POST['estimate_amount']) && $_POST['estimate_amount'] !== '' ? (float)$_POST['estimate_amount'] : 0.00;
    $advance_amount         = isset($_POST['advance_amount']) && $_POST['advance_amount'] !== '' ? (float)$_POST['advance_amount'] : (isset($_POST['final_amount']) && $_POST['final_amount'] !== '' ? (float)$_POST['final_amount'] : 0.00);
    $final_amount           = $advance_amount;
    
    $mechanic_total_amount  = isset($_POST['mechanic_total_amount']) && $_POST['mechanic_total_amount'] !== '' ? (float)$_POST['mechanic_total_amount'] : 0.00;
    $mechanic_given_amount  = isset($_POST['mechanic_given_amount']) && $_POST['mechanic_given_amount'] !== '' ? (float)$_POST['mechanic_given_amount'] : 0.00;
    $mechanic_balance_amount= max(0, $mechanic_total_amount - $mechanic_given_amount);

    if (empty($error)) {
        if (empty($customer_id) || empty($customer_no) || empty($customer_name) || empty($car_number) || empty($car_name)) {
            $error = "Please fill in all required customer and car fields.";
        }
    }

    if (empty($error)) {
        // Fast-path idempotency check: was this exact token already committed successfully?
        $tokenCheck = checkIdempotencyToken($pdo, $submissionToken, $_SESSION['user_id'], 'add_car');
        if ($tokenCheck['status'] === 'invalid' || $tokenCheck['status'] === 'unauthorized') {
            $error = $tokenCheck['error'];
        } elseif ($tokenCheck['status'] === 'already_success') {
            // Token already processed! Redirect user to existing result without creating duplicates.
            header("Location: update_car.php?id=" . $tokenCheck['result_car_id'] . "&info=" . urlencode("This car entry was already processed."));
            exit;
        }
    }

    if (empty($error)) {
        $newlyUploadedFiles = [];
        try {
            $pdo->beginTransaction();

            // Atomically claim the idempotency token inside the transaction
            $claim = claimIdempotencyToken($pdo, $submissionToken, $_SESSION['user_id'], 'add_car');
            if (!$claim['claimed']) {
                if (!empty($claim['already_success'])) {
                    $pdo->rollBack();
                    header("Location: update_car.php?id=" . $claim['result_car_id'] . "&info=" . urlencode("This car entry was already processed."));
                    exit;
                }
                
                // Concurrent request processing this token: wait briefly and recheck
                $pdo->rollBack();
                for ($w = 0; $w < 8; $w++) {
                    usleep(500000); // 500ms
                    $recheck = checkIdempotencyToken($pdo, $submissionToken, $_SESSION['user_id'], 'add_car');
                    if ($recheck['status'] === 'already_success') {
                        header("Location: update_car.php?id=" . $recheck['result_car_id'] . "&info=" . urlencode("This car entry was already processed."));
                        exit;
                    }
                }
                throw new Exception("Another submission is currently being processed for this form. Please check the car list.");
            }

            // 1. Check if customer exists by Phone or ID
            $custCheck = $pdo->prepare("SELECT id, customer_id, customer_no FROM customers WHERE customer_no = ? OR customer_id = ?");
            $custCheck->execute([$customer_no, $customer_id]);
            $existingCust = $custCheck->fetch();

            if ($existingCust) {
                // If phone matched, update this existing customer
                $customer_id = $existingCust['customer_id'];
                $stmtCust = $pdo->prepare("UPDATE customers SET customer_no = ?, alternate_phone = ?, customer_name = ?, city = ? WHERE id = ?");
                $stmtCust->execute([$customer_no, $alternate_phone, $customer_name, $city, $existingCust['id']]);
            } else {
                // For a new customer, ensure customer_id is genuinely unique (prevent collision if another tab used same ID)
                $idCheck = $pdo->prepare("SELECT id FROM customers WHERE customer_id = ?");
                $idCheck->execute([$customer_id]);
                if ($idCheck->fetch()) {
                    $customer_id = generateNextCustomerID($pdo);
                }
                $stmtCust = $pdo->prepare("INSERT INTO customers (customer_id, customer_no, alternate_phone, customer_name, city) VALUES (?, ?, ?, ?, ?)");
                $stmtCust->execute([$customer_id, $customer_no, $alternate_phone, $customer_name, $city]);
            }

            // 2. Financial Calculations
            $total_amount = $estimate_amount;
            $balance_amount = max(0, $total_amount - $advance_amount);
            if ($advance_amount >= $total_amount && $total_amount > 0) {
                $payment_status = 'Paid';
                $balance_amount = 0.00;
            } elseif ($advance_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Pending';
            }

            // 3. Insert Car Record
            $stmtCar = $pdo->prepare("INSERT INTO cars (
                customer_id, car_number, car_name, car_color, 
                is_mechanic_vehicle, mechanic_name, workshop_name, mechanic_contact, mechanic_location, customer_contact,
                estimate_amount, total_amount, final_amount, advance_amount, balance_amount, 
                mechanic_total_amount, mechanic_given_amount, mechanic_balance_amount,
                payment_status, status, selected_parts, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?, ?)");
            $stmtCar->execute([
                $customer_id,
                $car_number,
                $car_name,
                $car_color,
                $is_mechanic_vehicle,
                $mechanic_name,
                $workshop_name,
                $mechanic_contact,
                $mechanic_location,
                $customer_contact,
                $estimate_amount,
                $total_amount,
                $final_amount,
                $advance_amount,
                $balance_amount,
                $mechanic_total_amount,
                $mechanic_given_amount,
                $mechanic_balance_amount,
                $payment_status,
                $selected_parts,
                $_SESSION['user_id']
            ]);
            $car_id = $pdo->lastInsertId();

            // 4. Handle Damage Photo Uploads safely
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $uploadDir = __DIR__ . '/../uploads/damage/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            if (!empty($_FILES['damage_photos']['name'][0])) {
                $filesCount = count($_FILES['damage_photos']['name']);
                for ($i = 0; $i < $filesCount; $i++) {
                    $fileName = $_FILES['damage_photos']['name'][$i];
                    $fileTmp = $_FILES['damage_photos']['tmp_name'][$i];
                    $fileSize = $_FILES['damage_photos']['size'][$i];
                    $fileError = $_FILES['damage_photos']['error'][$i];

                    if ($fileError === UPLOAD_ERR_OK && is_uploaded_file($fileTmp)) {
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if (empty($ext)) $ext = 'jpg';

                        // Verify MIME type
                        $mimeType = '';
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $fileTmp);
                            finfo_close($finfo);
                        } elseif (function_exists('mime_content_type')) {
                            $mimeType = mime_content_type($fileTmp);
                        } else {
                            $imgInfo = @getimagesize($fileTmp);
                            $mimeType = $imgInfo['mime'] ?? '';
                        }

                        if (in_array($ext, $allowedExts) && in_array($mimeType, $allowedMimes) && $fileSize <= 5 * 1024 * 1024) {
                            $uniqueName = 'dmg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                            $targetPath = $uploadDir . $uniqueName;

                            if (move_uploaded_file($fileTmp, $targetPath)) {
                                $newlyUploadedFiles[] = $targetPath;
                                $stmtPhoto = $pdo->prepare("INSERT INTO car_photos (car_id, photo_type, photo_path, uploaded_by) VALUES (?, 'damage', ?, ?)");
                                $stmtPhoto->execute([$car_id, 'uploads/damage/' . $uniqueName, $_SESSION['user_id']]);
                            }
                        }
                    }
                }
            }

            // 5. Add Initial Work History
            $historyDesc = 'Car registered into workshop system.';
            if (!empty($selected_parts)) {
                $historyDesc .= ' Selected Work Areas: ' . $selected_parts;
            }
            logWorkHistory($pdo, $car_id, 'New', $historyDesc, $_SESSION['user_id']);

            // 6. Complete Idempotency Token
            completeIdempotencyToken($pdo, $submissionToken, $car_id);

            // 7. Commit Transaction as one atomic unit
            $pdo->commit();

            // 8. Reset token in session for next fresh form
            resetAddCarToken();

            header("Location: update_car.php?id=" . $car_id . "&success=" . urlencode("Car job created successfully!"));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Safely clean up any newly uploaded files from failed attempt
            foreach ($newlyUploadedFiles as $upFile) {
                if (file_exists($upFile)) {
                    @unlink($upFile);
                }
            }
            // Mark token as failed to allow user to retry
            failIdempotencyToken($pdo, $submissionToken);

            error_log("Add car error: " . $e->getMessage());
            $error = "Error adding car job: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Car Job | Sun Painting Works</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
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
      <li class="sidebar-item active">
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
        <h1 class="top-bar-title"><i class="fa-solid fa-car-tunnel text-gold"></i> Add New Car Job</h1>
      </div>

      <div style="display: flex; gap: 10px; align-items: center;">
        <a href="car_list.php" class="btn btn-outline-gold" style="padding: 8px 16px; font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Car List</a>
      </div>
    </header>

    <div class="content-body">
      <?php if (!empty($error)): ?>
        <div style="background: rgba(231, 76, 60, 0.15); border: 1px solid #E74C3C; color: #FF6B6B; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-triangle-exclamation"></i> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <form id="add-car-form" action="add_car.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="submission_token" value="<?php echo e($submissionToken); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
        
        <!-- SECTION 1: CUSTOMER DETAILS -->
        <div class="card-box">
          <div class="card-box-header" style="flex-wrap: wrap; gap: 10px;">
            <div class="card-box-title"><i class="fa-solid fa-address-card text-gold"></i> 1. Customer Details</div>
            <div style="font-size: 0.85rem; color: var(--gold-dark); font-weight: 600;">
              <i class="fa-solid fa-magnifying-glass"></i> Search existing customer by typing phone number
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Customer Phone Number *</label>
              <input type="text" name="customer_no" id="customer_no" class="form-control" placeholder="Type phone no to search..." required list="existing_customers_datalist" autocomplete="off" value="<?php echo e($_POST['customer_no'] ?? ''); ?>" oninput="onCustomerPhoneInput(this.value)">
              <datalist id="existing_customers_datalist">
                <?php foreach ($allCustomers as $ac): ?>
                  <option value="<?php echo e($ac['customer_no']); ?>"><?php echo e($ac['customer_name']); ?> (<?php echo e($ac['customer_id']); ?> - <?php echo e($ac['city'] ?: 'City N/A'); ?>)</option>
                <?php endforeach; ?>
              </datalist>
              <div id="customer_found_status" style="margin-top: 6px; font-size: 0.85rem; font-weight: 700;"></div>
            </div>

            <div class="form-group">
              <label class="form-label">Customer ID (Auto-Generated / Existing)</label>
              <input type="text" name="customer_id" id="customer_id" class="form-control" value="<?php echo e($nextCustomerId); ?>" readonly style="background: rgba(212,175,55,0.1); border-color: var(--border-gold); font-weight: 700; color: var(--gold-light);">
            </div>

            <div class="form-group">
              <label class="form-label">Alternate Phone (Optional)</label>
              <input type="text" name="alternate_phone" id="alternate_phone" class="form-control" placeholder="e.g. 9842299079" value="<?php echo e($_POST['alternate_phone'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Customer Name *</label>
              <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="e.g. Ramesh Kumar" required value="<?php echo e($_POST['customer_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">City (Optional)</label>
              <input type="text" name="city" id="city" class="form-control" placeholder="e.g. Gobichettipalayam" value="<?php echo e($_POST['city'] ?? ''); ?>">
            </div>
          </div>
        </div>

        <!-- SECTION 2: CAR DETAILS & CLASSIFICATION -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-car-side text-gold"></i> 2. Vehicle Details & Classification</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Car Number *</label>
              <input type="text" name="car_number" class="form-control" placeholder="e.g. TN 36 AB 1234" required style="text-transform: uppercase;" value="<?php echo e($_POST['car_number'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Car Model / Name *</label>
              <input type="text" name="car_name" class="form-control" placeholder="e.g. Maruti Swift / Hyundai i20" required value="<?php echo e($_POST['car_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Car Color</label>
              <input type="text" name="car_color" class="form-control" placeholder="e.g. Pearl White / Midnight Black" value="<?php echo e($_POST['car_color'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border-light);">
            <div class="form-group" style="flex: 1 1 100%;">
              <label class="form-label" style="font-weight: 700; color: var(--gold-light);">
                <i class="fa-solid fa-wrench"></i> Vehicle Source / Type:
              </label>
              <div style="display: flex; gap: 20px; margin-top: 8px; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(255,255,255,0.05); padding: 10px 20px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                  <input type="radio" name="is_mechanic_vehicle" value="No" <?php echo ($_POST['is_mechanic_vehicle'] ?? 'No') === 'No' ? 'checked' : ''; ?> onchange="toggleMechanicSection(false)">
                  <span><i class="fa-solid fa-user"></i> Direct Customer Vehicle</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(212,175,55,0.1); padding: 10px 20px; border-radius: var(--radius-sm); border: 1px solid var(--border-gold); color: var(--gold-primary); font-weight: 600;">
                  <input type="radio" name="is_mechanic_vehicle" value="Yes" <?php echo ($_POST['is_mechanic_vehicle'] ?? '') === 'Yes' ? 'checked' : ''; ?> onchange="toggleMechanicSection(true)">
                  <span><i class="fa-solid fa-screwdriver-wrench"></i> Mechanic / Workshop Vehicle</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 3: MECHANIC & WORKSHOP DETAILS -->
        <div class="card-box" id="mechanic-details-box" style="display: <?php echo ($_POST['is_mechanic_vehicle'] ?? '') === 'Yes' ? 'block' : 'none'; ?>; border: 1px solid var(--border-gold); background: rgba(212, 175, 55, 0.03);">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-screwdriver-wrench text-gold"></i> 3. Mechanic & Workshop Information</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Workshop Name</label>
              <input type="text" name="workshop_name" id="workshop_name" class="form-control" placeholder="e.g. Sri Ram Auto Tech / Express Motors" value="<?php echo e($_POST['workshop_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Mechanic Name</label>
              <input type="text" name="mechanic_name" id="mechanic_name" class="form-control" placeholder="e.g. Shanmugam Mechanic" value="<?php echo e($_POST['mechanic_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Mechanic Contact Number</label>
              <input type="text" name="mechanic_contact" id="mechanic_contact" class="form-control" placeholder="e.g. 9876543210" value="<?php echo e($_POST['mechanic_contact'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Mechanic / Workshop Location</label>
              <input type="text" name="mechanic_location" id="mechanic_location" class="form-control" placeholder="e.g. Sathyamangalam Main Road, Erode" value="<?php echo e($_POST['mechanic_location'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Customer Contact Number (End Customer)</label>
              <input type="text" name="customer_contact" id="customer_contact" class="form-control" placeholder="e.g. Vehicle Owner Phone Number" value="<?php echo e($_POST['customer_contact'] ?? ''); ?>">
            </div>
          </div>
        </div>

        <!-- SECTION 3: VEHICLE SIDE & BODY PART WORK SELECTOR -->
        <div class="card-box">
          <div class="card-box-header" style="flex-wrap: wrap; gap: 10px;">
            <div class="card-box-title">
              <i class="fa-solid fa-car-burst text-gold"></i> 3. Vehicle Side & Body Part Work Selector
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
              Select body sides & check affected parts for painting / denting / repair
            </div>
          </div>

          <input type="hidden" name="selected_parts" id="selected_parts_input" value="<?php echo e($_POST['selected_parts'] ?? ''); ?>">

          <div style="margin-bottom: 20px;">
            <label class="form-label" style="font-weight: 700; color: var(--gold-light); margin-bottom: 10px; display: block;">
              Choose Vehicle Sides / Sections
            </label>
            <div class="side-selector-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px;">
              
              <label class="side-card-btn">
                <input type="checkbox" class="side-toggle-cb" data-side="left_side" onchange="toggleSideSection('left_side', this.checked)">
                <div class="side-card-content">
                  <i class="fa-solid fa-car-side" style="transform: scaleX(-1); font-size: 1.3rem;"></i>
                  <span>Left Side</span>
                </div>
              </label>

              <label class="side-card-btn">
                <input type="checkbox" class="side-toggle-cb" data-side="right_side" onchange="toggleSideSection('right_side', this.checked)">
                <div class="side-card-content">
                  <i class="fa-solid fa-car-side" style="font-size: 1.3rem;"></i>
                  <span>Right Side</span>
                </div>
              </label>

              <label class="side-card-btn">
                <input type="checkbox" class="side-toggle-cb" data-side="front_bumper" onchange="toggleSideSection('front_bumper', this.checked)">
                <div class="side-card-content">
                  <i class="fa-solid fa-bullseye" style="font-size: 1.3rem;"></i>
                  <span>Front Bumper</span>
                </div>
              </label>

              <label class="side-card-btn">
                <input type="checkbox" class="side-toggle-cb" data-side="back_bumper" onchange="toggleSideSection('back_bumper', this.checked)">
                <div class="side-card-content">
                  <i class="fa-solid fa-shield-halved" style="font-size: 1.3rem;"></i>
                  <span>Back Bumper</span>
                </div>
              </label>

              <label class="side-card-btn">
                <input type="checkbox" class="side-toggle-cb" data-side="top_roof" onchange="toggleSideSection('top_roof', this.checked)">
                <div class="side-card-content">
                  <i class="fa-solid fa-arrow-up-from-ground-water" style="font-size: 1.3rem;"></i>
                  <span>Top / Roof</span>
                </div>
              </label>

              <label class="side-card-btn">
                <input type="checkbox" class="side-toggle-cb" data-side="bottom_underbody" onchange="toggleSideSection('bottom_underbody', this.checked)">
                <div class="side-card-content">
                  <i class="fa-solid fa-arrow-down-up-lock" style="font-size: 1.3rem;"></i>
                  <span>Bottom / Underbody</span>
                </div>
              </label>

              <label class="side-card-btn">
                <input type="checkbox" class="side-toggle-cb" data-side="boot_space" onchange="toggleSideSection('boot_space', this.checked)">
                <div class="side-card-content">
                  <i class="fa-solid fa-box-open" style="font-size: 1.3rem;"></i>
                  <span>Boot Space</span>
                </div>
              </label>

            </div>
          </div>

          <!-- Dynamic Sub-Parts Accordion Containers -->
          <div id="parts-containers-wrapper">
            
            <!-- LEFT SIDE PARTS -->
            <div class="side-parts-box" id="parts-box-left_side" style="display: none;">
              <div class="side-parts-header">
                <div style="font-weight: 700; color: var(--gold-primary);"><i class="fa-solid fa-car-side" style="transform: scaleX(-1);"></i> Left Side Parts</div>
                <button type="button" class="btn btn-silver" style="padding: 3px 10px; font-size: 0.75rem;" onclick="selectAllPartsInSide('left_side', true)">Select All Parts</button>
              </div>
              <div class="parts-pill-grid">
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Left Side" value="Left Fronter / Fender" onchange="syncPartsSelection()"><span>Left Fronter / Fender</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Left Side" value="Left Front Door" onchange="syncPartsSelection()"><span>Left Front Door</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Left Side" value="Left Rear Door" onchange="syncPartsSelection()"><span>Left Back Door</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Left Side" value="Left Quarter Panel" onchange="syncPartsSelection()"><span>Left Quarter Panel</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Left Side" value="Left Running Board" onchange="syncPartsSelection()"><span>Left Running Board</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Left Side" value="Left Side Mirror" onchange="syncPartsSelection()"><span>Left Side Mirror (ORVM)</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Left Side" value="Left Pillars (A/B/C)" onchange="syncPartsSelection()"><span>Left Pillars</span></label>
              </div>
            </div>

            <!-- RIGHT SIDE PARTS -->
            <div class="side-parts-box" id="parts-box-right_side" style="display: none;">
              <div class="side-parts-header">
                <div style="font-weight: 700; color: var(--gold-primary);"><i class="fa-solid fa-car-side"></i> Right Side Parts</div>
                <button type="button" class="btn btn-silver" style="padding: 3px 10px; font-size: 0.75rem;" onclick="selectAllPartsInSide('right_side', true)">Select All Parts</button>
              </div>
              <div class="parts-pill-grid">
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Right Side" value="Right Fronter / Fender" onchange="syncPartsSelection()"><span>Right Fronter / Fender</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Right Side" value="Right Front Door" onchange="syncPartsSelection()"><span>Right Front Door</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Right Side" value="Right Rear Door" onchange="syncPartsSelection()"><span>Right Back Door</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Right Side" value="Right Quarter Panel" onchange="syncPartsSelection()"><span>Right Quarter Panel</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Right Side" value="Right Running Board" onchange="syncPartsSelection()"><span>Right Running Board</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Right Side" value="Right Side Mirror" onchange="syncPartsSelection()"><span>Right Side Mirror (ORVM)</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Right Side" value="Right Pillars (A/B/C)" onchange="syncPartsSelection()"><span>Right Pillars</span></label>
              </div>
            </div>

            <!-- FRONT BUMPER PARTS -->
            <div class="side-parts-box" id="parts-box-front_bumper" style="display: none;">
              <div class="side-parts-header">
                <div style="font-weight: 700; color: var(--gold-primary);"><i class="fa-solid fa-bullseye"></i> Front Bumper & Front Parts</div>
                <button type="button" class="btn btn-silver" style="padding: 3px 10px; font-size: 0.75rem;" onclick="selectAllPartsInSide('front_bumper', true)">Select All Parts</button>
              </div>
              <div class="parts-pill-grid">
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Front Bumper" value="Front Bumper Main" onchange="syncPartsSelection()"><span>Front Bumper Main</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Front Bumper" value="Front Grille" onchange="syncPartsSelection()"><span>Front Grille</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Front Bumper" value="Front Bumper Guard" onchange="syncPartsSelection()"><span>Front Bumper Guard</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Front Bumper" value="Bonnet / Hood" onchange="syncPartsSelection()"><span>Bonnet / Engine Hood</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Front Bumper" value="Headlight Right" onchange="syncPartsSelection()"><span>Headlight Right</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Front Bumper" value="Headlight Left" onchange="syncPartsSelection()"><span>Headlight Left</span></label>
              </div>
            </div>

            <!-- BACK BUMPER PARTS -->
            <div class="side-parts-box" id="parts-box-back_bumper" style="display: none;">
              <div class="side-parts-header">
                <div style="font-weight: 700; color: var(--gold-primary);"><i class="fa-solid fa-shield-halved"></i> Back Bumper & Rear Parts</div>
                <button type="button" class="btn btn-silver" style="padding: 3px 10px; font-size: 0.75rem;" onclick="selectAllPartsInSide('back_bumper', true)">Select All Parts</button>
              </div>
              <div class="parts-pill-grid">
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Back Bumper" value="Back Bumper Main" onchange="syncPartsSelection()"><span>Back Bumper Main</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Back Bumper" value="Rear Bumper Guard" onchange="syncPartsSelection()"><span>Rear Bumper Guard</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Back Bumper" value="Tail Light Right" onchange="syncPartsSelection()"><span>Tail Light Right</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Back Bumper" value="Tail Light Left" onchange="syncPartsSelection()"><span>Tail Light Left</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Back Bumper" value="Rear License Plate Area" onchange="syncPartsSelection()"><span>Rear License Plate Housing</span></label>
              </div>
            </div>

            <!-- TOP / ROOF PARTS -->
            <div class="side-parts-box" id="parts-box-top_roof" style="display: none;">
              <div class="side-parts-header">
                <div style="font-weight: 700; color: var(--gold-primary);"><i class="fa-solid fa-arrow-up-from-ground-water"></i> Top / Roof Parts</div>
                <button type="button" class="btn btn-silver" style="padding: 3px 10px; font-size: 0.75rem;" onclick="selectAllPartsInSide('top_roof', true)">Select All Parts</button>
              </div>
              <div class="parts-pill-grid">
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Top / Roof" value="Main Roof Panel" onchange="syncPartsSelection()"><span>Main Roof Panel</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Top / Roof" value="Sunroof Assembly" onchange="syncPartsSelection()"><span>Sunroof Glass & Frame</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Top / Roof" value="Front Windshield Frame" onchange="syncPartsSelection()"><span>Front Windshield Frame</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Top / Roof" value="Rear Windshield Frame" onchange="syncPartsSelection()"><span>Rear Windshield Frame</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Top / Roof" value="Roof Rails" onchange="syncPartsSelection()"><span>Roof Rails</span></label>
              </div>
            </div>

            <!-- BOTTOM / UNDERBODY PARTS -->
            <div class="side-parts-box" id="parts-box-bottom_underbody" style="display: none;">
              <div class="side-parts-header">
                <div style="font-weight: 700; color: var(--gold-primary);"><i class="fa-solid fa-arrow-down-up-lock"></i> Bottom / Underbody Parts</div>
                <button type="button" class="btn btn-silver" style="padding: 3px 10px; font-size: 0.75rem;" onclick="selectAllPartsInSide('bottom_underbody', true)">Select All Parts</button>
              </div>
              <div class="parts-pill-grid">
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Bottom / Underbody" value="Underbody Engine Shield" onchange="syncPartsSelection()"><span>Underbody Engine Shield</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Bottom / Underbody" value="Underside Running Board" onchange="syncPartsSelection()"><span>Underside Running Board</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Bottom / Underbody" value="Exhaust System Guard" onchange="syncPartsSelection()"><span>Exhaust System Guard</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Bottom / Underbody" value="Subframe Mounts" onchange="syncPartsSelection()"><span>Subframe / Suspension Mounts</span></label>
              </div>
            </div>

            <!-- BOOT SPACE PARTS -->
            <div class="side-parts-box" id="parts-box-boot_space" style="display: none;">
              <div class="side-parts-header">
                <div style="font-weight: 700; color: var(--gold-primary);"><i class="fa-solid fa-box-open"></i> Boot Space & Dikki Parts</div>
                <button type="button" class="btn btn-silver" style="padding: 3px 10px; font-size: 0.75rem;" onclick="selectAllPartsInSide('boot_space', true)">Select All Parts</button>
              </div>
              <div class="parts-pill-grid">
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Boot Space" value="Boot Lid / Dikki Door" onchange="syncPartsSelection()"><span>Boot Lid / Dikki Door</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Boot Space" value="Boot Inner Floor" onchange="syncPartsSelection()"><span>Boot Inner Floor</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Boot Space" value="Tailgate Assembly" onchange="syncPartsSelection()"><span>Tailgate Assembly</span></label>
                <label class="part-pill-label"><input type="checkbox" class="part-cb" data-side-label="Boot Space" value="Boot Lock & Hinge" onchange="syncPartsSelection()"><span>Boot Lock & Hinge</span></label>
              </div>
            </div>

          </div>

          <!-- Live Selected Summary Banner -->
          <div id="selected-parts-summary-box" style="margin-top: 20px; padding: 14px 18px; border-radius: var(--radius-sm); background: rgba(212, 175, 55, 0.08); border: 1px solid var(--border-gold); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
              <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--gold-primary); letter-spacing: 0.5px;">
                <i class="fa-solid fa-list-check"></i> Selected Work / Damage Areas:
              </div>
              <div id="selected-parts-text" style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-top: 4px;">
                No specific side/parts selected yet. Click any side above to choose parts.
              </div>
            </div>
            <span id="selected-parts-badge" class="badge" style="background: var(--gold-primary); color: #000; font-weight: 800; font-size: 0.9rem; padding: 6px 14px;">0 Parts</span>
          </div>
        </div>

        <!-- SECTION 4: FINANCIAL AMOUNTS & PAYMENT DETAILS -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-calculator text-gold"></i> 4. Financial Amounts & Payment Details</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Total Vehicle Amount (Estimate) <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(e.g. ₹15,000)</span></label>
              <div style="position: relative;">
                <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gold-primary); font-weight: 700; font-size: 1rem;">₹</span>
                <input type="number" step="1" id="estimate_amount" name="estimate_amount" class="form-control" style="padding-left: 32px;" placeholder="Enter the Amount" value="<?php echo (isset($_POST['estimate_amount']) && $_POST['estimate_amount'] !== '' && (float)$_POST['estimate_amount'] > 0) ? (int)$_POST['estimate_amount'] : ''; ?>" oninput="calculateVehicleAmounts()">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Advance Amount Paid <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(e.g. ₹5,000)</span></label>
              <div style="position: relative;">
                <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #2ECC71; font-weight: 700; font-size: 1rem;">₹</span>
                <input type="number" step="1" id="advance_amount" name="advance_amount" class="form-control" style="padding-left: 32px;" placeholder="Enter the Amount" value="<?php echo (isset($_POST['advance_amount']) && $_POST['advance_amount'] !== '' && (float)$_POST['advance_amount'] > 0) ? (int)$_POST['advance_amount'] : ''; ?>" oninput="calculateVehicleAmounts()">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Calculated Vehicle Balance Due</label>
              <div id="calc_balance_display" style="font-size: 1.4rem; font-weight: 800; color: #E74C3C; padding: 10px 0;">₹0</div>
            </div>
          </div>

          <!-- MECHANIC FINANCIAL BREAKDOWN -->
          <div id="mechanic-financials-box" style="display: <?php echo ($_POST['is_mechanic_vehicle'] ?? '') === 'Yes' ? 'block' : 'none'; ?>; margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--border-gold);">
            <div style="font-weight: 700; color: var(--gold-primary); margin-bottom: 15px; font-size: 1rem;">
              <i class="fa-solid fa-coins"></i> Workshop / Mechanic Financial Breakdown
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Mechanic Total / Agreed Amount <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(e.g. ₹12,000)</span></label>
                <div style="position: relative;">
                  <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gold-primary); font-weight: 700; font-size: 1rem;">₹</span>
                  <input type="number" step="1" id="mechanic_total_amount" name="mechanic_total_amount" class="form-control" style="padding-left: 32px;" placeholder="Enter the Amount" value="<?php echo (isset($_POST['mechanic_total_amount']) && $_POST['mechanic_total_amount'] !== '' && (float)$_POST['mechanic_total_amount'] > 0) ? (int)$_POST['mechanic_total_amount'] : ''; ?>" oninput="calculateVehicleAmounts()">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Given Amount to Mechanic <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(e.g. ₹3,000)</span></label>
                <div style="position: relative;">
                  <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #2ECC71; font-weight: 700; font-size: 1rem;">₹</span>
                  <input type="number" step="1" id="mechanic_given_amount" name="mechanic_given_amount" class="form-control" style="padding-left: 32px;" placeholder="Enter the Amount" value="<?php echo (isset($_POST['mechanic_given_amount']) && $_POST['mechanic_given_amount'] !== '' && (float)$_POST['mechanic_given_amount'] > 0) ? (int)$_POST['mechanic_given_amount'] : ''; ?>" oninput="calculateVehicleAmounts()">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Mechanic Balance Payable</label>
                <div id="calc_mech_balance_display" style="font-size: 1.4rem; font-weight: 800; color: #F39C12; padding: 10px 0;">₹0</div>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 5: BEFORE PAINTING PHOTOS UPLOAD -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-camera text-gold"></i> 5. Before Painting Photos (Upload & Camera)</div>
          </div>

          <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
            <!-- Button 1: Upload Photos -->
            <button type="button" class="btn btn-gold" onclick="document.getElementById('photo-upload-input').click();">
              <i class="fa-solid fa-folder-open"></i> Upload Photos
            </button>
            <input type="file" id="photo-upload-input" name="damage_photos[]" multiple accept="image/*" style="display: none;">

            <!-- Button 2: Take Photo (Camera) -->
            <button type="button" class="btn btn-outline-gold" onclick="triggerDeviceCamera({ targetInputId: 'photo-upload-input', fallbackCameraInputId: 'photo-camera-input', previewGalleryId: 'preview-gallery' });">
              <i class="fa-solid fa-camera"></i> 📷 Take Photo
            </button>
            <input type="file" id="photo-camera-input" accept="image/*" capture="environment" style="display: none;">
          </div>

          <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 15px;">
            Formats allowed: JPG, JPEG, PNG, WEBP (Max 5 MB per image). Use "Take Photo" to capture directly using device or mobile camera.
          </p>

          <!-- Live Preview Gallery -->
          <div id="preview-gallery" class="preview-gallery"></div>
        </div>

        <div style="text-align: right; margin-top: 25px;">
          <button type="submit" id="submit-car-btn" class="btn btn-gold" style="padding: 14px 40px; font-size: 1.05rem;">
            <i class="fa-solid fa-circle-check"></i> CREATE CAR WORK ENTRY
          </button>
        </div>

      </form>
    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/camera.js"></script>
<script>
const customersDatabase = <?php echo json_encode($allCustomers); ?>;
const defaultNextCustomerId = "<?php echo e($nextCustomerId); ?>";

function onCustomerPhoneInput(phone) {
  const cleanPhone = phone.trim();
  const found = customersDatabase.find(c => c.customer_no === cleanPhone);
  const statusDiv = document.getElementById('customer_found_status');

  if (found) {
    document.getElementById('customer_id').value = found.customer_id;
    document.getElementById('customer_name').value = found.customer_name;
    document.getElementById('alternate_phone').value = found.alternate_phone || '';
    document.getElementById('city').value = found.city || '';
    if (statusDiv) {
      statusDiv.innerHTML = '<span style="color: #27AE60;"><i class="fa-solid fa-circle-check"></i> Existing Customer Found (' + found.customer_name + ' - ' + found.customer_id + ')</span>';
    }
  } else {
    document.getElementById('customer_id').value = defaultNextCustomerId;
    if (statusDiv) {
      statusDiv.innerHTML = cleanPhone.length >= 5 ? '<span style="color: var(--gold-dark);"><i class="fa-solid fa-user-plus"></i> New Customer Registration</span>' : '';
    }
  }
}

function toggleMechanicSection(show) {
  const mechDetailsBox = document.getElementById('mechanic-details-box');
  const mechFinancialsBox = document.getElementById('mechanic-financials-box');
  if (mechDetailsBox) mechDetailsBox.style.display = show ? 'block' : 'none';
  if (mechFinancialsBox) mechFinancialsBox.style.display = show ? 'block' : 'none';
}

function calculateVehicleAmounts() {
  const estimate = parseFloat(document.getElementById('estimate_amount')?.value || 0);
  const advance = parseFloat(document.getElementById('advance_amount')?.value || 0);
  const vehicleBalance = Math.max(0, estimate - advance);

  const calcBalDisp = document.getElementById('calc_balance_display');
  if (calcBalDisp) {
    calcBalDisp.textContent = '₹' + Math.round(vehicleBalance).toLocaleString('en-IN');
  }

  const mechTotal = parseFloat(document.getElementById('mechanic_total_amount')?.value || 0);
  const mechGiven = parseFloat(document.getElementById('mechanic_given_amount')?.value || 0);
  const mechBalance = Math.max(0, mechTotal - mechGiven);

  const calcMechBalDisp = document.getElementById('calc_mech_balance_display');
  if (calcMechBalDisp) {
    calcMechBalDisp.textContent = '₹' + Math.round(mechBalance).toLocaleString('en-IN');
  }
}

function toggleSideSection(sideKey, isChecked) {
  const partsBox = document.getElementById('parts-box-' + sideKey);
  if (partsBox) {
    partsBox.style.display = isChecked ? 'block' : 'none';
  }
  if (!isChecked && partsBox) {
    partsBox.querySelectorAll('.part-cb').forEach(cb => cb.checked = false);
  }
  syncPartsSelection();
}

function selectAllPartsInSide(sideKey, checkVal) {
  const partsBox = document.getElementById('parts-box-' + sideKey);
  if (partsBox) {
    partsBox.querySelectorAll('.part-cb').forEach(cb => cb.checked = checkVal);
  }
  syncPartsSelection();
}

function syncPartsSelection() {
  const grouped = {};
  let totalCount = 0;

  document.querySelectorAll('.side-toggle-cb').forEach(sideCb => {
    const sideKey = sideCb.getAttribute('data-side');
    if (sideCb.checked) {
      const partsBox = document.getElementById('parts-box-' + sideKey);
      if (partsBox) {
        const checkedSubParts = Array.from(partsBox.querySelectorAll('.part-cb:checked'));
        if (checkedSubParts.length > 0) {
          const sideLabel = checkedSubParts[0].getAttribute('data-side-label') || sideKey;
          grouped[sideLabel] = checkedSubParts.map(cb => cb.value);
          totalCount += checkedSubParts.length;
        } else {
          const sideCardText = sideCb.closest('.side-card-btn')?.querySelector('span')?.textContent || sideKey;
          grouped[sideCardText] = ['Entire Section'];
          totalCount += 1;
        }
      }
    }
  });

  const formattedLines = [];
  for (const [side, parts] of Object.entries(grouped)) {
    formattedLines.push(side + ' (' + parts.join(', ') + ')');
  }

  const finalString = formattedLines.join(' | ');
  const inputEl = document.getElementById('selected_parts_input');
  if (inputEl) inputEl.value = finalString;

  const textDisp = document.getElementById('selected-parts-text');
  const badgeDisp = document.getElementById('selected-parts-badge');

  if (textDisp) {
    textDisp.textContent = finalString !== '' ? finalString : 'No specific side/parts selected yet. Click any side above to choose parts.';
  }
  if (badgeDisp) {
    badgeDisp.textContent = totalCount + (totalCount === 1 ? ' Part' : ' Parts');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  calculateVehicleAmounts();
  syncPartsSelection();

  const cameraInput = document.getElementById('photo-camera-input');
  const uploadInput = document.getElementById('photo-upload-input');
  const previewGallery = document.getElementById('preview-gallery');

  if (cameraInput && uploadInput && previewGallery) {
    cameraInput.addEventListener('change', (e) => {
      if (!e.target.files || e.target.files.length === 0) return;
      const file = e.target.files[0];
      
      const dt = new DataTransfer();
      if (uploadInput.files) {
        for (let i = 0; i < uploadInput.files.length; i++) {
          dt.items.add(uploadInput.files[i]);
        }
      }
      dt.items.add(file);
      uploadInput.files = dt.files;

      // Trigger change event on upload input to refresh preview
      const event = new Event('change', { bubbles: true });
      uploadInput.dispatchEvent(event);
      cameraInput.value = '';
    });
  // Double-submission and rapid double-click prevention
  const addCarForm = document.getElementById('add-car-form');
  const submitCarBtn = document.getElementById('submit-car-btn');
  let isCarSubmitting = false;

  if (addCarForm && submitCarBtn) {
    addCarForm.addEventListener('submit', function(e) {
      // 1. Client-side HTML5 validation check
      if (!addCarForm.checkValidity()) {
        // Validation failed; let the browser indicate missing required inputs
        return;
      }

      // 2. Prevent duplicate submits if already in progress
      if (isCarSubmitting) {
        e.preventDefault();
        return false;
      }

      // 3. Flag submission in progress
      isCarSubmitting = true;

      // 4. Update button text and visual loading state
      submitCarBtn.style.pointerEvents = 'none';
      submitCarBtn.classList.add('btn-loading');
      submitCarBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating car entry...';
      
      setTimeout(() => {
        submitCarBtn.disabled = true;
      }, 0);
    });
  }
});
</script>
</body>
</html>
