<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sun Painting Works | Professional Car Painting & Auto Body Workshop</title>
  <meta name="description" content="Sun Painting Works - Professional Car Painting, Denting, Polishing, Under Body Coating, Scratch Removal, and Touch-Up Painting in Gobichettipalayam, Erode.">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- FontAwesome Icons CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="assets/images/logo.png">

  <!-- Global Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">
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
