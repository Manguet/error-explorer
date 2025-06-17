/**
 * Security Page Component - BEM Architecture
 * Gestion des animations et interactions de la page sécurité
 */
class SecurityPage {
    constructor() {
        // Éléments DOM
        this.page = document.querySelector('.security-page');
        this.heroSection = document.querySelector('.security-hero');
        this.certificationCards = document.querySelectorAll('.security-certification');
        this.measureCards = document.querySelectorAll('.security-measure');
        this.privacyFeatures = document.querySelectorAll('.security-privacy__feature');
        this.heroStats = document.querySelectorAll('.security-hero__stat');
        this.shieldIcon = document.querySelector('.security-hero__shield-icon');
        this.lockIcon = document.querySelector('.security-privacy__lock-icon');

        // Configuration
        this.config = {
            observerThreshold: 0.1,
            staggerDelay: 150,
            animationDuration: 600,
            easing: 'ease-out'
        };

        // État
        this.isVisible = false;
        this.animatedElements = new Set();
        this.counters = new Map();

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.page) return;

        this.bindEvents();
        this.initIntersectionObserver();
        this.initAnimatedElements();
        this.enhanceAccessibility();
        this.initSecurityBadges();
        this.initCounters();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Effets hover sur les cartes de certification
        this.certificationCards.forEach(card => {
            card.addEventListener('mouseenter', this.handleCertificationHover.bind(this));
            card.addEventListener('mouseleave', this.handleCertificationLeave.bind(this));
        });

        // Effets hover sur les mesures de sécurité
        this.measureCards.forEach(card => {
            card.addEventListener('mouseenter', this.handleMeasureHover.bind(this));
            card.addEventListener('mouseleave', this.handleMeasureLeave.bind(this));
        });

        // Effets hover sur les fonctionnalités de confidentialité
        this.privacyFeatures.forEach(feature => {
            feature.addEventListener('mouseenter', this.handlePrivacyFeatureHover.bind(this));
            feature.addEventListener('mouseleave', this.handlePrivacyFeatureLeave.bind(this));
        });

        // Gestion du redimensionnement
        window.addEventListener('resize', this.handleResize.bind(this));

