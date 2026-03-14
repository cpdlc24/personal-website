/**
 * particles.js
 * A clean, radiating particle field inspired by antigravity.
 * It tracks the mouse and creates a subtle flowing effect outward.
 */
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.createElement('canvas');
    canvas.id = 'unified-bg-canvas';
    document.body.prepend(canvas);

    const ctx = canvas.getContext('2d');
    let width, height;
    let particles = [];
    
    // Mouse tracking
    let mouse = { x: -1000, y: -1000 };
    
    // Colors from the reference image (blue, orange, purple, yellow)
    const colors = ['#4285F4', '#EA4335', '#FBBC05', '#34A853', '#8A2BE2'];

    function resize() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    }

    window.addEventListener('resize', resize);
    resize();
    
    window.addEventListener('mousemove', (e) => {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
    });
    
    window.addEventListener('mouseleave', () => {
        mouse.x = -1000;
        mouse.y = -1000;
    });

    class Particle {
        constructor() {
            this.reset();
            // Start at random positions instead of center
            this.x = Math.random() * width;
            this.y = Math.random() * height;
        }

        reset() {
            this.angle = Math.random() * Math.PI * 2;
            this.radius = Math.random() * 200 + 50; // Distance from center
            this.centerX = width / 2;
            this.centerY = height / 2;
            this.x = this.centerX + Math.cos(this.angle) * this.radius;
            this.y = this.centerY + Math.sin(this.angle) * this.radius;
            this.speed = Math.random() * 0.5 + 0.1;
            this.size = Math.random() * 2.5 + 1;
            this.color = colors[Math.floor(Math.random() * colors.length)];
            this.life = 0;
            this.maxLife = Math.random() * 100 + 50;
            
            // Flow outward
            this.vx = Math.cos(this.angle) * this.speed;
            this.vy = Math.sin(this.angle) * this.speed;
        }

        update() {
            this.x += this.vx;
            this.y += this.vy;
            this.life++;

            // Interaction with mouse
            let dx = mouse.x - this.x;
            let dy = mouse.y - this.y;
            let distance = Math.sqrt(dx * dx + dy * dy);
            if (distance < 150) {
                let forceDirectionX = dx / distance;
                let forceDirectionY = dy / distance;
                let force = (150 - distance) / 150;
                this.x -= forceDirectionX * force * 5;
                this.y -= forceDirectionY * force * 5;
            }

            // Reset if out of bounds or life ended
            if (this.life >= this.maxLife || 
                this.x < 0 || this.x > width || 
                this.y < 0 || this.y > height) {
                
                // Spawn near center
                this.angle = Math.random() * Math.PI * 2;
                this.radius = Math.random() * 100; // spawn closer to center
                this.x = (width/2) + Math.cos(this.angle) * this.radius;
                this.y = (height/2) + Math.sin(this.angle) * this.radius;
                this.vx = Math.cos(this.angle) * this.speed;
                this.vy = Math.sin(this.angle) * this.speed;
                this.life = 0;
            }
        }

        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fillStyle = this.color;
            // Fade in and fade out based on life
            let opacity = 1;
            if (this.life < 20) opacity = this.life / 20;
            if (this.life > this.maxLife - 20) opacity = (this.maxLife - this.life) / 20;
            
            ctx.globalAlpha = opacity;
            ctx.fill();
            ctx.globalAlpha = 1;
        }
    }

    // Initialize particles
    const particleCount = 400;
    for (let i = 0; i < particleCount; i++) {
        particles.push(new Particle());
    }

    function animate() {
        ctx.clearRect(0, 0, width, height);
        for (let i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();
        }
        requestAnimationFrame(animate);
    }

    animate();
});
