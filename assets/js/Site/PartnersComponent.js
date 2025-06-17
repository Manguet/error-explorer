/**
 * Partners Component - BEM Architecture
 * Gestion des animations, filtres et interactions de la page partenaires
 */
class PartnersComponent {
    constructor() {
        // Éléments DOM
        this.partnersPage = document.querySelector('.partners');
        this.heroSection = document.querySelector('.partners-hero');
        this.statsSection = document.querySelector('.partners-stats');
        this.integrationsSection = document.querySelector('.partners-integrations');
        this.testimonialsSection = document.querySelector('.partners-testimonials');

        // Éléments spécifiques
        this.filterButtons = document.querySelectorAll('.partners-integrations__filter-button');
        this.integrationCards = document.querySelectorAll('.partners-integrations__card');
        this.statsItems = document.querySelectorAll('.partners-stats__item');
        this.statsNumbers = document.querySelectorAll('.partners-stats__number[data-count]');
        this.integrationNodes = document.querySelectorAll('.partners-hero__integration-node');
        this.ctaButtons = document.querySelectorAll('.partners-hero__button, .partners-cta__button');
        this.cardButtons = document.querySelectorAll('.partners-integrations__card-button');

        // Configuration
        this.config = {
            observerThreshold: 0.2,
            countDuration: 2000,
            filterDuration: 300,
            staggerDelay: 100,
            parallaxIntensity: 0.3
        };

        // État
        this.isInitialized = false;
        this.activeFilter = 'all';
        this.hasAnimated = {
            hero: false,
            stats: false,
            integrations: false,
            testimonials: false
        };
        this.activeCounters = new Map();

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.partnersPage) return;

        this.bindEvents();
        this.initIntersectionObserver();
        this.initFilterSystem();
        this.initParallaxEffects();
        this.enhanceAccessibility();
        this.initHeroAnimations();

        this.isInitialized = true;
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Gestion des filtres
        this.filterButtons.forEach(button => {
            button.addEventListener('click', this.handleFilterClick.bind(this));
        });

        // Effets sur les boutons CTA
        this.ctaButtons.forEach(button => {
            button.addEventListener('mouseenter', this.handleCtaHover.bind(this));
            button.addEventListener('mouseleave', this.handleCtaLeave.bind(this));
            button.addEventListener('click', this.handleCtaClick.bind(this));
        });

        // Effets sur les cartes d'intégration
        this.integrationCards.forEach(card => {
            card.addEventListener('mouseenter', this.handleCardHover.bind(this));
            card.addEventListener('mouseleave', this.handleCardLeave.bind(this));
        });

        // Effets sur les boutons de cartes
        this.cardButtons.forEach(button => {
            button.addEventListener('click', this.handleCardButtonClick.bind(this));
        });

        // Gestion du redimensionnement
        window.addEventListener('resize', this.handleResize.bind(this));

