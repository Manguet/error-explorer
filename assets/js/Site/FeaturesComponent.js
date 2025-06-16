/**
 * Features Component - BEM Architecture
 * Gestion des animations et interactions des fonctionnalités
 */
class FeaturesComponent {
    constructor() {
        // Éléments DOM
        this.features = document.querySelector('.features');
        this.featureItems = document.querySelectorAll('.features__item');
        this.mockInterfaces = document.querySelectorAll('.features__mock-interface');
        this.mockLines = document.querySelectorAll('.features__mock-line');
        this.benefits = document.querySelectorAll('.features__benefit');
        this.icons = document.querySelectorAll('.features__icon');

        // Configuration
        this.config = {
            observerThreshold: 0.2,
            staggerDelay: 300,
            mockAnimationDelay: 800,
            lineAnimationSpeed: 150
        };

        // État
        this.hasAnimated = false;
        this.isVisible = false;
        this.activeAnimations = new Set();

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.features) return;

        this.bindEvents();
        this.initIntersectionObserver();
        this.initMockInterfaces();
        this.enhanceAccessibility();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Effets hover sur les items de fonctionnalités
        this.featureItems.forEach(item => {
            item.addEventListener('mouseenter', this.handleItemHover.bind(this));
            item.addEventListener('mouseleave', this.handleItemLeave.bind(this));
        });

        // Effets sur les interfaces mockées
        this.mockInterfaces.forEach(mockInterface => {
            mockInterface.addEventListener('mouseenter', this.handleMockHover.bind(this));
            mockInterface.addEventListener('mouseleave', this.handleMockLeave.bind(this));
        });

        // Effets sur les bénéfices
        this.benefits.forEach(benefit => {
            benefit.addEventListener('mouseenter', this.handleBenefitHover.bind(this));
            benefit.addEventListener('mouseleave', this.handleBenefitLeave.bind(this));
        });

        // Effets sur les icônes
        this.icons.forEach(icon => {
            icon.addEventListener('mouseenter', this.handleIconHover.bind(this));
            icon.addEventListener('mouseleave', this.handleIconLeave.bind(this));
        });

        // Gestion du redimensionnement
        window.addEventListener('resize', this.handleResize.bind(this));
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

        // Observer la section complète
        observer.observe(this.features);

