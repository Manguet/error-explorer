/**
 * Dashboard Component - BEM Architecture
 * Composant principal du dashboard avec gestion modulaire
 */
class DashboardComponent {
    constructor() {
        // Configuration
        this.config = {
            autoRefreshInterval: 30000,
            animationDuration: 300,
            debounceDelay: 250,
            notificationDuration: 5000
        };

        // État du composant
        this.state = {
            isMobileMenuOpen: false,
            isAutoRefreshEnabled: false,
            refreshInterval: null,
            lastUpdate: null
        };

        // Éléments DOM
        this.elements = {
            sidebar: document.getElementById('sidebar'),
            mobileToggle: document.getElementById('mobile-toggle'),
            mobileOverlay: document.getElementById('mobile-overlay'),
            flashContainer: document.getElementById('dashboard-flash-messages'),
            liveStatus: document.querySelector('.live-status')
        };

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        this.setupEventListeners();
        this.setupMobileMenu();
        this.setupAutoRefresh();
        this.setupFlashMessages();
        this.setupKeyboardShortcuts();
        this.restoreUserPreferences();

        // Initialiser les sous-composants
        this.initializeSubComponents();
    }

    /**
     * Configuration des event listeners principaux
     */
    setupEventListeners() {
        // Mobile menu
        if (this.elements.mobileToggle) {
            this.elements.mobileToggle.addEventListener('click', () => {
                this.toggleMobileMenu();
            });
        }

        if (this.elements.mobileOverlay) {
            this.elements.mobileOverlay.addEventListener('click', () => {
                this.closeMobileMenu();
            });
        }

        // Gestion du redimensionnement
        window.addEventListener('resize', this.debounce(() => {
            this.handleResize();
        }, this.config.debounceDelay));

        // Auto-refresh toggles
        document.addEventListener('change', (e) => {
            if (e.target.id === 'auto-refresh-toggle') {
                this.toggleAutoRefresh(e.target.checked);
            }
        });

        // Escape key handling
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.handleEscapeKey();
            }
        });
    }

    /**
     * Configuration du menu mobile
     */
    setupMobileMenu() {
        if (!this.elements.sidebar || !this.elements.mobileToggle) return;

        // Attributes ARIA
        this.elements.mobileToggle.setAttribute('aria-expanded', 'false');
        this.elements.mobileToggle.setAttribute('aria-controls', 'sidebar');
        this.elements.sidebar.setAttribute('aria-hidden', 'true');
    }

    /**
     * Basculer le menu mobile
     */
    toggleMobileMenu() {
        this.state.isMobileMenuOpen = !this.state.isMobileMenuOpen;

        if (this.state.isMobileMenuOpen) {
            this.openMobileMenu();
        } else {
            this.closeMobileMenu();
        }
    }

    /**
     * Ouvrir le menu mobile
     */
    openMobileMenu() {
        if (!this.elements.sidebar) return;

        this.state.isMobileMenuOpen = true;
        this.elements.sidebar.classList.add('sidebar--mobile-open');
        this.elements.mobileOverlay.classList.add('mobile-overlay--active');
        this.elements.mobileToggle.classList.add('mobile-toggle--active');

        // ARIA updates
        this.elements.mobileToggle.setAttribute('aria-expanded', 'true');
        this.elements.sidebar.setAttribute('aria-hidden', 'false');

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    /**
     * Fermer le menu mobile
     */
    closeMobileMenu() {
        if (!this.elements.sidebar) return;

        this.state.isMobileMenuOpen = false;
        this.elements.sidebar.classList.remove('sidebar--mobile-open');
        this.elements.mobileOverlay.classList.remove('mobile-overlay--active');
        this.elements.mobileToggle.classList.remove('mobile-toggle--active');

        // ARIA updates
        this.elements.mobileToggle.setAttribute('aria-expanded', 'false');
        this.elements.sidebar.setAttribute('aria-hidden', 'true');

        // Restore body scroll
        document.body.style.overflow = '';
    }

    /**
     * Configuration de l'auto-refresh
     */
    setupAutoRefresh() {
        // Restaurer la préférence utilisateur
        const savedPreference = localStorage.getItem('dashboard-auto-refresh');
        if (savedPreference !== null) {
            const isEnabled = savedPreference === 'true';
            this.setAutoRefresh(isEnabled);

            // Mettre à jour les toggles
            document.querySelectorAll('#auto-refresh-toggle').forEach(toggle => {
                toggle.checked = isEnabled;
            });
        }
    }

    /**
     * Basculer l'auto-refresh
     */
    toggleAutoRefresh(enabled) {
        this.setAutoRefresh(enabled);
        localStorage.setItem('dashboard-auto-refresh', enabled.toString());
    }

    /**
     * Activer/désactiver l'auto-refresh
     */
    setAutoRefresh(enabled) {
        this.state.isAutoRefreshEnabled = enabled;

        if (enabled) {
            this.startAutoRefresh();
        } else {
            this.stopAutoRefresh();
        }
    }

    /**
     * Démarrer l'auto-refresh
     */
    startAutoRefresh() {
        this.stopAutoRefresh(); // Clear existing interval

        this.state.refreshInterval = setInterval(() => {
            this.performAutoRefresh();
        }, this.config.autoRefreshInterval);
    }

    /**
     * Arrêter l'auto-refresh
     */
    stopAutoRefresh() {
        if (this.state.refreshInterval) {
            clearInterval(this.state.refreshInterval);
            this.state.refreshInterval = null;
        }
    }

    /**
     * Effectuer l'auto-refresh
     */
    async performAutoRefresh() {
        try {
            // Dispatch custom event pour les composants enfants
            const event = new CustomEvent('dashboard:autoRefresh', {
                detail: { timestamp: Date.now() }
            });
            document.dispatchEvent(event);

            // Mettre à jour le status live
            this.updateLiveStatus();
            this.state.lastUpdate = new Date();

        } catch (error) {
            console.error('Auto-refresh error:', error);
        }
    }

    /**
     * Mettre à jour l'indicateur live
     */
    updateLiveStatus() {
        if (this.elements.liveStatus) {
            this.elements.liveStatus.style.transform = 'scale(1.1)';
            setTimeout(() => {
                this.elements.liveStatus.style.transform = '';
            }, 200);
        }
    }

    /**
     * Configuration des messages flash
     */
    setupFlashMessages() {
        const flashMessages = document.querySelectorAll('[data-auto-dismiss]');

        flashMessages.forEach(message => {
            const delay = parseInt(message.getAttribute('data-auto-dismiss'));
            if (delay) {
                setTimeout(() => {
                    this.dismissFlashMessage(message);
                }, delay);
            }

            // Setup close button
            const closeBtn = message.querySelector('.flash-message__close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.dismissFlashMessage(message);
                });
            }
        });
    }

    /**
     * Supprimer un message flash
     */
    dismissFlashMessage(message) {
        message.style.animation = 'flashSlideOut 0.3s ease-out forwards';
        setTimeout(() => {
            if (message.parentElement) {
                message.remove();
            }
        }, 300);
    }

    /**
     * Configuration des raccourcis clavier
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ignorer si dans un champ de saisie
            if (this.isInInputField(e.target)) return;

            // Ctrl/Cmd + R pour refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                window.location.reload();
            }

            // / pour focus sur la recherche
            if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });
    }

    /**
     * Vérifier si l'élément est un champ de saisie
     */
    isInInputField(element) {
        const inputElements = ['INPUT', 'TEXTAREA', 'SELECT'];
        return inputElements.includes(element.tagName) ||
               element.contentEditable === 'true';
    }

    /**
     * Gérer la touche Escape
     */
    handleEscapeKey() {
        // Fermer le menu mobile s'il est ouvert
        if (this.state.isMobileMenuOpen) {
            this.closeMobileMenu();
            return;
        }

        // Vider la recherche si elle a le focus
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && searchInput === document.activeElement) {
            searchInput.value = '';
            searchInput.blur();
        }
    }

    /**
     * Gérer le redimensionnement
     */
    handleResize() {
        // Fermer le menu mobile sur desktop
        if (window.innerWidth >= 1024 && this.state.isMobileMenuOpen) {
            this.closeMobileMenu();
        }
    }

    /**
     * Restaurer les préférences utilisateur
     */
    restoreUserPreferences() {
        // Auto-refresh déjà géré dans setupAutoRefresh

        // Autres préférences peuvent être ajoutées ici
        const theme = localStorage.getItem('dashboard-theme');
        if (theme) {
            document.body.setAttribute('data-theme', theme);
        }
    }

    /**
     * Initialiser les sous-composants
     */
    initializeSubComponents() {
        // Initialiser les tooltips
        this.initTooltips();

        // Initialiser les dropdowns
        this.initDropdowns();

        // Initialiser les modals (si nécessaire)
        this.initModals();
    }

    /**
     * Initialiser les tooltips
     */
    initTooltips() {
        const tooltipElements = document.querySelectorAll('[title], [data-tooltip]');

        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target);
            });

            element.addEventListener('mouseleave', (e) => {
                this.hideTooltip(e.target);
            });
        });
    }

    /**
     * Afficher un tooltip
     */
    showTooltip(element) {
        const text = element.getAttribute('data-tooltip') || element.getAttribute('title');
        if (!text) return;

        // Supprimer le title pour éviter le tooltip natif
        if (element.hasAttribute('title')) {
            element.setAttribute('data-original-title', element.getAttribute('title'));
            element.removeAttribute('title');
        }

        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
        `;

        document.body.appendChild(tooltip);

        // Positionner le tooltip
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';

        element._tooltip = tooltip;
    }

    /**
     * Cacher un tooltip
     */
    hideTooltip(element) {
        if (element._tooltip) {
            element._tooltip.remove();
            element._tooltip = null;
        }

        // Restaurer le title original
        if (element.hasAttribute('data-original-title')) {
            element.setAttribute('title', element.getAttribute('data-original-title'));
            element.removeAttribute('data-original-title');
        }
    }

    /**
     * Initialiser les dropdowns
     */
    initDropdowns() {
        // Implementation des dropdowns si nécessaire
    }

    /**
     * Initialiser les modals
     */
    initModals() {
        // Implementation des modals si nécessaire
    }

    // ===== MÉTHODES UTILITAIRES =====

    /**
     * Afficher une notification
     */
    showNotification(message, type = 'info', duration = null) {
        const notificationDuration = duration || this.config.notificationDuration;
        const container = this.elements.flashContainer || document.body;

        const notification = document.createElement('div');
        notification.className = `flash-message flash-message--${type}`;
        notification.innerHTML = `
            <span class="flash-message__content">${message}</span>
            <button class="flash-message__close">&times;</button>
        `;

        container.appendChild(notification);

        // Event listener pour le bouton fermer
        const closeBtn = notification.querySelector('.flash-message__close');
        closeBtn.addEventListener('click', () => {
            this.dismissFlashMessage(notification);
        });

        // Auto-remove
        setTimeout(() => {
            if (notification.parentElement) {
                this.dismissFlashMessage(notification);
            }
        }, notificationDuration);

        return notification;
    }

    /**
     * Effectuer une action AJAX
     */
    async performAction(url, method = 'POST', data = null) {
        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (data) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                return true;
            } else {
                this.showNotification(result.error || 'Une erreur est survenue', 'error');
                return false;
            }
        } catch (error) {
            this.showNotification('Erreur de connexion', 'error');
            console.error('Action error:', error);
            return false;
        }
    }

    /**
     * Copier dans le presse-papiers
     */
    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showNotification('Copié dans le presse-papiers !', 'success');
            }).catch(() => {
                this.fallbackCopyTextToClipboard(text);
            });
        } else {
            this.fallbackCopyTextToClipboard(text);
        }
    }

    /**
     * Fallback pour copier dans le presse-papiers
     */
    fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            this.showNotification('Copié dans le presse-papiers !', 'success');
        } catch (err) {
            this.showNotification('Erreur lors de la copie', 'error');
        }

        document.body.removeChild(textArea);
    }

    /**
     * Debounce utility
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
     * Throttle utility
     */
    throttle(func, limit) {
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

    /**
     * Format number utility
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
     * Format date utility
     */
    formatDate(date) {
        if (typeof date === 'string') {
            date = new Date(date);
        }

        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        if (days === 0) {
            return 'Aujourd\'hui';
        } else if (days === 1) {
            return 'Hier';
        } else if (days < 7) {
            return `Il y a ${days} jours`;
        } else {
            return date.toLocaleDateString('fr-FR');
        }
    }

    /**
     * Nettoyer les resources
     */
    destroy() {
        this.stopAutoRefresh();

        // Supprimer les event listeners
        if (this.elements.mobileToggle) {
            this.elements.mobileToggle.removeEventListener('click', this.toggleMobileMenu);
        }
    }
}

// Export pour utilisation externe
window.DashboardComponent = DashboardComponent;