        // Gestion du défilement pour les effets parallax
        window.addEventListener('scroll', this.handleScroll.bind(this));

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
                rootMargin: '0px 0px -50px 0px'
            }
        );

        // Observer les sections principales
        [this.heroSection, this.statsSection, this.integrationsSection, this.testimonialsSection]
            .filter(section => section)
            .forEach(section => observer.observe(section));
    }

    /**
     * Gestion des intersections
     */
    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = entry.target;

                if (target.classList.contains('partners-hero') && !this.hasAnimated.hero) {
                    this.animateHeroSection();
                    this.hasAnimated.hero = true;
                } else if (target.classList.contains('partners-stats') && !this.hasAnimated.stats) {
                    this.animateStatsSection();
                    this.hasAnimated.stats = true;
                } else if (target.classList.contains('partners-integrations') && !this.hasAnimated.integrations) {
                    this.animateIntegrationsSection();
                    this.hasAnimated.integrations = true;
                } else if (target.classList.contains('partners-testimonials') && !this.hasAnimated.testimonials) {
                    this.animateTestimonialsSection();
                    this.hasAnimated.testimonials = true;
                }
            }
        });
    }

    /**
     * Animation de la section hero
     */
    animateHeroSection() {
        // Animation des nœuds d'intégration
        this.integrationNodes.forEach((node, index) => {
            if (node.classList.contains('partners-hero__integration-node--satellite')) {
                setTimeout(() => {
                    node.style.animation = 'satelliteAppear 0.8s ease-out forwards';
                }, index * this.config.staggerDelay);
            }
        });

        // Émission d'événement personnalisé
        this.dispatchCustomEvent('partners:heroAnimated');
    }

    /**
     * Animation de la section statistiques
     */
    animateStatsSection() {
        // Animation des compteurs
        this.animateCounters();

        // Animation staggered des éléments
        this.statsItems.forEach((item, index) => {
            setTimeout(() => {
                item.style.animation = 'fadeInUp 0.6s ease-out forwards';
            }, index * this.config.staggerDelay);
        });

        // Émission d'événement personnalisé
        this.dispatchCustomEvent('partners:statsAnimated');
    }

    /**
     * Animation des compteurs
     */
    animateCounters() {
        this.statsNumbers.forEach(counter => {
            const target = counter.getAttribute('data-count');
            this.animateCounter(counter, target);
        });
    }

    /**
     * Animation d'un compteur individuel
     */
    animateCounter(element, target) {
        const counterId = Math.random().toString(36).substr(2, 9);

        // Parsing du target pour gérer les formats comme "25+", "99.9%", "<5s"
        let numericTarget = 0;
        let suffix = '';
        let prefix = '';

        if (target.includes('%')) {
            numericTarget = parseFloat(target.replace('%', ''));
            suffix = '%';
        } else if (target.includes('+')) {
            numericTarget = parseInt(target.replace('+', ''));
            suffix = '+';
        } else if (target.includes('<')) {
            prefix = '<';
            numericTarget = parseInt(target.replace('<', '').replace('s', ''));
            suffix = 's';
        } else if (target.includes('k+')) {
            numericTarget = parseInt(target.replace('k+', ''));
            suffix = 'k+';
        } else {
            numericTarget = parseFloat(target) || 0;
        }

        const startTime = performance.now();
        const startValue = 0;

        const updateCounter = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / this.config.countDuration, 1);

            // Fonction d'easing
            const easedProgress = this.easeOutQuart(progress);
            const currentValue = startValue + (numericTarget - startValue) * easedProgress;

            // Formatage de la valeur
            let displayValue;
            if (suffix === '%' || suffix === 's') {
                displayValue = currentValue.toFixed(1);
            } else {
                displayValue = Math.floor(currentValue);
            }

            element.textContent = prefix + displayValue + suffix;

            if (progress < 1) {
                this.activeCounters.set(counterId, requestAnimationFrame(updateCounter));
            } else {
                element.textContent = target; // Valeur finale exacte
                this.activeCounters.delete(counterId);
            }
        };

        this.activeCounters.set(counterId, requestAnimationFrame(updateCounter));
    }

    /**
     * Animation de la section intégrations
     */
    animateIntegrationsSection() {
        // Animation staggered des cartes visibles
        const visibleCards = Array.from(this.integrationCards)
            .filter(card => !card.classList.contains('filtered-out'));

        visibleCards.forEach((card, index) => {
            setTimeout(() => {
                card.style.animation = 'fadeInUp 0.6s ease-out forwards';
            }, index * (this.config.staggerDelay / 2));
        });

        // Émission d'événement personnalisé
        this.dispatchCustomEvent('partners:integrationsAnimated');
    }

    /**
     * Animation de la section témoignages
     */
    animateTestimonialsSection() {
        const testimonialCards = this.testimonialsSection?.querySelectorAll('.partners-testimonials__card');

        testimonialCards?.forEach((card, index) => {
            setTimeout(() => {
                card.style.animation = 'fadeInUp 0.6s ease-out forwards';
            }, index * this.config.staggerDelay);
        });

        // Émission d'événement personnalisé
        this.dispatchCustomEvent('partners:testimonialsAnimated');
    }

    /**
     * Initialisation du système de filtres
     */
    initFilterSystem() {
        // Marquer toutes les cartes comme visibles initialement
        this.integrationCards.forEach(card => {
            card.classList.add('filtered-in');
        });
    }

    /**
     * Gestion des clics sur les filtres
     */
    handleFilterClick(e) {
        const button = e.currentTarget;
        const category = button.getAttribute('data-category');

        if (category === this.activeFilter) return;

        // Mise à jour de l'état actif
        this.filterButtons.forEach(btn => btn.classList.remove('partners-integrations__filter-button--active'));
        button.classList.add('partners-integrations__filter-button--active');
        this.activeFilter = category;

        // Application du filtre avec animation
        this.applyFilter(category);

        // Émission d'événement personnalisé
        this.dispatchCustomEvent('partners:filterChanged', { category });
    }

    /**
     * Application du filtre avec animations
     */
    applyFilter(category) {
        const cards = Array.from(this.integrationCards);

        // Phase 1: Masquer les cartes non pertinentes
        cards.forEach(card => {
            const cardCategory = card.getAttribute('data-category');
            const shouldShow = category === 'all' || cardCategory === category;

            if (!shouldShow) {
                card.classList.remove('filtered-in');
                card.classList.add('filtered-out');
            }
        });

        // Phase 2: Afficher les cartes pertinentes avec un délai
        setTimeout(() => {
            let visibleIndex = 0;
            cards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');
                const shouldShow = category === 'all' || cardCategory === category;

                if (shouldShow) {
                    setTimeout(() => {
                        card.classList.remove('filtered-out');
                        card.classList.add('filtered-in');
                    }, visibleIndex * 50);
                    visibleIndex++;
                }
            });
        }, this.config.filterDuration);
    }

    /**
     * Initialisation des effets parallax
     */
    initParallaxEffects() {
        // Effet parallax sur le background pattern du hero
        this.heroBackgroundPattern = this.heroSection?.querySelector('.partners-hero__background-pattern');
    }

    /**
     * Gestion du défilement pour les effets parallax
     */
    handleScroll() {
        if (!this.heroBackgroundPattern) return;

        const scrollTop = window.pageYOffset;
        const heroRect = this.heroSection.getBoundingClientRect();

        if (heroRect.bottom > 0 && heroRect.top < window.innerHeight) {
            const parallaxOffset = scrollTop * this.config.parallaxIntensity;
            this.heroBackgroundPattern.style.transform = `translate(${parallaxOffset}px, ${parallaxOffset}px)`;
        }
    }

    /**
     * Gestion du hover sur les boutons CTA
     */
    handleCtaHover(e) {
        const button = e.currentTarget;
        const icon = button.querySelector('.partners-hero__button-icon, .partners-cta__button-icon');

        if (icon) {
            icon.style.transform = 'translateX(5px)';
        }

        // Effet de particules pour les boutons primaires
        if (button.classList.contains('partners-hero__button--primary') ||
            button.classList.contains('partners-cta__button--primary')) {
            this.createHoverParticles(button);
        }
    }

    /**
     * Gestion de la fin du hover sur les boutons CTA
     */
    handleCtaLeave(e) {
        const button = e.currentTarget;
        const icon = button.querySelector('.partners-hero__button-icon, .partners-cta__button-icon');

        if (icon) {
            icon.style.transform = 'translateX(0)';
        }
    }

    /**
     * Gestion des clics sur les boutons CTA
     */
    handleCtaClick(e) {
        const button = e.currentTarget;

        // Animation de clic
        button.style.transform = 'scale(0.98)';
        setTimeout(() => {
            button.style.transform = '';
        }, 150);

        // Tracking des clics (pour analytics)
        const buttonText = button.querySelector('.partners-hero__button-text, .partners-cta__button-text')?.textContent;
        this.dispatchCustomEvent('partners:ctaClicked', {
            text: buttonText,
            href: button.href
        });
    }

    /**
     * Gestion du hover sur les cartes d'intégration
     */
    handleCardHover(e) {
        const card = e.currentTarget;
        const logo = card.querySelector('.partners-integrations__card-logo');

        if (logo) {
            logo.style.transform = 'scale(1.1) rotate(5deg)';
        }

        // Effet subtil sur les icônes de fonctionnalités
        const featureIcons = card.querySelectorAll('.partners-integrations__feature-icon');
        featureIcons.forEach((icon, index) => {
            setTimeout(() => {
                icon.style.transform = 'scale(1.1)';
                icon.style.color = '#3b82f6';
            }, index * 50);
        });
    }

    /**
     * Gestion de la fin du hover sur les cartes
     */
    handleCardLeave(e) {
        const card = e.currentTarget;
        const logo = card.querySelector('.partners-integrations__card-logo');

        if (logo) {
            logo.style.transform = '';
        }

        // Réinitialisation des icônes
        const featureIcons = card.querySelectorAll('.partners-integrations__feature-icon');
        featureIcons.forEach(icon => {
            icon.style.transform = '';
            icon.style.color = '';
        });
    }

    /**
     * Gestion des clics sur les boutons de cartes
     */
    handleCardButtonClick(e) {
        const button = e.currentTarget;
        const card = button.closest('.partners-integrations__card');
        const partnerName = card.querySelector('.partners-integrations__card-title')?.textContent;

        // Animation de clic
        button.style.transform = 'translateY(-1px) scale(0.98)';
        setTimeout(() => {
            button.style.transform = '';
        }, 150);

        // Tracking
        this.dispatchCustomEvent('partners:integrationClicked', {
            partner: partnerName,
            href: button.href
        });
    }

    /**
     * Création de particules au hover
     */
    createHoverParticles(element) {
        const rect = element.getBoundingClientRect();
        const particleCount = 6;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            const angle = (360 / particleCount) * i;
            const distance = 30 + Math.random() * 20;

            particle.style.cssText = `
                position: fixed;
                width: 3px;
                height: 3px;
                background: linear-gradient(45deg, #3b82f6, #8b5cf6);
                border-radius: 50%;
                pointer-events: none;
                top: ${rect.top + rect.height / 2}px;
                left: ${rect.left + rect.width / 2}px;
                z-index: 1000;
                opacity: 0.8;
                animation: hoverParticleFloat 1s ease-out forwards;
                --angle: ${angle}deg;
                --distance: ${distance}px;
            `;

            document.body.appendChild(particle);
        }

        // Nettoyage après animation
        setTimeout(() => {
            document.querySelectorAll('[style*="hoverParticleFloat"]').forEach(p => p.remove());
        }, 1000);
    }

    /**
     * Initialisation des animations du hero
     */
    initHeroAnimations() {
        // Animation en boucle des nœuds satellites
        this.integrationNodes.forEach(node => {
            if (node.classList.contains('partners-hero__integration-node--satellite')) {
                setInterval(() => {
                    const logo = node.querySelector('.partners-hero__integration-logo');
                    if (logo && Math.random() > 0.7) { // 30% de chance
                        logo.style.transform = 'scale(1.1)';
                        setTimeout(() => {
                            logo.style.transform = '';
                        }, 200);
                    }
                }, 3000 + Math.random() * 2000);
            }
        });
    }

    /**
     * Amélioration de l'accessibilité
     */
    enhanceAccessibility() {
        // Ajouter des labels ARIA appropriés
        this.filterButtons.forEach(button => {
            const category = button.getAttribute('data-category');
            button.setAttribute('aria-label', `Filtrer par ${category}`);
        });

        // Support clavier pour les interactions
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                // Réinitialiser le filtre
                this.handleFilterClick({ currentTarget: this.filterButtons[0] });
            }
        });

        // Améliorer les annonces pour les lecteurs d'écran
        const announceRegion = document.createElement('div');
        announceRegion.setAttribute('aria-live', 'polite');
        announceRegion.setAttribute('aria-atomic', 'true');
        announceRegion.className = 'sr-only';
        document.body.appendChild(announceRegion);
        this.announceRegion = announceRegion;
    }

    /**
     * Gestion du redimensionnement
     */
    handleResize() {
        // Recalculer les positions pour les effets parallax
        this.handleScroll();
    }

    /**
     * Gestion de la visibilité de la page
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // Arrêter les animations coûteuses
            this.activeCounters.forEach((id, key) => {
                cancelAnimationFrame(id);
                this.activeCounters.delete(key);
            });
        }
    }

    /**
     * Fonction d'easing pour les animations
     */
    easeOutQuart(t) {
        return 1 - (--t) * t * t * t;
    }

    /**
     * Émission d'événements personnalisés
     */
    dispatchCustomEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, {
            detail: {
                component: 'PartnersComponent',
                timestamp: new Date().getTime(),
                ...detail
            },
            bubbles: true
        });
        document.dispatchEvent(event);
    }

    /**
     * Nettoyage du composant
     */
    destroy() {
        // Annuler les animations en cours
        this.activeCounters.forEach((id) => {
            cancelAnimationFrame(id);
        });
        this.activeCounters.clear();

        // Supprimer les event listeners
        window.removeEventListener('resize', this.handleResize);
        window.removeEventListener('scroll', this.handleScroll);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);

        // Nettoyer les particules
        document.querySelectorAll('[style*="hoverParticleFloat"]').forEach(p => p.remove());

        // Supprimer la région d'annonces
        if (this.announceRegion) {
            this.announceRegion.remove();
        }
    }
}

// ===== STYLES CSS POUR LES ANIMATIONS =====
const style = document.createElement('style');
style.textContent = `
    @keyframes hoverParticleFloat {
        0% {
            transform: translate(0, 0) scale(1);
            opacity: 0.8;
        }
        100% {
            transform: translate(
                calc(cos(var(--angle) * 3.14159 / 180) * var(--distance)),
                calc(sin(var(--angle) * 3.14159 / 180) * var(--distance))
            ) scale(0);
            opacity: 0;
        }
    }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
`;
document.head.appendChild(style);

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du composant Partners
    window.partnersComponent = new PartnersComponent();
});

// Export pour utilisation externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PartnersComponent;
} else if (typeof window !== 'undefined') {
    window.PartnersComponent = PartnersComponent;
}
