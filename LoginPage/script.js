$(document).ready(function() {

    // 1. Three.js Background Particle Sphere
    initThreeJS();

    // 2. Image Sequencer Logic
    const canvas = document.getElementById('sequence-canvas');
    const ctx = canvas.getContext('2d');
    
    const TOTAL_FRAMES = 21;
    
    // We will simulate 800x800 internal rendering canvas base
    canvas.width = 800;
    canvas.height = 800;

    const images = new Array(TOTAL_FRAMES);
    let loadedImages = 0;

    // Preload
    for (let i = 1; i <= TOTAL_FRAMES; i++) {
        const img = new Image();
        const index = i - 1;
        
        // Setup image. To allow you to preview without assets, 
        // we'll draw a fallback if loading fails
        img.onload = () => {
            images[index] = img;
            loadedImages++;
            checkAllLoaded();
        };

        const frameNumber = String(i).padStart(3, '0');

        img.onerror = () => {
            console.warn(`assets/frame_${frameNumber}.png missing. Applying fallback.`);
            const fallback = createFallbackCanvasText(`Frame ${i}`);
            images[index] = fallback;
            loadedImages++;
            checkAllLoaded();
        };

        img.src = `assets/frame_${frameNumber}.png`;
    }

    // Fallback image generator (returns an Image-like object via canvas data URL)
    function createFallbackCanvasText(text) {
        const tempC = document.createElement('canvas');
        tempC.width = 800;
        tempC.height = 800;
        const tCtx = tempC.getContext('2d');
        
        // draw slightly visible gradient box
        let gradient = tCtx.createLinearGradient(0,0,800,800);
        gradient.addColorStop(0, "rgba(92, 60, 252, 0.1)");
        gradient.addColorStop(1, "rgba(255, 255, 255, 0.05)");
        tCtx.fillStyle = gradient;
        tCtx.fillRect(0,0,800,800);

        tCtx.fillStyle = '#a0a5cc';
        tCtx.font = 'bold 60px Outfit, sans-serif';
        tCtx.textAlign = 'center';
        tCtx.textBaseline = 'middle';
        tCtx.fillText(text, 400, 400);

        const img = new Image();
        img.src = tempC.toDataURL();
        return img;
    }

    let isAnimationStarted = false;

    function checkAllLoaded() {
        if (loadedImages === TOTAL_FRAMES && !isAnimationStarted) {
            isAnimationStarted = true;
            playSequence();
        }
    }

    function renderFrame(index) {
        if (!images[index] || (!images[index].complete && !images[index].src.startsWith('data:'))) return;
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Draw centered and scaled to fit the 800x800 bounded box
        const img = images[index];
        const scale = Math.min(canvas.width / img.width, canvas.height / img.height);
        const drawWidth = img.width * scale;
        const drawHeight = img.height * scale;
        const x = (canvas.width - drawWidth) / 2;
        const y = (canvas.height - drawHeight) / 2;
        
        ctx.drawImage(img, x, y, drawWidth, drawHeight);
    }

    function playSequence() {
        let currentFrame = 0;
        let lastTimestamp = 0;
        let isLooping = false;
        let formShown = false;

        // Render very first frame instantly to avoid empty canvas flash
        renderFrame(0);

        function step(timestamp) {
            if (!lastTimestamp) lastTimestamp = timestamp;
            const elapsed = timestamp - lastTimestamp;

            let currentFPS = isLooping ? 12 : 6;
            let frameInterval = 1000 / currentFPS;

            if (elapsed >= frameInterval) {
                renderFrame(currentFrame);
                
                // Show form at frame 15 (index 14)
                if (currentFrame === 14 && !formShown) {
                    showForm();
                    formShown = true;
                }

                currentFrame++;
                lastTimestamp = timestamp - (elapsed % frameInterval);

                if (!isLooping && currentFrame >= TOTAL_FRAMES) {
                    isLooping = true;
                    // Switch to looping frames 18-21 (indices 17-20)
                    currentFrame = 17;
                } else if (isLooping && currentFrame >= TOTAL_FRAMES) {
                    // Stay in loop
                    currentFrame = 17;
                }
            }
            requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    }

    function showForm() {
        const $formWrapper = $('#sign-in-container');
        // Show then apply exactly 1-second fade in
        $formWrapper.show().css({opacity: 0}).animate({opacity: 1}, 1000);
    }

    // ==========================================
    // Three.js Background Implementation
    // ==========================================
    function initThreeJS() {
        const container = document.getElementById('three-container');
        if (!container) return;

        const scene = new THREE.Scene();
        
        const camera = new THREE.PerspectiveCamera(
            75, window.innerWidth / window.innerHeight, 0.1, 1000
        );
        camera.position.z = 100;

        const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        container.appendChild(renderer.domElement);

        // Particles
        const geometry = new THREE.BufferGeometry();
        const particlesCount = 700;
        const posArray = new Float32Array(particlesCount * 3);

        for(let i=0; i < particlesCount * 3; i++) {
            // SPHERE DISTRIBUTION
            posArray[i] = (Math.random() - 0.5) * 250;
        }

        geometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));

        const material = new THREE.PointsMaterial({
            size: 0.8,
            color: 0x8e72ff,
            transparent: true,
            opacity: 0.6,
            blending: THREE.AdditiveBlending
        });

        const particlesMesh = new THREE.Points(geometry, material);
        scene.add(particlesMesh);

        // Mouse Interactivity
        let mouseX = 0;
        let mouseY = 0;
        
        document.addEventListener('mousemove', (event) => {
            mouseX = event.clientX / window.innerWidth - 0.5;
            mouseY = event.clientY / window.innerHeight - 0.5;
        });

        const clock = new THREE.Clock();

        function animate() {
            requestAnimationFrame(animate);
            const elapsedTime = clock.getElapsedTime();

            // Slower vertical floating
            particlesMesh.rotation.y = elapsedTime * 0.03;
            particlesMesh.rotation.x = elapsedTime * 0.015;

            // Parallax mouse effect
            camera.position.x += (mouseX * 40 - camera.position.x) * 0.05;
            camera.position.y += (-mouseY * 40 - camera.position.y) * 0.05;
            camera.lookAt(scene.position);

            renderer.render(scene, camera);
        }
        animate();

        // Responsive Resizing
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });
    }

});
