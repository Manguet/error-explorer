/**
 * Stats Component - BEM Architecture
 * Gestion des animations de compteurs et des interactions
 */
class StatsComponent {
    constructor() {
        // Éléments DOM
        this.stats = document.querySelector('.stats');
        this.statsItems = document.querySelectorAll('.stats__item');
        this.counters = document.querySelectorAll('.stats__number[data-count]');
        this.trends = document.querySelectorAll('.stats__trend');

        // Configuration
        this.config = {
            observerThreshold: 0.3,
            countDuration: 2000,
            staggerDelay: 150,
            easing: 'easeOutQuart'
        };

        // État
        this.hasAnimated = false;
        this.isVisible = false;
        this.activeCounters = new Map();

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.stats) return;

        this.bindEvents();
        this.initIntersectionObserver();
        this.enhanceAccessibility();
        this.initRealTimeUpdates();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Effets hover sur les stats items
        this.statsItems.forEach(item => {
            item.addEventListener('mouseenter', this.handleItemHover.bind(this));
            item.addEventListener('mouseleave', this.handleItemLeave.bind(this));
        });

        // Effets sur les indicateurs de tendance
        this.trends.forEach(trend => {
            trend.addEventListener('mouseenter', this.handleTrendHover.bind(this));
            trend.addEventListener('mouseleave', this.handleTrendLeave.bind(this));
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
        const observer = new IntersectionObserver(
            this.handleIntersection.bind(this),
            {
                threshold: this.config.observerThreshold,
                rootMargin: '0px 0px -100px 0px'
            }
        );

        observer.observe(this.stats);
    }

    /**
     * Gestion de l'intersection (apparition des stats)
     */
    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting && !this.hasAnimated) {
                this.isVisible = true;
                this.startStatsAnimation();
                this.hasAnimated = true;
            } else if (!entry.isIntersecting) {
                this.isVisible = false;
            }
        });
    }

    /**
     * Animation principale des statistiques
     */
    startStatsAnimation() {
        // Animation d'apparition des items avec délai séquentiel
        this.animateStatsItems();

        // Démarrer les animations de comptage
        setTimeout(() => {
            this.startCounterAnimations();
        }, 500);

        // Animer les indicateurs de tendance
        setTimeout(() => {
            this.animateTrendIndicators();
        }, 1000);
    }

    /**
     * Animation d'apparition des items de statistiques
     */
    animateStatsItems() {
        this.statsItems.forEach((item, index) => {
            setTimeout(() => {
                item.classList.add('fade-in-up');
            }, index * this.config.staggerDelay);
        });
    }

    /**
     * Animation des compteurs
     */
    startCounterAnimations() {
        this.counters.forEach((counter, index) => {
            setTimeout(() => {
                this.animateCounter(counter);
            }, index * 200);
        });
    }

    /**
     * Animation d'un compteur spécifique
     */
    animateCounter(counter) {
        const target = parseInt(counter.getAttribute('data-count'));
        const startTime = performance.now();
        const duration = this.config.countDuration;

        // Ajouter la classe d'animation
        counter.classList.add('counting');

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Fonction d'easing
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = Math.floor(target * easeOutQuart);

            // Mettre à jour le contenu
            counter.textContent = this.formatNumber(current, target);

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                // Animation terminée
                counter.classList.remove('counting');
                this.onCounterComplete(counter, target);
            }
        };

        requestAnimationFrame(animate);
        this.activeCounters.set(counter, { animate, startTime });
    }

    /**
     * Formatage des nombres
     */
    formatNumber(current, target) {
        if (target >= 1000000) {
            return (current / 1000000).toFixed(1) + 'M';
        } else if (target >= 1000) {
            return (current / 1000).toFixed(1) + 'K';
        }
        return current.toLocaleString();
    }

    /**
     * Callback à la fin de l'animation d'un compteur
     */
    onCounterComplete(counter, finalValue) {
        // Effect de "pop" final
        counter.style.transform = 'scale(1.1)';
        setTimeout(() => {
            counter.style.transform = 'scale(1)';
        }, 200);

        // Effet de particules (optionnel)
        this.createCounterParticles(counter);

        // Nettoyage
        this.activeCounters.delete(counter);
    }

    /**
     * Animation des indicateurs de tendance
     */
    animateTrendIndicators() {
        this.trends.forEach((trend, index) => {
            setTimeout(() => {
                trend.style.opacity = '0';
                trend.style.transform = 'scale(0.8) translateY(10px)';

                setTimeout(() => {
                    trend.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                    trend.style.opacity = '1';
                    trend.style.transform = 'scale(1) translateY(0)';
                }, 50);
            }, index * 100);
        });
    }

    /**
     * Gestion du hover sur les items de stats
     */
    handleItemHover(e) {
        const item = e.currentTarget;
        const icon = item.querySelector('.stats__item-icon');
        const number = item.querySelector('.stats__number');

        // Animation de l'icône
        if (icon) {
            icon.style.transform = 'scale(1.1) rotate(5deg)';
        }

        // Mise en évidence du nombre
        if (number) {
            number.style.transform = 'scale(1.05)';
            number.style.color = this.getItemAccentColor(item);
        }

        // Effet de lueur
        this.addGlowEffect(item);
    }

    /**
     * Gestion de la fin du hover sur les items
     */
    handleItemLeave(e) {
        const item = e.currentTarget;
        const icon = item.querySelector('.stats__item-icon');
        const number = item.querySelector('.stats__number');

        // Remettre l'icône en position normale
        if (icon) {
            icon.style.transform = '';
        }

        // Remettre le nombre normal
        if (number) {
            number.style.transform = '';
            number.style.color = '';
        }

        // Supprimer l'effet de lueur
        this.removeGlowEffect(item);
    }

    /**
     * Obtenir la couleur d'accent d'un item
     */
    getItemAccentColor(item) {
        if (item.classList.contains('stats__item--primary')) return '#3b82f6';
        if (item.classList.contains('stats__item--success')) return '#10b981';
        if (item.classList.contains('stats__item--info')) return '#60a5fa';
        if (item.classList.contains('stats__item--warning')) return '#f59e0b';
        return '#ffffff';
    }

    /**
     * Ajouter un effet de lueur
     */
    addGlowEffect(item) {
        const color = this.getItemAccentColor(item);
        item.style.boxShadow = `0 20px 40px ${color}20, 0 0 0 1px ${color}30`;
    }

    /**
     * Supprimer l'effet de lueur
     */
    removeGlowEffect(item) {
        item.style.boxShadow = '';
    }

    /**
     * Gestion du hover sur les indicateurs de tendance
     */
    handleTrendHover(e) {
        const trend = e.currentTarget;
        const icon = trend.querySelector('.stats__trend-icon');

        trend.style.transform = 'scale(1.05)';

        if (icon) {
            icon.style.transform = 'rotate(10deg)';
        }
    }

    /**
     * Gestion de la fin du hover sur les tendances
     */
    handleTrendLeave(e) {
        const trend = e.currentTarget;
        const icon = trend.querySelector('.stats__trend-icon');

        trend.style.transform = '';

        if (icon) {
            icon.style.transform = '';
        }
    }

    /**
     * Création de particules pour les compteurs
     */
    createCounterParticles(counter) {
        const particles = [];
        const particleCount = 6;
        const rect = counter.getBoundingClientRect();

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'stats__counter-particle';

            const angle = (360 / particleCount) * i;
            const distance = 20 + Math.random() * 15;

            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: ${this.getItemAccentColor(counter.closest('.stats__item'))};
                border-radius: 50%;
                pointer-events: none;
                top: ${rect.top + rect.height / 2}px;
                left: ${rect.left + rect.width / 2}px;
                z-index: 1000;
                animation: particleExplosion 0.8s ease-out forwards;
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
        }, 800);
    }

    /**
     * Initialisation des mises à jour en temps réel
     */
    initRealTimeUpdates() {
        // Simulation de mises à jour (en production, utiliser WebSockets ou SSE)
        if (this.shouldEnableRealTime()) {
            setInterval(() => {
                this.updateRealTimeData();
            }, 30000); // Mise à jour toutes les 30 secondes
        }
    }

    /**
     * Vérifier si les mises à jour en temps réel doivent être activées
     */
    shouldEnableRealTime() {
        return document.querySelector('.stats__live-badge') !== null;
    }

    /**
     * Mise à jour des données en temps réel
     */
    updateRealTimeData() {
        if (!this.isVisible) return;

        // Simuler de nouvelles données (remplacer par un appel API réel)
        this.counters.forEach(counter => {
            const currentValue = parseInt(counter.getAttribute('data-count'));
            const variation = Math.floor(Math.random() * 10) - 5; // ±5
            const newValue = Math.max(0, currentValue + variation);

            if (newValue !== currentValue) {
                counter.setAttribute('data-count', newValue);
                this.animateValueChange(counter, currentValue, newValue);
            }
        });
    }

    /**
     * Animation du changement de valeur
     */
    animateValueChange(counter, oldValue, newValue) {
        // Animation subtile de changement
        counter.style.transform = 'scale(1.05)';
        counter.style.color = newValue > oldValue ? '#10b981' : '#ef4444';

        setTimeout(() => {
            counter.textContent = this.formatNumber(newValue, newValue);

            setTimeout(() => {
                counter.style.transform = '';
                counter.style.color = '';
            }, 300);
        }, 150);
    }

    /**
     * Gestion du redimensionnement
     */
    handleResize() {
        // Réajuster les animations si nécessaire
        if (window.innerWidth < 768) {
            this.adaptToMobile();
        }
    }

    /**
     * Adaptation pour mobile
     */
    adaptToMobile() {
        // Réduire la durée des animations sur mobile
        this.config.countDuration = 1500;
        this.config.staggerDelay = 100;
    }

    /**
     * Gestion de la visibilité de la page
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // Pause les compteurs actifs
            this.pauseActiveCounters();
        } else {
            // Reprendre les compteurs
            this.resumeActiveCounters();
        }
    }

    /**
     * Pause des compteurs actifs
     */
    pauseActiveCounters() {
        this.activeCounters.forEach((data, counter) => {
            data.paused = true;
            data.pauseTime = performance.now();
        });
    }

    /**
     * Reprise des compteurs
     */
    resumeActiveCounters() {
        this.activeCounters.forEach((data, counter) => {
            if (data.paused) {
                const pauseDuration = performance.now() - data.pauseTime;
                data.startTime += pauseDuration;
                data.paused = false;
            }
        });
    }

    /**
     * Amélioration de l'accessibilité
     */
    enhanceAccessibility() {
        // Ajouter des labels ARIA pour les compteurs
        this.counters.forEach(counter => {
            const item = counter.closest('.stats__item');
            const title = item?.querySelector('.stats__item-title')?.textContent;
            const description = item?.querySelector('.stats__item-description')?.textContent;

            if (title && description) {
                counter.setAttribute('aria-label', `${title}: ${description}`);
            }
        });

        // Ajouter un rôle aux items statistiques
        this.statsItems.forEach(item => {
            item.setAttribute('role', 'region');
            item.setAttribute('tabindex', '0');
        });

        // Support de la navigation clavier
        this.statsItems.forEach(item => {
            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.handleItemActivation(item);
                }
            });
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

        // Annoncer la statistique aux lecteurs d'écran
        const title = item.querySelector('.stats__item-title')?.textContent;
        const number = item.querySelector('.stats__number')?.textContent;
        const description = item.querySelector('.stats__item-description')?.textContent;

        if (title && number && description) {
            const announcement = `${title}: ${number}. ${description}`;
            this.announceToScreenReader(announcement);
        }
    }

    /**
     * Annoncer aux lecteurs d'écran
     */
    announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', true);
        announcement.style.cssText = 'position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;';
        announcement.textContent = message;

        document.body.appendChild(announcement);

        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    }

    /**
     * API publique - Redémarrer les animations
     */
    restartAnimations() {
        this.hasAnimated = false;
        this.startStatsAnimation();
    }

    /**
     * API publique - Mettre à jour une statistique
     */
    updateStat(index, newValue) {
        const counter = this.counters[index];
        if (counter) {
            const oldValue = parseInt(counter.getAttribute('data-count'));
            counter.setAttribute('data-count', newValue);
            this.animateValueChange(counter, oldValue, newValue);
        }
    }

    /**
     * API publique - Obtenir les valeurs actuelles
     */
    getCurrentValues() {
        const values = {};
        this.counters.forEach((counter, index) => {
            const title = counter.closest('.stats__item')
                ?.querySelector('.stats__item-title')?.textContent;
            const value = parseInt(counter.getAttribute('data-count'));
            if (title) {
                values[title] = value;
            }
        });
        return values;
    }

    /**
     * Nettoyage
     */
    destroy() {
        // Supprimer les événements
        this.statsItems.forEach(item => {
            item.removeEventListener('mouseenter', this.handleItemHover);
            item.removeEventListener('mouseleave', this.handleItemLeave);
        });

        this.trends.forEach(trend => {
            trend.removeEventListener('mouseenter', this.handleTrendHover);
            trend.removeEventListener('mouseleave', this.handleTrendLeave);
        });

        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);

        // Nettoyer les compteurs actifs
        this.activeCounters.clear();
    }
}

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser le composant Stats
    window.statsComponent = new StatsComponent();

    // Ajouter les styles CSS manquants pour les animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes particleExplosion {
            to {
                transform: rotate(var(--angle)) translateX(var(--distance)) scale(0);
                opacity: 0;
            }
        }

        .stats__counter-particle {
            animation-timing-function: ease-out;
        }

        .stats__item:focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 4px;
            border-radius: 16px;
        }

        .stats__trend:focus-visible {
            outline: 2px solid currentColor;
            outline-offset: 2px;
            border-radius: 20px;
        }
    `;
    document.head.appendChild(style);
});

// Export pour utilisation externe
window.StatsComponent = StatsComponent;
