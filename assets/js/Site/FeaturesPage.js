class FeaturesPage {
    constructor() {
        this.init();
    }

    init() {
        this.setupObservers();
        this.setupAnimations();
        this.setupInteractions();
        this.setupAccessibility();
    }

    // ===== INTERSECTION OBSERVERS =====
    setupObservers() {
        // Observer pour les animations d'entrée
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const fadeInObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-up');

                    // Animation spéciale pour les éléments avec délai
                    this.animateWithDelay(entry.target);
                }
            });
        }, observerOptions);

        // Observer les éléments à animer
        document.querySelectorAll('.features-core__item').forEach(item => {
            fadeInObserver.observe(item);
        });

        document.querySelectorAll('.features-integrations__card').forEach(card => {
            fadeInObserver.observe(card);
        });

        // Observer pour les métriques du hero
        const heroMetrics = document.querySelectorAll('.features-hero__metric-value');
        if (heroMetrics.length > 0) {
            const metricsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animateMetric(entry.target);
                        metricsObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            heroMetrics.forEach(metric => metricsObserver.observe(metric));
        }
    }

    // ===== ANIMATIONS =====
    setupAnimations() {
        // Animation des lignes de mock interface
        this.animateMockLines();

        // Animation des icônes
        this.animateIcons();

        // Animation des cartes d'intégration
        this.animateIntegrationCards();
    }

    animateWithDelay(element) {
        // Animation séquentielle des bénéfices
        const benefits = element.querySelectorAll('.features-core__benefit');
        benefits.forEach((benefit, index) => {
            setTimeout(() => {
                benefit.style.opacity = '1';
                benefit.style.transform = 'translateX(0)';
            }, (index + 1) * 200);
        });

        // Animation séquentielle des lignes de mock
        const mockLines = element.querySelectorAll('.features-core__mock-line');
        mockLines.forEach((line) => {
            const delay = parseInt(line.dataset.delay) || 0;
            setTimeout(() => {
                line.style.opacity = '1';
                line.style.transform = 'translateX(0)';
            }, delay);
        });
    }

    animateMetric(element) {
        const originalValue = element.textContent;
        const numericValue = parseFloat(originalValue.replace(/[^\d.]/g, ''));

        if (isNaN(numericValue)) return;

        let currentValue = 0;
        const increment = numericValue / 30;
        const duration = 1500;
        const stepTime = duration / 30;

        const counter = setInterval(() => {
            currentValue += increment;

            if (currentValue >= numericValue) {
                currentValue = numericValue;
                clearInterval(counter);
            }

            // Formater selon le type de métrique
            if (originalValue.includes('%')) {
                element.textContent = `${currentValue.toFixed(1)}%`;
            } else if (originalValue.includes('h')) {
                element.textContent = `${currentValue.toFixed(1)}h`;
            } else {
                element.textContent = Math.floor(currentValue).toString();
            }
        }, stepTime);
    }

    animateMockLines() {
        document.querySelectorAll('.features-core__mock-interface').forEach(mockInterface => {
            const lines = mockInterface.querySelectorAll('.features-core__mock-line');

            mockInterface.addEventListener('mouseenter', () => {
                lines.forEach((line, index) => {
                    setTimeout(() => {
                        line.style.background = 'rgba(59, 130, 246, 0.1)';
                        line.style.borderLeft = '3px solid #3b82f6';
                        line.style.transform = 'translateX(8px)';
                    }, index * 100);
                });
            });

            mockInterface.addEventListener('mouseleave', () => {
                lines.forEach(line => {
                    line.style.background = '';
                    line.style.borderLeft = '';
                    line.style.transform = 'translateX(0)';
                });
            });
        });
    }

    animateIcons() {
        // Animation aléatoire des icônes
        document.querySelectorAll('.features-core__icon, .features-integrations__icon').forEach(icon => {
            const randomDelay = Math.random() * 5000 + 3000;

            setInterval(() => {
                icon.style.transform = 'scale(1.1) rotate(5deg)';
                setTimeout(() => {
                    icon.style.transform = 'scale(1) rotate(0deg)';
                }, 200);
            }, randomDelay);
        });
    }

    animateIntegrationCards() {
        document.querySelectorAll('.features-integrations__card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                const icon = card.querySelector('.features-integrations__icon');
                const features = card.querySelectorAll('.features-integrations__features li');

                // Animation de l'icône
                if (icon) {
                    icon.style.transform = 'scale(1.1) rotate(-5deg)';
                }

                // Animation séquentielle des fonctionnalités
                features.forEach((feature, index) => {
                    setTimeout(() => {
                        feature.style.transform = 'translateX(8px)';
                        feature.style.color = '#ffffff';
                    }, index * 50);
                });
            });

            card.addEventListener('mouseleave', () => {
                const icon = card.querySelector('.features-integrations__icon');
                const features = card.querySelectorAll('.features-integrations__features li');

                if (icon) {
                    icon.style.transform = 'scale(1) rotate(0deg)';
                }

                features.forEach(feature => {
                    feature.style.transform = 'translateX(0)';
                    feature.style.color = '';
                });
            });
        });
    }

    // ===== INTERACTIONS =====
    setupInteractions() {
        // Smooth scroll pour les ancres
        this.setupSmoothScroll();

        // Parallax effects
        this.setupParallax();

        // Hover effects avancés
        this.setupHoverEffects();
    }

    setupSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.querySelector(anchor.getAttribute('href'));

                if (target) {
                    const headerHeight = document.querySelector('.header')?.offsetHeight || 80;
                    const targetPosition = target.offsetTop - headerHeight - 20;

                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    setupParallax() {
        let ticking = false;

        const updateParallax = () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;

            // Parallax pour les éléments de background
            document.querySelectorAll('.features-hero__background-particles').forEach(element => {
                element.style.transform = `translateY(${rate}px)`;
            });

            document.querySelectorAll('.features-core__background-pattern').forEach(element => {
                element.style.transform = `translateY(${rate * 0.3}px)`;
            });

            ticking = false;
        };

        const requestTick = () => {
            if (!ticking) {
                requestAnimationFrame(updateParallax);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestTick);
    }

    setupHoverEffects() {
        // Effet de glow sur les boutons CTA
        document.querySelectorAll('.features-hero__cta, .features-cta__button').forEach(button => {
            button.addEventListener('mouseenter', () => {
                button.style.boxShadow = '0 15px 35px rgba(59, 130, 246, 0.6), 0 0 20px rgba(59, 130, 246, 0.3)';
            });

            button.addEventListener('mouseleave', () => {
                button.style.boxShadow = '';
            });
        });

        // Effet de highlight sur les métriques
        document.querySelectorAll('.features-hero__metric').forEach(metric => {
            metric.addEventListener('mouseenter', () => {
                metric.style.background = 'rgba(139, 92, 246, 0.1)';
                metric.style.borderColor = 'rgba(139, 92, 246, 0.3)';
            });

            metric.addEventListener('mouseleave', () => {
                metric.style.background = '';
                metric.style.borderColor = '';
            });
        });
    }

    // ===== ACCESSIBILITÉ =====
    setupAccessibility() {
        // Améliorer la navigation au clavier
        this.setupKeyboardNavigation();

        // Respecter les préférences de mouvement
        this.respectMotionPreferences();

        // Améliorer les focus states
        this.setupFocusStates();
    }

    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                // Améliorer la visibilité du focus
                const focusedElement = document.activeElement;

                if (focusedElement.matches('.features-core__item, .features-integrations__card')) {
                    focusedElement.style.outline = '2px solid #3b82f6';
                    focusedElement.style.outlineOffset = '4px';
                }
            }
        });

        // Enlever l'outline personnalisé au clic
        document.addEventListener('click', (e) => {
            if (e.target.matches('.features-core__item, .features-integrations__card')) {
                e.target.style.outline = '';
                e.target.style.outlineOffset = '';
            }
        });
    }

    respectMotionPreferences() {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        if (prefersReducedMotion.matches) {
            // Désactiver les animations complexes
            const style = document.createElement('style');
            style.textContent = `
        * {
          animation-duration: 0.01ms !important;
          animation-iteration-count: 1 !important;
          transition-duration: 0.01ms !important;
        }
      `;
            document.head.appendChild(style);
        }
    }

    setupFocusStates() {
        // Améliorer les focus states pour les éléments interactifs
        const focusableElements = document.querySelectorAll(`
      .features-hero__cta,
      .features-cta__button,
      .features-core__item,
      .features-integrations__card
    `);

        focusableElements.forEach(element => {
            element.addEventListener('focus', () => {
                element.style.outline = '2px solid #3b82f6';
                element.style.outlineOffset = '2px';
            });

            element.addEventListener('blur', () => {
                element.style.outline = '';
                element.style.outlineOffset = '';
            });
        });
    }

    // ===== UTILITAIRES =====
    static debounce(func, wait) {
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

    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// ===== INITIALISATION =====
document.addEventListener('DOMContentLoaded', () => {
    new FeaturesPage();
});

// ===== ANIMATIONS CSS DYNAMIQUES =====
const dynamicStyles = `
  .features-core__item.fade-in-up {
    opacity: 1;
    transform: translateY(0);
    transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .features-integrations__card.fade-in-up {
    opacity: 1;
    transform: translateY(0);
    transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .features-core__benefit {
    opacity: 0;
    transform: translateX(-20px);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .features-core__mock-line {
    opacity: 0;
    transform: translateX(-20px);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .features-integrations__features li {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .features-hero__metric {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .features-core__icon,
  .features-integrations__icon {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  @media (prefers-reduced-motion: reduce) {
    * {
      animation: none !important;
      transition: none !important;
    }
  }
`;

// Injecter les styles dynamiques
const styleElement = document.createElement('style');
styleElement.textContent = dynamicStyles;
document.head.appendChild(styleElement);

// ===== EXPORT POUR TESTS =====
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FeaturesPage;
}
