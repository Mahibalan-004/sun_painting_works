/**
 * Universal Camera Handler for Sun Painting Works
 * Supports Mobile (native camera with/without SSL) and Laptop/Desktop (Webcam stream with SSL/localhost + fallback)
 */

(function () {
  let activeStream = null;
  let currentTargetInput = null;
  let currentPreviewGallery = null;
  let currentAutoSubmit = false;
  let currentFacingMode = 'environment';

  // Mobile device detection
  function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
      (navigator.maxTouchPoints > 1 && window.innerWidth <= 820);
  }

  // Create Modal DOM Elements once
  function ensureCameraModal() {
    if (document.getElementById('sun-camera-modal')) return;

    const modalHtml = `
      <div id="sun-camera-modal" class="camera-modal-backdrop" style="display: none;">
        <div class="camera-modal-dialog">
          <div class="camera-modal-header">
            <div style="font-weight: 700; color: var(--gold-primary); font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
              <i class="fa-solid fa-camera"></i> Laptop / Device Camera
            </div>
            <button type="button" class="camera-modal-close" onclick="closeCameraModal()">&times;</button>
          </div>

          <div class="camera-modal-body">
            <div id="camera-loading-notice" style="display: none; position: absolute; inset: 0; background: rgba(0,0,0,0.85); color: #FFF; z-index: 10; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 20px; text-align: center;">
              <i class="fa-solid fa-spinner fa-spin text-gold" style="font-size: 2rem;"></i>
              <div style="font-weight: 600;">Accessing Camera...</div>
            </div>

            <div id="camera-error-notice" style="display: none; position: absolute; inset: 0; background: rgba(20,20,20,0.95); color: #FFF; z-index: 10; flex-direction: column; align-items: center; justify-content: center; gap: 15px; padding: 25px; text-align: center;">
              <i class="fa-solid fa-triangle-exclamation" style="font-size: 2.2rem; color: #E74C3C;"></i>
              <div id="camera-error-msg" style="font-size: 0.95rem; color: #E2E8F0;">Camera access was not permitted or is unavailable.</div>
              <button type="button" class="btn btn-gold" style="padding: 8px 18px; font-size: 0.85rem;" onclick="triggerFallbackInput()">
                <i class="fa-solid fa-folder-open"></i> Browse / Select File Instead
              </button>
            </div>

            <video id="sun-camera-video" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; background: #000; border-radius: 8px;"></video>
            <canvas id="sun-camera-canvas" style="display: none;"></canvas>
            <img id="sun-camera-snapshot-preview" style="display: none; width: 100%; height: 100%; object-fit: contain; background: #000; border-radius: 8px;" alt="Snapshot Preview">
          </div>

          <div class="camera-modal-footer">
            <div id="camera-controls-live" style="display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 10px;">
              <button type="button" class="btn btn-silver" style="padding: 9px 16px; font-size: 0.85rem;" onclick="switchCamera()">
                <i class="fa-solid fa-rotate"></i> Flip Camera
              </button>

              <button type="button" class="btn btn-gold" style="padding: 11px 24px; font-size: 0.95rem; font-weight: 700;" onclick="capturePhotoSnapshot()">
                <i class="fa-solid fa-camera"></i> Capture Photo
              </button>

              <button type="button" class="btn btn-silver" style="padding: 9px 16px; font-size: 0.85rem;" onclick="closeCameraModal()">
                Cancel
              </button>
            </div>

            <div id="camera-controls-review" style="display: none; justify-content: space-between; align-items: center; width: 100%; gap: 10px;">
              <button type="button" class="btn btn-silver" style="padding: 9px 18px; font-size: 0.85rem;" onclick="retakePhotoSnapshot()">
                <i class="fa-solid fa-rotate-left"></i> Retake
              </button>

              <button type="button" class="btn btn-gold" style="padding: 11px 24px; font-size: 0.95rem; font-weight: 700;" onclick="confirmCapturedPhoto()">
                <i class="fa-solid fa-check"></i> Use This Photo
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    const div = document.createElement('div');
    div.innerHTML = modalHtml;
    document.body.appendChild(div.firstElementChild);
  }

  // Trigger Native Fallback Input
  window.triggerFallbackInput = function() {
    closeCameraModal();
    if (currentTargetInput) {
      currentTargetInput.click();
    }
  };

  // Main Entry Point
  window.triggerDeviceCamera = function (options) {
    const { targetInputId, fallbackCameraInputId, previewGalleryId, autoSubmit } = options;
    const targetInput = document.getElementById(targetInputId);
    const fallbackInput = document.getElementById(fallbackCameraInputId) || targetInput;

    currentTargetInput = targetInput;
    currentPreviewGallery = previewGalleryId ? document.getElementById(previewGalleryId) : null;
    currentAutoSubmit = !!autoSubmit;

    // 1. If Mobile device: native input with capture="environment" opens the phone camera directly!
    // Works with SSL and without SSL on all iOS & Android versions!
    if (isMobileDevice()) {
      if (fallbackInput) {
        fallbackInput.click();
      } else if (targetInput) {
        targetInput.click();
      }
      return;
    }

    // 2. If Laptop / Desktop:
    // Check if getUserMedia WebRTC is available
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      ensureCameraModal();
      openLaptopCameraModal();
    } else {
      // Laptop on non-SSL HTTP where browser blocks getUserMedia: fallback to file selector / camera input
      if (fallbackInput) {
        fallbackInput.click();
      } else if (targetInput) {
        targetInput.click();
      }
    }
  };

  async function openLaptopCameraModal() {
    const modal = document.getElementById('sun-camera-modal');
    const video = document.getElementById('sun-camera-video');
    const loading = document.getElementById('camera-loading-notice');
    const errorNotice = document.getElementById('camera-error-notice');
    const snapshotPreview = document.getElementById('sun-camera-snapshot-preview');
    const liveControls = document.getElementById('camera-controls-live');
    const reviewControls = document.getElementById('camera-controls-review');

    modal.style.display = 'flex';
    loading.style.display = 'flex';
    errorNotice.style.display = 'none';
    video.style.display = 'block';
    snapshotPreview.style.display = 'none';
    liveControls.style.display = 'flex';
    reviewControls.style.display = 'none';

    stopActiveStream();

    try {
      const constraints = {
        video: {
          facingMode: currentFacingMode,
          width: { ideal: 1280 },
          height: { ideal: 720 }
        },
        audio: false
      };

      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      activeStream = stream;
      video.srcObject = stream;
      await video.play();
      loading.style.display = 'none';
    } catch (err) {
      console.warn('Webcam stream failed:', err);
      loading.style.display = 'none';
      errorNotice.style.display = 'flex';
      const msg = document.getElementById('camera-error-msg');
      if (msg) {
        msg.textContent = 'Webcam unavailable or permission denied. You can select a photo file directly.';
      }
    }
  }

  function stopActiveStream() {
    if (activeStream) {
      activeStream.getTracks().forEach(track => track.stop());
      activeStream = null;
    }
  }

  window.closeCameraModal = function () {
    const modal = document.getElementById('sun-camera-modal');
    if (modal) modal.style.display = 'none';
    stopActiveStream();
  };

  window.switchCamera = function () {
    currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
    openLaptopCameraModal();
  };

  window.capturePhotoSnapshot = function () {
    const video = document.getElementById('sun-camera-video');
    const canvas = document.getElementById('sun-camera-canvas');
    const preview = document.getElementById('sun-camera-snapshot-preview');
    const liveControls = document.getElementById('camera-controls-live');
    const reviewControls = document.getElementById('camera-controls-review');

    if (!video || !canvas) return;

    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    preview.src = dataUrl;
    preview.style.display = 'block';
    video.style.display = 'none';

    liveControls.style.display = 'none';
    reviewControls.style.display = 'flex';
  };

  window.retakePhotoSnapshot = function () {
    const video = document.getElementById('sun-camera-video');
    const preview = document.getElementById('sun-camera-snapshot-preview');
    const liveControls = document.getElementById('camera-controls-live');
    const reviewControls = document.getElementById('camera-controls-review');

    preview.style.display = 'none';
    video.style.display = 'block';
    liveControls.style.display = 'flex';
    reviewControls.style.display = 'none';
  };

  window.confirmCapturedPhoto = function () {
    const canvas = document.getElementById('sun-camera-canvas');
    if (!canvas || !currentTargetInput) {
      closeCameraModal();
      return;
    }

    canvas.toBlob((blob) => {
      if (!blob) {
        closeCameraModal();
        return;
      }

      const fileName = 'photo_' + Date.now() + '.jpg';
      const file = new File([blob], fileName, { type: 'image/jpeg' });

      // Add to target input using DataTransfer
      const dt = new DataTransfer();
      if (currentTargetInput.files && currentTargetInput.multiple) {
        for (let i = 0; i < currentTargetInput.files.length; i++) {
          dt.items.add(currentTargetInput.files[i]);
        }
      }
      dt.items.add(file);
      currentTargetInput.files = dt.files;

      // Dispatch change event to update preview or trigger form submit
      const changeEvt = new Event('change', { bubbles: true });
      currentTargetInput.dispatchEvent(changeEvt);

      // Add thumbnail to preview gallery if present
      if (currentPreviewGallery) {
        addPreviewThumbnail(currentPreviewGallery, canvas.toDataURL('image/jpeg', 0.8), fileName, file);
      }

      closeCameraModal();

      if (currentAutoSubmit && currentTargetInput.form) {
        currentTargetInput.form.submit();
      }
    }, 'image/jpeg', 0.92);
  };

  function addPreviewThumbnail(gallery, dataUrl, name, file) {
    const wrap = document.createElement('div');
    wrap.className = 'preview-item';
    wrap.style.position = 'relative';
    wrap.style.display = 'inline-block';
    wrap.style.margin = '6px';

    wrap.innerHTML = `
      <img src="${dataUrl}" alt="${name}" style="width: 90px; height: 90px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-gold);">
      <button type="button" style="position: absolute; top: -6px; right: -6px; background: #E74C3C; color: #FFF; border: none; border-radius: 50%; width: 22px; height: 22px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center;">&times;</button>
    `;

    wrap.querySelector('button').addEventListener('click', () => {
      wrap.remove();
      if (currentTargetInput && currentTargetInput.files) {
        const dt = new DataTransfer();
        for (let i = 0; i < currentTargetInput.files.length; i++) {
          if (currentTargetInput.files[i] !== file) {
            dt.items.add(currentTargetInput.files[i]);
          }
        }
        currentTargetInput.files = dt.files;
      }
    });

    gallery.appendChild(wrap);
  }
})();
