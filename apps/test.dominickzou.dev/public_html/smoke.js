'use strict';

/**
 * smoke.js — Text particle formation for the landing page.
 * Exports window.SmokeText with init() and destroy() methods.
 * Background particles are handled separately by bg_particles.js.
 */
window.SmokeText = (() => {
    const RETURN_SPEED = 0.09;
    const FRICTION = 0.92;
    const HIT_RADIUS = 100;
    const HIT_MULTIPLIER = 0.8;
    const PARTICLE_RADIUS = 1.5;
    const NAME_GRID = 2;
    const BRACE_GRID = 2;

    // Dark mode detection
    const darkMQ = window.matchMedia('(prefers-color-scheme: dark)');
    let isDark = darkMQ.matches;
    darkMQ.addEventListener('change', e => { isDark = e.matches; });

    let W, H, dpr;
    let canvas, ctx;
    let particles = [];
    let animId = null;
    let time = 0;
    let active = false;
    const mouse = { x: -9999, y: -9999, px: -9999, py: -9999, vx: 0, vy: 0 };

    function onMouseMove(e) {
        mouse.px = mouse.x; mouse.py = mouse.y;
        mouse.x = e.clientX; mouse.y = e.clientY;
        mouse.vx = mouse.x - mouse.px; mouse.vy = mouse.y - mouse.py;
    }
    function onMouseLeave() {
        mouse.x = mouse.px = -9999; mouse.y = mouse.py = -9999;
        mouse.vx = mouse.vy = 0;
    }
    function onTouchMove(e) {
        e.preventDefault();
        mouse.px = mouse.x; mouse.py = mouse.y;
        mouse.x = e.touches[0].clientX; mouse.y = e.touches[0].clientY;
        mouse.vx = mouse.x - mouse.px; mouse.vy = mouse.y - mouse.py;
    }
    function onTouchEnd() {
        mouse.x = mouse.px = -9999; mouse.y = mouse.py = -9999;
        mouse.vx = mouse.vy = 0;
    }

    function sampleStroke(drawFn, gridSpacing, out) {
        const off = document.createElement('canvas');
        const oc = off.getContext('2d');
        off.width = W; off.height = H;
        drawFn(oc);
        const data = oc.getImageData(0, 0, W, H).data;

        for (let y = 0; y < H; y += gridSpacing) {
            for (let x = 0; x < W; x += gridSpacing) {
                if (Math.random() < 0.15) continue;
                const jx = (Math.random() - 0.5) * gridSpacing * 0.9;
                const jy = (Math.random() - 0.5) * gridSpacing * 0.9;
                const sx = Math.round(x + jx);
                const sy = Math.round(y + jy);
                if (sx < 0 || sx >= W || sy < 0 || sy >= H) continue;
                const idx = (sy * W + sx) * 4;
                if (data[idx + 3] > 128) {
                    let spawnX, spawnY;
                    if (Math.random() < 0.1) {
                        if (Math.random() < 0.5) {
                            spawnX = Math.random() < 0.5 ? 0 : W;
                            spawnY = Math.random() * H;
                        } else {
                            spawnX = Math.random() * W;
                            spawnY = Math.random() < 0.5 ? 0 : H;
                        }
                    } else {
                        spawnX = Math.random() * W;
                        spawnY = Math.random() * H;
                    }
                    out.push({
                        homeX: sx, homeY: sy,
                        x: spawnX, y: spawnY,
                        vx: 0, vy: 0,
                        radius: PARTICLE_RADIUS * (0.5 + Math.random() * 1.0),
                        alpha: 0.3 + Math.random() * 0.55,
                        driftPhase: Math.random() * Math.PI * 2,
                        driftSpeed: 0.3 + Math.random() * 0.7,
                        driftAmp: 1.5 + Math.random() * 2.0
                    });
                }
            }
        }
    }

    function initParticles() {
        particles = [];
        const nameFontSize = Math.min(W * 0.065, 100);
        const braceFontSize = nameFontSize * 3.5;
        const fontFamily = 'Inter, -apple-system, sans-serif';

        const measure = document.createElement('canvas').getContext('2d');
        measure.font = `600 ${nameFontSize}px ${fontFamily}`;
        const nameWidth = measure.measureText('\'Dominick Zou\'').width;
        const braceOffset = nameWidth / 2 + braceFontSize * 0.08;

        sampleStroke(oc => {
            oc.font = `600 ${nameFontSize}px ${fontFamily}`;
            oc.textAlign = 'center'; oc.textBaseline = 'middle';
            oc.fillStyle = '#000';
            oc.fillText('\'Dominick Zou\'', W / 2, H / 2);
        }, NAME_GRID, particles);

        sampleStroke(oc => {
            oc.font = `200 ${braceFontSize}px ${fontFamily}`;
            oc.textAlign = 'center'; oc.textBaseline = 'middle';
            oc.fillStyle = '#000';
            oc.fillText('{', W / 2 - braceOffset, H / 2);
            oc.fillText('}', W / 2 + braceOffset, H / 2);
        }, BRACE_GRID, particles);
    }

    function resize() {
        if (!canvas) return;
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        W = window.innerWidth; H = window.innerHeight;
        canvas.width = W * dpr; canvas.height = H * dpr;
        canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        initParticles();
    }

    function animate() {
        if (!active) return;
        ctx.clearRect(0, 0, W, H);
        time += 0.016;

        const cursorSpeed = Math.sqrt(mouse.vx * mouse.vx + mouse.vy * mouse.vy);
        const activeRadius = Math.max(30, HIT_RADIUS - cursorSpeed * 2);

        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];
            const dx = p.x - mouse.x;
            const dy = p.y - mouse.y;
            const dist = Math.sqrt(dx * dx + dy * dy);

            if (dist < activeRadius && dist > 0.1 && cursorSpeed > 1) {
                const proximity = 1 - dist / activeRadius;
                const hitStrength = cursorSpeed * HIT_MULTIPLIER * proximity;
                p.vx += (dx / dist) * hitStrength * 0.3;
                p.vy += (dy / dist) * hitStrength * 0.3;
                p.vx += mouse.vx * hitStrength * 0.04;
                p.vy += mouse.vy * hitStrength * 0.04;
            }

            p.vx *= FRICTION; p.vy *= FRICTION;
            p.x += p.vx; p.y += p.vy;

            const t = time * p.driftSpeed + p.driftPhase;
            p.x += (p.homeX + Math.sin(t) * p.driftAmp - p.x) * RETURN_SPEED;
            p.y += (p.homeY + Math.cos(t * 0.7) * p.driftAmp - p.y) * RETURN_SPEED;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = isDark ? '#fff' : `rgba(25, 25, 45, ${p.alpha})`;
            ctx.fill();
        }

        mouse.vx *= 0.5; mouse.vy *= 0.5;
        animId = requestAnimationFrame(animate);
    }

    return {
        init() {
            canvas = document.getElementById('fluid');
            if (!canvas || active) return;
            ctx = canvas.getContext('2d');
            active = true;
            time = 0;

            canvas.addEventListener('mousemove', onMouseMove);
            canvas.addEventListener('mouseleave', onMouseLeave);
            canvas.addEventListener('touchmove', onTouchMove, { passive: false });
            canvas.addEventListener('touchend', onTouchEnd);
            window.addEventListener('resize', resize);

            resize();
            animId = requestAnimationFrame(animate);
        },

        destroy() {
            active = false;
            if (animId) { cancelAnimationFrame(animId); animId = null; }
            if (canvas) {
                canvas.removeEventListener('mousemove', onMouseMove);
                canvas.removeEventListener('mouseleave', onMouseLeave);
                canvas.removeEventListener('touchmove', onTouchMove);
                canvas.removeEventListener('touchend', onTouchEnd);
            }
            window.removeEventListener('resize', resize);
            particles = [];
            canvas = null; ctx = null;
        }
    };
})();
