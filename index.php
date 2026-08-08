<?php
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section with 3D Car Viewer -->
<section id="home" class="hero">
  <div class="hero-container">
    <div class="hero-content">
      <div class="hero-badge">
        <i class="fa-solid fa-crown"></i> Premier Workshop
      </div>
      <h1 class="hero-title">SUN PAINTING <span class="text-gold">WORKS</span></h1>
      <div class="hero-motto">"PAINTED TO PERFECTION"</div>
      <p class="hero-subtitle">Professional Car Painting & Auto Body Works in Gobichettipalayam. Restoring your vehicle to immaculate showroom elegance.</p>
      
      <div class="hero-location">
        <i class="fa-solid fa-location-dot text-gold"></i>
        <span>Kullampalayam Pirivu, Gobichettipalayam, Erode – 638453</span>
      </div>

      <div class="hero-actions">
        <a href="#services" class="btn btn-gold"><i class="fa-solid fa-spray-can"></i> OUR SERVICES</a>
        <a href="login.php" class="btn btn-outline-gold"><i class="fa-solid fa-right-to-bracket"></i> WORKSHOP LOGIN</a>
      </div>
    </div>

    <!-- 3D Interactive Car Showroom Canvas -->
    <div class="hero-3d-wrapper">
      <div id="car3d-canvas"></div>
      <div class="car-3d-controls-hint">
        <i class="fa-solid fa-hand-pointer text-gold"></i> Drag to Rotate 360° | Scroll to Zoom
      </div>
    </div>
  </div>
</section>

<!-- Services Section -->
<section id="services" class="services-section">
  <div class="section-header">
    <div class="section-subtitle">What We Do</div>
    <h2 class="section-title">Our Master <span class="text-gold">Services</span></h2>
    <p style="color: var(--text-muted); margin-top: 10px;">We combine expert craftsmanship, modern auto-body technology, and premium paint materials to deliver stunning results.</p>
  </div>

  <div class="services-grid">
    <!-- Service 1 -->
    <div class="service-card">
      <div class="service-icon"><i class="fa-solid fa-spray-can"></i></div>
      <h3 class="service-name">Car Painting Works</h3>
      <p class="service-desc">Complete vehicle body repainting using high-gloss metallic paint, computer color matching, and dust-free baking for a factory mirror finish.</p>
    </div>

    <!-- Service 2 -->
    <div class="service-card">
      <div class="service-icon"><i class="fa-solid fa-hammer"></i></div>
      <h3 class="service-name">Denting Works</h3>
      <p class="service-desc">Expert panel dent pulling, body frame realigning, and heavy impact collision repair to restore perfect body contours.</p>
    </div>

    <!-- Service 3 -->
    <div class="service-card">
      <div class="service-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
      <h3 class="service-name">Car Polishing</h3>
      <p class="service-desc">Multi-stage paint correction, ceramic polymer coating, and high-shine machine polishing to eliminate oxidation and restore deep gloss.</p>
    </div>

    <!-- Service 4 -->
    <div class="service-card">
      <div class="service-icon"><i class="fa-solid fa-shield-halved"></i></div>
      <h3 class="service-name">Under Body Coating</h3>
      <p class="service-desc">Heavy-duty anti-corrosion rubberized underbody coating protecting your chassis from rust, road salt, moisture, and flying stones.</p>
    </div>

    <!-- Service 5 -->
    <div class="service-card">
      <div class="service-icon"><i class="fa-solid fa-eraser"></i></div>
      <h3 class="service-name">Scratch Removal</h3>
      <p class="service-desc">Advanced clear-coat buffing, wet sanding, and deep key scratch elimination without compromising original paint layers.</p>
    </div>

    <!-- Service 6 -->
    <div class="service-card">
      <div class="service-icon"><i class="fa-solid fa-brush"></i></div>
      <h3 class="service-name">Touch-Up Painting</h3>
      <p class="service-desc">Targeted spot touch-ups for stone chips, door dings, and bumper corner scuffs with seamless color blending.</p>
    </div>
  </div>
</section>

<!-- About Section -->
<section id="about" class="about-section">
  <div class="about-container">
    <div class="about-image-wrapper">
      <img src="assets/images/logo.png" alt="Sun Painting Works Official Logo" class="about-logo">
    </div>

    <div class="about-content">
      <div class="section-subtitle">About Sun Painting Works</div>
      <h2>Driven by Quality, <span class="text-gold">Crafted with Perfection</span></h2>
      <p class="about-text">
        Located at <strong>Kullampalayam Pirivu, Gobichettipalayam, Erode</strong>, <strong>Sun Painting Works</strong> is a leading car painting and auto body workshop dedicated to restoring cars to pristine condition.
      </p>
      <p class="about-text">
        With years of hands-on expertise in precision denting, high-finish metallic car painting, scratch removal, and ceramic polishing, our workshop handles every car with meticulous care and state-of-the-art tooling.
      </p>

      <div class="about-features">
        <div class="feature-item"><i class="fa-solid fa-circle-check"></i> High-Gloss Car Painting</div>
        <div class="feature-item"><i class="fa-solid fa-circle-check"></i> Precision Denting Repair</div>
        <div class="feature-item"><i class="fa-solid fa-circle-check"></i> Mirror Shine Polishing</div>
        <div class="feature-item"><i class="fa-solid fa-circle-check"></i> Anti-Rust Under Coating</div>
        <div class="feature-item"><i class="fa-solid fa-circle-check"></i> Deep Scratch Removal</div>
        <div class="feature-item"><i class="fa-solid fa-circle-check"></i> Spot Touch-Up Paint</div>
      </div>
    </div>
  </div>
</section>

<!-- Contact Section -->
<section id="contact" class="contact-section">
  <div class="section-header">
    <div class="section-subtitle">Get In Touch</div>
    <h2 class="section-title">Visit Our <span class="text-gold">Workshop</span></h2>
  </div>

  <div class="contact-card-main">
    <div class="contact-info">
      <h3>SUN PAINTING WORKS</h3>
      <p class="contact-address">
        Kullampalayam Pirivu,<br>
        Gobichettipalayam,<br>
        Erode – 638453, Tamil Nadu, India
      </p>

      <div class="phone-numbers">
        <div class="phone-item">
          <i class="fa-solid fa-phone"></i>
          <a href="tel:9442399079" style="color: inherit; text-decoration: none;">94423 99079</a>
        </div>
        <div class="phone-item">
          <i class="fa-solid fa-phone"></i>
          <a href="tel:9842299079" style="color: inherit; text-decoration: none;">98422 99079</a>
        </div>
      </div>
    </div>

    <div class="contact-buttons">
      <a href="tel:9442399079" class="contact-btn-item btn-call">
        <i class="fa-solid fa-phone-volume"></i> CALL NOW
      </a>
      <a href="https://wa.me/919442399079?text=Hello%20Sun%20Painting%20Works,%20I%20want%20an%20estimate%20for%20my%20car" target="_blank" class="contact-btn-item btn-whatsapp">
        <i class="fa-brands fa-whatsapp"></i> WHATSAPP US
      </a>
      <a href="https://maps.app.goo.gl/ebu2WLZGgK1B8vfp9?g_st=aw" target="_blank" class="contact-btn-item btn-maps">
        <i class="fa-solid fa-location-arrow"></i> GOOGLE MAPS LOCATION
      </a>
    </div>
  </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
