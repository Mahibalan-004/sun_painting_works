/* ============================================================
   SUN PAINTING WORKS - 3D AUTOMOTIVE PAINTING WORKSHOP SCENE
   Realistic Passenger Car, Automotive Painter & Spray Gun,
   Paint Mist Atomization, Overhead Paint Booth Lighting,
   Smooth 360° Turntable Rotation, and OrbitControls Interaction.
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('car3d-canvas');
  if (!container) return;

  // 0. WebGL Support Check
  function isWebGLAvailable() {
    try {
      const canvas = document.createElement('canvas');
      return !!(window.WebGLRenderingContext && (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
    } catch (e) {
      return false;
    }
  }

  if (!isWebGLAvailable()) {
    container.innerHTML = `
      <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#d4af37;text-align:center;padding:20px;font-family:sans-serif;">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:2rem;margin-bottom:12px;"></i>
        <h4 style="margin:0 0 8px 0;color:#fff;">3D Workshop Experience</h4>
        <p style="margin:0;font-size:0.85rem;color:#94a3b8;">WebGL acceleration is required to render the interactive 3D painting scene.</p>
      </div>
    `;
    return;
  }

  // Loading Overlay inside container
  const loaderEl = document.createElement('div');
  loaderEl.id = 'scene-3d-loader';
  loaderEl.style.cssText = `
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: rgba(10, 11, 14, 0.75);
    backdrop-filter: blur(4px);
    z-index: 10;
    transition: opacity 0.5s ease;
    pointer-events: none;
    color: #d4af37;
    font-family: 'Outfit', sans-serif;
  `;
  loaderEl.innerHTML = `
    <div style="width: 44px; height: 44px; border: 3px solid rgba(212,175,55,0.2); border-top-color: #d4af37; border-radius: 50%; animation: spin3d 0.8s linear infinite;"></div>
    <div style="margin-top: 14px; font-size: 0.85rem; letter-spacing: 1.5px; font-weight: 600; text-transform: uppercase;">
      Preparing Paint Booth...
    </div>
    <style>@keyframes spin3d { to { transform: rotate(360deg); } }</style>
  `;
  container.style.position = 'relative';
  container.appendChild(loaderEl);

  function hideLoader() {
    if (loaderEl && loaderEl.parentNode) {
      loaderEl.style.opacity = '0';
      setTimeout(() => {
        if (loaderEl.parentNode) loaderEl.parentNode.removeChild(loaderEl);
      }, 500);
    }
  }

  // 1. Scene, Camera, Renderer Setup
  const isInitialDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const initialBg = isInitialDark ? 0x0a0b0e : 0xf4f6f9;

  const scene = new THREE.Scene();
  scene.background = new THREE.Color(initialBg);
  scene.fog = new THREE.FogExp2(initialBg, 0.018);

  const initialAspect = container.clientWidth / container.clientHeight;
  const camera = new THREE.PerspectiveCamera(
    initialAspect < 1.0 ? 50 : 42,
    initialAspect,
    0.1,
    1000
  );
  // Initial camera angled to highlight both painter and car
  camera.position.set(4.8, 2.3, 5.2);

  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.setSize(container.clientWidth, container.clientHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.25;
  container.appendChild(renderer.domElement);

  // 2. OrbitControls
  const controls = new THREE.OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.05;
  controls.target.set(0, 0.95, 0.2); // Center target between car body and painter
  controls.maxPolarAngle = Math.PI / 2 - 0.03; // Prevent dipping beneath floor
  controls.minDistance = 3.2;
  controls.maxDistance = 11.0;
  controls.autoRotate = true;
  controls.autoRotateSpeed = 1.35; // Majestic, stately rotation

  // Check reduced-motion preference
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    controls.autoRotate = false;
  }

  // 3. Automotive Workshop Lighting Rig
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.85);
  scene.add(ambientLight);

  // Primary Daylight Showroom Key Light
  const keyLight = new THREE.DirectionalLight(0xffffff, 2.4);
  keyLight.position.set(6, 9, 5);
  keyLight.castShadow = true;
  keyLight.shadow.mapSize.width = 1024;
  keyLight.shadow.mapSize.height = 1024;
  keyLight.shadow.camera.near = 0.5;
  keyLight.shadow.camera.far = 25;
  keyLight.shadow.camera.left = -5;
  keyLight.shadow.camera.right = 5;
  keyLight.shadow.camera.top = 5;
  keyLight.shadow.camera.bottom = -5;
  keyLight.shadow.bias = -0.0005;
  scene.add(keyLight);

  // Sun Gold Contour Rim Light (Opposite side)
  const goldRimLight = new THREE.DirectionalLight(0xd4af37, 2.0);
  goldRimLight.position.set(-6, 7, -5);
  scene.add(goldRimLight);

  // Cool Silver Fill Light
  const silverFillLight = new THREE.DirectionalLight(0x94a3b8, 1.2);
  silverFillLight.position.set(-4, 5, 6);
  scene.add(silverFillLight);

  // Dedicated Wet-Coat Spray Spot Accent
  const spraySpotLight = new THREE.PointLight(0x60a5fa, 2.5, 3.5);
  spraySpotLight.position.set(0.65, 1.15, 0.95);
  scene.add(spraySpotLight);

  // 4. Workshop Environment, Epoxy Floor & Turntable
  const presentationGroup = new THREE.Group();
  scene.add(presentationGroup);

  // Ambient Workshop Floor (Lower Plane)
  const floorMat = new THREE.MeshStandardMaterial({
    color: isInitialDark ? 0x0f1117 : 0xffffff,
    roughness: 0.18,
    metalness: 0.15,
  });
  const floorGeo = new THREE.PlaneGeometry(45, 45);
  const floorMesh = new THREE.Mesh(floorGeo, floorMat);
  floorMesh.rotation.x = -Math.PI / 2;
  floorMesh.position.y = -0.01;
  floorMesh.receiveShadow = true;
  scene.add(floorMesh);

  // Professional Workshop Turntable Stage (Inside presentationGroup for smooth unified rotation)
  const turntableMat = new THREE.MeshStandardMaterial({
    color: isInitialDark ? 0x161922 : 0xf1f5f9,
    metalness: 0.45,
    roughness: 0.25,
  });
  const turntableGeo = new THREE.CylinderGeometry(3.6, 3.65, 0.08, 48);
  const turntableMesh = new THREE.Mesh(turntableGeo, turntableMat);
  turntableMesh.position.y = 0.04;
  turntableMesh.receiveShadow = true;
  presentationGroup.add(turntableMesh);

  // Gold Turntable Outer Chamfer Ring
  const rimGoldMat = new THREE.MeshStandardMaterial({
    color: 0xd4af37,
    metalness: 0.95,
    roughness: 0.15,
  });
  const ringGeo = new THREE.TorusGeometry(3.62, 0.025, 12, 64);
  const ringMesh = new THREE.Mesh(ringGeo, rimGoldMat);
  ringMesh.rotation.x = Math.PI / 2;
  ringMesh.position.y = 0.08;
  presentationGroup.add(ringMesh);

  // Inner Turntable Concentric Workline
  const innerRingGeo = new THREE.RingGeometry(2.35, 2.37, 48);
  const innerRingMat = new THREE.MeshBasicMaterial({
    color: 0xd4af37,
    side: THREE.DoubleSide,
    transparent: true,
    opacity: 0.45,
  });
  const innerRingMesh = new THREE.Mesh(innerRingGeo, innerRingMat);
  innerRingMesh.rotation.x = -Math.PI / 2;
  innerRingMesh.position.y = 0.082;
  presentationGroup.add(innerRingMesh);

  // Overhead Paint Booth LED Luminaire Bank Fixtures (Suspended overhead)
  const boothLuminaireMat = new THREE.MeshStandardMaterial({
    color: 0x222630,
    metalness: 0.8,
    roughness: 0.2,
  });
  const boothBulbMat = new THREE.MeshBasicMaterial({
    color: 0xffffff,
  });

  function createLuminaireBank(x, y, z, rotY) {
    const bankGroup = new THREE.Group();
    bankGroup.position.set(x, y, z);
    bankGroup.rotation.y = rotY;

    // Housing Frame
    const frameGeo = new THREE.BoxGeometry(3.2, 0.08, 0.35);
    const frame = new THREE.Mesh(frameGeo, boothLuminaireMat);
    bankGroup.add(frame);

    // Glowing Diffuser Tube
    const tubeGeo = new THREE.BoxGeometry(3.0, 0.03, 0.22);
    const tube = new THREE.Mesh(tubeGeo, boothBulbMat);
    tube.position.y = -0.035;
    bankGroup.add(tube);

    return bankGroup;
  }

  const leftLuminaire = createLuminaireBank(0, 3.4, 1.8, 0.05);
  const rightLuminaire = createLuminaireBank(0, 3.4, -1.8, -0.05);
  presentationGroup.add(leftLuminaire);
  presentationGroup.add(rightLuminaire);

  // 5. Car and Painter Groups
  const carGroup = new THREE.Group();
  const painterGroup = new THREE.Group();
  presentationGroup.add(carGroup);
  presentationGroup.add(painterGroup);

  // Dynamic Theme Switching
  function update3DThemeColors(e) {
    const effectiveTheme = e && e.detail ? e.detail.effectiveTheme : document.documentElement.getAttribute('data-theme');
    const isDark = effectiveTheme === 'dark';
    const bgColor = isDark ? 0x0a0b0e : 0xf4f6f9;
    const flColor = isDark ? 0x0f1117 : 0xffffff;
    const ttColor = isDark ? 0x161922 : 0xf1f5f9;

    scene.background = new THREE.Color(bgColor);
    scene.fog = new THREE.FogExp2(bgColor, 0.018);
    floorMesh.material.color.setHex(flColor);
    turntableMesh.material.color.setHex(ttColor);
  }
  window.addEventListener('themeChanged', update3DThemeColors);

  // 6. Paint Spray Mist Atomization Particle System
  let mistParticles = null;
  const PARTICLE_COUNT = 85;
  const mistPositions = new Float32Array(PARTICLE_COUNT * 3);
  const mistVelocities = [];

  // Spray Gun Nozzle world coordinates in presentationGroup
  // Painter is positioned at (0.45, 0.08, 1.68), rotated Math.PI (faces -Z toward car door)
  // Spray gun nozzle is located at approximately (0.68, 1.25, 1.05)
  // Target vehicle door impact point is at (0.68, 1.25, 0.88)
  const nozzleOrigin = new THREE.Vector3(0.68, 1.25, 1.05);
  const sprayTarget = new THREE.Vector3(0.68, 1.25, 0.88);

  for (let i = 0; i < PARTICLE_COUNT; i++) {
    const t = Math.random();
    // Interpolate between nozzle and car body panel with gentle radial spread
    const px = nozzleOrigin.x + (sprayTarget.x - nozzleOrigin.x) * t + (Math.random() - 0.5) * 0.08 * t;
    const py = nozzleOrigin.y + (sprayTarget.y - nozzleOrigin.y) * t + (Math.random() - 0.5) * 0.09 * t;
    const pz = nozzleOrigin.z + (sprayTarget.z - nozzleOrigin.z) * t + (Math.random() - 0.5) * 0.03 * t;

    mistPositions[i * 3] = px;
    mistPositions[i * 3 + 1] = py;
    mistPositions[i * 3 + 2] = pz;

    mistVelocities.push({
      speed: 0.007 + Math.random() * 0.012,
      progress: t,
      spreadX: (Math.random() - 0.5) * 0.08,
      spreadY: (Math.random() - 0.5) * 0.08
    });
  }

  const mistGeometry = new THREE.BufferGeometry();
  mistGeometry.setAttribute('position', new THREE.BufferAttribute(mistPositions, 3));

  const mistMaterial = new THREE.PointsMaterial({
    color: 0x93c5fd, // Atomized metallic pearl basecoat mist
    size: 0.032,
    transparent: true,
    opacity: 0.65,
    blending: THREE.AdditiveBlending,
    depthWrite: false
  });

  mistParticles = new THREE.Points(mistGeometry, mistMaterial);
  presentationGroup.add(mistParticles);

  function updateMistParticles() {
    if (!mistParticles) return;
    const posAttr = mistParticles.geometry.attributes.position;
    const positions = posAttr.array;

    for (let i = 0; i < PARTICLE_COUNT; i++) {
      const v = mistVelocities[i];
      v.progress += v.speed;
      if (v.progress > 1.0) {
        v.progress = 0.0;
        v.spreadX = (Math.random() - 0.5) * 0.09;
        v.spreadY = (Math.random() - 0.5) * 0.09;
      }
      const t = v.progress;
      positions[i * 3] = nozzleOrigin.x + (sprayTarget.x - nozzleOrigin.x) * t + v.spreadX * t;
      positions[i * 3 + 1] = nozzleOrigin.y + (sprayTarget.y - nozzleOrigin.y) * t + v.spreadY * t;
      positions[i * 3 + 2] = nozzleOrigin.z + (sprayTarget.z - nozzleOrigin.z) * t;
    }
    posAttr.needsUpdate = true;

    // Subtle pulsing on the fresh wet paint contact point
    spraySpotLight.intensity = 2.0 + Math.sin(Date.now() * 0.008) * 0.6;
  }

  // 7. Load 3D Models (GLB) with Robust Procedural Fallbacks
  let modelsToLoad = 2;
  function checkAllLoaded() {
    modelsToLoad--;
    if (modelsToLoad <= 0) {
      hideLoader();
    }
  }

  const gltfLoader = typeof THREE.GLTFLoader !== 'undefined' ? new THREE.GLTFLoader() : null;

  // 7A. Load Realistic Car Model
  if (gltfLoader) {
    gltfLoader.load(
      'assets/models/car.glb',
      (gltf) => {
        const carModel = gltf.scene;
        carModel.traverse((node) => {
          if (node.isMesh) {
            node.castShadow = true;
            node.receiveShadow = true;
            if (node.material) {
              node.material.envMapIntensity = 1.3;
              node.material.needsUpdate = true;
            }
          }
        });

        // Compute bounds and place wheels exactly on the turntable
        const box = new THREE.Box3().setFromObject(carModel);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());

        // Center car on turntable, wheels resting at turntable surface (Y = 0.08)
        carModel.position.x = -center.x;
        carModel.position.z = -center.z;
        carModel.position.y = 0.08 - box.min.y;

        carGroup.add(carModel);
        console.log('Automotive Car GLB model loaded successfully.');
        checkAllLoaded();
      },
      undefined,
      (err) => {
        console.warn('car.glb could not be loaded; building procedural high-detail car:', err);
        buildProceduralCar(carGroup);
        checkAllLoaded();
      }
    );
  } else {
    buildProceduralCar(carGroup);
    checkAllLoaded();
  }

  // 7B. Load Realistic Painter Model
  if (gltfLoader) {
    gltfLoader.load(
      'assets/models/painter.glb',
      (gltf) => {
        const painterModel = gltf.scene;
        painterModel.traverse((node) => {
          if (node.isMesh) {
            node.castShadow = true;
            node.receiveShadow = true;
            if (node.material) {
              node.material.envMapIntensity = 1.1;
              node.material.needsUpdate = true;
            }
          }
        });

        // Painter in painter.glb faces along -X with gun pointing -X
        // We rotate painter by -Math.PI / 2 so painter faces towards -Z (directly at the car side panel)
        painterModel.rotation.y = -Math.PI / 2;

        // Position painter standing on turntable beside the vehicle door
        // Car side is at Z ~ 0.88. Painter origin placed at (0.45, 0.08, 1.72)
        // With local gun nozzle at X_local = -0.68, rotated -90deg gives Z_world = 1.72 - 0.68 = 1.04
        painterModel.position.set(0.45, 0.08, 1.72);

        painterGroup.add(painterModel);
        console.log('Human Automotive Painter GLB model loaded successfully.');
        checkAllLoaded();
      },
      undefined,
      (err) => {
        console.warn('painter.glb could not be loaded; building procedural automotive painter:', err);
        buildProceduralPainter(painterGroup);
        checkAllLoaded();
      }
    );
  } else {
    buildProceduralPainter(painterGroup);
    checkAllLoaded();
  }

  // Fallback timeout to ensure loader never hangs if network stalls
  setTimeout(hideLoader, 3000);

  // ============================================================
  // PROCEDURAL HIGH-DETAIL PASSENGER CAR (Fallback Engine)
  // ============================================================
  function buildProceduralCar(group) {
    const paintMat = new THREE.MeshStandardMaterial({
      color: 0x142b4d, // Deep Sapphire Blue Metallic
      metalness: 0.92,
      roughness: 0.12,
    });
    const goldMat = new THREE.MeshStandardMaterial({
      color: 0xd4af37,
      metalness: 0.95,
      roughness: 0.15,
    });
    const glassMat = new THREE.MeshPhysicalMaterial({
      color: 0x1e293b,
      metalness: 0.1,
      roughness: 0.08,
      transmission: 0.7,
      transparent: true,
    });
    const rubberMat = new THREE.MeshStandardMaterial({
      color: 0x18181b,
      roughness: 0.85,
    });

    // Lower Chassis
    const chassisGeo = new THREE.BoxGeometry(4.0, 0.55, 1.8);
    const chassisMesh = new THREE.Mesh(chassisGeo, paintMat);
    chassisMesh.position.set(0, 0.48, 0);
    chassisMesh.castShadow = true;
    chassisMesh.receiveShadow = true;
    group.add(chassisMesh);

    // Aerodynamic Cabin
    const cabinGeo = new THREE.BoxGeometry(2.2, 0.58, 1.5);
    const cabinMesh = new THREE.Mesh(cabinGeo, paintMat);
    cabinMesh.position.set(-0.25, 1.02, 0);
    cabinMesh.castShadow = true;
    group.add(cabinMesh);

    // Windshield
    const windshieldGeo = new THREE.BoxGeometry(0.72, 0.55, 1.46);
    const windshieldMesh = new THREE.Mesh(windshieldGeo, glassMat);
    windshieldMesh.position.set(0.70, 0.98, 0);
    windshieldMesh.rotation.z = -0.32;
    group.add(windshieldMesh);

    // Rear Window
    const rearWindowGeo = new THREE.BoxGeometry(0.68, 0.52, 1.46);
    const rearWindowMesh = new THREE.Mesh(rearWindowGeo, glassMat);
    rearWindowMesh.position.set(-1.22, 0.98, 0);
    rearWindowMesh.rotation.z = 0.34;
    group.add(rearWindowMesh);

    // Side Windows
    const sideGlassL = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.44, 0.04), glassMat);
    sideGlassL.position.set(-0.25, 1.05, 0.74);
    group.add(sideGlassL);
    const sideGlassR = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.44, 0.04), glassMat);
    sideGlassR.position.set(-0.25, 1.05, -0.74);
    group.add(sideGlassR);

    // Gold Sport Stripes
    const stripeMesh = new THREE.Mesh(new THREE.BoxGeometry(4.04, 0.02, 0.32), goldMat);
    stripeMesh.position.set(0, 0.77, 0);
    group.add(stripeMesh);

    // Front Gold Grille & Bumper
    const grilleMesh = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.32, 1.3), goldMat);
    grilleMesh.position.set(2.01, 0.45, 0);
    group.add(grilleMesh);

    // LED Headlights
    const headLightMat = new THREE.MeshStandardMaterial({
      color: 0xffffff,
      emissive: 0xfff3c4,
      emissiveIntensity: 2.5
    });
    const headL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.14, 0.35), headLightMat);
    headL.position.set(2.01, 0.58, 0.62);
    const headR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.14, 0.35), headLightMat);
    headR.position.set(2.01, 0.58, -0.62);
    group.add(headL);
    group.add(headR);

    // Rear LED Taillights
    const tailLightMat = new THREE.MeshStandardMaterial({
      color: 0xdc2626,
      emissive: 0xef4444,
      emissiveIntensity: 2.0
    });
    const tailL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.12, 0.36), tailLightMat);
    tailL.position.set(-2.01, 0.58, 0.62);
    const tailR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.12, 0.36), tailLightMat);
    tailR.position.set(-2.01, 0.58, -0.62);
    group.add(tailL);
    group.add(tailR);

    // 4 Wheels with Gold Rims
    const wheelPositions = [
      [1.25, 0.34, 0.92],
      [1.25, 0.34, -0.92],
      [-1.25, 0.34, 0.92],
      [-1.25, 0.34, -0.92]
    ];
    wheelPositions.forEach((pos) => {
      const wg = new THREE.Group();
      wg.position.set(pos[0], pos[1], pos[2]);

      const tire = new THREE.Mesh(new THREE.CylinderGeometry(0.34, 0.34, 0.26, 24), rubberMat);
      tire.rotation.x = Math.PI / 2;
      tire.castShadow = true;
      wg.add(tire);

      const rim = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.24, 0.27, 12), goldMat);
      rim.rotation.x = Math.PI / 2;
      wg.add(rim);

      group.add(wg);
    });
  }

  // ============================================================
  // PROCEDURAL AUTOMOTIVE PAINTER (Fallback Engine)
  // ============================================================
  function buildProceduralPainter(group) {
    group.position.set(0.45, 0.08, 1.72);
    group.rotation.y = -Math.PI / 2;

    const suitMat = new THREE.MeshStandardMaterial({
      color: 0x1e2430,
      metalness: 0.15,
      roughness: 0.65
    });
    const accentMat = new THREE.MeshStandardMaterial({
      color: 0xd4af37,
      metalness: 0.6,
      roughness: 0.3
    });
    const maskMat = new THREE.MeshStandardMaterial({
      color: 0x334155,
      metalness: 0.3,
      roughness: 0.4
    });
    const chromeMat = new THREE.MeshStandardMaterial({
      color: 0xf1f5f9,
      metalness: 0.95,
      roughness: 0.1
    });

    // Head with protective hood
    const head = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.26, 0.24), suitMat);
    head.position.set(0, 1.62, 0);
    head.castShadow = true;
    group.add(head);

    // Respirator mask on face
    const mask = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.12, 0.14), maskMat);
    mask.position.set(-0.12, 1.57, 0);
    group.add(mask);

    // Respirator Dual Filter Cartridges
    const f1 = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, 0.06, 12), accentMat);
    f1.rotation.x = Math.PI / 2;
    f1.position.set(-0.12, 1.57, 0.11);
    const f2 = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, 0.06, 12), accentMat);
    f2.rotation.x = Math.PI / 2;
    f2.position.set(-0.12, 1.57, -0.11);
    group.add(f1);
    group.add(f2);

    // Safety Visor
    const visor = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.09, 0.18), new THREE.MeshStandardMaterial({
      color: 0x38bdf8,
      metalness: 0.5,
      roughness: 0.1
    }));
    visor.position.set(-0.11, 1.66, 0);
    group.add(visor);

    // Torso (Coveralls)
    const torso = new THREE.Mesh(new THREE.BoxGeometry(0.36, 0.68, 0.48), suitMat);
    torso.position.set(0, 1.15, 0);
    torso.castShadow = true;
    group.add(torso);

    // Gold Chest Stripe
    const chestStripe = new THREE.Mesh(new THREE.BoxGeometry(0.38, 0.06, 0.50), accentMat);
    chestStripe.position.set(0, 1.28, 0);
    group.add(chestStripe);

    // Legs
    const leg1 = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.09, 0.72, 12), suitMat);
    leg1.position.set(-0.06, 0.42, 0.15);
    leg1.castShadow = true;
    group.add(leg1);

    const leg2 = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.09, 0.72, 12), suitMat);
    leg2.position.set(0.06, 0.42, -0.15);
    leg2.castShadow = true;
    group.add(leg2);

    // Boots
    const bootMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.8 });
    const b1 = new THREE.Mesh(new THREE.BoxGeometry(0.26, 0.12, 0.14), bootMat);
    b1.position.set(-0.10, 0.06, 0.15);
    const b2 = new THREE.Mesh(new THREE.BoxGeometry(0.26, 0.12, 0.14), bootMat);
    b2.position.set(0.06, 0.06, -0.15);
    group.add(b1);
    group.add(b2);

    // Right Arm aiming spray gun forward
    const arm1 = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.065, 0.45, 10), suitMat);
    arm1.rotation.z = Math.PI / 2;
    arm1.position.set(-0.35, 1.25, 0.24);
    arm1.castShadow = true;
    group.add(arm1);

    // Left Arm supporting
    const arm2 = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.065, 0.38, 10), suitMat);
    arm2.position.set(-0.05, 1.15, -0.25);
    arm2.castShadow = true;
    group.add(arm2);

    // HVLP Spray Gun Body
    const gunBody = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.20, 10), chromeMat);
    gunBody.rotation.z = Math.PI / 2;
    gunBody.position.set(-0.68, 1.25, 0.22);
    group.add(gunBody);

    // Gun Handle
    const gunHandle = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, 0.12, 8), chromeMat);
    gunHandle.position.set(-0.62, 1.17, 0.22);
    group.add(gunHandle);

    // Gravity Feed Paint Cup
    const cup = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.045, 0.16, 12), accentMat);
    cup.position.set(-0.66, 1.38, 0.22);
    group.add(cup);
  }

  // 8. Animation & Render Loop
  let animationFrameId = null;

  function animate() {
    animationFrameId = requestAnimationFrame(animate);

    // Controls update (autoRotate smoothly handles scene rotation)
    controls.update();

    // Update dynamic paint atomization mist
    updateMistParticles();

    renderer.render(scene, camera);
  }
  animate();

  // 9. Responsive Resize Handler
  function onWindowResize() {
    if (!container || !renderer || !camera) return;
    const width = container.clientWidth;
    const height = container.clientHeight;
    const aspect = width / height;
    camera.aspect = aspect;
    if (aspect < 1.0) {
      // For mobile/vertical orientations, pull camera back slightly so vehicle & painter remain in full view
      camera.fov = 50;
    } else {
      camera.fov = 42;
    }
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  }
  window.addEventListener('resize', onWindowResize);

  // Clean-up on page unload to prevent memory leaks
  window.addEventListener('beforeunload', () => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    window.removeEventListener('resize', onWindowResize);
    window.removeEventListener('themeChanged', update3DThemeColors);
  });
});