        // Gestion de la visibilité de la page
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
    }

    /**
     * Initialisation de l'Intersection Observer
     */
    initIntersectionObserver() {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.animateElement(entry.target);
                }
            });
        }, {
            threshold: this.config.observerThreshold,
            rootMargin: '50px 0px'
        });

        // Observer les éléments animables
        const animatableElements = [
            ...this.certificationCards,
            ...this.measureCards,
            ...this.privacyFeatures,
            ...this.heroStats
        ];

        animatableElements.forEach(element => {
            this.observer.observe(element);
        });
    }

    /**
     * Initialisation des éléments animés
     */
    initAnimatedElements() {
        // Marquer les éléments comme non animés initialement
        this.certificationCards.forEach((card, index) => {
            card.style.animationDelay = `${index * this.config.staggerDelay}ms`;
        });

        this.measureCards.forEach((card, index) => {
            card.style.animationDelay = `${index * this.config.staggerDelay}ms`;
        });

        this.privacyFeatures.forEach((feature, index) => {
            feature.style.animationDelay = `${index * 100}ms`;
        });
    }

    /**
     * Animation d'un élément
     */
    animateElement(element) {
        if (this.animatedElements.has(element)) return;

        this.animatedElements.add(element);

        if (element.classList.contains('security-certification')) {
            element.classList.add('fade-in-up');
        } else if (element.classList.contains('security-measure')) {
            element.classList.add('fade-in-up');
        } else if (element.classList.contains('security-privacy__feature')) {
            element.classList.add('slide-in-left');
        } else if (element.classList.contains('security-hero__stat')) {
            this.animateStatCounter(element);
        }
    }

    /**
     * Animation des compteurs de statistiques
     */
    animateStatCounter(statElement) {
        const valueElement = statElement.querySelector('.security-hero__stat-value');
        if (!valueElement) return;

        const text = valueElement.textContent.trim();
        const isPercentage = text.includes('%');
        const numericValue = parseFloat(text.replace(/[^\d.]/g, ''));

        if (isNaN(numericValue)) return;

        let currentValue = 0;
        const increment = numericValue / 60; // Animation sur ~1 seconde à 60fps
        const suffix = isPercentage ? '%' : '';

        const counter = setInterval(() => {
            currentValue += increment;

            if (currentValue >= numericValue) {
                currentValue = numericValue;
                clearInterval(counter);
            }

            if (isPercentage) {
                valueElement.textContent = `${currentValue.toFixed(1)}${suffix}`;
            } else {
                valueElement.textContent = `${Math.floor(currentValue)}${suffix}`;
            }
        }, 16);

        this.counters.set(statElement, counter);
    }

    /**
     * Gestion du hover sur les cartes de certification
     */
    handleCertificationHover(e) {
        const card = e.currentTarget;
        const status = card.querySelector('.security-certification__status');

        if (status) {
            status.style.transform = 'scale(1.05)';
            status.style.boxShadow = '0 4px 12px rgba(16, 185, 129, 0.3)';
        }

        // Effet de parallaxe léger
        card.addEventListener('mousemove', this.handleCertificationMouseMove.bind(this));
    }

    /**
     * Gestion du mouse leave sur les cartes de certification
     */
    handleCertificationLeave(e) {
        const card = e.currentTarget;
        const status = card.querySelector('.security-certification__status');

        if (status) {
            status.style.transform = 'scale(1)';
            status.style.boxShadow = 'none';
        }

        card.style.transform = 'translateY(-5px)';
        card.removeEventListener('mousemove', this.handleCertificationMouseMove.bind(this));
    }

    /**
     * Effet de parallaxe sur les cartes de certification
     */
    handleCertificationMouseMove(e) {
        const card = e.currentTarget;
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        const centerX = rect.width / 2;
        const centerY = rect.height / 2;

        const rotateX = (y - centerY) / centerY * 5;
        const rotateY = (centerX - x) / centerX * 5;

        card.style.transform = `translateY(-5px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    }

    /**
     * Gestion du hover sur les mesures de sécurité
     */
    handleMeasureHover(e) {
        const card = e.currentTarget;
        const indicator = card.querySelector('.security-measure__indicator-dot');

        if (indicator) {
            indicator.style.animationDuration = '0.5s';
            indicator.style.transform = 'scale(1.5)';
        }

        // Animation de l'icône
        const icon = card.querySelector('.security-measure__icon');
        if (icon) {
            icon.style.transform = 'scale(1.1) rotate(5deg)';
        }
    }

    /**
     * Gestion du mouse leave sur les mesures de sécurité
     */
    handleMeasureLeave(e) {
        const card = e.currentTarget;
        const indicator = card.querySelector('.security-measure__indicator-dot');

        if (indicator) {
            indicator.style.animationDuration = '2s';
            indicator.style.transform = 'scale(1)';
        }

        const icon = card.querySelector('.security-measure__icon');
        if (icon) {
            icon.style.transform = 'scale(1) rotate(0deg)';
        }
    }

    /**
     * Gestion du hover sur les fonctionnalités de confidentialité
     */
    handlePrivacyFeatureHover(e) {
        const feature = e.currentTarget;
        const icon = feature.querySelector('.security-privacy__feature-icon');

        if (icon) {
            icon.style.transform = 'scale(1.1)';
            icon.style.background = 'rgba(59, 130, 246, 0.15)';
        }

        // Effet de glow
        feature.style.boxShadow = '0 8px 25px rgba(59, 130, 246, 0.15)';
    }

    /**
     * Gestion du mouse leave sur les fonctionnalités de confidentialité
     */
    handlePrivacyFeatureLeave(e) {
        const feature = e.currentTarget;
        const icon = feature.querySelector('.security-privacy__feature-icon');

        if (icon) {
            icon.style.transform = 'scale(1)';
            icon.style.background = 'rgba(59, 130, 246, 0.1)';
        }

        feature.style.boxShadow = 'none';
    }

    /**
     * Initialisation des badges de sécurité interactifs
     */
    initSecurityBadges() {
        const badges = document.querySelectorAll('.security-hero__badge, .security-certification__status');

        badges.forEach(badge => {
            badge.addEventListener('click', (e) => {
                e.preventDefault();
                this.showSecurityTooltip(badge);
            });
        });
    }

    /**
     * Affichage d'une tooltip de sécurité
     */
    showSecurityTooltip(element) {
        // Supprimer les tooltips existantes
        document.querySelectorAll('.security-tooltip').forEach(tooltip => {
            tooltip.remove();
        });

        const tooltip = document.createElement('div');
        tooltip.className = 'security-tooltip';
        tooltip.innerHTML = `
            <div class="security-tooltip__arrow"></div>
            <div class="security-tooltip__content">
                <div class="security-tooltip__title">Sécurité Vérifiée</div>
                <div class="security-tooltip__text">
                    Cette certification est régulièrement auditée par des organismes indépendants.
                </div>
            </div>
        `;

        document.body.appendChild(tooltip);

        // Positionner la tooltip
        const rect = element.getBoundingClientRect();
        tooltip.style.position = 'fixed';
        tooltip.style.top = `${rect.bottom + 10}px`;
        tooltip.style.left = `${rect.left + rect.width / 2}px`;
        tooltip.style.transform = 'translateX(-50%)';
        tooltip.style.zIndex = '1000';

        // Styles de la tooltip
        this.addTooltipStyles();

        // Animation d'apparition
        setTimeout(() => {
            tooltip.classList.add('security-tooltip--visible');
        }, 10);

        // Supprimer après 3 secondes
        setTimeout(() => {
            tooltip.classList.remove('security-tooltip--visible');
            setTimeout(() => tooltip.remove(), 300);
        }, 3000);
    }

    /**
     * Ajout des styles pour les tooltips
     */
    addTooltipStyles() {
        if (document.getElementById('security-tooltip-styles')) return;

        const style = document.createElement('style');
        style.id = 'security-tooltip-styles';
        style.textContent = `
            .security-tooltip {
                background: rgba(15, 23, 42, 0.95);
                border: 1px solid rgba(59, 130, 246, 0.3);
                border-radius: 8px;
                padding: 12px 16px;
                backdrop-filter: blur(20px);
                max-width: 250px;
                opacity: 0;
                transform: translateX(-50%) translateY(-10px);
                transition: all 0.3s ease;
                pointer-events: none;
            }
            
            .security-tooltip--visible {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
            
            .security-tooltip__arrow {
                position: absolute;
                top: -6px;
                left: 50%;
                transform: translateX(-50%);
                width: 12px;
                height: 12px;
                background: rgba(15, 23, 42, 0.95);
                border: 1px solid rgba(59, 130, 246, 0.3);
                border-bottom: none;
                border-right: none;
                transform: translateX(-50%) rotate(45deg);
            }
            
            .security-tooltip__title {
                color: #3b82f6;
                font-weight: 600;
                font-size: 14px;
                margin-bottom: 4px;
            }
            
            .security-tooltip__text {
                color: rgba(255, 255, 255, 0.8);
                font-size: 13px;
                line-height: 1.4;
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Initialisation des compteurs de sécurité
     */
    initCounters() {
        const statElements = document.querySelectorAll('.security-hero__stat-value');

        statElements.forEach(element => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.animatedElements.has(entry.target)) {
                        this.animateStatCounter(entry.target.closest('.security-hero__stat'));
                    }
                });
            }, { threshold: 0.5 });

            observer.observe(element);
        });
    }

    /**
     * Amélioration de l'accessibilité
     */
    enhanceAccessibility() {
        // ARIA labels pour les cartes de sécurité
        this.measureCards.forEach((card, index) => {
            const title = card.querySelector('.security-measure__title');
            const description = card.querySelector('.security-measure__description');

            if (title && description) {
                card.setAttribute('role', 'article');
                card.setAttribute('aria-labelledby', `security-measure-title-${index}`);
                card.setAttribute('aria-describedby', `security-measure-desc-${index}`);

                title.id = `security-measure-title-${index}`;
                description.id = `security-measure-desc-${index}`;
            }
        });

        // Amélioration du focus
        const focusableElements = [
            ...this.certificationCards,
            ...this.measureCards,
            ...this.privacyFeatures
        ];

        focusableElements.forEach(element => {
            if (!element.hasAttribute('tabindex')) {
                element.setAttribute('tabindex', '0');
            }

            element.addEventListener('focus', (e) => {
                e.target.style.outline = '2px solid #3b82f6';
                e.target.style.outlineOffset = '2px';
            });

            element.addEventListener('blur', (e) => {
                e.target.style.outline = 'none';
            });
        });
    }

    /**
     * Gestion du redimensionnement
     */
    handleResize() {
        // Recalculer les positions et animations si nécessaire
        this.debounce(() => {
            // Réinitialiser les transformations des cartes
            this.certificationCards.forEach(card => {
                card.style.transform = '';
            });

            // Recalculer les délais d'animation
            this.initAnimatedElements();
        }, 250)();
    }

    /**
     * Gestion du changement de visibilité de la page
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // Pause des animations coûteuses
            this.pauseAnimations();
        } else {
            // Reprise des animations
            this.resumeAnimations();
        }
    }

    /**
     * Pause des animations
     */
    pauseAnimations() {
        // Arrêter les compteurs actifs
        this.counters.forEach((counter, element) => {
            clearInterval(counter);
        });

        // Pause des animations CSS
        const animatedElements = document.querySelectorAll('[class*="security-"]');
        animatedElements.forEach(element => {
            element.style.animationPlayState = 'paused';
        });
    }

    /**
     * Reprise des animations
     */
    resumeAnimations() {
        // Reprendre les animations CSS
        const animatedElements = document.querySelectorAll('[class*="security-"]');
        animatedElements.forEach(element => {
            element.style.animationPlayState = 'running';
        });
    }

    /**
     * Fonction de debounce pour optimiser les performances
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Simulation d'une vérification de sécurité en temps réel
     */
    initSecurityMonitoring() {
        // Simuler des vérifications de sécurité périodiques
        setInterval(() => {
            this.updateSecurityStatus();
        }, 30000); // Mise à jour toutes les 30 secondes
    }

    /**
     * Mise à jour du statut de sécurité
     */
    updateSecurityStatus() {
        const indicators = document.querySelectorAll('.security-measure__indicator-dot');

        indicators.forEach(indicator => {
            // Simuler une vérification
            indicator.style.opacity = '0.5';

            setTimeout(() => {
                indicator.style.opacity = '1';
                // Ajouter une pulsation pour indiquer la vérification
                indicator.style.animation = 'pulse 0.5s ease-in-out';

                setTimeout(() => {
                    indicator.style.animation = 'pulse 2s ease-in-out infinite';
                }, 500);
            }, 1000);
        });
    }

    /**
     * Gestion des interactions clavier
     */
    handleKeyboardInteraction(e) {
        // Navigation avec les touches fléchées
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            this.navigateCards(e.key === 'ArrowDown' ? 1 : -1);
        }

        // Activation avec Entrée ou Espace
        if (e.key === 'Enter' || e.key === ' ') {
            const focusedElement = document.activeElement;
            if (focusedElement && focusedElement.classList.contains('security-measure')) {
                e.preventDefault();
                this.highlightSecurityMeasure(focusedElement);
            }
        }
    }

    /**
     * Navigation entre les cartes avec le clavier
     */
    navigateCards(direction) {
        const focusableCards = [...this.measureCards, ...this.certificationCards];
        const currentIndex = focusableCards.indexOf(document.activeElement);

        if (currentIndex === -1) {
            focusableCards[0]?.focus();
            return;
        }

        const nextIndex = (currentIndex + direction + focusableCards.length) % focusableCards.length;
        focusableCards[nextIndex]?.focus();
    }

    /**
     * Mise en évidence d'une mesure de sécurité
     */
    highlightSecurityMeasure(element) {
        // Effet de mise en évidence
        element.style.boxShadow = '0 0 20px rgba(59, 130, 246, 0.5)';
        element.style.transform = 'translateY(-8px) scale(1.02)';

        // Annonce vocale pour les lecteurs d'écran
        const title = element.querySelector('.security-measure__title')?.textContent;
        const description = element.querySelector('.security-measure__description')?.textContent;

        if (title && description) {
            this.announceToScreenReader(`Mesure de sécurité: ${title}. ${description}`);
        }

        // Retour à l'état normal après 2 secondes
        setTimeout(() => {
            element.style.boxShadow = '';
            element.style.transform = '';
        }, 2000);
    }

    /**
     * Annonce pour les lecteurs d'écran
     */
    announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.style.position = 'absolute';
        announcement.style.left = '-10000px';
        announcement.style.width = '1px';
        announcement.style.height = '1px';
        announcement.style.overflow = 'hidden';

        document.body.appendChild(announcement);
        announcement.textContent = message;

        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    }

    /**
     * Nettoyage lors de la destruction du composant
     */
    destroy() {
        // Nettoyer les observers
        if (this.observer) {
            this.observer.disconnect();
        }

        // Nettoyer les compteurs
        this.counters.forEach(counter => {
            clearInterval(counter);
        });

        // Supprimer les event listeners
        window.removeEventListener('resize', this.handleResize.bind(this));
        document.removeEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
        document.removeEventListener('keydown', this.handleKeyboardInteraction.bind(this));

        // Nettoyer les tooltips
        document.querySelectorAll('.security-tooltip').forEach(tooltip => {
            tooltip.remove();
        });

        // Supprimer les styles personnalisés
        const customStyles = document.getElementById('security-tooltip-styles');
        if (customStyles) {
            customStyles.remove();
        }
    }
}

// Initialisation automatique lorsque le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    // Vérifier que nous sommes sur la page de sécurité
    if (document.querySelector('.security-page')) {
        window.securityPage = new SecurityPage();

        // Ajouter la gestion des interactions clavier
        document.addEventListener('keydown', (e) => {
            window.securityPage.handleKeyboardInteraction(e);
        });

        // Initialiser le monitoring de sécurité
        window.securityPage.initSecurityMonitoring();
    }
});

// Nettoyage lors du déchargement de la page
window.addEventListener('beforeunload', () => {
    if (window.securityPage) {
        window.securityPage.destroy();
    }
});
