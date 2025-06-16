/**
 * Pricing Component Moderne - BEM Architecture
 * Gestion des animations 3D, effets glassmorphism et interactions avancées
 */
class ModernPricingComponent {
    constructor() {
        // Éléments DOM avec nouvelle nomenclature
        this.pricing = document.querySelector('.pricing');
        this.pricingCards = document.querySelectorAll('.pricing__card');
        this.ctaButtons = document.querySelectorAll('.pricing__cta');
        this.comparisonToggle = document.querySelector('.pricing__comparison-toggle');
        this.usageBars = document.querySelectorAll('.pricing__usage-fill');
        this.metrics = document.querySelectorAll('.pricing__metric');
        this.floatingElements = document.querySelectorAll('.floating-element');

        // Configuration moderne
        this.config = {
            observerThreshold: 0.1,
            staggerDelay: 150,
            animationDuration: 400,
            usageAnimationDelay: 2000,
            particleCount: 12,
            rippleSize: 200
        };

        // État
        this.hasAnimated = false;
        this.isVisible = false;
        this.comparisonOpen = false;
        this.selectedPlan = null;
        this.animations = new Map();

        this.init();
    }

    /**
     * Initialisation du composant moderne
     */
    init() {
        if (!this.pricing) return;

        this.bindModernEvents();
        this.initIntersectionObserver();
        this.initPlanSelection();
        this.enhanceAccessibility();
        this.initParticleSystem();
        this.startFloatingAnimation();
    }

    /**
     * Liaison des événements modernes
     */
    bindModernEvents() {
        // Effets hover 3D sur les cartes
        this.pricingCards.forEach(card => {
            card.addEventListener('mouseenter', this.handleModernCardHover.bind(this));
            card.addEventListener('mouseleave', this.handleModernCardLeave.bind(this));
            card.addEventListener('mousemove', this.handleCardMouseMove.bind(this));
        });

        // Effets CTA avec ripple moderne
        this.ctaButtons.forEach(button => {
            button.addEventListener('click', this.handleModernCtaClick.bind(this));
        });

        // Toggle de comparaison avec animation
        if (this.comparisonToggle) {
            this.comparisonToggle.addEventListener('click', this.handleModernComparisonToggle.bind(this));
        }

        // Gestion du redimensionnement
        window.addEventListener('resize', this.handleResize.bind(this));

        // Gestion du scroll pour les effets parallax
        window.addEventListener('scroll', this.handleScroll.bind(this), { passive: true });
    }

    /**
     * Initialisation du système de particules
     */
    initParticleSystem() {
        if (!this.pricing) return;

        // Créer des particules flottantes dynamiques
        for (let i = 0; i < this.config.particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'pricing-particle';
            particle.style.cssText = `
                position: absolute;
                width: ${Math.random() * 4 + 2}px;
                height: ${Math.random() * 4 + 2}px;
                background: rgba(59, 130, 246, ${Math.random() * 0.5 + 0.2});
                border-radius: 50%;
                top: ${Math.random() * 100}%;
                left: ${Math.random() * 100}%;
                animation: particleFloat ${Math.random() * 10 + 15}s linear infinite;
                animation-delay: ${Math.random() * 5}s;
                pointer-events: none;
                z-index: 1;
            `;

            this.pricing.appendChild(particle);
        }

        // Ajouter les styles CSS pour les particules
        this.addParticleStyles();
    }

    /**
     * Démarrer l'animation des éléments flottants
     */
    startFloatingAnimation() {
        this.floatingElements.forEach((element, index) => {
            // Animation personnalisée pour chaque élément
            const duration = Math.random() * 3 + 4; // 4-7 secondes
            const delay = index * 0.5;

            element.style.animationDuration = `${duration}s`;
            element.style.animationDelay = `${delay}s`;
        });
    }

