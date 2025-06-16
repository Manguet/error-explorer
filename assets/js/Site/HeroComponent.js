/**
 * Hero Component - BEM Architecture
 * Gestion des animations de typing et des interactions
 */
class HeroComponent {
    constructor() {
        // Éléments DOM
        this.hero = document.querySelector('.hero');
        this.codeLines = document.querySelectorAll('.hero__code-line');
        this.ctaButtons = document.querySelectorAll('.hero__cta');
        this.codeWindow = document.querySelector('.hero__code-window');

        // Configuration
        this.config = {
            typingSpeed: 50,
            lineDelay: 800,
            observerThreshold: 0.3,
            animationDuration: 300
        };

        // État
        this.hasAnimated = false;
        this.typingTimeout = null;
        this.isVisible = false;

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.hero) return;

        this.bindEvents();
        this.initIntersectionObserver();
        this.initParallaxEffect();
        this.enhanceAccessibility();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Effets sur les boutons CTA
        this.ctaButtons.forEach(button => {
            button.addEventListener('mouseenter', this.handleCtaHover.bind(this));
            button.addEventListener('mouseleave', this.handleCtaLeave.bind(this));
            button.addEventListener('click', this.handleCtaClick.bind(this));
        });

        // Effet sur la fenêtre de code
        if (this.codeWindow) {
            this.codeWindow.addEventListener('mouseenter', this.handleCodeWindowHover.bind(this));
            this.codeWindow.addEventListener('mouseleave', this.handleCodeWindowLeave.bind(this));
        }

        // Gestion du redimensionnement
        window.addEventListener('resize', this.handleResize.bind(this));

