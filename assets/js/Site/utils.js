/**
 * Observateur d'intersection réutilisable
 */
class IntersectionManager {
    constructor(options = {}) {
        this.defaultOptions = {
            threshold: 0.2,
            rootMargin: '0px 0px -50px 0px',
            ...options
        };
        this.observer = new IntersectionObserver(
            this.handleIntersection.bind(this),
            this.defaultOptions
        );
        this.callbacks = new Map();
    }

    observe(element, callback) {
        this.callbacks.set(element, callback);
        this.observer.observe(element);
    }

    unobserve(element) {
        this.callbacks.delete(element);
        this.observer.unobserve(element);
    }

    handleIntersection(entries) {
        entries.forEach(entry => {
            const callback = this.callbacks.get(entry.target);
            if (callback && entry.isIntersecting) {
                callback(entry);
            }
        });
    }
}

/**
 * Gestionnaire d'animations d'entrée
 */
class AnimationManager {
    constructor() {
        this.intersectionManager = new IntersectionManager();
        this.init();
    }

    init() {
        // Animation fade-in générique
        document.querySelectorAll('.fade-in-up').forEach((el, index) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';

            this.intersectionManager.observe(el, () => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Animation des cartes avec délai séquentiel
        this.animateCards();

        // Animation des compteurs
        this.animateCounters();
    }

    animateCards() {
        const cardContainers = document.querySelectorAll('.pricing-grid, .stats-grid, .features');

        cardContainers.forEach(container => {
            const cards = container.querySelectorAll('.pricing-card, .stat-item, .feature-section-v2');

            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            });

            this.intersectionManager.observe(container, () => {
                cards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 150);
                });
            });
        });
    }

    animateCounters() {
        const counters = document.querySelectorAll('.stat-number[data-count]');

        counters.forEach(counter => {
            this.intersectionManager.observe(counter, () => {
                this.startCountAnimation(counter);
            });
        });
    }

    startCountAnimation(element) {
        const target = parseInt(element.getAttribute('data-count'));
        const duration = 2000;
        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = Math.floor(target * easeOutQuart);

            // Format numbers
            element.textContent = this.formatNumber(current, target);

            if (progress < 1) {
                element.classList.add('counting');
                requestAnimationFrame(animate);
            } else {
                element.classList.remove('counting');
                // Final scale effect
                element.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 200);
            }
        };

        requestAnimationFrame(animate);
    }

    formatNumber(current, target) {
        if (target >= 1000000) {
            return (current / 1000000).toFixed(1) + 'M+';
        } else if (target >= 1000) {
            return (current / 1000).toFixed(1) + 'K+';
        }
        return current.toString();
    }
}

/**
 * Gestionnaire d'effets hover avancés
 */
class HoverEffectsManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupCardHovers();
        this.setupButtonEffects();
        this.setupFeatureHovers();
    }

    setupCardHovers() {
        const cards = document.querySelectorAll('.pricing-card, .stat-item, .feature-card');

        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-10px) scale(1.02)';
                card.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.15)';
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
                card.style.boxShadow = '';
            });
        });
    }

    setupButtonEffects() {
        const buttons = document.querySelectorAll('.btn, .cta-button, .plan-cta-btn');

        buttons.forEach(btn => {
            // Shine effect
            btn.addEventListener('mouseenter', () => {
                if (btn.classList.contains('btn-primary')) {
                    btn.style.boxShadow = '0 12px 30px rgba(59, 130, 246, 0.6), 0 0 20px rgba(59, 130, 246, 0.3)';
                }
            });

            btn.addEventListener('mouseleave', () => {
                btn.style.boxShadow = '';
            });

            // Ripple effect on click
            btn.addEventListener('click', this.createRippleEffect.bind(this));
        });
    }

    setupFeatureHovers() {
        const features = document.querySelectorAll('.feature-item, .checkmark');

        features.forEach(feature => {
            feature.addEventListener('mouseenter', () => {
                const checkmark = feature.querySelector('.checkmark, .feature-check');
                if (checkmark) {
                    checkmark.style.transform = 'scale(1.2) rotate(360deg)';
                    checkmark.style.boxShadow = '0 4px 12px rgba(16, 185, 129, 0.5)';
                }
            });

            feature.addEventListener('mouseleave', () => {
                const checkmark = feature.querySelector('.checkmark, .feature-check');
                if (checkmark) {
                    checkmark.style.transform = 'scale(1) rotate(0deg)';
                    checkmark.style.boxShadow = '';
                }
            });
        });
    }

    createRippleEffect(e) {
        const button = e.currentTarget;
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;

        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple-effect');

        // Add ripple styles if not present
        if (!document.getElementById('ripple-styles')) {
            const style = document.createElement('style');
            style.id = 'ripple-styles';
            style.textContent = `
                .ripple-effect {
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.5);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                }
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        button.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }
}

/**
 * Initialisation globale
 */
class App {
    constructor() {
        this.animationManager = new AnimationManager();
        this.hoverEffectsManager = new HoverEffectsManager();

        this.init();
    }

    init() {
        // Attendre que le DOM soit chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', this.onDOMReady.bind(this));
        } else {
            this.onDOMReady();
        }
    }

    onDOMReady() {
        // Animations d'entrée de la page
        this.animatePageEntrance();
    }

    animatePageEntrance() {
        const hero = document.querySelector('.hero-content');
        if (hero) {
            hero.style.opacity = '0';
            hero.style.transform = 'translateY(50px)';

            setTimeout(() => {
                hero.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                hero.style.opacity = '1';
                hero.style.transform = 'translateY(0)';
            }, 300);
        }
    }
}

const app = new App();