    /**
     * Gestion moderne du hover sur les cartes avec effet 3D
     */
    handleModernCardHover(e) {
        const card = e.currentTarget;
        const metrics = card.querySelectorAll('.pricing__metric');
        const features = card.querySelectorAll('.pricing__feature');

        // Effet 3D amélioré
        card.style.willChange = 'transform';
        card.style.transformStyle = 'preserve-3d';

        // Animation des métriques avec délai séquentiel
        metrics.forEach((metric, index) => {
            setTimeout(() => {
                metric.style.transform = 'translateZ(10px) scale(1.05)';
                metric.style.boxShadow = '0 8px 25px rgba(59, 130, 246, 0.2)';
            }, index * 50);
        });

        // Animation des features
        features.forEach((feature, index) => {
            setTimeout(() => {
                feature.style.transform = 'translateX(12px)';
                feature.style.background = 'rgba(255, 255, 255, 0.03)';
            }, index * 30);
        });

        // Effet de glow moderne
        this.addModernCardGlow(card);

        // Créer des particules autour de la carte
        this.createHoverParticles(card);
    }

    /**
     * Gestion de la fin du hover moderne
     */
    handleModernCardLeave(e) {
        const card = e.currentTarget;
        const metrics = card.querySelectorAll('.pricing__metric');
        const features = card.querySelectorAll('.pricing__feature');

        // Reset des transformations
        card.style.willChange = 'auto';

        metrics.forEach(metric => {
            metric.style.transform = '';
            metric.style.boxShadow = '';
        });

        features.forEach(feature => {
            feature.style.transform = '';
            feature.style.background = '';
        });

        // Supprimer l'effet de glow
        this.removeModernCardGlow(card);
    }

    /**
     * Gestion du mouvement de la souris pour effet parallax
     */
    handleCardMouseMove(e) {
        const card = e.currentTarget;
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        const centerX = rect.width / 2;
        const centerY = rect.height / 2;

        const rotateX = (y - centerY) / 20;
        const rotateY = (centerX - x) / 20;

        card.style.transform = `
            translateY(-12px) 
            rotateX(${rotateX}deg) 
            rotateY(${rotateY}deg) 
            scale(1.02)
        `;
    }

    /**
     * Gestion moderne du clic CTA avec effet ripple avancé
     */
    handleModernCtaClick(e) {
        const button = e.currentTarget;
        const planSlug = button.getAttribute('data-plan');

        e.preventDefault();

        // Effet ripple moderne avec multiple ondes
        this.createAdvancedRipple(button, e);

        // Animation du bouton
        button.style.transform = 'scale(0.95)';
        setTimeout(() => {
            button.style.transform = '';
        }, 150);

        // Sélectionner le plan avec animation
        if (planSlug) {
            this.selectPlanWithAnimation(planSlug);
        }

        // Créer des particules de succès
        this.createSuccessParticles(button);

        // Redirection après animation
        setTimeout(() => {
            window.location.href = button.href;
        }, 600);
    }

    /**
     * Créer un effet ripple avancé avec multiple ondes
     */
    createAdvancedRipple(element, event) {
        const rect = element.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;

        // Créer 3 ondes de tailles différentes
        for (let i = 0; i < 3; i++) {
            const ripple = document.createElement('div');
            const size = this.config.rippleSize + (i * 50);

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                border-radius: 50%;
                background: rgba(255, 255, 255, ${0.3 - (i * 0.1)});
                transform: translate(${x - size/2}px, ${y - size/2}px) scale(0);
                animation: advancedRipple ${0.6 + (i * 0.2)}s cubic-bezier(0.4, 0, 0.2, 1);
                pointer-events: none;
                z-index: 10;
            `;

            element.style.position = 'relative';
            element.style.overflow = 'hidden';
            element.appendChild(ripple);

            setTimeout(() => ripple.remove(), (600 + (i * 200)));
        }
    }

    /**
     * Créer des particules au hover
     */
    createHoverParticles(card) {
        const rect = card.getBoundingClientRect();
        const particles = 8;

        for (let i = 0; i < particles; i++) {
            const particle = document.createElement('div');
            const angle = (360 / particles) * i;
            const distance = 80 + Math.random() * 40;

            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: linear-gradient(45deg, #3b82f6, #8b5cf6);
                border-radius: 50%;
                pointer-events: none;
                top: ${rect.top + rect.height / 2}px;
                left: ${rect.left + rect.width / 2}px;
                z-index: 1000;
                animation: hoverParticleFloat 1.5s ease-out forwards;
                --angle: ${angle}deg;
                --distance: ${distance}px;
            `;

            document.body.appendChild(particle);
        }

        // Nettoyer après animation
        setTimeout(() => {
            document.querySelectorAll('[style*="hoverParticleFloat"]').forEach(p => p.remove());
        }, 1500);
    }