        // Gestion de la visibilité de la page
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
    }

    /**
     * Initialisation de l'Intersection Observer
     */
    initIntersectionObserver() {
        const observer = new IntersectionObserver(
            this.handleIntersection.bind(this),
            {
                threshold: this.config.observerThreshold,
                rootMargin: '0px 0px -100px 0px'
            }
        );

        observer.observe(this.hero);
    }

    /**
     * Gestion de l'intersection (apparition du hero)
     */
    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting && !this.hasAnimated) {
                this.isVisible = true;
                this.startHeroAnimation();
                this.hasAnimated = true;
            } else if (!entry.isIntersecting) {
                this.isVisible = false;
            }
        });
    }

    /**
     * Animation principale du hero
     */
    startHeroAnimation() {
        // Animation du badge avec effet de pulsation
        this.animateHeroBadge();

        // Animation de la description
        this.animateHeroDescription();

        // Animation des boutons CTA
        this.animateHeroActions();

        // Démarrer l'animation de typing du code
        setTimeout(() => {
            this.startTypingAnimation();
        }, 1000);
    }

    /**
     * Animation du badge
     */
    animateHeroBadge() {
        const badge = document.querySelector('.hero__badge');
        if (!badge) return;

        badge.style.opacity = '0';
        badge.style.transform = 'translateY(-20px) scale(0.9)';

        setTimeout(() => {
            badge.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
            badge.style.opacity = '1';
            badge.style.transform = 'translateY(0) scale(1)';
        }, 100);
    }

    /**
     * Animation de la description
     */
    animateHeroDescription() {
        const description = document.querySelector('.hero__description');
        if (!description) return;

        description.style.opacity = '0';
        description.style.transform = 'translateY(20px)';

        setTimeout(() => {
            description.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
            description.style.opacity = '1';
            description.style.transform = 'translateY(0)';
        }, 800);
    }

    /**
     * Animation des boutons d'action
     */
    animateHeroActions() {
        const actions = document.querySelector('.hero__actions');
        if (!actions) return;

        const buttons = actions.querySelectorAll('.hero__cta');
        buttons.forEach((button, index) => {
            button.style.opacity = '0';
            button.style.transform = 'translateY(20px)';

            setTimeout(() => {
                button.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                button.style.opacity = '1';
                button.style.transform = 'translateY(0)';
            }, 1200 + (index * 150));
        });
    }

    /**
     * Animation de typing du code
     */
    startTypingAnimation() {
        if (!this.codeLines.length) return;

        // Réinitialiser toutes les lignes
        this.codeLines.forEach(line => {
            line.style.opacity = '0';
            line.style.transform = 'translateX(-20px)';
        });

        // Animer chaque ligne séquentiellement
        this.codeLines.forEach((line, index) => {
            setTimeout(() => {
                this.animateCodeLine(line, index);
            }, index * 200);
        });
    }

    /**
     * Animation d'une ligne de code spécifique
     */
    animateCodeLine(line, index) {
        // Animation d'apparition
        line.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
        line.style.opacity = '1';
        line.style.transform = 'translateX(0)';

        // Effet spécial pour les lignes importantes
        if (line.classList.contains('hero__code-line--error')) {
            setTimeout(() => {
                this.highlightErrorLine(line);
            }, 500);
        } else if (line.classList.contains('hero__code-line--success')) {
            setTimeout(() => {
                this.highlightSuccessLine(line);
            }, 300);
        }
    }

    /**
     * Mise en évidence d'une ligne d'erreur
     */
    highlightErrorLine(line) {
        line.style.animation = 'errorPulse 2s infinite';

        // Ajouter un effet de notification
        const notification = document.createElement('div');
        notification.className = 'hero__code-notification';
        notification.innerHTML = '⚠️ Erreur détectée';

        line.style.position = 'relative';
        line.appendChild(notification);

        // Supprimer la notification après 3 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }

    /**
     * Mise en évidence d'une ligne de succès
     */
    highlightSuccessLine(line) {
        const originalColor = line.style.color;
        line.style.color = '#10b981';

        setTimeout(() => {
            line.style.color = originalColor;
        }, 1000);
    }

    /**
     * Effet parallaxe pour l'arrière-plan
     */
    initParallaxEffect() {
        const backgroundGrid = document.querySelector('.hero__background-grid');
        const backgroundParticles = document.querySelector('.hero__background-particles');

        if (!backgroundGrid || !backgroundParticles) return;

        let ticking = false;

        const updateParallax = () => {
            if (!this.isVisible) return;

            const scrollY = window.scrollY;
            const heroHeight = this.hero.offsetHeight;
            const scrollPercent = Math.min(scrollY / heroHeight, 1);

            // Effet parallaxe sur la grille
            backgroundGrid.style.transform = `translate(${scrollPercent * 20}px, ${scrollPercent * 20}px)`;

            // Effet parallaxe sur les particules
            backgroundParticles.style.transform = `translate(${scrollPercent * -10}px, ${scrollPercent * 15}px)`;

            ticking = false;
        };

        const requestParallaxUpdate = () => {
            if (!ticking) {
                requestAnimationFrame(updateParallax);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestParallaxUpdate, { passive: true });
    }

    /**
     * Gestion du hover sur les boutons CTA
     */
    handleCtaHover(e) {
        const button = e.currentTarget;
        const icon = button.querySelector('.hero__cta-icon');

        if (icon) {
            icon.style.transform = 'translateX(5px) scale(1.1)';
        }

        // Effet de particules sur le bouton primaire
        if (button.classList.contains('hero__cta--primary')) {
            this.createButtonParticles(button);
        }
    }

    /**
     * Gestion de la fin du hover sur les boutons CTA
     */
    handleCtaLeave(e) {
        const button = e.currentTarget;
        const icon = button.querySelector('.hero__cta-icon');

        if (icon) {
            icon.style.transform = '';
        }
    }

    /**
     * Gestion du clic sur les boutons CTA
     */
    handleCtaClick(e) {
        const button = e.currentTarget;

        // Effet de ripple
        this.createRippleEffect(button, e);
    }

    /**
     * Création d'un effet de ripple
     */
    createRippleEffect(element, event) {
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(${x}px, ${y}px) scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
            z-index: 10;
        `;

        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    /**
     * Création de particules sur les boutons
     */
    createButtonParticles(button) {
        const particles = [];
        const particleCount = 8;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'hero__button-particle';

            const angle = (360 / particleCount) * i;
            const distance = 30 + Math.random() * 20;

            particle.style.cssText = `
                position: absolute;
                width: 4px;
                height: 4px;
                background: #60a5fa;
                border-radius: 50%;
                pointer-events: none;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                animation: particleFloat 1s ease-out forwards;
                animation-delay: ${i * 0.1}s;
                --angle: ${angle}deg;
                --distance: ${distance}px;
            `;

            button.appendChild(particle);
            particles.push(particle);
        }

        // Nettoyer les particules après l'animation
        setTimeout(() => {
            particles.forEach(particle => {
                if (particle.parentNode) {
                    particle.remove();
                }
            });
        }, 1500);
    }

    /**
     * Gestion du hover sur la fenêtre de code
     */
    handleCodeWindowHover() {
        if (!this.codeWindow) return;

        // Effet d'éclairage sur les lignes de code
        this.codeLines.forEach((line, index) => {
            setTimeout(() => {
                line.style.backgroundColor = 'rgba(255, 255, 255, 0.02)';
            }, index * 50);
        });
    }

    /**
     * Gestion de la fin du hover sur la fenêtre de code
     */
    handleCodeWindowLeave() {
        if (!this.codeWindow) return;

        this.codeLines.forEach(line => {
            line.style.backgroundColor = '';
        });
    }

    /**
     * Gestion du redimensionnement
     */
    handleResize() {
        // Réajuster les animations si nécessaire
        if (this.hasAnimated && window.innerWidth < 768) {
            this.adaptToMobile();
        }
    }

    /**
     * Adaptation pour mobile
     */
    adaptToMobile() {
        // Réduire les animations pour mobile
        this.codeLines.forEach(line => {
            line.style.animationDuration = '0.3s';
        });
    }

    /**
     * Gestion de la visibilité de la page
     */
    handleVisibilityChange() {
        if (document.hidden && this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }
    }

    /**
     * Amélioration de l'accessibilité
     */
    enhanceAccessibility() {
        // Ajouter des labels ARIA
        this.ctaButtons.forEach((button, index) => {
            if (!button.getAttribute('aria-label')) {
                const text = button.querySelector('.hero__cta-text')?.textContent;
                if (text) {
                    button.setAttribute('aria-label', text);
                }
            }
        });

        // Gestion du focus clavier
        this.ctaButtons.forEach(button => {
            button.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    button.click();
                }
            });
        });
    }

    /**
     * API publique - Redémarrer l'animation
     */
    restartAnimation() {
        this.hasAnimated = false;
        this.startHeroAnimation();
    }

    /**
     * API publique - Arrêter toutes les animations
     */
    stopAnimations() {
        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }

        this.codeLines.forEach(line => {
            line.style.animation = 'none';
        });
    }

    /**
     * Nettoyage
     */
    destroy() {
        // Supprimer les événements
        this.ctaButtons.forEach(button => {
            button.removeEventListener('mouseenter', this.handleCtaHover);
            button.removeEventListener('mouseleave', this.handleCtaLeave);
            button.removeEventListener('click', this.handleCtaClick);
        });

        if (this.codeWindow) {
            this.codeWindow.removeEventListener('mouseenter', this.handleCodeWindowHover);
            this.codeWindow.removeEventListener('mouseleave', this.handleCodeWindowLeave);
        }

        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);

        // Nettoyer les timeouts
        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }
    }
}

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser le composant Hero
    window.heroComponent = new HeroComponent();

    // Ajouter les styles CSS manquants pour les animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: translate(var(--x, 0), var(--y, 0)) scale(2);
                opacity: 0;
            }
        }

        @keyframes particleFloat {
            to {
                transform: translate(-50%, -50%) 
                          rotate(var(--angle)) 
                          translateX(var(--distance)) 
                          scale(0);
                opacity: 0;
            }
        }

        .hero__code-notification {
            background: rgba(239, 68, 68, 0.9);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            white-space: nowrap;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateY(-50%) translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(-50%) translateX(0);
            }
        }

        @media (max-width: 768px) {
            .hero__code-notification {
                display: none;
            }
        }
    `;
    document.head.appendChild(style);
});

// Export pour utilisation externe
window.HeroComponent = HeroComponent;
