/**
 * CTA Section Controller
 * Gère l'animation du score de sécurité et les interactions
 */
class CTAController {
    constructor() {
        this.scoreValue = 0;
        this.targetScore = 75;
        this.animationDuration = 3000;
        this.isAnimating = false;

        this.init();
    }

    init() {
        this.elements = this.getElements();
        this.setupIntersectionObserver();
        this.setupEventListeners();
        this.createGradientDefinition();
    }

    getElements() {
        const ctaSection = document.querySelector('.cta');
        if (!ctaSection) return null;

        return {
            section: ctaSection,
            scoreValueElement: ctaSection.querySelector('.cta__score-value'),
            gaugeProgress: ctaSection.querySelector('.cta__gauge-progress'),
            features: ctaSection.querySelectorAll('.cta__feature'),
            demoStatus: ctaSection.querySelector('.cta__demo-status'),
            button: ctaSection.querySelector('.cta__button')
        };
    }

    createGradientDefinition() {
        if (!this.elements?.gaugeProgress) return;

        const svg = this.elements.gaugeProgress.closest('svg');
        if (!svg) return;

        // Créer la définition de gradient
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        const gradient = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');

        gradient.setAttribute('id', 'gaugeGradient');
        gradient.setAttribute('x1', '0%');
        gradient.setAttribute('y1', '0%');
        gradient.setAttribute('x2', '100%');
        gradient.setAttribute('y2', '0%');

        // Stops de gradient basés sur le score
        const stops = [
            { offset: '0%', color: '#ef4444' },
            { offset: '40%', color: '#f59e0b' },
            { offset: '70%', color: '#10b981' },
            { offset: '100%', color: '#059669' }
        ];

        stops.forEach(stop => {
            const stopElement = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
            stopElement.setAttribute('offset', stop.offset);
            stopElement.setAttribute('stop-color', stop.color);
            gradient.appendChild(stopElement);
        });

        defs.appendChild(gradient);
        svg.insertBefore(defs, svg.firstChild);
    }

    setupIntersectionObserver() {
        if (!this.elements?.section) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.isAnimating) {
                    this.startAnimation();
                }
            });
        }, {
            threshold: 0.3,
            rootMargin: '0px 0px -100px 0px'
        });

        observer.observe(this.elements.section);
    }

    setupEventListeners() {
        if (!this.elements?.button) return;

        // Effet de hover amélioré
        this.elements.button.addEventListener('mouseenter', () => {
            this.elements.button.style.setProperty('--hover-scale', '1.02');
        });

        this.elements.button.addEventListener('mouseleave', () => {
            this.elements.button.style.setProperty('--hover-scale', '1');
        });

        // Tracking des clics
        this.elements.button.addEventListener('click', (e) => {
            this.trackButtonClick(e);
        });
    }

    async startAnimation() {
        if (this.isAnimating) return;

        this.isAnimating = true;

        // Démarrer les animations en parallèle
        await Promise.all([
            this.animateScore(),
            this.animateGauge(),
            this.animateFeatures()
        ]);

        this.updateDemoStatus('Analyse terminée');
    }

    animateScore() {
        return new Promise((resolve) => {
            if (!this.elements?.scoreValueElement) {
                resolve();
                return;
            }

            const startTime = Date.now();
            const startValue = 0;

            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / this.animationDuration, 1);

                // Utiliser une courbe d'animation personnalisée
                const easedProgress = this.easeOutCubic(progress);
                this.scoreValue = Math.round(startValue + (this.targetScore - startValue) * easedProgress);

                this.elements.scoreValueElement.textContent = this.scoreValue;

                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    resolve();
                }
            };

            animate();
        });
    }

    animateGauge() {
        return new Promise((resolve) => {
            if (!this.elements?.gaugeProgress) {
                resolve();
                return;
            }

            const circumference = 251.2; // Périmètre de l'arc
            const startTime = Date.now();

            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / this.animationDuration, 1);

                const easedProgress = this.easeOutCubic(progress);
                const scoreProgress = this.scoreValue / 100;
                const dashOffset = circumference - (circumference * scoreProgress);

                this.elements.gaugeProgress.style.strokeDashoffset = dashOffset;

                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    resolve();
                }
            };

            animate();
        });
    }

    animateFeatures() {
        return new Promise((resolve) => {
            if (!this.elements?.features?.length) {
                resolve();
                return;
            }

            const features = Array.from(this.elements.features);
            let completedAnimations = 0;

            features.forEach((feature, index) => {
                const delay = parseInt(feature.dataset.delay || '0');

                setTimeout(() => {
                    feature.style.opacity = '1';
                    feature.style.transform = 'translateY(0)';
                    feature.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';

                    completedAnimations++;
                    if (completedAnimations === features.length) {
                        resolve();
                    }
                }, delay);
            });
        });
    }

    updateDemoStatus(message) {
        if (!this.elements?.demoStatus) return;

        const statusText = this.elements.demoStatus.querySelector('span');
        if (statusText) {
            statusText.textContent = message;
            this.elements.demoStatus.style.color = '#10b981';
        }
    }

    easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    trackButtonClick(event) {
        // Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'click', {
                event_category: 'CTA',
                event_label: 'Improve Score Button',
                value: this.scoreValue
            });
        }

        // Effet visuel de clic
        const button = event.currentTarget;
        button.style.transform = 'translateY(-1px) scale(0.98)';

        setTimeout(() => {
            button.style.transform = '';
        }, 150);
    }

    // Méthode publique pour redémarrer l'animation
    restartAnimation() {
        this.isAnimating = false;
        this.scoreValue = 0;

        if (this.elements?.scoreValueElement) {
            this.elements.scoreValueElement.textContent = '0';
        }

        if (this.elements?.gaugeProgress) {
            this.elements.gaugeProgress.style.strokeDashoffset = '251.2';
        }

        if (this.elements?.features) {
            this.elements.features.forEach(feature => {
                feature.style.opacity = '0';
                feature.style.transform = 'translateY(20px)';
            });
        }

        this.updateDemoStatus('Analyse en cours...');
        this.startAnimation();
    }
}

