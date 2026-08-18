<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$error = '';
$success = '';

// Generate next Customer ID automatically
$nextCustomerId = generateNextCustomerID($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Financial Amounts
    $estimate_amount        = isset($_POST['estimate_amount']) && $_POST['estimate_amount'] !== '' ? (float)$_POST['estimate_amount'] : 0.00;
    $advance_amount         = isset($_POST['advance_amount']) && $_POST['advance_amount'] !== '' ? (float)$_POST['advance_amount'] : (isset($_POST['final_amount']) && $_POST['final_amount'] !== '' ? (float)$_POST['final_amount'] : 0.00);
    $final_amount           = $advance_amount;
    
    $mechanic_total_amount  = isset($_POST['mechanic_total_amount']) && $_POST['mechanic_total_amount'] !== '' ? (float)$_POST['mechanic_total_amount'] : 0.00;
    $mechanic_given_amount  = isset($_POST['mechanic_given_amount']) && $_POST['mechanic_given_amount'] !== '' ? (float)$_POST['mechanic_given_amount'] : 0.00;
    $mechanic_balance_amount= max(0, $mechanic_total_amount - $mechanic_given_amount);

    if (empty($customer_id) || empty($customer_no) || empty($customer_name) || empty($car_number) || empty($car_name)) {
        $error = "Please fill in all required customer and car fields.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Insert or check existing Customer
            $custCheck = $pdo->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
            $custCheck->execute([$customer_id]);
            if ($custCheck->fetch()) {
                $customer_id = generateNextCustomerID($pdo);
            }

            $stmtCust = $pdo->prepare("INSERT INTO customers (customer_id, customer_no, alternate_phone, customer_name, city) VALUES (?, ?, ?, ?, ?)");
            $stmtCust->execute([$customer_id, $customer_no, $alternate_phone, $customer_name, $city]);

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
                payment_status, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");
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
                $_SESSION['user_id']
            ]);
            $car_id = $pdo->lastInsertId();

            // 4. Handle Damage Photo Uploads (both file picker & camera inputs)
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $uploadDir = __DIR__ . '/../uploads/damage/';

            if (!empty($_FILES['damage_photos']['name'][0])) {
                $filesCount = count($_FILES['damage_photos']['name']);
                for ($i = 0; $i < $filesCount; $i++) {
                    $fileName = $_FILES['damage_photos']['name'][$i];
                    $fileTmp = $_FILES['damage_photos']['tmp_name'][$i];
                    $fileSize = $_FILES['damage_photos']['size'][$i];
                    $fileError = $_FILES['damage_photos']['error'][$i];

                    if ($fileError === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if (empty($ext)) $ext = 'jpg';
                        if (in_array($ext, $allowedExts) && $fileSize <= 5 * 1024 * 1024) {
                            $uniqueName = 'dmg_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                            $targetPath = $uploadDir . $uniqueName;

                            if (move_uploaded_file($fileTmp, $targetPath)) {
                                $stmtPhoto = $pdo->prepare("INSERT INTO car_photos (car_id, photo_type, photo_path, uploaded_by) VALUES (?, 'damage', ?, ?)");
                                $stmtPhoto->execute([$car_id, 'uploads/damage/' . $uniqueName, $_SESSION['user_id']]);
                            }
                        }
                    }
                }
            }

            // 5. Add Initial Work History
            logWorkHistory($pdo, $car_id, 'New', 'Car registered into workshop system.', $_SESSION['user_id']);

            $pdo->commit();

            header("Location: update_car.php?id=" . $car_id . "&success=" . urlencode("Car job created successfully!"));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
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
      <h1 class="top-bar-title"><i class="fa-solid fa-car-tunnel text-gold"></i> Add New Car Job</h1>
      <a href="car_list.php" class="btn btn-outline-gold" style="padding: 8px 16px; font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Car List</a>
    </header>

    <div class="content-body">
      <?php if (!empty($error)): ?>
        <div style="background: rgba(231, 76, 60, 0.15); border: 1px solid #E74C3C; color: #FF6B6B; padding: 14px; border-radius: var(--radius-sm); margin-bottom: 25px;">
          <i class="fa-solid fa-triangle-exclamation"></i> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <form action="add_car.php" method="POST" enctype="multipart/form-data">
        
        <!-- SECTION 1: CUSTOMER DETAILS -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-address-card text-gold"></i> 1. Customer Details</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Customer ID (Auto-Generated)</label>
              <input type="text" name="customer_id" class="form-control" value="<?php echo e($nextCustomerId); ?>" readonly style="background: rgba(212,175,55,0.1); border-color: var(--border-gold); font-weight: 700; color: var(--gold-light);">
            </div>

            <div class="form-group">
              <label class="form-label">Customer Number *</label>
              <input type="text" name="customer_no" class="form-control" placeholder="e.g. 9442399079" required value="<?php echo e($_POST['customer_no'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Alternate Phone (Optional)</label>
              <input type="text" name="alternate_phone" class="form-control" placeholder="e.g. 9842299079" value="<?php echo e($_POST['alternate_phone'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Customer Name *</label>
              <input type="text" name="customer_name" class="form-control" placeholder="e.g. Ramesh Kumar" required value="<?php echo e($_POST['customer_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">City (Optional)</label>
              <input type="text" name="city" class="form-control" placeholder="e.g. Gobichettipalayam" value="<?php echo e($_POST['city'] ?? ''); ?>">
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

        <!-- SECTION 4: WORK & FINANCIAL ESTIMATE -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-calculator text-gold"></i> 4. Financial Amounts & Payment Details</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Total Vehicle Amount (Estimate)</label>
              <div style="position: relative;">
                <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gold-primary); font-weight: 700; font-size: 1rem;">₹</span>
                <input type="number" step="0.01" id="estimate_amount" name="estimate_amount" class="form-control" style="padding-left: 32px;" placeholder="0.00" value="<?php echo isset($_POST['estimate_amount']) && $_POST['estimate_amount'] !== '' ? e($_POST['estimate_amount']) : '0.00'; ?>" oninput="calculateVehicleAmounts()">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Advance Amount Paid</label>
              <div style="position: relative;">
                <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #2ECC71; font-weight: 700; font-size: 1rem;">₹</span>
                <input type="number" step="0.01" id="advance_amount" name="advance_amount" class="form-control" style="padding-left: 32px;" placeholder="0.00" value="<?php echo isset($_POST['advance_amount']) && $_POST['advance_amount'] !== '' ? e($_POST['advance_amount']) : (isset($_POST['final_amount']) ? e($_POST['final_amount']) : '0.00'); ?>" oninput="calculateVehicleAmounts()">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Calculated Vehicle Balance Due</label>
              <div id="calc_balance_display" style="font-size: 1.4rem; font-weight: 800; color: #E74C3C; padding: 10px 0;">₹0.00</div>
            </div>
          </div>

          <!-- MECHANIC FINANCIAL BREAKDOWN -->
          <div id="mechanic-financials-box" style="display: <?php echo ($_POST['is_mechanic_vehicle'] ?? '') === 'Yes' ? 'block' : 'none'; ?>; margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--border-gold);">
            <div style="font-weight: 700; color: var(--gold-primary); margin-bottom: 15px; font-size: 1rem;">
              <i class="fa-solid fa-coins"></i> Workshop / Mechanic Financial Breakdown
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Mechanic Total / Agreed Amount</label>
                <div style="position: relative;">
                  <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gold-primary); font-weight: 700; font-size: 1rem;">₹</span>
                  <input type="number" step="0.01" id="mechanic_total_amount" name="mechanic_total_amount" class="form-control" style="padding-left: 32px;" placeholder="0.00" value="<?php echo isset($_POST['mechanic_total_amount']) ? e($_POST['mechanic_total_amount']) : '0.00'; ?>" oninput="calculateVehicleAmounts()">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Given Amount to Mechanic</label>
                <div style="position: relative;">
                  <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #2ECC71; font-weight: 700; font-size: 1rem;">₹</span>
                  <input type="number" step="0.01" id="mechanic_given_amount" name="mechanic_given_amount" class="form-control" style="padding-left: 32px;" placeholder="0.00" value="<?php echo isset($_POST['mechanic_given_amount']) ? e($_POST['mechanic_given_amount']) : '0.00'; ?>" oninput="calculateVehicleAmounts()">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Mechanic Balance Payable</label>
                <div id="calc_mech_balance_display" style="font-size: 1.4rem; font-weight: 800; color: #F39C12; padding: 10px 0;">₹0.00</div>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 5: DAMAGE PHOTOS UPLOAD -->
        <div class="card-box">
          <div class="card-box-header">
            <div class="card-box-title"><i class="fa-solid fa-camera text-gold"></i> 5. Damage Photos (Upload & Camera)</div>
          </div>

          <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
            <!-- Button 1: Upload Photos -->
            <button type="button" class="btn btn-gold" onclick="document.getElementById('photo-upload-input').click();">
              <i class="fa-solid fa-folder-open"></i> Upload Photos
            </button>
            <input type="file" id="photo-upload-input" name="damage_photos[]" multiple accept="image/*" style="display: none;">

            <!-- Button 2: Take Photo (Camera) -->
            <button type="button" class="btn btn-outline-gold" onclick="document.getElementById('photo-camera-input').click();">
              <i class="fa-solid fa-camera"></i> 📷 Take Photo
            </button>
            <input type="file" id="photo-camera-input" accept="image/*" capture="environment" style="display: none;">
          </div>

          <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 15px;">
            Formats allowed: JPG, JPEG, PNG, WEBP (Max 5 MB per image). Use "Take Photo" on mobile devices to open camera directly.
          </p>

          <!-- Live Preview Gallery -->
          <div id="preview-gallery" class="preview-gallery"></div>
        </div>

        <div style="text-align: right; margin-top: 25px;">
          <button type="submit" class="btn btn-gold" style="padding: 14px 40px; font-size: 1.05rem;">
            <i class="fa-solid fa-circle-check"></i> CREATE CAR WORK ENTRY
          </button>
        </div>

      </form>
    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
<script>
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
    calcBalDisp.textContent = '₹' + vehicleBalance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  const mechTotal = parseFloat(document.getElementById('mechanic_total_amount')?.value || 0);
  const mechGiven = parseFloat(document.getElementById('mechanic_given_amount')?.value || 0);
  const mechBalance = Math.max(0, mechTotal - mechGiven);

  const calcMechBalDisp = document.getElementById('calc_mech_balance_display');
  if (calcMechBalDisp) {
    calcMechBalDisp.textContent = '₹' + mechBalance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  calculateVehicleAmounts();

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
  }
});
</script>
</body>
</html>
