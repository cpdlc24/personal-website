document.addEventListener('DOMContentLoaded', () => {
    // --- Scroll animations ---
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const slideUpElements = document.querySelectorAll('.slide-up');
    slideUpElements.forEach(el => observer.observe(el));

    // --- Interactive Navbar ---
    const navbar = document.querySelector('.navbar');
    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.style.background = 'rgba(20, 25, 40, 0.6)';
            navbar.style.backdropFilter = 'blur(30px)';
            if (window.scrollY > lastScrollY) {
                // Scroll Down
                navbar.style.transform = 'translateY(-150%)';
            } else {
                // Scroll Up
                navbar.style.transform = 'translateY(0)';
            }
        } else {
            navbar.style.background = 'var(--glass-bg)';
            navbar.style.backdropFilter = 'blur(20px)';
            navbar.style.transform = 'translateY(0)';
        }
        lastScrollY = window.scrollY;
    });

    // --- Form submission handling (Contact Page) ---
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const btn = contactForm.querySelector('.submit-btn');
            const feedback = document.getElementById('form-feedback');
            
            // Add loading state
            btn.classList.add('loading');
            feedback.classList.remove('show');
            
            // Simulate API Request
            setTimeout(() => {
                btn.classList.remove('loading');
                contactForm.reset();
                feedback.classList.add('show');
                
                // Hide feedback after 5 seconds
                setTimeout(() => {
                    feedback.classList.remove('show');
                }, 5000);
                
            }, 1500);
        });
    }

    // --- Dynamic Background Interaction ---
    initParticleCanvas();
});

// Interactive Particle Network connected to Cursor
function initParticleCanvas() {
    const canvas = document.getElementById('particleCanvas');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    let w, h;
    let particles = [];
    
    // Mouse Interaction setup
    let mouse = {
        x: null,
        y: null,
        radius: 150
    };

    window.addEventListener('mousemove', function(event) {
        mouse.x = event.x;
        mouse.y = event.y;
    });
    
    // Reset mouse when leaving window
    window.addEventListener('mouseout', function() {
        mouse.x = null;
        mouse.y = null;
    });

    function init() {
        resize();
        createParticles();
        animate();
    }

    function resize() {
        w = canvas.width = window.innerWidth;
        h = canvas.height = window.innerHeight;
    }

    window.addEventListener('resize', () => {
        resize();
        particles = [];
        createParticles();
    });

    class Particle {
        constructor() {
            this.x = Math.random() * w;
            this.y = Math.random() * h;
            this.size = Math.random() * 2 + 1;
            this.speedX = Math.random() * 1 - 0.5;
            this.speedY = Math.random() * 1 - 0.5;
            this.color = 'rgba(0, 242, 254, 0.4)';
        }
        update() {
            this.x += this.speedX;
            this.y += this.speedY;

            // Bounce off edges
            if (this.x > w || this.x < 0) this.speedX = -this.speedX;
            if (this.y > h || this.y < 0) this.speedY = -this.speedY;

            // Collision with mouse
            // Distance calculation
            let dx = mouse.x - this.x;
            let dy = mouse.y - this.y;
            let distance = Math.sqrt(dx * dx + dy * dy);
            
            if (distance < mouse.radius) {
                const forceDirectionX = dx / distance;
                const forceDirectionY = dy / distance;
                const force = (mouse.radius - distance) / mouse.radius;
                
                const directionX = forceDirectionX * force * 5;
                const directionY = forceDirectionY * force * 5;
                
                this.x -= directionX;
                this.y -= directionY;
            }
        }
        draw() {
            ctx.fillStyle = this.color;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.closePath();
            ctx.fill();
        }
    }

    function createParticles() {
        // Adjust particle count based on screen size for performance
        let numParticles = (w * h) / 10000;
        if (numParticles > 300) numParticles = 300; // Cap
        
        for (let i = 0; i < numParticles; i++) {
            particles.push(new Particle());
        }
    }

    function connectParticles() {
        let opacityValue = 1;
        for (let a = 0; a < particles.length; a++) {
            for (let b = a; b < particles.length; b++) {
                let dx = particles[a].x - particles[b].x;
                let dy = particles[a].y - particles[b].y;
                let distance = Math.sqrt(dx * dx + dy * dy);

                if (distance < 120) {
                    opacityValue = 1 - (distance / 120);
                    ctx.strokeStyle = `rgba(0, 242, 254, ${opacityValue * 0.2})`;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(particles[a].x, particles[a].y);
                    ctx.lineTo(particles[b].x, particles[b].y);
                    ctx.stroke();
                }
            }
            // Connect to mouse
            if (mouse.x != null && mouse.y != null) {
                let dx = particles[a].x - mouse.x;
                let dy = particles[a].y - mouse.y;
                let distance = Math.sqrt(dx * dx + dy * dy);
                if (distance < mouse.radius) {
                    opacityValue = 1 - (distance / mouse.radius);
                    ctx.strokeStyle = `rgba(79, 172, 254, ${opacityValue * 0.5})`;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(particles[a].x, particles[a].y);
                    ctx.lineTo(mouse.x, mouse.y);
                    ctx.stroke();
                }
            }
        }
    }

    function animate() {
        ctx.clearRect(0, 0, w, h);
        
        for (let i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();
        }
        connectParticles();
        requestAnimationFrame(animate);
    }

    init();
}
