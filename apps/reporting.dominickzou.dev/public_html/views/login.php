<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="prefetch" href="https://test.dominickzou.dev/">
    <style>
        body { margin: 0; overflow: hidden; background: #fafafa; font-family: 'Inter', -apple-system, sans-serif; -webkit-font-smoothing: antialiased; }
        #bg-particles { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }

        /* ── Top Nav (matches test.dominickzou.dev subpage nav) ── */
        .top-nav {
            position: fixed; top: 0; width: 100%; z-index: 50;
            padding: 2.5rem 4rem;
        }
        .top-nav .nav-inner {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.65rem; letter-spacing: 0.15em;
            text-transform: uppercase; font-weight: 500;
        }
        .top-nav a {
            color: #111; text-decoration: none;
            transition: opacity 0.3s ease;
        }
        .top-nav a:hover { opacity: 0.5; }
        .top-nav .nav-links { display: flex; gap: 2rem; }
        .top-nav .nav-links a { opacity: 0.5; }
        .top-nav .nav-links a:hover { opacity: 1; }

        /* ── Login Container ── */
        .login-container {
            position: relative; z-index: 10;
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center;
        }
        .login-card {
            width: 100%; max-width: 440px; margin: 0 1.5rem;
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 24px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.04);
            padding: 3.5rem 3rem 3rem;
            opacity: 0; transform: translateY(16px);
            animation: loginCardIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.3s forwards;
        }
        @keyframes loginCardIn {
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header { text-align: center; margin-bottom: 3rem; }
        .login-header h1 {
            font-size: 2rem; font-weight: 500; letter-spacing: -0.03em;
            color: #111; margin: 0 0 6px;
        }
        .login-header p {
            font-size: 10px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.12em; color: #9ca3af; margin: 0;
        }

        .login-form { display: flex; flex-direction: column; gap: 1.75rem; }
        .form-group { position: relative; }
        .form-group label {
            display: block; font-size: 10px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.1em;
            color: #9ca3af; margin-bottom: 8px;
        }
        .form-group input {
            width: 100%; background: transparent;
            border: none; border-bottom: 1px solid #e5e7eb;
            padding: 12px 0; font-size: 15px; color: #111;
            outline: none; transition: border-color 0.2s ease;
            border-radius: 0; font-family: inherit;
            box-sizing: border-box;
        }
        .form-group input:focus { border-bottom-color: #111; }
        .form-group input::placeholder { color: #d1d5db; }

        .login-error {
            font-size: 13px; color: #dc2626;
            padding: 10px 16px; background: rgba(220,38,38,0.04);
            border-radius: 10px; display: flex; align-items: center; gap: 10px;
            margin-bottom: 0.25rem;
            animation: errorShake 0.35s ease;
        }
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }

        .login-footer {
            display: flex; justify-content: center;
            margin-top: 0.5rem;
        }
        .btn-submit {
            font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.08em; color: #fff; background: #111;
            border: none; border-radius: 10px; padding: 12px 36px;
            cursor: pointer; transition: all 0.2s ease;
            font-family: inherit;
        }
        .btn-submit:hover { background: #333; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        @media (max-width: 640px) {
            .top-nav { padding: 1.5rem; }
            .top-nav .nav-links { gap: 1.2rem; }
            .login-card { padding: 2.5rem 2rem 2rem; }
        }
    </style>
</head>
<body>
    <canvas id="bg-particles"></canvas>

    <!-- Top Navigation Bar (test.dominickzou.dev style) -->
    <nav class="top-nav">
        <div class="nav-inner">
            <a href="https://test.dominickzou.dev">Dominick Zou</a>
            <div class="nav-links">
                <a href="https://test.dominickzou.dev/work.html">Work</a>
                <a href="https://test.dominickzou.dev/about.html">About</a>
                <a href="https://test.dominickzou.dev/contact.html">Contact</a>
            </div>
        </div>
    </nav>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Analytics</h1>
                <p>Login Portal</p>
            </div>

            <div id="errorBox" style="display:none;"></div>

            <form id="loginForm" class="login-form" autocomplete="on">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                </div>

                <div class="login-footer">
                    <button type="submit" class="btn-submit" id="submitBtn">Authenticate</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // ── AJAX Login ────────────────────────────────────────────────────────
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const errorBox = document.getElementById('errorBox');
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;

        btn.disabled = true;
        btn.textContent = 'Authenticating…';
        errorBox.style.display = 'none';

        try {
            const res = await fetch('/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
            });

            const data = await res.json();

            if (data.ok) {
                btn.textContent = 'Success';
                window.location.href = '/dashboard';
            } else {
                errorBox.innerHTML = `
                    <div class="login-error">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        ${data.error || 'Invalid credentials'}
                    </div>`;
                errorBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Authenticate';
            }
        } catch (err) {
            errorBox.innerHTML = `
                <div class="login-error">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Connection error. Please try again.
                </div>`;
            errorBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Authenticate';
        }
    });

    // ── Background Particles (synced with test.dominickzou.dev) ─────────────
    (() => {
        const canvas = document.getElementById('bg-particles');
        const ctx = canvas.getContext('2d');
        const PARTICLE_COUNT = 120;
        const FRICTION = 0.92;
        const HIT_RADIUS = 120;
        const HIT_MULTIPLIER = 0.6;

        // Seeded PRNG for deterministic particle positions
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
            document.cookie = '_particle_epoch=' + now + ';domain=.dominickzou.dev;path=/;max-age=86400;SameSite=Lax';
            return now;
        }
        const epoch = getEpoch();
        function getTime() { return (performance.now() - epoch) / 1000; }

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
    </script>
</body>
</html>