        // Observer chaque item individuellement
        this.featureItems.forEach(item => {
            const itemObserver = new IntersectionObserver(
                this.handleItemIntersection.bind(this),
                {
                    threshold: 0.3,
                    rootMargin: '0px 0px -50px 0px'
                }
            );
            itemObserver.observe(item);
        });
    }

    /**
     * Gestion de l'intersection principale
     */
    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting && !this.hasAnimated) {
                this.isVisible = true;
                this.startFeaturesAnimation();
                this.hasAnimated = true;
            } else if (!entry.isIntersecting) {
                this.isVisible = false;
            }
        });
    }

    /**
     * Gestion de l'intersection des items individuels
     */
    handleItemIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                this.animateFeatureItem(entry.target);
            }
        });
    }

    /**
     * Animation principale des fonctionnalités
     */
    startFeaturesAnimation() {
        // Animation du header
        this.animateHeader();

        // Animation séquentielle des items
        this.featureItems.forEach((item, index) => {
            setTimeout(() => {
                this.animateFeatureItem(item);
            }, index * this.config.staggerDelay);
        });
    }

    /**
     * Animation du header
     */
    animateHeader() {
        const header = document.querySelector('.features__header');
        if (!header) return;

        const title = header.querySelector('.features__title');
        const description = header.querySelector('.features__description');

        // Animation du titre
        if (title) {
            title.style.opacity = '0';
            title.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                title.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                title.style.opacity = '1';
                title.style.transform = 'translateY(0)';
            }, 100);
        }

        // Animation de la description
        if (description) {
            description.style.opacity = '0';
            description.style.transform = 'translateY(20px)';
            setTimeout(() => {
                description.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                description.style.opacity = '1';
                description.style.transform = 'translateY(0)';
            }, 300);
        }
    }

    /**
     * Animation d'un item de fonctionnalité
     */
    animateFeatureItem(item) {
        if (item.classList.contains('fade-in-up')) return;

        // Animation d'apparition de l'item
        item.classList.add('fade-in-up');

        // Animation des bénéfices
        setTimeout(() => {
            this.animateBenefits(item);
        }, 400);

        // Animation de l'interface mockée
        setTimeout(() => {
            this.animateMockInterface(item);
        }, this.config.mockAnimationDelay);
    }

    /**
     * Animation des bénéfices d'un item
     */
    animateBenefits(item) {
        const benefits = item.querySelectorAll('.features__benefit');
        benefits.forEach((benefit, index) => {
            setTimeout(() => {
                benefit.style.opacity = '1';
                benefit.style.transform = 'translateX(0)';
            }, index * this.config.lineAnimationSpeed);
        });
    }

    /**
     * Animation de l'interface mockée
     */
    animateMockInterface(item) {
        const mockLines = item.querySelectorAll('.features__mock-line');

        mockLines.forEach((line, index) => {
            const delay = parseInt(line.getAttribute('data-delay')) || (index * 500);

            setTimeout(() => {
                line.style.opacity = '1';
                line.style.transform = 'translateX(0)';

                // Effet spécial pour certaines lignes
                if (line.classList.contains('features__mock-line--alert')) {
                    this.triggerAlertEffect(line);
                }
            }, delay);
        });
    }

    /**
     * Effet spécial pour les lignes d'alerte
     */
    triggerAlertEffect(line) {
        // Animation d'alerte
        setTimeout(() => {
            line.style.animation = 'alertPulse 2s infinite';

            // Créer une notification contextuelle
            this.createAlertNotification(line);
        }, 500);
    }

    /**
     * Créer une notification d'alerte contextuelle
     */
    createAlertNotification(line) {
        const notification = document.createElement('div');
        notification.className = 'features__alert-notification';
        notification.innerHTML = '🚨 Alerte en temps réel !';

        notification.style.cssText = `
            position: absolute;
            top: -40px;
            right: 10px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            animation: alertNotificationSlide 0.5s ease-out;
            z-index: 10;
        `;

        const mockInterface = line.closest('.features__mock-interface');
        if (mockInterface) {
            mockInterface.style.position = 'relative';
            mockInterface.appendChild(notification);

            // Supprimer après 3 secondes
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
    }

    /**
     * Initialisation des interfaces mockées
     */
    initMockInterfaces() {
        this.mockInterfaces.forEach(mockInterface => {
            // Ajouter des effets de hover interactifs
            const lines = mockInterface.querySelectorAll('.features__mock-line');

            lines.forEach((line, index) => {
                line.addEventListener('mouseenter', () => {
                    this.highlightMockLine(line, index);
                });

                line.addEventListener('mouseleave', () => {
                    this.unhighlightMockLine(line);
                });
            });
        });
    }

    /**
     * Mise en évidence d'une ligne mockée
     */
    highlightMockLine(line, index) {
        line.style.background = 'rgba(59, 130, 246, 0.1)';
        line.style.borderLeft = '3px solid #3b82f6';
        line.style.transform = 'translateX(8px)';

        // Ajouter un tooltip contextuel
        this.showLineTooltip(line, index);
    }

    /**
     * Suppression de la mise en évidence
     */
    unhighlightMockLine(line) {
        // Garder les styles originaux pour les lignes spéciales
        if (!line.classList.contains('features__mock-line--alert') &&
            !line.classList.contains('features__mock-line--error') &&
            !line.classList.contains('features__mock-line--suggestion')) {
            line.style.background = '';
            line.style.borderLeft = '';
        }
        line.style.transform = '';

        // Supprimer le tooltip
        this.hideLineTooltip(line);
    }

    /**
     * Afficher un tooltip pour une ligne
     */
    showLineTooltip(line, index) {
        // Détecter le type de fonctionnalité
        const featureItem = line.closest('.features__item');
        const isDetection = featureItem?.classList.contains('features__item--detection');
        const isGrouping = featureItem?.classList.contains('features__item--grouping');

        // Tooltips spécifiques par fonctionnalité
        let tooltips = [];

        if (isDetection) {
            tooltips = [
                'Détection automatique en temps réel',
                'Localisation précise du fichier',
                'Horodatage de l\'erreur',
                'Notification instantanée de l\'équipe'
            ];
        } else if (isGrouping) {
            tooltips = [
                'Stack trace détaillée',
                'Analyse de la typo',
                'Suggestion de correction IA'
            ];
        } else {
            // Fallback générique
            tooltips = [
                'Information contextuelle',
                'Détail technique',
                'Analyse automatique'
            ];
        }

        const tooltip = document.createElement('div');
        tooltip.className = 'features__line-tooltip';
        tooltip.textContent = tooltips[index] || 'Information contextuelle';

        tooltip.style.cssText = `
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        `;

        line.style.position = 'relative';
        line.appendChild(tooltip);

        // Animation d'apparition
        setTimeout(() => {
            tooltip.style.opacity = '1';
        }, 100);
    }

    /**
     * Masquer le tooltip
     */
    hideLineTooltip(line) {
        const tooltip = line.querySelector('.features__line-tooltip');
        if (tooltip) {
            tooltip.style.opacity = '0';
            setTimeout(() => {
                tooltip.remove();
            }, 300);
        }
    }

    /**
     * Gestion du hover sur les items
     */
    handleItemHover(e) {
        const item = e.currentTarget;
        const icon = item.querySelector('.features__icon');
        const mockInterface = item.querySelector('.features__mock-interface');

        // Animation de l'icône
        if (icon) {
            icon.style.transform = 'scale(1.1) rotate(5deg)';
        }

        // Effet sur l'interface mockée
        if (mockInterface) {
            mockInterface.style.transform = 'translateY(-5px) scale(1.02)';
        }

        // Ajouter un effet de lueur
        this.addItemGlow(item);
    }

    /**
     * Gestion de la fin du hover sur les items
     */
    handleItemLeave(e) {
        const item = e.currentTarget;
        const icon = item.querySelector('.features__icon');
        const mockInterface = item.querySelector('.features__mock-interface');

        // Remettre l'icône en position normale
        if (icon) {
            icon.style.transform = '';
        }

        // Remettre l'interface mockée
        if (mockInterface) {
            mockInterface.style.transform = '';
        }

        // Supprimer l'effet de lueur
        this.removeItemGlow(item);
    }

    /**
     * Ajouter un effet de lueur à un item
     */
    addItemGlow(item) {
        const glowColor = this.getItemGlowColor(item);
        item.style.boxShadow = `0 20px 40px ${glowColor}15, 0 0 0 1px ${glowColor}20`;
    }

    /**
     * Supprimer l'effet de lueur
     */
    removeItemGlow(item) {
        item.style.boxShadow = '';
    }

    /**
     * Obtenir la couleur de lueur d'un item
     */
    getItemGlowColor(item) {
        if (item.classList.contains('features__item--detection')) return '#ef4444';
        if (item.classList.contains('features__item--grouping')) return '#3b82f6';
        return '#ffffff';
    }

    /**
     * Gestion du hover sur les interfaces mockées
     */
    handleMockHover(e) {
        const mockInterface = e.currentTarget;
        const lines = mockInterface.querySelectorAll('.features__mock-line');

        // Effet de scanning des lignes
        lines.forEach((line, index) => {
            setTimeout(() => {
                line.style.background = 'rgba(255, 255, 255, 0.03)';
            }, index * 50);
        });
    }

    /**
     * Gestion de la fin du hover sur les interfaces mockées
     */
    handleMockLeave(e) {
        const mockInterface = e.currentTarget;
        const lines = mockInterface.querySelectorAll('.features__mock-line');

        lines.forEach(line => {
            // Garder les backgrounds spéciaux
            if (!line.classList.contains('features__mock-line--alert') &&
                !line.classList.contains('features__mock-line--error') &&
                !line.classList.contains('features__mock-line--suggestion')) {
                line.style.background = '';
            }
        });
    }

    /**
     * Gestion du hover sur les bénéfices
     */
    handleBenefitHover(e) {
        const benefit = e.currentTarget;
        const icon = benefit.querySelector('.features__benefit-icon');

        if (icon) {
            icon.style.transform = 'scale(1.2) rotate(360deg)';
            icon.style.boxShadow = '0 4px 12px rgba(16, 185, 129, 0.4)';
        }
    }

    /**
     * Gestion de la fin du hover sur les bénéfices
     */
    handleBenefitLeave(e) {
        const benefit = e.currentTarget;
        const icon = benefit.querySelector('.features__benefit-icon');

        if (icon) {
            icon.style.transform = '';
            icon.style.boxShadow = '';
        }
    }

    /**
     * Gestion du hover sur les icônes
     */
    handleIconHover(e) {
        const icon = e.currentTarget;

        // Effet de pulsation
        icon.style.animation = 'iconPulse 0.6s ease-in-out';

        // Créer des particules autour de l'icône
        this.createIconParticles(icon);
    }

    /**
     * Gestion de la fin du hover sur les icônes
     */
    handleIconLeave(e) {
        const icon = e.currentTarget;
        icon.style.animation = '';
    }

    /**
     * Créer des particules autour d'une icône
     */
    createIconParticles(icon) {
        const particles = [];
        const particleCount = 6;
        const rect = icon.getBoundingClientRect();

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'features__icon-particle';

            const angle = (360 / particleCount) * i;
            const distance = 20 + Math.random() * 10;

            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: currentColor;
                border-radius: 50%;
                pointer-events: none;
                top: ${rect.top + rect.height / 2}px;
                left: ${rect.left + rect.width / 2}px;
                z-index: 1000;
                animation: iconParticleFloat 1s ease-out forwards;
                --angle: ${angle}deg;
                --distance: ${distance}px;
            `;

            document.body.appendChild(particle);
            particles.push(particle);
        }

        // Nettoyer les particules après l'animation
        setTimeout(() => {
            particles.forEach(particle => {
                if (particle.parentNode) {
                    particle.remove();
                }
            });
        }, 1000);
    }

    /**
     * Gestion du redimensionnement
     */
    handleResize() {
        // Réajuster les animations pour mobile
        if (window.innerWidth < 768) {
            this.adaptToMobile();
        }
    }

    /**
     * Adaptation pour mobile
     */
    adaptToMobile() {
        // Réduire les délais d'animation
        this.config.staggerDelay = 200;
        this.config.lineAnimationSpeed = 100;
    }

    /**
     * Amélioration de l'accessibilité
     */
    enhanceAccessibility() {
        // Ajouter des rôles ARIA
        this.featureItems.forEach(item => {
            item.setAttribute('role', 'region');
            item.setAttribute('tabindex', '0');

            const title = item.querySelector('.features__content-title')?.textContent;
            if (title) {
                item.setAttribute('aria-label', `Fonctionnalité: ${title}`);
            }
        });

        // Navigation clavier
        this.featureItems.forEach(item => {
            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.handleItemActivation(item);
                }
            });
        });

        // Améliorer les interfaces mockées
        this.mockInterfaces.forEach(mockInterface => {
            mockInterface.setAttribute('role', 'img');
            mockInterface.setAttribute('aria-label', 'Interface de démonstration');
        });
    }

    /**
     * Activation d'un item via le clavier
     */
    handleItemActivation(item) {
        // Effet visuel d'activation
        item.style.transform = 'scale(0.98)';
        setTimeout(() => {
            item.style.transform = '';
        }, 150);

        // Redémarrer l'animation de l'interface mockée
        this.restartMockAnimation(item);
    }

    /**
     * Redémarrer l'animation d'une interface mockée
     */
    restartMockAnimation(item) {
        const mockLines = item.querySelectorAll('.features__mock-line');

        // Réinitialiser les lignes
        mockLines.forEach(line => {
            line.style.opacity = '0';
            line.style.transform = 'translateX(-20px)';
        });

        // Relancer l'animation
        setTimeout(() => {
            this.animateMockInterface(item);
        }, 100);
    }

    /**
     * API publique - Redémarrer toutes les animations
     */
    restartAnimations() {
        this.hasAnimated = false;

        // Réinitialiser tous les items
        this.featureItems.forEach(item => {
            item.classList.remove('fade-in-up');
            item.style.opacity = '0';
            item.style.transform = 'translateY(40px)';
        });

        // Relancer l'animation
        setTimeout(() => {
            this.startFeaturesAnimation();
        }, 100);
    }

    /**
     * API publique - Animer un item spécifique
     */
    animateItem(index) {
        const item = this.featureItems[index];
        if (item) {
            this.animateFeatureItem(item);
        }
    }

    /**
     * Nettoyage
     */
    destroy() {
        // Supprimer les événements
        this.featureItems.forEach(item => {
            item.removeEventListener('mouseenter', this.handleItemHover);
            item.removeEventListener('mouseleave', this.handleItemLeave);
        });

        this.mockInterfaces.forEach(mockInterface => {
            mockInterface.removeEventListener('mouseenter', this.handleMockHover);
            mockInterface.removeEventListener('mouseleave', this.handleMockLeave);
        });

        this.benefits.forEach(benefit => {
            benefit.removeEventListener('mouseenter', this.handleBenefitHover);
            benefit.removeEventListener('mouseleave', this.handleBenefitLeave);
        });

        this.icons.forEach(icon => {
            icon.removeEventListener('mouseenter', this.handleIconHover);
            icon.removeEventListener('mouseleave', this.handleIconLeave);
        });

        window.removeEventListener('resize', this.handleResize);

        // Nettoyer les animations actives
        this.activeAnimations.clear();
    }
}

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser le composant Features
    window.featuresComponent = new FeaturesComponent();

    // Ajouter les styles CSS manquants pour les animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes alertNotificationSlide {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes iconPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
        }

        @keyframes iconParticleFloat {
            to {
                transform: rotate(var(--angle)) translateX(var(--distance)) scale(0);
                opacity: 0;
            }
        }

        .features__item:focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 4px;
            border-radius: 24px;
        }

        .features__mock-interface:focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
            border-radius: 16px;
        }

        @media (max-width: 768px) {
            .features__alert-notification,
            .features__line-tooltip {
                display: none;
            }
        }
    `;
    document.head.appendChild(style);
});

// Export pour utilisation externe
window.FeaturesComponent = FeaturesComponent;