    /**
     * Créer des particules de succès
     */
    createSuccessParticles(button) {
        const rect = button.getBoundingClientRect();
        const particles = 12;

        for (let i = 0; i < particles; i++) {
            const particle = document.createElement('div');
            const angle = (360 / particles) * i;
            const distance = 60 + Math.random() * 30;

            particle.style.cssText = `
                position: fixed;
                width: 6px;
                height: 6px;
                background: linear-gradient(45deg, #10b981, #06d6a0);
                border-radius: 50%;
                pointer-events: none;
                top: ${rect.top + rect.height / 2}px;
                left: ${rect.left + rect.width / 2}px;
                z-index: 1000;
                animation: successParticleFloat 2s ease-out forwards;
                --angle: ${angle}deg;
                --distance: ${distance}px;
            `;

            document.body.appendChild(particle);
        }

        // Nettoyer après animation
        setTimeout(() => {
            document.querySelectorAll('[style*="successParticleFloat"]').forEach(p => p.remove());
        }, 2000);
    }

    /**
     * Sélection de plan avec animation moderne
     */
    selectPlanWithAnimation(planSlug) {
        // Désélectionner tous les plans
        this.pricingCards.forEach(card => {
            card.classList.remove('pricing__card--selected');
        });

        // Sélectionner le plan choisi
        const selectedCard = document.querySelector(`[data-plan="${planSlug}"]`);
        if (selectedCard) {
            selectedCard.classList.add('pricing__card--selected');
            this.selectedPlan = planSlug;

            // Animation de sélection avancée
            this.animateCardSelection(selectedCard);

            // Notification moderne
            if (window.notify) {
                notify.success(`Plan "${selectedCard.querySelector('.pricing__plan-name').textContent}" sélectionné !`, {
                    duration: 3000
                });
            }
        }
    }

    /**
     * Animation de sélection de carte moderne
     */
    animateCardSelection(card) {
        // Effet de pulsation avec glow
        card.style.animation = 'modernCardSelection 1s cubic-bezier(0.4, 0, 0.2, 1)';

        // Créer un effet de ring autour de la carte
        this.createSelectionRing(card);

        setTimeout(() => {
            card.style.animation = '';
        }, 1000);
    }

    /**
     * Créer un anneau de sélection
     */
    createSelectionRing(card) {
        const rect = card.getBoundingClientRect();
        const ring = document.createElement('div');

        ring.style.cssText = `
            position: fixed;
            top: ${rect.top - 10}px;
            left: ${rect.left - 10}px;
            width: ${rect.width + 20}px;
            height: ${rect.height + 20}px;
            border: 3px solid transparent;
            border-radius: 24px;
            pointer-events: none;
            z-index: 1000;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6) border-box;
            mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            mask-composite: subtract;
            animation: selectionRing 2s ease-out forwards;
        `;

        document.body.appendChild(ring);

        setTimeout(() => ring.remove(), 2000);
    }

    /**
     * Gestion moderne du toggle de comparaison
     */
    handleModernComparisonToggle() {
        this.comparisonOpen = !this.comparisonOpen;

        // Animation de l'icône avec rotation fluide
        const icon = this.comparisonToggle.querySelector('.pricing__comparison-icon');
        if (icon) {
            icon.style.transform = this.comparisonOpen ? 'rotate(180deg) scale(1.1)' : 'rotate(0deg) scale(1)';
        }

        // Animation du bouton
        this.comparisonToggle.style.transform = 'scale(0.95)';
        setTimeout(() => {
            this.comparisonToggle.style.transform = '';
        }, 150);

        // Effet de particules
        this.createToggleParticles(this.comparisonToggle);

        // Notification moderne
        if (window.notify) {
            const message = this.comparisonOpen ?
                'Tableau de comparaison détaillé - bientôt disponible !' :
                'Comparaison fermée';

            notify.info(message, {
                duration: 3000,
                actions: [{
                    id: 'learn-more',
                    label: 'En savoir plus',
                    handler: () => console.log('Redirect to features page')
                }]
            });
        }
    }

