/**
 * Fluid Amorphous Canvas Background (Light, Global View)
 * Implements a slow-moving, airy organic mesh that covers the screen and subtly shifts with the mouse.
 */

const canvas = document.createElement('canvas');
canvas.id = 'unified-bg-canvas';
document.body.insertBefore(canvas, document.body.firstChild);
const ctx = canvas.getContext('2d');

let width, height;
let time = 0;

function resize() {
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = width;
    canvas.height = height;
}

window.addEventListener('resize', resize);
resize();

// Create multiple massive light-toned orbs for a non-localized, global feel
const orbs = [
    { color: '#f8fafc', size: 0.9, offsetX: 0, offsetY: 0 },       // slate-50
    { color: '#f1f5f9', size: 0.8, offsetX: 0.3, offsetY: -0.2 },  // slate-100
    { color: '#e2e8f0', size: 1.0, offsetX: -0.2, offsetY: 0.3 }   // slate-200
];

function drawMesh() {
    ctx.clearRect(0, 0, width, height);
    
    // Heavy blur for an extremely soft, fluid mesh look
    ctx.filter = 'blur(140px)';
    
    const baseRadius = Math.max(width, height) * 0.6;
    
    for (let i = 0; i < orbs.length; i++) {
        const orb = orbs[i];
        
        // Slowly shifting positions (passive orbit)
        const ox = (width / 2) + (Math.sin(time * 0.4 + i) * width * orb.offsetX);
        const oy = (height / 2) + (Math.cos(time * 0.4 + i) * height * orb.offsetY);
        
        // Slight organic pulsing
        const r = baseRadius * orb.size * (1 + Math.sin(time + i * 1.5) * 0.05);
        
        ctx.beginPath();
        ctx.arc(ox, oy, r, 0, Math.PI * 2);
        ctx.fillStyle = orb.color;
        ctx.fill();
    }
    
    ctx.filter = 'none';
    time += 0.008; // Very slow and elegant
    requestAnimationFrame(drawMesh);
}

drawMesh();
