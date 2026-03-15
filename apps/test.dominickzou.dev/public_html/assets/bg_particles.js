'use strict';

/**
 * bg_particles.js
 * Persistent floating background particles.
 * Uses seeded PRNG + shared cookie epoch for cross-domain continuity.
 */
(() => {
    const canvas = document.createElement('canvas');
    canvas.id = 'bg-particles';
    canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;';
    document.body.prepend(canvas);

    const ctx = canvas.getContext('2d');
    const PARTICLE_COUNT = 120;
    const FRICTION = 0.92;
    const HIT_RADIUS = 120;
    const HIT_MULTIPLIER = 0.6;

    // Seeded PRNG for deterministic particle positions across domains
    function mulberry32(seed) {
        return function() {
            seed |= 0; seed = seed + 0x6D2B79F5 | 0;
            let t = Math.imul(seed ^ seed >>> 15, 1 | seed);
            t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
            return ((t ^ t >>> 14) >>> 0) / 4294967296;
        };
    }

    // Shared epoch via cookie on .dominickzou.dev
    function getEpoch() {
        const m = document.cookie.match(/_particle_epoch=([0-9.]+)/);
        if (m) return Number(m[1]);
        const now = performance.now();
        setEpoch(now);
        return now;
    }
    function setEpoch(val) {
        document.cookie = '_particle_epoch=' + val + ';domain=.dominickzou.dev;path=/;max-age=86400;SameSite=Lax';
    }

    const epoch = getEpoch();
    function getTime() {
        return (performance.now() - epoch) / 1000;
    }

    let W, H, dpr;
    let particles = [];
    const mouse = { x: -9999, y: -9999, px: -9999, py: -9999, vx: 0, vy: 0 };

    window.addEventListener('mousemove', e => {
        mouse.px = mouse.x; mouse.py = mouse.y;
        mouse.x = e.clientX; mouse.y = e.clientY;
        mouse.vx = mouse.x - mouse.px; mouse.vy = mouse.y - mouse.py;
    });
    window.addEventListener('mouseleave', () => {
        mouse.x = mouse.px = -9999; mouse.y = mouse.py = -9999;
        mouse.vx = mouse.vy = 0;
    });
    window.addEventListener('touchmove', e => {
        mouse.px = mouse.x; mouse.py = mouse.y;
        mouse.x = e.touches[0].clientX; mouse.y = e.touches[0].clientY;
        mouse.vx = mouse.x - mouse.px; mouse.vy = mouse.y - mouse.py;
    }, { passive: true });
    window.addEventListener('touchend', () => {
        mouse.x = mouse.px = -9999; mouse.y = mouse.py = -9999;
        mouse.vx = mouse.vy = 0;
    });

    function initParticles() {
        particles = [];
        const seeded = mulberry32(42);
        const now = getTime();
        for (let i = 0; i < PARTICLE_COUNT; i++) {
            const px = seeded() * W;
            const py = seeded() * H;
            const driftPhase = seeded() * Math.PI * 2;
            const driftSpeed = 0.15 + seeded() * 0.4;
            const driftAmp = 15 + seeded() * 40;
            const t = now * driftSpeed + driftPhase;
            particles.push({
                homeX: px, homeY: py,
                x: px + Math.sin(t) * driftAmp,
                y: py + Math.cos(t * 0.7) * driftAmp,
                vx: 0, vy: 0,
                radius: 0.8 + seeded() * 1.0,
                alpha: 0.35 + seeded() * 0.3,
                driftPhase: driftPhase,
                driftSpeed: driftSpeed,
                driftAmp: driftAmp
            });
        }
    }

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        W = window.innerWidth; H = window.innerHeight;
        canvas.width = W * dpr; canvas.height = H * dpr;
        canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        initParticles();
    }

    function animate() {
        ctx.clearRect(0, 0, W, H);
        const time = getTime();

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
            p.x += (p.homeX + Math.sin(t) * p.driftAmp - p.x) * 0.09;
            p.y += (p.homeY + Math.cos(t * 0.7) * p.driftAmp - p.y) * 0.09;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(25, 25, 45, ${p.alpha})`;
            ctx.fill();
        }

        mouse.vx *= 0.5; mouse.vy *= 0.5;
        requestAnimationFrame(animate);
    }

    window.addEventListener('resize', resize);
    resize();
    requestAnimationFrame(animate);
})();