    /**
     * Créer des particules pour le toggle
     */
    createToggleParticles(button) {
        const rect = button.getBoundingClientRect();
        const particles = 6;

        for (let i = 0; i < particles; i++) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                width: 3px;
                height: 3px;
                background: #3b82f6;
                border-radius: 50%;
                pointer-events: none;
                top: ${rect.top + rect.height / 2}px;
                left: ${rect.left + rect.width / 2}px;
                z-index: 1000;
                animation: toggleParticleFloat 1s ease-out forwards;
                --angle: ${(360 / particles) * i}deg;
                --distance: 40px;
            `;

            document.body.appendChild(particle);
        }

        setTimeout(() => {
            document.querySelectorAll('[style*="toggleParticleFloat"]').forEach(p => p.remove());
        }, 1000);
    }

    /**
     * Effet de glow moderne pour les cartes
     */
    addModernCardGlow(card) {
        const isPopular = card.classList.contains('pricing__card--popular');

        if (isPopular) {
            card.style.boxShadow = `
                0 25px 50px rgba(59, 130, 246, 0.3),
                0 0 60px rgba(139, 92, 246, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.1)
            `;
        } else {
            card.style.boxShadow = `
                0 25px 50px rgba(255, 255, 255, 0.05),
                0 0 40px rgba(59, 130, 246, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1)
            `;
        }
    }

    /**
     * Supprimer l'effet de glow
     */
    removeModernCardGlow(card) {
        card.style.boxShadow = '';
    }

    /**
     * Gestion du scroll pour les effets parallax
     */
    handleScroll() {
        if (!this.isVisible) return;

        const scrollY = window.scrollY;
        const pricingRect = this.pricing.getBoundingClientRect();

        // Effet parallax sur les éléments flottants
        this.floatingElements.forEach((element, index) => {
            const speed = 0.5 + (index * 0.1);
            const yPos = -(scrollY * speed);
            element.style.transform = `translateY(${yPos}px) scale(${1 + (index * 0.1)})`;
        });

        // Effet parallax sur les particules
        const particles = document.querySelectorAll('.pricing-particle');
        particles.forEach((particle, index) => {
            const speed = 0.3 + (index * 0.05);
            const yPos = -(scrollY * speed);
            particle.style.transform = `translateY(${yPos}px)`;
        });
    }

    /**
     * Initialisation de l'Intersection Observer
     */
    initIntersectionObserver() {
        const observer = new IntersectionObserver(
            this.handleIntersection.bind(this),
            {
                threshold: this.config.observerThreshold,
                rootMargin: '0px 0px -200px 0px'
            }
        );

        observer.observe(this.pricing);
    }

    /**
     * Gestion de l'intersection moderne
     */
    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting && !this.hasAnimated) {
                this.isVisible = true;
                this.startModernPricingAnimation();
                this.hasAnimated = true;
            } else if (!entry.isIntersecting) {
                this.isVisible = false;
            }
        });
    }

    /**
     * Animation principale moderne
     */
    startModernPricingAnimation() {
        // Animation des cartes avec délai séquentiel
        this.animateModernPricingCards();

        // Animation des métriques
        setTimeout(() => {
            this.animateMetrics();
        }, 1000);
    }

    /**
     * Animation des cartes modernes
     */
    animateModernPricingCards() {
        this.pricingCards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';

                // Animation spéciale pour la carte populaire
                if (card.classList.contains('pricing__card--popular')) {
                    this.animatePopularCard(card);
                }
            }, index * this.config.staggerDelay);
        });
    }

    /**
     * Animation spéciale pour la carte populaire
     */
    animatePopularCard(card) {
        const badge = card.querySelector('.pricing__popular-badge');

        if (badge) {
            setTimeout(() => {
                badge.style.animation = 'modernPopularPulse 2s infinite';
            }, 500);
        }

        // Créer des particules autour de la carte populaire
        setTimeout(() => {
            this.createPopularCardParticles(card);
        }, 800);
    }

    /**
     * Créer des particules pour la carte populaire
     */
    createPopularCardParticles(card) {
        const rect = card.getBoundingClientRect();
        const particles = 15;

        for (let i = 0; i < particles; i++) {
            const particle = document.createElement('div');
            const angle = (360 / particles) * i;
            const distance = 100 + Math.random() * 50;

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
                animation: popularCardParticles 3s ease-out forwards;
                --angle: ${angle}deg;
                --distance: ${distance}px;
                animation-delay: ${Math.random() * 2}s;
            `;

            document.body.appendChild(particle);
        }

