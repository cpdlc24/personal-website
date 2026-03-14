/**
 * Landing Page Interactive Smoke Canvas (Wavy 3D Fluid Ink)
 * Generates wavy, distorted sprites rather than perfect spheres, creating 
 * intersecting sweeps and folds that emulate complex 3D fluid simulations.
 */

const canvas = document.createElement('canvas');
canvas.id = 'landing-bg-canvas';
document.body.insertBefore(canvas, document.body.firstChild);
const ctx = canvas.getContext('2d');

let width, height;
let particles = [];
let mouse = { x: -1000, y: -1000, radius: 250 }; 

// Pre-render "ink" puffs with ridged, wavy edges to create fluid folds when overlapping.
function createFluidWaveSprite(colorSettings) {
    const offscreen = document.createElement('canvas');
    const size = 500; // Very large for sweeping curves
    const center = size / 2;
    offscreen.width = size;
    offscreen.height = size;
    const offCtx = offscreen.getContext('2d');

    const grad = offCtx.createRadialGradient(center, center, 0, center, center, center * 0.8);
    
    // Deep, dark core with a smooth transitional edge
    grad.addColorStop(0, colorSettings.core);        
    grad.addColorStop(0.3, colorSettings.mid); 
    grad.addColorStop(0.7, colorSettings.edge); 
    grad.addColorStop(1, 'rgba(0,0,0,0)');                   

    offCtx.fillStyle = grad;
    
    // Instead of a perfect circle, draw a heavily distorted, wavy "splat"
    // When many of these rotate and overlap, their distinct curves look like 3D fluid folds
    offCtx.beginPath();
    for (let i = 0; i <= Math.PI * 2.1; i += 0.05) {
        // 3 major sweeping ridges, 5 minor structural waves
        let radius = center * (0.6 + Math.sin(i * 3) * 0.25 + Math.cos(i * 5) * 0.15);
        let x = center + Math.cos(i) * radius;
        let y = center + Math.sin(i) * radius;
        
        if (i === 0) offCtx.moveTo(x, y);
        else offCtx.lineTo(x, y);
    }
    offCtx.closePath();
    offCtx.fill();

    return offscreen;
}

// Exactly matching the rich indigo/slate/charcoal core of the image
const spriteTemplates = [
    createFluidWaveSprite({ core: 'rgba(2, 6, 23, 1)',  mid: 'rgba(15, 23, 42, 0.95)', edge: 'rgba(30, 41, 59, 0.3)' }),    
    createFluidWaveSprite({ core: 'rgba(5, 5, 25, 1)',  mid: 'rgba(10, 15, 50, 0.95)', edge: 'rgba(15, 25, 60, 0.3)' }),   
    createFluidWaveSprite({ core: 'rgba(15, 8, 30, 1)', mid: 'rgba(25, 12, 50, 0.95)', edge: 'rgba(35, 15, 70, 0.3)' }),  
    createFluidWaveSprite({ core: 'rgba(12, 12, 12, 1)',mid: 'rgba(24, 24, 24, 0.95)', edge: 'rgba(36, 36, 36, 0.3)' })  
];

function resize() {
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = width;
    canvas.height = height;
    initParticles();
}

function randomGaussian(mean = 0, stdev = 1) {
    const u = 1 - Math.random(); 
    const v = Math.random();
    const z = Math.sqrt( -2.0 * Math.log( u ) ) * Math.cos( 2.0 * Math.PI * v );
    return z * stdev + mean;
}

