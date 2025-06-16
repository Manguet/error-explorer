class AboutPage {
    constructor() {
        this.init();
    }

    init() {
        this.initCounters();
        this.initScrollAnimations();
        this.initIntersectionObserver();
    }

    /**
     * Animation des compteurs
     */
    initCounters() {
        const counters = document.querySelectorAll('.js-counter');

        counters.forEach(counter => {
            this.animateCounter(counter);
        });
    }

    /**
     * Anime un compteur spécifique
     * @param {HTMLElement} counter - L'élément compteur
     */
    animateCounter(counter) {
        const target = parseInt(counter.getAttribute('data-count'));
        const duration = 2000; // 2 secondes
        const increment = target / (duration / 16); // 60fps
        let current = 0;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }

            // Formatage du nombre avec séparateurs
            counter.textContent = Math.floor(current).toLocaleString('fr-FR');
        }, 16);
    }

    /**
     * Animations au scroll
     */
    initScrollAnimations() {
        // Animation des éléments au scroll
        const animatedElements = document.querySelectorAll('[data-animate]');

        animatedElements.forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
        });
    }

    /**
     * Intersection Observer pour les animations
     */
    initIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.handleIntersection(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Observer les compteurs
        const counters = document.querySelectorAll('.js-counter');
        counters.forEach(counter => {
            observer.observe(counter.closest('.about-hero__stat') || counter.closest('.about-metrics__item'));
        });

        // Observer les sections animées
        const animatedSections = document.querySelectorAll('.about-mission, .about-values, .about-metrics, .about-cta');
        animatedSections.forEach(section => {
            observer.observe(section);
        });

        // Observer les éléments individuels
        const animatedElements = document.querySelectorAll('[data-animate]');
        animatedElements.forEach(element => {
            observer.observe(element);
        });
    }

    /**
     * Gère l'intersection d'un élément
     * @param {HTMLElement} target - L'élément intersecté
     */
    handleIntersection(target) {
        // Animation des compteurs
        const counters = target.querySelectorAll('.js-counter');
        counters.forEach(counter => {
            if (!counter.hasAttribute('data-animated')) {
                counter.setAttribute('data-animated', 'true');
                this.animateCounter(counter);
            }
        });

        // Animation des sections
        if (target.classList.contains('about-mission') ||
            target.classList.contains('about-values') ||
            target.classList.contains('about-metrics') ||
            target.classList.contains('about-cta')) {

            target.style.opacity = '1';
            target.style.transform = 'translateY(0)';
            target.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
        }

        // Animation des éléments individuels
        if (target.hasAttribute('data-animate')) {
            const delay = target.getAttribute('data-delay') || 0;

            setTimeout(() => {
                target.style.opacity = '1';
                target.style.transform = 'translateY(0)';
                target.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            }, delay);
        }
    }

    /**
     * Utilitaire pour formater les nombres
     * @param {number} num - Le nombre à formater
     * @returns {string} - Le nombre formaté
     */
    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    /**
     * Ajoute des effets de parallax légers
     */
    initParallax() {
        const parallaxElements = document.querySelectorAll('.about-hero__gradient-1, .about-hero__gradient-2');

        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;

            parallaxElements.forEach((element, index) => {
                const speed = index === 0 ? 0.3 : 0.5;
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    }

    /**
     * Gestion du thème sombre/clair (si nécessaire)
     */
    initThemeSupport() {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

        // Écouter les changements de thème
        prefersDark.addEventListener('change', (e) => {
            if (e.matches) {
                document.body.classList.add('dark-theme');
            } else {
                document.body.classList.remove('dark-theme');
            }
        });
    }

    /**
     * Optimisation des performances
     */
    initPerformanceOptimizations() {
        // Lazy loading des images de fond
        const backgroundElements = document.querySelectorAll('[data-bg]');

        const bgObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bgImage = entry.target.getAttribute('data-bg');
                    if (bgImage) {
                        entry.target.style.backgroundImage = `url(${bgImage})`;
                        entry.target.removeAttribute('data-bg');
                    }
                    bgObserver.unobserve(entry.target);
                }
            });
        });

        backgroundElements.forEach(element => {
            bgObserver.observe(element);
        });
    }

    /**
     * Gestion des erreurs
     */
    handleError(error, context = '') {
        console.warn(`[AboutPage${context}] Error:`, error);

        // En production, vous pourriez vouloir envoyer ces erreurs à votre service de monitoring
        if (window.ErrorExplorer && typeof window.ErrorExplorer.captureException === 'function') {
            window.ErrorExplorer.captureException(error, {
                context: `AboutPage${context}`,
                page: 'about'
            });
        }
    }
}

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    try {
        new AboutPage();
    } catch (error) {
        console.error('Erreur lors de l\'initialisation de la page à propos:', error);
    }
});

// Support pour les navigateurs plus anciens
if (!window.IntersectionObserver) {
    // Fallback simple pour les navigateurs sans IntersectionObserver
    document.addEventListener('DOMContentLoaded', () => {
        const counters = document.querySelectorAll('.js-counter');
        counters.forEach(counter => {
            const aboutPage = new AboutPage();
            aboutPage.animateCounter(counter);
        });
    });
}

// Export pour utilisation en module (si nécessaire)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AboutPage;
}
