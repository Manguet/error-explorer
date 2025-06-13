/**
 * Header Component - BEM Architecture
 * Gestion du header avec scroll, menu mobile et animations
 */
class HeaderComponent {
    constructor() {
        // Éléments DOM
        this.header = document.querySelector('.header');
        this.mobileToggle = document.querySelector('.header__mobile-toggle');
        this.mobileMenu = document.querySelector('.header__mobile-menu');
        this.menuLinks = document.querySelectorAll('.header__menu-link, .header__mobile-nav-link');

        // État
        this.isMenuOpen = false;
        this.lastScrollY = window.scrollY;
        this.scrollThreshold = 50;

        // Configuration
        this.config = {
            scrolledClass: 'header--scrolled',
            mobileOpenClass: 'header--mobile-open',
            toggleActiveClass: 'header__mobile-toggle--active',
            linkActiveClass: 'header__menu-link--active',
            animatedClass: 'header--animated'
        };

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.header) return;

        this.bindEvents();
        this.setActiveLink();
        this.animateEntrance();
        this.addPassiveScrollListener();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Menu mobile
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', this.handleMobileToggle.bind(this));
        }

        if (this.mobileMenu) {
            this.mobileMenu.addEventListener('click', this.handleMobileMenuClick.bind(this));
        }

        document.addEventListener('keydown', this.handleKeyDown.bind(this));
        document.addEventListener('click', this.handleOutsideClick.bind(this));
        window.addEventListener('resize', this.handleResize.bind(this));
    }

    /**
     * Gestion du scroll avec performance optimisée
     */
    addPassiveScrollListener() {
        let ticking = false;

        const scrollHandler = () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.handleScroll();
                    ticking = false;
                });
                ticking = true;
            }
        };

        window.addEventListener('scroll', scrollHandler, { passive: true });
    }

    /**
     * Gestion du scroll
     */
    handleScroll() {
        const currentScrollY = window.scrollY;

        // Ajouter/supprimer la classe scrolled
        if (currentScrollY > this.scrollThreshold) {
            this.header.classList.add(this.config.scrolledClass);
        } else {
            this.header.classList.remove(this.config.scrolledClass);
        }

        // Fermer le menu mobile au scroll
        if (this.isMenuOpen && Math.abs(currentScrollY - this.lastScrollY) > 50) {
            this.closeMobileMenu();
        }

        this.lastScrollY = currentScrollY;
    }

    /**
     * Toggle du menu mobile
     */
    handleMobileToggle(e) {
        e.preventDefault();
        e.stopPropagation();

        if (this.isMenuOpen) {
            this.closeMobileMenu();
        } else {
            this.openMobileMenu();
        }
    }

    /**
     * Ouverture du menu mobile
     */
    openMobileMenu() {
        this.isMenuOpen = true;

        // Classes CSS
        this.header.classList.add(this.config.mobileOpenClass);
        this.mobileToggle.classList.add(this.config.toggleActiveClass);

        // Affichage et animation
        this.mobileMenu.style.display = 'block';

        // Force reflow avant animation
        this.mobileMenu.offsetHeight;

        requestAnimationFrame(() => {
            this.mobileMenu.classList.add('header__mobile-menu--open');
        });

        // Désactiver le scroll du body
        document.body.style.overflow = 'hidden';

        // Focus trap pour accessibilité
        this.setupFocusTrap();

        // Événement personnalisé
        this.dispatchEvent('mobileMenuOpen');
    }

    /**
     * Fermeture du menu mobile
     */
    closeMobileMenu() {
        this.isMenuOpen = false;

        // Classes CSS
        this.header.classList.remove(this.config.mobileOpenClass);
        this.mobileToggle.classList.remove(this.config.toggleActiveClass);
        this.mobileMenu.classList.remove('header__mobile-menu--open');

        // Animation de fermeture
        setTimeout(() => {
            if (!this.isMenuOpen) {
                this.mobileMenu.style.display = 'none';
            }
        }, 300);

        // Réactiver le scroll du body
        document.body.style.overflow = '';

        // Supprimer le focus trap
        this.removeFocusTrap();

        // Événement personnalisé
        this.dispatchEvent('mobileMenuClose');
    }

    /**
     * Gestion des clics dans le menu mobile
     */
    handleMobileMenuClick(e) {
        // Fermer le menu si on clique sur un lien
        if (e.target.matches('.header__mobile-nav-link, .header__mobile-button')) {
            this.closeMobileMenu();
        }
    }

    /**
     * Gestion des touches clavier
     */
    handleKeyDown(e) {
        switch (e.key) {
            case 'Escape':
                if (this.isMenuOpen) {
                    this.closeMobileMenu();
                }
                break;
        }
    }

    /**
     * Gestion des clics extérieurs
     */
    handleOutsideClick(e) {
        if (this.isMenuOpen && !this.header.contains(e.target)) {
            this.closeMobileMenu();
        }
    }

    /**
     * Gestion du redimensionnement
     */
    handleResize() {
        // Fermer le menu mobile si on passe en desktop
        if (window.innerWidth > 1024 && this.isMenuOpen) {
            this.closeMobileMenu();
        }
    }

    /**
     * Définir le lien actif basé sur l'URL
     */
    setActiveLink() {
        const currentPath = window.location.pathname;

        this.menuLinks.forEach(link => {
            link.classList.remove(this.config.linkActiveClass, 'header__mobile-nav-link--active');

            const linkPath = new URL(link.href).pathname;

            if (linkPath === currentPath || (currentPath === '/' && linkPath === '/')) {
                if (link.classList.contains('header__menu-link')) {
                    link.classList.add(this.config.linkActiveClass);
                } else if (link.classList.contains('header__mobile-nav-link')) {
                    link.classList.add('header__mobile-nav-link--active');
                }
            }
        });
    }

    /**
     * Animation d'entrée du header
     */
    animateEntrance() {
        this.header.style.opacity = '0';
        this.header.style.transform = 'translateY(-100%)';

        setTimeout(() => {
            this.header.classList.add(this.config.animatedClass);
            this.header.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            this.header.style.opacity = '1';
            this.header.style.transform = 'translateY(0)';
        }, 100);
    }

    /**
     * Focus trap pour le menu mobile (accessibilité)
     */
    setupFocusTrap() {
        const focusableElements = this.mobileMenu.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );

        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        this.focusTrapHandler = (e) => {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        };

        document.addEventListener('keydown', this.focusTrapHandler);
        firstElement.focus();
    }

    /**
     * Supprimer le focus trap
     */
    removeFocusTrap() {
        if (this.focusTrapHandler) {
            document.removeEventListener('keydown', this.focusTrapHandler);
            this.focusTrapHandler = null;
        }
    }

    /**
     * Dispatch d'événements personnalisés
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`header:${eventName}`, {
            detail: {
                header: this.header,
                ...detail
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * Scroll vers une section
     */
    scrollToSection(targetId) {
        const targetElement = document.getElementById(targetId);
        if (!targetElement) return;

        if (window.headerComponent) {
            window.headerComponent.scrollToSection(targetId);
        }
    }

    /**
     * API publique - Méthodes utilitaires
     */
    setScrolledState(scrolled = true) {
        if (scrolled) {
            this.header.classList.add(this.config.scrolledClass);
        } else {
            this.header.classList.remove(this.config.scrolledClass);
        }
    }

    isMobileMenuOpen() {
        return this.isMenuOpen;
    }

    updateActiveLink(path) {
        this.menuLinks.forEach(link => {
            link.classList.remove(this.config.linkActiveClass, 'header__mobile-nav-link--active');

            const linkPath = new URL(link.href).pathname;

            if (linkPath === path) {
                if (link.classList.contains('header__menu-link')) {
                    link.classList.add(this.config.linkActiveClass);
                } else if (link.classList.contains('header__mobile-nav-link')) {
                    link.classList.add('header__mobile-nav-link--active');
                }
            }
        });
    }

    destroy() {
        // Supprimer les événements
        window.removeEventListener('scroll', this.handleScroll);
        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('keydown', this.handleKeyDown);
        document.removeEventListener('click', this.handleOutsideClick);

        if (this.mobileToggle) {
            this.mobileToggle.removeEventListener('click', this.handleMobileToggle);
        }

        if (this.mobileMenu) {
            this.mobileMenu.removeEventListener('click', this.handleMobileMenuClick);
        }

        this.removeFocusTrap();

        // Réinitialiser les styles
        document.body.style.overflow = '';

        // Supprimer les classes ajoutées
        this.header.classList.remove(
            this.config.scrolledClass,
            this.config.mobileOpenClass,
            this.config.animatedClass
        );
    }
}

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du header
    window.headerComponent = new HeaderComponent();

    // Gestion des liens d'ancre avec smooth scroll
    document.querySelectorAll('a[href^="#"], [data-scroll-to]').forEach(link => {
        link.addEventListener('click', (e) => {
            const targetId = link.getAttribute('data-scroll-to') ||
                link.getAttribute('href')?.substring(1);

            if (targetId && document.getElementById(targetId)) {
                e.preventDefault();
                window.headerComponent.scrollToSection(targetId);
            }
        });
    });
});

// Export pour utilisation externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = HeaderComponent;
} else if (typeof window !== 'undefined') {
    window.HeaderComponent = HeaderComponent;
}