function initParticles() {
    particles = [];
    
    // Dense particle count to interlock the wavy folds
    const numParticles = 160; 
    
    const centerX = width / 2;
    const centerY = height / 2;
    
    // Macro Clusters to build asymmetrical silhouette
    const scale = Math.min(width, height) * 0.25;
    const baseRadius = scale * 2.2; // Massive curves
    
    const clusters = [
        { x: 0, y: 0, weight: 0.5, spread: 0.5 },           // Massive Center Core
        { x: -0.4, y: -0.3, weight: 0.125, spread: 0.4 },   // Top Left
        { x: 0.5, y: -0.3, weight: 0.125, spread: 0.4 },    // Top Right
        { x: -0.5, y: 0.4, weight: 0.125, spread: 0.4 },    // Bottom Left
        { x: 0.4, y: 0.5, weight: 0.125, spread: 0.4 }      // Bottom Right
    ];

    for (let i = 0; i < numParticles; i++) {
        let rand = Math.random();
        let selectedCluster = clusters[0];
        let runningWeight = 0;
        for (let cluster of clusters) {
            runningWeight += cluster.weight;
            if (rand <= runningWeight) {
                selectedCluster = cluster;
                break;
            }
        }
        
        let targetX = centerX + (selectedCluster.x * scale);
        let targetY = centerY + (selectedCluster.y * scale);
        let spread = selectedCluster.spread * scale;

        let px = randomGaussian(targetX, spread);
        let py = randomGaussian(targetY, spread);
        
        // Push heavy outliers in
        const distFromTarget = Math.sqrt(Math.pow(px-targetX, 2) + Math.pow(py-targetY, 2));
        if (distFromTarget > spread * 1.5) {
            px = targetX + (Math.random() - 0.5) * spread;
            py = targetY + (Math.random() - 0.5) * spread;
        }

        particles.push({
            x: px,
            y: py,
            size: baseRadius * (Math.random() * 0.4 + 0.6), 
            baseX: px,
            baseY: py,
            density: (Math.random() * 60) + 20, 
            baseOpacity: Math.random() * 0.6 + 0.4, 
            opacity: 0,
            angle: Math.random() * Math.PI * 2, // Random initial orientation of the wavy folds
            sprite: spriteTemplates[Math.floor(Math.random() * spriteTemplates.length)],
            driftSpeedX: (Math.random() * 0.001) + 0.0005,
            driftSpeedY: (Math.random() * 0.001) + 0.0005,
            driftOffset: Math.random() * Math.PI * 2,
            rotationDir: i % 2 === 0 ? 1 : -1,
            rotationSpeed: Math.random() * 0.00004 + 0.00001
        });
        
        particles[i].opacity = particles[i].baseOpacity;
    }
}

window.addEventListener('resize', resize);
window.addEventListener('mousemove', (e) => {
    mouse.x = e.clientX;
    mouse.y = e.clientY;
});
window.addEventListener('mouseout', () => {
    mouse.x = -1000;
    mouse.y = -1000;
});
resize();

function animate() {
    ctx.clearRect(0, 0, width, height);
    
    // Normal alpha blending to maintain raw color density
    ctx.globalCompositeOperation = 'source-over';
    
    for (let i = 0; i < particles.length; i++) {
        let p = particles[i];
        
        let dx = mouse.x - p.x;
        let dy = mouse.y - p.y;
        let distance = Math.sqrt(dx * dx + dy * dy);
        
        if (distance < mouse.radius) {
            let forceDirectionX = dx / distance;
            let forceDirectionY = dy / distance;
            let force = (mouse.radius - distance) / mouse.radius;
            
            p.x -= forceDirectionX * force * p.density;
            p.y -= forceDirectionY * force * p.density;
            p.opacity = Math.max(0, p.opacity - 0.15); // Disperse
        } else {
            if (p.x !== p.baseX) p.x -= (p.x - p.baseX) / 15; 
            if (p.y !== p.baseY) p.y -= (p.y - p.baseY) / 15;
            if (p.opacity < p.baseOpacity) p.opacity += 0.03; 
        }
        
        // Lissajous curve drifting (figure-8 flowing over time, not just circles)
        const driftX = Math.sin(Date.now() * p.driftSpeedX + p.driftOffset) * 0.8;
        const driftY = Math.sin(Date.now() * p.driftSpeedY * 1.5 + p.driftOffset) * 0.8;
        
        let drawX = p.x + driftX;
        let drawY = p.y + driftY;

        if (p.opacity > 0.01) {
            ctx.globalAlpha = p.opacity;
            
            ctx.save();
            ctx.translate(drawX, drawY);
            // Constant, varied rotation causing the wavy ridges to slide over one another seamlessly
            ctx.rotate(p.angle + (Date.now() * p.rotationSpeed * p.rotationDir)); 
            
            ctx.drawImage(p.sprite, -p.size, -p.size, p.size * 2, p.size * 2);
            
            ctx.restore();
        }
    }
    
    ctx.globalAlpha = 1.0;
    requestAnimationFrame(animate);
}

animate();
