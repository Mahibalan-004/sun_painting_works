/* ============================================================
   SUN PAINTING WORKS - 3D AUTOMOTIVE WORKSHOP SCENE
   Realistic Red Sports Sedan, Glossy Automotive Finish,
   Tiled Workshop Floor, Overhead Paint Booth LED Lighting,
   Human Automotive Painter & Atomized Paint Spray Mist,
   Smooth 360° Rotation, and OrbitControls Interaction.
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

  // Loading Overlay
  const loaderEl = document.createElement('div');
  loaderEl.id = 'scene-3d-loader';
  loaderEl.style.cssText = `
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: rgba(10, 11, 14, 0.78);
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
  const initialBg = isInitialDark ? 0x0a0c12 : 0x11141c; // Deep workshop ambient background

  const scene = new THREE.Scene();
  scene.background = new THREE.Color(initialBg);
  scene.fog = new THREE.FogExp2(initialBg, 0.018);

  const initialAspect = container.clientWidth / container.clientHeight;
  const camera = new THREE.PerspectiveCamera(
    initialAspect < 1.0 ? 48 : 40,
    initialAspect,
    0.1,
    1000
  );
  // Camera framed to display the red sports sedan and painter with dramatic reflection angles
  camera.position.set(4.6, 2.1, 4.8);

  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.setSize(container.clientWidth, container.clientHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.30;
  container.appendChild(renderer.domElement);

  // 2. OrbitControls
  const controls = new THREE.OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.05;
  controls.target.set(0, 0.85, 0.15); // Focused on red sedan body & painter
  controls.maxPolarAngle = Math.PI / 2 - 0.03; // Prevent dipping beneath floor
  controls.minDistance = 3.0;
  controls.maxDistance = 11.0;
  controls.autoRotate = true;
  controls.autoRotateSpeed = 1.10; // Majestic, stately smooth rotation

  // Check reduced-motion preference
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    controls.autoRotate = false;
  }

  // 3. Professional Automotive Studio & Paint Booth Lighting Rig
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.90);
  scene.add(ambientLight);

  // High-Angle Crisp Daylight Key Light (Simulates paint booth luminaire banks)
  const keyLight = new THREE.DirectionalLight(0xffffff, 2.8);
  keyLight.position.set(6, 10, 5);
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
  keyLight.shadow.radius = 2;
  scene.add(keyLight);

  // Sun Gold Contour Rim Light (Opposite quadrant - accentuates vehicle curves)
  const goldRimLight = new THREE.DirectionalLight(0xd4af37, 2.2);
  goldRimLight.position.set(-6, 7, -5);
  scene.add(goldRimLight);

  // Cool Silver Fill Light
  const silverFillLight = new THREE.DirectionalLight(0xa5b4fc, 1.3);
  silverFillLight.position.set(-4, 5, 6);
  scene.add(silverFillLight);

  // Front Facia Spotlight (Emphasizes front grille, headlights, and red hood)
  const frontSpotLight = new THREE.SpotLight(0xffffff, 2.0);
  frontSpotLight.position.set(6, 4, 0);
  frontSpotLight.target.position.set(0, 0.6, 0);
  frontSpotLight.angle = Math.PI / 4;
  frontSpotLight.penumbra = 0.6;
  scene.add(frontSpotLight);
  scene.add(frontSpotLight.target);

  // Wet-Coat Spray Accent PointLight
  const spraySpotLight = new THREE.PointLight(0x60a5fa, 2.4, 3.5);
  spraySpotLight.position.set(0.65, 1.15, 0.95);
  scene.add(spraySpotLight);

  // 4. Workshop Environment & Realistic Tiled Floor
  const presentationGroup = new THREE.Group();
  scene.add(presentationGroup);

  // Procedural HD Tiled Workshop Floor Texture Generator
  function createWorkshopTileTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    const ctx = canvas.getContext('2d');

    const tileSize = 64; // 8x8 tiles
    for (let x = 0; x < 512; x += tileSize) {
      for (let y = 0; y < 512; y += tileSize) {
        // Tile body - dark charcoal reflective ceramic tile
        const grad = ctx.createLinearGradient(x, y, x + tileSize, y + tileSize);
        grad.addColorStop(0, '#1c1f26');
        grad.addColorStop(0.5, '#15171d');
        grad.addColorStop(1, '#111317');
        ctx.fillStyle = grad;
        ctx.fillRect(x, y, tileSize, tileSize);

        // Specular bevel border
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.07)';
        ctx.lineWidth = 1;
        ctx.strokeRect(x + 1, y + 1, tileSize - 2, tileSize - 2);

        // Dark grout line
        ctx.strokeStyle = '#07080a';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, tileSize, tileSize);
      }
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(10, 10);
    return texture;
  }

  const tileTexture = createWorkshopTileTexture();
  const floorMat = new THREE.MeshStandardMaterial({
    map: tileTexture,
    roughness: 0.20, // Glossy reflective workshop epoxy tiles
    metalness: 0.32,
  });
  const floorGeo = new THREE.PlaneGeometry(36, 36);
  const floorMesh = new THREE.Mesh(floorGeo, floorMat);
  floorMesh.rotation.x = -Math.PI / 2;
  floorMesh.position.y = 0;
  floorMesh.receiveShadow = true;
  presentationGroup.add(floorMesh);

  // Gold Workshop Detailing Bay Outer Ring
  const rimGoldMat = new THREE.MeshStandardMaterial({
    color: 0xd4af37,
    metalness: 0.95,
    roughness: 0.15,
  });
  const ringGeo = new THREE.TorusGeometry(3.60, 0.022, 12, 64);
  const ringMesh = new THREE.Mesh(ringGeo, rimGoldMat);
  ringMesh.rotation.x = Math.PI / 2;
  ringMesh.position.y = 0.02;
  presentationGroup.add(ringMesh);

  // Concentric Inner Staging Grid Line
  const innerRingGeo = new THREE.RingGeometry(2.35, 2.37, 48);
  const innerRingMat = new THREE.MeshBasicMaterial({
    color: 0xd4af37,
    side: THREE.DoubleSide,
    transparent: true,
    opacity: 0.40,
  });
  const innerRingMesh = new THREE.Mesh(innerRingGeo, innerRingMat);
  innerRingMesh.rotation.x = -Math.PI / 2;
  innerRingMesh.position.y = 0.025;
  presentationGroup.add(innerRingMesh);

  // Overhead Paint Booth LED Luminaire Fixtures (Produce realistic linear reflections on the car)
  const boothLuminaireMat = new THREE.MeshStandardMaterial({
    color: 0x181a20,
    metalness: 0.85,
    roughness: 0.20,
  });
  const boothBulbMat = new THREE.MeshBasicMaterial({
    color: 0xffffff,
  });

  function createLuminaireBank(x, y, z, rotY) {
    const bankGroup = new THREE.Group();
    bankGroup.position.set(x, y, z);
    bankGroup.rotation.y = rotY;

    // Housing Frame
    const frameGeo = new THREE.BoxGeometry(3.6, 0.08, 0.36);
    const frame = new THREE.Mesh(frameGeo, boothLuminaireMat);
    bankGroup.add(frame);

    // Glowing Diffuser Tube
    const tubeGeo = new THREE.BoxGeometry(3.4, 0.03, 0.24);
    const tube = new THREE.Mesh(tubeGeo, boothBulbMat);
    tube.position.y = -0.035;
    bankGroup.add(tube);

    return bankGroup;
  }

  const leftLuminaire = createLuminaireBank(0, 3.3, 1.8, 0.04);
  const rightLuminaire = createLuminaireBank(0, 3.3, -1.8, -0.04);
  const centerLuminaire = createLuminaireBank(0, 3.5, 0, 0);
  presentationGroup.add(leftLuminaire);
  presentationGroup.add(rightLuminaire);
  presentationGroup.add(centerLuminaire);

  // 5. Car and Painter Groups
  const carGroup = new THREE.Group();
  const painterGroup = new THREE.Group();
  presentationGroup.add(carGroup);
  presentationGroup.add(painterGroup);

  // Dynamic Theme Switch Listener
  function update3DThemeColors(e) {
    const effectiveTheme = e && e.detail ? e.detail.effectiveTheme : document.documentElement.getAttribute('data-theme');
    const isDark = effectiveTheme === 'dark';
    const bgColor = isDark ? 0x0a0c12 : 0x11141c;
    scene.background = new THREE.Color(bgColor);
    scene.fog = new THREE.FogExp2(bgColor, 0.018);
  }
  window.addEventListener('themeChanged', update3DThemeColors);

  // 6. Paint Spray Mist Atomization Particle System
  let mistParticles = null;
  const PARTICLE_COUNT = 85;
  const mistPositions = new Float32Array(PARTICLE_COUNT * 3);
  const mistVelocities = [];

  // Spray Gun Nozzle coordinates relative to presentationGroup
  // Painter stands at (0.45, 0.02, 1.68) facing -Z towards the red car door
  const nozzleOrigin = new THREE.Vector3(0.68, 1.25, 1.05);
  const sprayTarget = new THREE.Vector3(0.68, 1.25, 0.88);

  for (let i = 0; i < PARTICLE_COUNT; i++) {
    const t = Math.random();
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
    color: 0x93c5fd, // Atomized pearl clearcoat spray
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

    // Pulsing fresh wet paint contact highlight
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

  // 7A. Load Realistic Red Sports Sedan GLB
  if (gltfLoader) {
    gltfLoader.load(
      'assets/models/car.glb',
      (gltf) => {
        const carModel = gltf.scene;

        carModel.traverse((node) => {
          if (node.isMesh) {
            node.castShadow = true;
            node.receiveShadow = true;

            const matName = (node.material && node.material.name) ? node.material.name : '';
            const meshName = node.name || '';

            // 1. High-Gloss Deep Candy Red Automotive Paint
            if (matName.includes('Red') || matName.includes('Paint') || meshName.includes('Red') || meshName.includes('Body')) {
              node.material = new THREE.MeshPhysicalMaterial({
                color: new THREE.Color(0xd20826), // Deep, vibrant sports car red
                metalness: 0.88,
                roughness: 0.12,
                clearcoat: 1.0,
                clearcoatRoughness: 0.04,
                reflectivity: 0.95,
              });
            }
            // 2. Tinted Safety Glass
            else if (matName.includes('Glass') || meshName.includes('Glass')) {
              node.material = new THREE.MeshPhysicalMaterial({
                color: new THREE.Color(0x0f172a),
                metalness: 0.15,
                roughness: 0.05,
                transmission: 0.70,
                transparent: true,
                opacity: 0.85
              });
            }
            // 3. Gloss Black Aerodynamic Trim
            else if (matName.includes('Black') || meshName.includes('Black')) {
              node.material = new THREE.MeshStandardMaterial({
                color: new THREE.Color(0x0d0f14),
                metalness: 0.80,
                roughness: 0.20
              });
            }
            // 4. Polished Chrome Accents
            else if (matName.includes('Chrome') || meshName.includes('Chrome')) {
              node.material = new THREE.MeshStandardMaterial({
                color: new THREE.Color(0xf8fafc),
                metalness: 0.98,
                roughness: 0.06
              });
            }
            // 5. Machined Sports Alloy Wheels
            else if (matName.includes('Alloy') || matName.includes('Rim') || meshName.includes('Rim')) {
              node.material = new THREE.MeshStandardMaterial({
                color: new THREE.Color(0xe2e8f0),
                metalness: 0.95,
                roughness: 0.16
              });
            }
            // 6. Performance Rubber Tires
            else if (matName.includes('Tire') || meshName.includes('Tire')) {
              node.material = new THREE.MeshStandardMaterial({
                color: new THREE.Color(0x131518),
                metalness: 0.05,
                roughness: 0.85
              });
            }
            // 7. Racing Red Brake Calipers
            else if (matName.includes('Caliper') || meshName.includes('Caliper')) {
              node.material = new THREE.MeshStandardMaterial({
                color: new THREE.Color(0xd80000),
                metalness: 0.40,
                roughness: 0.20
              });
            }
            // 8. Projector LED Headlights & DRL
            else if (matName.includes('Headlight') || meshName.includes('Projector') || meshName.includes('DRL')) {
              node.material = new THREE.MeshStandardMaterial({
                color: new THREE.Color(0xffffff),
                emissive: new THREE.Color(0xffffff),
                emissiveIntensity: 3.0,
                roughness: 0.05
              });
            }
            // 9. Modern Ruby LED Taillights
            else if (matName.includes('Taillight') || meshName.includes('Taillight')) {
              node.material = new THREE.MeshStandardMaterial({
                color: new THREE.Color(0x990000),
                emissive: new THREE.Color(0xff0d25),
                emissiveIntensity: 2.2,
                roughness: 0.10
              });
            }
          }
        });

        // Compute bounds and place wheels exactly on the tiled workshop floor
        const box = new THREE.Box3().setFromObject(carModel);
        const center = box.getCenter(new THREE.Vector3());

        carModel.position.x = -center.x;
        carModel.position.z = -center.z;
        carModel.position.y = 0.02 - box.min.y;

        carGroup.add(carModel);
        console.log('Realistic Red Sports Sedan GLB model loaded successfully.');
        checkAllLoaded();
      },
      undefined,
      (err) => {
        console.warn('car.glb could not be loaded; building procedural red sports sedan:', err);
        buildProceduralCar(carGroup);
        checkAllLoaded();
      }
    );
  } else {
    buildProceduralCar(carGroup);
    checkAllLoaded();
  }

  // 7B. Load Realistic Human Painter Model
  if (gltfLoader) {
    gltfLoader.load(
      'assets/models/painter.glb',
      (gltf) => {
        const painterModel = gltf.scene;
        painterModel.traverse((node) => {
          if (node.isMesh) {
            node.castShadow = true;
            node.receiveShadow = true;
          }
        });

        // Rotate painter by -90° so spray gun points directly at the red sports sedan door panel
        painterModel.rotation.y = -Math.PI / 2;
        painterModel.position.set(0.45, 0.02, 1.72);

        painterGroup.add(painterModel);
        console.log('Automotive Painter GLB model loaded successfully.');
        checkAllLoaded();
      },
      undefined,
      (err) => {
        console.warn('painter.glb could not be loaded; building procedural painter fallback:', err);
        buildProceduralPainter(painterGroup);
        checkAllLoaded();
      }
    );
  } else {
    buildProceduralPainter(painterGroup);
    checkAllLoaded();
  }

  // Fallback timeout to ensure loader never hangs
  setTimeout(hideLoader, 3000);

  // ============================================================
  // PROCEDURAL RED SPORTS SEDAN FALLBACK ENGINE
  // ============================================================
  function buildProceduralCar(group) {
    const redPaintMat = new THREE.MeshPhysicalMaterial({
      color: 0xd20826,
      metalness: 0.88,
      roughness: 0.12,
      clearcoat: 1.0,
      clearcoatRoughness: 0.04,
    });
    const blackTrimMat = new THREE.MeshStandardMaterial({
      color: 0x0f1115,
      metalness: 0.80,
      roughness: 0.20,
    });
    const chromeMat = new THREE.MeshStandardMaterial({
      color: 0xf8fafc,
      metalness: 0.98,
      roughness: 0.06,
    });
    const glassMat = new THREE.MeshPhysicalMaterial({
      color: 0x0f172a,
      metalness: 0.15,
      roughness: 0.05,
      transmission: 0.70,
      transparent: true,
    });
    const rubberMat = new THREE.MeshStandardMaterial({
      color: 0x131518,
      roughness: 0.85,
    });
    const rimMat = new THREE.MeshStandardMaterial({
      color: 0xe2e8f0,
      metalness: 0.95,
      roughness: 0.16,
    });

    // Lower Chassis
    const chassisGeo = new THREE.BoxGeometry(4.30, 0.34, 1.86);
    const chassisMesh = new THREE.Mesh(chassisGeo, redPaintMat);
    chassisMesh.position.set(0, 0.44, 0);
    chassisMesh.castShadow = true;
    chassisMesh.receiveShadow = true;
    group.add(chassisMesh);

    // Front Sloping Hood
    const hoodGeo = new THREE.BoxGeometry(1.50, 0.28, 1.80);
    const hoodMesh = new THREE.Mesh(hoodGeo, redPaintMat);
    hoodMesh.position.set(1.35, 0.70, 0);
    hoodMesh.rotation.z = -0.05;
    hoodMesh.castShadow = true;
    group.add(hoodMesh);

    // Aerodynamic Passenger Cabin
    const cabinGeo = new THREE.BoxGeometry(2.10, 0.54, 1.58);
    const cabinMesh = new THREE.Mesh(cabinGeo, redPaintMat);
    cabinMesh.position.set(-0.16, 1.14, 0);
    cabinMesh.castShadow = true;
    group.add(cabinMesh);

    // Slanted Windshield
    const windshieldGeo = new THREE.BoxGeometry(0.72, 0.52, 1.52);
    const windshieldMesh = new THREE.Mesh(windshieldGeo, glassMat);
    windshieldMesh.position.set(0.74, 1.10, 0);
    windshieldMesh.rotation.z = -0.32;
    group.add(windshieldMesh);

    // Fastback Rear Glass
    const rearGlassGeo = new THREE.BoxGeometry(0.68, 0.50, 1.50);
    const rearGlassMesh = new THREE.Mesh(rearGlassGeo, glassMat);
    rearGlassMesh.position.set(-1.06, 1.12, 0);
    rearGlassMesh.rotation.z = 0.34;
    group.add(rearGlassMesh);

    // Front Bumper & Gloss Black Splitter
    const bumperGeo = new THREE.BoxGeometry(0.24, 0.34, 1.82);
    const bumperMesh = new THREE.Mesh(bumperGeo, redPaintMat);
    bumperMesh.position.set(2.16, 0.48, 0);
    group.add(bumperMesh);

    const splitterMesh = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.06, 1.86), blackTrimMat);
    splitterMesh.position.set(2.20, 0.26, 0);
    group.add(splitterMesh);

    // Front Grille
    const grilleMesh = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.28, 1.05), blackTrimMat);
    grilleMesh.position.set(2.23, 0.54, 0);
    group.add(grilleMesh);

    // LED Headlights
    const hlMat = new THREE.MeshStandardMaterial({ color: 0xffffff, emissive: 0xffffff, emissiveIntensity: 2.5 });
    const hlL = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.14, 0.36), hlMat);
    hlL.position.set(2.15, 0.64, 0.64);
    const hlR = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.14, 0.36), hlMat);
    hlR.position.set(2.15, 0.64, -0.64);
    group.add(hlL);
    group.add(hlR);

    // LED Taillights
    const tlMat = new THREE.MeshStandardMaterial({ color: 0x990000, emissive: 0xff0d25, emissiveIntensity: 2.2 });
    const tlBar = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.08, 1.70), tlMat);
    tlBar.position.set(-2.16, 0.72, 0);
    group.add(tlBar);

    // Quad Chrome Exhausts
    for (const ez of [0.58, 0.70, -0.58, -0.70]) {
      const exMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.046, 0.046, 0.14, 12), chromeMat);
      exMesh.rotation.z = Math.PI / 2;
      exMesh.position.set(-2.22, 0.28, ez);
      group.add(exMesh);
    }

    // 4 Performance Alloy Wheels & Tires
    const wheelPositions = [
      [1.42, 0.35, 0.92],
      [1.42, 0.35, -0.92],
      [-1.42, 0.35, 0.92],
      [-1.42, 0.35, -0.92]
    ];
    wheelPositions.forEach((pos) => {
      const wg = new THREE.Group();
      wg.position.set(pos[0], pos[1], pos[2]);

      const tire = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.35, 0.25, 24), rubberMat);
      tire.rotation.x = Math.PI / 2;
      tire.castShadow = true;
      wg.add(tire);

      const rim = new THREE.Mesh(new THREE.CylinderGeometry(0.25, 0.25, 0.26, 16), rimMat);
      rim.rotation.x = Math.PI / 2;
      wg.add(rim);

      group.add(wg);
    });
  }

  // ============================================================
  // PROCEDURAL PAINTER FALLBACK ENGINE
  // ============================================================
  function buildProceduralPainter(group) {
    group.position.set(0.45, 0.02, 1.72);
    group.rotation.y = -Math.PI / 2;

    const suitMat = new THREE.MeshStandardMaterial({ color: 0x1e2430, roughness: 0.65 });
    const accentMat = new THREE.MeshStandardMaterial({ color: 0xd4af37, roughness: 0.30 });
    const chromeMat = new THREE.MeshStandardMaterial({ color: 0xf1f5f9, metalness: 0.95, roughness: 0.10 });

    const head = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.26, 0.24), suitMat);
    head.position.set(0, 1.62, 0);
    group.add(head);

    const torso = new THREE.Mesh(new THREE.BoxGeometry(0.36, 0.68, 0.48), suitMat);
    torso.position.set(0, 1.15, 0);
    group.add(torso);

    const leg1 = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.09, 0.72, 12), suitMat);
    leg1.position.set(-0.06, 0.42, 0.15);
    const leg2 = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.09, 0.72, 12), suitMat);
    leg2.position.set(0.06, 0.42, -0.15);
    group.add(leg1);
    group.add(leg2);

    const arm1 = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.065, 0.45, 10), suitMat);
    arm1.rotation.z = Math.PI / 2;
    arm1.position.set(-0.35, 1.25, 0.24);
    group.add(arm1);

    const gunBody = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.20, 10), chromeMat);
    gunBody.rotation.z = Math.PI / 2;
    gunBody.position.set(-0.68, 1.25, 0.22);
    group.add(gunBody);

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
      camera.fov = 48;
    } else {
      camera.fov = 40;
    }
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  }
  window.addEventListener('resize', onWindowResize);

  // Clean-up on page unload
  window.addEventListener('beforeunload', () => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    window.removeEventListener('resize', onWindowResize);
    window.removeEventListener('themeChanged', update3DThemeColors);
  });
});
