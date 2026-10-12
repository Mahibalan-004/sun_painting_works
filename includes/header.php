<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';

// Canonical Public URL Configuration
// In production, specify the public domain via APP_URL environment variable or define it below.
// Example: define('APP_URL', 'https://www.sunpaintingworks.com');
// Note: Per production guidelines, localhost, private IP addresses, and invented domains are strictly prohibited in public metadata.
if (!defined('APP_URL')) {
    define('APP_URL', getenv('APP_URL') !== false ? trim(getenv('APP_URL')) : '');
}

$appUrlConfigured = defined('APP_URL') && APP_URL !== '';
$publicBaseUrl = $appUrlConfigured ? rtrim(APP_URL, '/') : '';
$canonicalUrl = $appUrlConfigured ? $publicBaseUrl . '/' : '';
$isHttps = $appUrlConfigured && (stripos($publicBaseUrl, 'https://') === 0);

$shareImageRel = 'assets/images/social-preview.png';
$shareImageUrl = $appUrlConfigured ? $publicBaseUrl . '/' . $shareImageRel : $shareImageRel;
$shareImageSecureUrl = $isHttps ? $shareImageUrl : '';

$ogTitle = 'SUN PAINTING WORKS | Painted to Perfection';
$ogDescription = 'Give your car a fresh, flawless finish at Sun Painting Works. Explore professional car painting, denting, scratch removal, polishing, and touch-up services in Gobichettipalayam, Tamil Nadu.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sun Painting Works | Professional Car Painting & Auto Body Workshop</title>
  <meta name="description" content="Sun Painting Works - Professional Car Painting, Denting, Polishing, Under Body Coating, Scratch Removal, and Touch-Up Painting in Gobichettipalayam, Erode.">
  
  <!-- Canonical URL -->
  <?php if ($appUrlConfigured): ?>
  <link rel="canonical" href="<?php echo e($canonicalUrl); ?>">
  <?php else: ?>
  <link rel="canonical" href="/">
  <?php endif; ?>

  <!-- Open Graph / Facebook Social Sharing Metadata -->
  <meta property="og:site_name" content="Sun Painting Works">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="en_IN">
  <meta property="og:title" content="<?php echo e($ogTitle); ?>">
  <meta property="og:description" content="<?php echo e($ogDescription); ?>">
  <?php if ($appUrlConfigured): ?>
  <meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
  <meta property="og:image" content="<?php echo e($shareImageUrl); ?>">
  <?php if (!empty($shareImageSecureUrl)): ?>
  <meta property="og:image:secure_url" content="<?php echo e($shareImageSecureUrl); ?>">
  <?php endif; ?>
  <?php else: ?>
  <meta property="og:url" content="/">
  <meta property="og:image" content="<?php echo e($shareImageRel); ?>">
  <?php endif; ?>
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Sun Painting Works - Painted to Perfection - Car Painting & Auto Body Workshop">

  <!-- Twitter / X Card Metadata -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo e($ogTitle); ?>">
  <meta name="twitter:description" content="<?php echo e($ogDescription); ?>">
  <?php if ($appUrlConfigured): ?>
  <meta name="twitter:image" content="<?php echo e($shareImageUrl); ?>">
  <?php else: ?>
  <meta name="twitter:image" content="<?php echo e($shareImageRel); ?>">
  <?php endif; ?>
  <meta name="twitter:image:alt" content="Sun Painting Works - Automotive Painting & Auto Body Workshop">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- FontAwesome Icons CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <link rel="shortcut icon" href="assets/images/logo.png" type="image/png">
  <link rel="apple-touch-icon" href="assets/images/logo.png">

  <!-- Global Stylesheet & Theme Engine -->
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="assets/js/theme.js"></script>
</head>
<body>

  <!-- Navigation Bar -->
  <nav class="navbar">
    <a href="index.php" class="navbar-brand">
      <img src="assets/images/logo.png" alt="Sun Painting Works Logo" class="navbar-logo">
      <div class="navbar-title">
        <span class="main-name">SUN PAINTING WORKS</span>
        <span class="sub-name">PAINTED TO PERFECTION</span>
      </div>
    </a>
    
    <ul class="nav-links">
      <li><a href="index.php#home">Home</a></li>
      <li><a href="index.php#services">Services</a></li>
      <li><a href="index.php#about">About</a></li>
      <li><a href="index.php#contact">Contact</a></li>

      <?php if (isLoggedIn()): ?>
        <?php if (isAdmin()): ?>
          <li><a href="admin/dashboard.php" class="btn btn-gold"><i class="fa-solid fa-gauge-high"></i> Admin Portal</a></li>
        <?php else: ?>
          <li><a href="user/dashboard.php" class="btn btn-gold"><i class="fa-solid fa-user-gear"></i> My Dashboard</a></li>
        <?php endif; ?>
        <li><a href="logout.php" class="btn btn-outline-gold"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
      <?php else: ?>
        <li><a href="login.php" class="btn btn-gold"><i class="fa-solid fa-lock"></i> LOGIN</a></li>
      <?php endif; ?>
    </ul>

    <button class="mobile-toggle" aria-label="Toggle Navigation Menu">
      <i class="fa-solid fa-bars"></i>
    </button>
  </nav>