        // Nettoyer après animation
        setTimeout(() => {
            document.querySelectorAll('[style*="popularCardParticles"]').forEach(p => p.remove());
        }, 5000);
    }

    /**
     * Animation des métriques avec compteurs
     */
    animateMetrics() {
        this.metrics.forEach((metric, index) => {
            setTimeout(() => {
                const value = metric.querySelector('.pricing__metric-value');
                if (value) {
                    // Effet de compteur pour les chiffres
                    const finalText = value.textContent;
                    const isNumeric = /^\d+/.test(finalText);

                    if (isNumeric) {
                        this.animateCounter(value, finalText);
                    } else {
                        // Animation simple pour les textes
                        value.style.transform = 'scale(1.2)';
                        value.style.color = '#3b82f6';
                        setTimeout(() => {
                            value.style.transform = 'scale(1)';
                            value.style.color = '';
                        }, 300);
                    }
                }
            }, index * 200);
        });
    }

    /**
     * Animation de compteur pour les métriques numériques
     */
    animateCounter(element, finalText) {
        const match = finalText.match(/^(\d+)/);
        if (!match) return;

        const finalNumber = parseInt(match[1]);
        const duration = 1500;
        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = Math.floor(finalNumber * easeOutQuart);

            element.textContent = finalText.replace(/^\d+/, current.toString());

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                // Animation finale
                element.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 200);
            }
        };

        requestAnimationFrame(animate);
    }

    /**
     * Ajout des styles CSS pour les particules et animations
     */
    addParticleStyles() {
        if (document.getElementById('modern-pricing-styles')) return;

        const style = document.createElement('style');
        style.id = 'modern-pricing-styles';
        style.textContent = `
            @keyframes particleFloat {
                0% {
                    transform: translateY(100vh) rotate(0deg);
                    opacity: 0;
                }
                10% {
                    opacity: 1;
                }
                90% {
                    opacity: 1;
                }
                100% {
                    transform: translateY(-100px) rotate(360deg);
                    opacity: 0;
                }
            }

            @keyframes advancedRipple {
                to {
                    transform: translate(var(--x, 0), var(--y, 0)) scale(2);
                    opacity: 0;
                }
            }

            @keyframes hoverParticleFloat {
                to {
                    transform: rotate(var(--angle)) translateX(var(--distance)) scale(0);
                    opacity: 0;
                }
            }

            @keyframes successParticleFloat {
                to {
                    transform: rotate(var(--angle)) translateX(var(--distance)) scale(0);
                    opacity: 0;
                }
            }

            @keyframes toggleParticleFloat {
                to {
                    transform: rotate(var(--angle)) translateX(var(--distance)) scale(0);
                    opacity: 0;
                }
            }

            @keyframes popularCardParticles {
                0% {
                    transform: rotate(var(--angle)) translateX(0) scale(1);
                    opacity: 1;
                }
                100% {
                    transform: rotate(var(--angle)) translateX(var(--distance)) scale(0);
                    opacity: 0;
                }
            }

            @keyframes modernCardSelection {
                0%, 100% {
                    transform: scale(1.05);
                }
                50% {
                    transform: scale(1.08);
                    box-shadow: 0 0 80px rgba(59, 130, 246, 0.6);
                }
            }

            @keyframes selectionRing {
                0% {
                    opacity: 1;
                    transform: scale(1);
                }
                100% {
                    opacity: 0;
                    transform: scale(1.1);
                }
            }

            @keyframes modernPopularPulse {
                0%, 100% {
                    transform: translateX(-50%) scale(1);
                    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
                }
                50% {
                    transform: translateX(-50%) scale(1.05);
                    box-shadow: 0 12px 35px rgba(59, 130, 246, 0.6);
                }
            }

            .pricing__card--selected {
                border-color: #3b82f6 !important;
                background: rgba(59, 130, 246, 0.15) !important;
                transform: translateY(-8px) scale(1.02) !important;
                box-shadow: 0 25px 50px rgba(59, 130, 246, 0.4) !important;
            }

            .pricing__card:focus-visible {
                outline: 3px solid #3b82f6;
                outline-offset: 4px;
            }

            @media (max-width: 768px) {
                .pricing__card:hover {
                    transform: translateY(-4px) !important;
                }
                
                .pricing__card--popular:hover {
                    transform: scale(1.02) translateY(-4px) !important;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .pricing-particle,
                .floating-element {
                    animation: none !important;
                }
                
                .pricing__card {
                    transition: none !important;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Initialisation de la sélection de plan
     */
    initPlanSelection() {
        const urlParams = new URLSearchParams(window.location.search);
        const planParam = urlParams.get('plan');

        if (planParam) {
            setTimeout(() => {
                this.selectPlanWithAnimation(planParam);
            }, 1000);
        }
    }

    /**
     * Amélioration de l'accessibilité
     */
    enhanceAccessibility() {
        this.pricingCards.forEach(card => {
            card.setAttribute('role', 'region');
            card.setAttribute('tabindex', '0');

            const planName = card.querySelector('.pricing__plan-name')?.textContent;
            if (planName) {
                card.setAttribute('aria-label', `Plan ${planName}`);
            }

            // Navigation clavier
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const ctaButton = card.querySelector('.pricing__cta');
                    if (ctaButton) {
                        ctaButton.click();
                    }
                }
            });
        });
    }

    /**
     * Gestion du redimensionnement
     */
    handleResize() {
        // Réajuster les animations pour mobile
        if (window.innerWidth < 768) {
            this.config.staggerDelay = 100;
            this.config.animationDuration = 300;
        } else {
            this.config.staggerDelay = 150;
            this.config.animationDuration = 400;
        }
    }

    /**
     * API publique - Redémarrer les animations
     */
    restartAnimations() {
        this.hasAnimated = false;

        this.pricingCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(60px)';
        });

        setTimeout(() => {
            this.startModernPricingAnimation();
        }, 100);
    }

    /**
     * API publique - Obtenir le plan sélectionné
     */
    getSelectedPlan() {
        return this.selectedPlan;
    }

    /**
     * Nettoyage moderne
     */
    destroy() {
        // Supprimer les événements
        this.pricingCards.forEach(card => {
            card.removeEventListener('mouseenter', this.handleModernCardHover);
            card.removeEventListener('mouseleave', this.handleModernCardLeave);
            card.removeEventListener('mousemove', this.handleCardMouseMove);
        });

        this.ctaButtons.forEach(button => {
            button.removeEventListener('click', this.handleModernCtaClick);
        });

        window.removeEventListener('resize', this.handleResize);
        window.removeEventListener('scroll', this.handleScroll);

        // Nettoyer les particules
        document.querySelectorAll('.pricing-particle').forEach(p => p.remove());

        // Supprimer les styles
        const styles = document.getElementById('modern-pricing-styles');
        if (styles) styles.remove();
    }
}

/**
 * Initialisation automatique
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser le composant moderne
    window.modernPricingComponent = new ModernPricingComponent();

    // Compatibilité avec l'ancien nom
    window.pricingComponent = window.modernPricingComponent;
});

// Export pour utilisation externe
window.ModernPricingComponent = ModernPricingComponent;
