/* ============================================================
   SUN PAINTING WORKS - MAIN JAVASCRIPT
   Mobile Menu Toggle, Show/Hide Password, Image Previews & Financial Math
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile Navbar Menu Toggle (Public Site)
  const mobileToggle = document.querySelector('.mobile-toggle');
  const navLinks = document.querySelector('.nav-links');

  if (mobileToggle && navLinks) {
    mobileToggle.addEventListener('click', () => {
      navLinks.classList.toggle('active');
      const icon = mobileToggle.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
      }
    });
  }

  // 1b. Mobile Dashboard Sidebar Toggle & Backdrop
  const sidebar = document.querySelector('.sidebar');
  const sidebarToggleBtns = document.querySelectorAll('.sidebar-toggle-btn');
  let backdrop = document.querySelector('.sidebar-backdrop');

  if (sidebar && !backdrop) {
    backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);
  }

  function setSidebarOpen(isOpen) {
    if (!sidebar) return;
    if (isOpen) {
      sidebar.classList.add('mobile-open');
      if (backdrop) backdrop.classList.add('active');
    } else {
      sidebar.classList.remove('mobile-open');
      if (backdrop) backdrop.classList.remove('active');
    }

    sidebarToggleBtns.forEach(btn => {
      btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      btn.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
    });
  }

  if (sidebar) {
    // Initial state for toggle buttons
    const isInitiallyOpen = sidebar.classList.contains('mobile-open');
    sidebarToggleBtns.forEach(btn => {
      btn.setAttribute('aria-expanded', isInitiallyOpen ? 'true' : 'false');
      if (!btn.getAttribute('aria-label')) {
        btn.setAttribute('aria-label', isInitiallyOpen ? 'Close navigation menu' : 'Open navigation menu');
      }

      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const currentlyOpen = sidebar.classList.contains('mobile-open');
        setSidebarOpen(!currentlyOpen);
      });
    });

    // Close button inside sidebar
    const sidebarCloseBtns = document.querySelectorAll('.sidebar-close-btn');
    sidebarCloseBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        setSidebarOpen(false);
      });
    });

    // Backdrop click (click outside sidebar)
    if (backdrop) {
      backdrop.addEventListener('click', () => {
        setSidebarOpen(false);
      });
    }

    // Press Escape to close sidebar
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
        setSidebarOpen(false);
      }
    });

    // Close mobile sidebar on resize to desktop width
    window.addEventListener('resize', () => {
      if (window.innerWidth > 992 && sidebar.classList.contains('mobile-open')) {
        setSidebarOpen(false);
      }
    });

    // Close mobile sidebar when clicking menu links on mobile
    const sidebarLinks = sidebar.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 992) {
          setSidebarOpen(false);
        }
      });
    });
  }

  // 2. Password Visibility Toggle
  const togglePassBtns = document.querySelectorAll('.toggle-password-btn');
  togglePassBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (input) {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        const icon = this.querySelector('i');
        if (icon) {
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        }
      }
    });
  });

  // 3. Multi-Image File Selection Previewer
  const imageInput = document.getElementById('photo-upload-input');
  const previewGallery = document.getElementById('preview-gallery');

  if (imageInput && previewGallery) {
    let selectedFiles = [];

    imageInput.addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      previewGallery.innerHTML = '';
      
      files.forEach((file, index) => {
        if (!file.type.match('image.*')) return;
        if (file.size > 5 * 1024 * 1024) {
          alert(`File "${file.name}" exceeds maximum allowed size of 5 MB.`);
          return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
          const div = document.createElement('div');
          div.className = 'preview-item';
          div.innerHTML = `
            <img src="${event.target.result}" alt="Preview">
            <button type="button" class="preview-remove-btn" data-index="${index}">&times;</button>
          `;
          previewGallery.appendChild(div);
        };
        reader.readAsDataURL(file);
      });
    });

    previewGallery.addEventListener('click', (e) => {
      if (e.target.classList.contains('preview-remove-btn')) {
        const index = parseInt(e.target.getAttribute('data-index'));
        const dt = new DataTransfer();
        const { files } = imageInput;

        for (let i = 0; i < files.length; i++) {
          if (i !== index) {
            dt.items.add(files[i]);
          }
        }
        imageInput.files = dt.files;
        e.target.closest('.preview-item').remove();
      }
    });
  }

  // 4. Financial Calculations in Add / Update Car Forms
  const estimateInput = document.getElementById('estimate_amount');
  const finalInput = document.getElementById('final_amount');
  const extraWorkInputs = document.querySelectorAll('.extra-work-amount-val');
  const totalDisplay = document.getElementById('calc_total_display');
  const balanceDisplay = document.getElementById('calc_balance_display');
  const totalHidden = document.getElementById('total_amount_hidden');
  const balanceHidden = document.getElementById('balance_amount_hidden');

  function updateFinancials() {
    const estimate = parseFloat(estimateInput ? estimateInput.value : 0) || 0;
    const finalAmount = parseFloat(finalInput ? finalInput.value : 0) || 0;

    let extraTotal = 0;
    document.querySelectorAll('.extra-work-amount-val').forEach(input => {
      extraTotal += parseFloat(input.value) || 0;
    });

    const total = estimate + extraTotal;
    const balance = Math.max(0, total - finalAmount);

    if (totalDisplay) totalDisplay.innerText = '₹' + Math.round(total).toLocaleString('en-IN');
    if (balanceDisplay) balanceDisplay.innerText = '₹' + Math.round(balance).toLocaleString('en-IN');
    if (totalHidden) totalHidden.value = Math.round(total);
    if (balanceHidden) balanceHidden.value = Math.round(balance);
  }

  if (estimateInput) estimateInput.addEventListener('input', updateFinancials);
  if (finalInput) finalInput.addEventListener('input', updateFinancials);
});
