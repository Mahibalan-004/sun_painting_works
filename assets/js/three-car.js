/* ============================================================
   SUN PAINTING WORKS - 3D ROTATING CAR ENGINE (THREE.JS)
   Automatic 360 Rotation, Mouse/Touch Drag, Zoom & Showroom Lights
   Loads assets/models/car.glb OR procedural metallic 3D car fallback
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('car3d-canvas');
  if (!container) return;

  // 1. Scene, Camera, Renderer Setup
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(0x0a0b0e);
  scene.fog = new THREE.FogExp2(0x0a0b0e, 0.025);

  const camera = new THREE.PerspectiveCamera(
    45,
    container.clientWidth / container.clientHeight,
    0.1,
    1000
  );
  camera.position.set(5, 2.5, 6);

  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setSize(container.clientWidth, container.clientHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.2;
  container.appendChild(renderer.domElement);

  // 2. OrbitControls (Mouse drag, Touch drag, Zoom)
  const controls = new THREE.OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.05;
  controls.maxPolarAngle = Math.PI / 2 - 0.02; // Don't clip ground
  controls.minDistance = 3;
  controls.maxDistance = 12;
  controls.autoRotate = true;
  controls.autoRotateSpeed = 1.8;

  // 3. Showroom Lighting setup
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
  scene.add(ambientLight);

  // Gold studio spotlight
  const goldSpot = new THREE.SpotLight(0xffd700, 4);
  goldSpot.position.set(8, 12, 6);
  goldSpot.angle = Math.PI / 4;
  goldSpot.penumbra = 0.8;
  goldSpot.castShadow = true;
  scene.add(goldSpot);

  // Metallic Silver Key Light
  const keyLight = new THREE.DirectionalLight(0xe8e8e8, 3);
  keyLight.position.set(-6, 8, -4);
  scene.add(keyLight);

  // Rim Light (Backlight)
  const rimLight = new THREE.DirectionalLight(0xd4af37, 2);
  rimLight.position.set(0, 5, -8);
  scene.add(rimLight);

  // 4. Showroom Floor & Grid
  const floorGeo = new THREE.PlaneGeometry(50, 50);
  const floorMat = new THREE.MeshStandardMaterial({
    color: 0x12141c,
    roughness: 0.2,
    metalness: 0.8,
  });
  const floor = new THREE.Mesh(floorGeo, floorMat);
  floor.rotation.x = -Math.PI / 2;
  floor.position.y = 0;
  floor.receiveShadow = true;
  scene.add(floor);

  // Floor Gold Grid
  const gridHelper = new THREE.GridHelper(30, 30, 0xd4af37, 0x222630);
  gridHelper.position.y = 0.01;
  scene.add(gridHelper);

  // 5. Car Container Group
  const carGroup = new THREE.Group();
  scene.add(carGroup);

  // 6. Attempt Loading GLB Model; Fallback to High-Detail Procedural 3D Car
  let carLoaded = false;
  if (typeof THREE.GLTFLoader !== 'undefined') {
    const loader = new THREE.GLTFLoader();
    loader.load(
      'assets/models/car.glb',
      (gltf) => {
        const model = gltf.scene;
        model.traverse((node) => {
          if (node.isMesh) {
            node.castShadow = true;
            node.receiveShadow = true;
          }
        });
        // Center & Scale GLB model
        const box = new THREE.Box3().setFromObject(model);
        const center = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const scale = 4 / maxDim;
        model.scale.set(scale, scale, scale);
        model.position.sub(center.multiplyScalar(scale));
        model.position.y += size.y * scale * 0.5;

        carGroup.add(model);
        carLoaded = true;
        console.log('GLB Car Model loaded successfully!');
      },
      undefined,
      (err) => {
        console.log('car.glb not found or failed to load. Rendering procedural metallic 3D car fallback.');
        buildProceduralCar(carGroup);
      }
    );
  } else {
    buildProceduralCar(carGroup);
  }

  // Helper Function: Build Realistic Procedural 3D Car
  function buildProceduralCar(group) {
    const bodyMat = new THREE.MeshStandardMaterial({
      color: 0x1a1c23,
      metalness: 0.9,
      roughness: 0.1,
    });

    const goldAccentMat = new THREE.MeshStandardMaterial({
      color: 0xd4af37,
      metalness: 0.8,
      roughness: 0.2,
    });

    const glassMat = new THREE.MeshPhysicalMaterial({
      color: 0x11151c,
      metalness: 0.1,
      roughness: 0.1,
      transmission: 0.6,
      transparent: true,
    });

    const chromeMat = new THREE.MeshStandardMaterial({
      color: 0xe0e0e0,
      metalness: 1.0,
      roughness: 0.05,
    });

    const rubberMat = new THREE.MeshStandardMaterial({
      color: 0x111111,
      roughness: 0.8,
    });

    // Main Car Chassis Base
    const baseGeo = new THREE.BoxGeometry(3.6, 0.7, 1.8);
    const baseMesh = new THREE.Mesh(baseGeo, bodyMat);
    baseMesh.position.y = 0.65;
    baseMesh.castShadow = true;
    group.add(baseMesh);

    // Car Cabin / Roof (Curved shape)
    const cabinGeo = new THREE.BoxGeometry(2.0, 0.6, 1.5);
    const cabinMesh = new THREE.Mesh(cabinGeo, bodyMat);
    cabinMesh.position.set(-0.2, 1.25, 0);
    cabinMesh.castShadow = true;
    group.add(cabinMesh);

    // Windshield Glass
    const glassGeo = new THREE.BoxGeometry(0.7, 0.55, 1.48);
    const glassMesh = new THREE.Mesh(glassGeo, glassMat);
    glassMesh.position.set(0.65, 1.25, 0);
    glassMesh.rotation.z = -0.3;
    group.add(glassMesh);

    // Gold Racing Stripes
    const stripeGeo = new THREE.BoxGeometry(3.62, 0.02, 0.3);
    const stripeMesh = new THREE.Mesh(stripeGeo, goldAccentMat);
    stripeMesh.position.set(0, 1.01, 0);
    group.add(stripeMesh);

    // Front Gold Grille & Bumper
    const grilleGeo = new THREE.BoxGeometry(0.05, 0.35, 1.2);
    const grilleMesh = new THREE.Mesh(grilleGeo, goldAccentMat);
    grilleMesh.position.set(1.81, 0.65, 0);
    group.add(grilleMesh);

    // LED Headlights
    const headLightGeo = new THREE.BoxGeometry(0.08, 0.15, 0.35);
    const headLightMat = new THREE.MeshStandardMaterial({
      color: 0xffffff,
      emissive: 0xfff0aa,
      emissiveIntensity: 2,
    });
    const headL = new THREE.Mesh(headLightGeo, headLightMat);
    headL.position.set(1.81, 0.72, 0.6);
    const headR = new THREE.Mesh(headLightGeo, headLightMat);
    headR.position.set(1.81, 0.72, -0.6);
    group.add(headL);
    group.add(headR);

    // Wheels (4 Alloy Wheels)
    const wheelPositions = [
      [1.1, 0.35, 0.95],
      [1.1, 0.35, -0.95],
      [-1.1, 0.35, 0.95],
      [-1.1, 0.35, -0.95],
    ];

    wheelPositions.forEach((pos) => {
      const wheelGroup = new THREE.Group();
      wheelGroup.position.set(pos[0], pos[1], pos[2]);

      // Tire Rubber
      const tireGeo = new THREE.CylinderGeometry(0.35, 0.35, 0.25, 24);
      const tireMesh = new THREE.Mesh(tireGeo, rubberMat);
      tireMesh.rotation.x = Math.PI / 2;
      tireMesh.castShadow = true;
      wheelGroup.add(tireMesh);

      // Gold Alloy Rim
      const rimGeo = new THREE.CylinderGeometry(0.24, 0.24, 0.26, 12);
      const rimMesh = new THREE.Mesh(rimGeo, goldAccentMat);
      rimMesh.rotation.x = Math.PI / 2;
      wheelGroup.add(rimMesh);

      group.add(wheelGroup);
    });
  }

  // 7. Animation Loop
  function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
  }
  animate();

  // 8. Responsive Resize Handler
  window.addEventListener('resize', () => {
    if (!container) return;
    camera.aspect = container.clientWidth / container.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(container.clientWidth, container.clientHeight);
  });
});