/**
 * CTA Particle System
 * Gère les particules flottantes en arrière-plan
 */
class CTAParticleSystem {
    constructor() {
        this.particles = [];
        this.isRunning = false;
        this.init();
    }

    init() {
        const ctaSection = document.querySelector('.cta');
        if (!ctaSection) return;

        this.container = ctaSection.querySelector('.cta__background-particles');
        if (!this.container) return;

        this.createParticles();
        this.startAnimation();
    }

    createParticles() {
        const particleCount = window.innerWidth > 768 ? 20 : 10;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'cta__particle-dynamic';
            particle.style.cssText = `
        position: absolute;
        width: ${Math.random() * 4 + 2}px;
        height: ${Math.random() * 4 + 2}px;
        background: rgba(255, 255, 255, ${Math.random() * 0.5 + 0.3});
        border-radius: 50%;
        pointer-events: none;
        left: ${Math.random() * 100}%;
        top: ${Math.random() * 100}%;
      `;

            this.container.appendChild(particle);

            this.particles.push({
                element: particle,
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight,
                vx: (Math.random() - 0.5) * 0.5,
                vy: (Math.random() - 0.5) * 0.5,
                life: Math.random() * 1000 + 1000
            });
        }
    }

    startAnimation() {
        if (this.isRunning) return;
        this.isRunning = true;

        const animate = () => {
            if (!this.isRunning) return;

            this.particles.forEach(particle => {
                particle.x += particle.vx;
                particle.y += particle.vy;
                particle.life--;

                // Rebonds sur les bords
                if (particle.x <= 0 || particle.x >= window.innerWidth) {
                    particle.vx *= -1;
                }
                if (particle.y <= 0 || particle.y >= window.innerHeight) {
                    particle.vy *= -1;
                }

                // Mettre à jour la position
                particle.element.style.left = particle.x + 'px';
                particle.element.style.top = particle.y + 'px';

                // Fade out en fin de vie
                if (particle.life < 100) {
                    particle.element.style.opacity = particle.life / 100;
                }

                // Régénérer la particule
                if (particle.life <= 0) {
                    particle.x = Math.random() * window.innerWidth;
                    particle.y = Math.random() * window.innerHeight;
                    particle.life = Math.random() * 1000 + 1000;
                    particle.element.style.opacity = Math.random() * 0.5 + 0.3;
                }
            });

            requestAnimationFrame(animate);
        };

        animate();
    }

    stop() {
        this.isRunning = false;
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    // Vérifier si on est sur une page avec la section CTA
    if (document.querySelector('.cta')) {
        window.ctaController = new CTAController();

        // Particules uniquement sur desktop pour les performances
        if (window.innerWidth > 768 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            window.ctaParticleSystem = new CTAParticleSystem();
        }
    }
});

// Cleanup au changement de page (pour les SPAs)
window.addEventListener('beforeunload', () => {
    if (window.ctaParticleSystem) {
        window.ctaParticleSystem.stop();
    }
});

// Gestion du redimensionnement
window.addEventListener('resize', () => {
    if (window.ctaController) {
        window.ctaController.restartAnimation();
    }
});
