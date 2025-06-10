document.addEventListener('DOMContentLoaded', function() {
    // ===== OBSERVER POUR LES ANIMATIONS AU SCROLL =====
    const observerOptions = {
        threshold: 0.2,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');

                // Animation spéciale pour les cartes d'intégration
                if (entry.target.classList.contains('integration-card')) {
                    animateIntegrationCard(entry.target);
                }

                // Animation spéciale pour les cartes de fonctionnalités avancées
                if (entry.target.classList.contains('advanced-feature-card')) {
                    animateAdvancedFeatureCard(entry.target);
                }
            }
        });
    }, observerOptions);

    // Observer les sections feature
    document.querySelectorAll('.feature-section-v2').forEach(section => {
        section.classList.add('fade-in-up');
        observer.observe(section);
    });

    // Observer les cartes d'intégration
    document.querySelectorAll('.integration-card').forEach(card => {
        observer.observe(card);
    });

    // Observer les cartes de fonctionnalités avancées
    document.querySelectorAll('.advanced-feature-card').forEach(card => {
        observer.observe(card);
    });

    // ===== ANIMATIONS SPÉCIFIQUES =====
    function animateIntegrationCard(card) {
        const icon = card.querySelector('.integration-icon');
        const features = card.querySelectorAll('.integration-features li');

        // Animation de l'icône
        if (icon) {
            icon.style.transform = 'scale(1.1) rotate(5deg)';
            setTimeout(() => {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }, 300);
        }

        // Animation séquentielle des fonctionnalités
        features.forEach((feature, index) => {
            setTimeout(() => {
                feature.style.opacity = '0.7';
                feature.style.transform = 'translateX(10px)';
                setTimeout(() => {
                    feature.style.opacity = '1';
                    feature.style.transform = 'translateX(0)';
                }, 100);
            }, index * 100);
        });
    }

    function animateAdvancedFeatureCard(card) {
        const icon = card.querySelector('.advanced-feature-icon');
        const listItems = card.querySelectorAll('.advanced-feature-list li');

        // Animation de l'icône
        if (icon) {
            icon.style.transform = 'scale(1.2)';
            setTimeout(() => {
                icon.style.transform = 'scale(1)';
            }, 200);
        }

        // Animation des éléments de la liste
        listItems.forEach((item, index) => {
            setTimeout(() => {
                item.style.transform = 'translateY(-5px)';
                setTimeout(() => {
                    item.style.transform = 'translateY(0)';
                }, 150);
            }, index * 50);
        });
    }

    // ===== HOVER EFFECTS AVANCÉS =====

    // Hover sur les cartes d'intégration
    document.querySelectorAll('.integration-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            const icon = this.querySelector('.integration-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(-5deg)';
                icon.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.3)';
            }

            // Effet de brillance
            this.style.background = 'rgba(255, 255, 255, 0.08)';
        });

        card.addEventListener('mouseleave', function() {
            const icon = this.querySelector('.integration-icon');
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0deg)';
                icon.style.boxShadow = '';
            }

            this.style.background = 'rgba(255, 255, 255, 0.05)';
        });
    });

    // Hover sur les cartes de fonctionnalités avancées
    document.querySelectorAll('.advanced-feature-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            const icon = this.querySelector('.advanced-feature-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1)';
                icon.style.boxShadow = '0 4px 15px rgba(59, 130, 246, 0.3)';
            }

            // Animation des checks
            const checks = this.querySelectorAll('.check-advanced');
            checks.forEach((check, index) => {
                setTimeout(() => {
                    check.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        check.style.transform = 'scale(1)';
                    }, 100);
                }, index * 50);
            });
        });

        card.addEventListener('mouseleave', function() {
            const icon = this.querySelector('.advanced-feature-icon');
            if (icon) {
                icon.style.transform = 'scale(1)';
                icon.style.boxShadow = '';
            }
        });
    });

    // ===== ANIMATIONS DES MOCK INTERFACES =====
    document.querySelectorAll('.feature-visual-v2').forEach(visual => {
        visual.addEventListener('mouseenter', function() {
            const lines = this.querySelectorAll('.mock-line-v2');
            lines.forEach((line, index) => {
                setTimeout(() => {
                    line.style.transform = 'translateX(5px)';
                    line.style.backgroundColor = 'rgba(59, 130, 246, 0.05)';
                    line.style.borderRadius = '4px';
                }, index * 100);
            });
        });

        visual.addEventListener('mouseleave', function() {
            const lines = this.querySelectorAll('.mock-line-v2');
            lines.forEach(line => {
                line.style.transform = 'translateX(0)';
                line.style.backgroundColor = '';
                line.style.borderRadius = '';
            });
        });
    });

    // ===== ANIMATION DES ICÔNES DE FONCTIONNALITÉS =====
    document.querySelectorAll('.feature-icon').forEach(icon => {
        // Animation de pulsation aléatoire
        const pulseAnimation = () => {
            icon.style.transform = 'scale(1.05)';
            setTimeout(() => {
                icon.style.transform = 'scale(1)';
            }, 200);
        };

        // Déclencher l'animation aléatoirement
        const randomInterval = Math.random() * 5000 + 3000; // Entre 3 et 8 secondes
        setInterval(pulseAnimation, randomInterval);
    });

    // ===== SCROLL SMOOTH POUR LES ANCRES =====
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
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

    // ===== ANIMATION D'ENTRÉE SÉQUENTIELLE DES CARTES =====
    function animateCardsSequentially(cards, delay = 150) {
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';

            setTimeout(() => {
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * delay);
        });
    }

    // Observer pour la grille d'intégrations
    const integrationsGrid = document.querySelector('.integrations-grid');
    if (integrationsGrid) {
        const integrationsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const cards = entry.target.querySelectorAll('.integration-card');
                    animateCardsSequentially(cards, 100);
                    integrationsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        integrationsObserver.observe(integrationsGrid);
    }

    // Observer pour la grille des fonctionnalités avancées
    const advancedGrid = document.querySelector('.advanced-features-grid');
    if (advancedGrid) {
        const advancedObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const cards = entry.target.querySelectorAll('.advanced-feature-card');
                    animateCardsSequentially(cards, 200);
                    advancedObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        advancedObserver.observe(advancedGrid);
    }

    // ===== EFFET DE TYPING SUR LES HIGHLIGHTS =====
    function typeWriterEffect(element, text, speed = 50) {
        element.textContent = '';
        let i = 0;

        const timer = setInterval(() => {
            if (i < text.length) {
                element.textContent += text.charAt(i);
                i++;
            } else {
                clearInterval(timer);
            }
        }, speed);
    }

    // Observer pour les éléments highlight dans les mock interfaces
    const highlightObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const highlights = entry.target.querySelectorAll('.highlight-v2');
                highlights.forEach((highlight, index) => {
                    const originalText = highlight.textContent;
                    setTimeout(() => {
                        typeWriterEffect(highlight, originalText, 30);
                    }, index * 500);
                });
                highlightObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.mock-interface-v2').forEach(mockInterface => {
        highlightObserver.observe(mockInterface);
    });

    // ===== PERFORMANCE OPTIMIZATIONS =====

    // Throttle pour les événements de scroll
    let ticking = false;
    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(() => {
                // Optimisations scroll si nécessaire
                ticking = false;
            });
            ticking = true;
        }
    }

    window.addEventListener('scroll', requestTick);

    // ===== ACCESSIBILITÉ =====

    // Réduire les animations si l'utilisateur préfère
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (prefersReducedMotion.matches) {
        // Désactiver les animations complexes
        document.querySelectorAll('.integration-card, .advanced-feature-card, .feature-icon').forEach(element => {
            element.style.transition = 'none';
        });
    }

    // Navigation au clavier améliorée
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            // Améliorer la visibilité du focus
            const focusedElement = document.activeElement;
            if (focusedElement.classList.contains('integration-card') ||
                focusedElement.classList.contains('advanced-feature-card')) {
                focusedElement.style.outline = '2px solid #3b82f6';
                focusedElement.style.outlineOffset = '4px';
            }
        }
    });

    // ===== STYLES CSS DYNAMIQUES =====
    const style = document.createElement('style');
    style.textContent = `
        .integration-card,
        .advanced-feature-card,
        .feature-icon,
        .mock-line-v2,
        .check-advanced {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fade-in-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .fade-in-up {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media (prefers-reduced-motion: reduce) {
            .integration-card,
            .advanced-feature-card,
            .feature-icon,
            .fade-in-up {
                transition: none !important;
                animation: none !important;
            }
        }
    `;
    document.head.appendChild(style);
});
