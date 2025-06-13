/**
 * Système de Notifications Avancé - Error Explorer
 * Gestion complète des notifications avec animations, actions et persistance
 */
class NotificationSystem {
    constructor() {
        // Configuration par défaut
        this.config = {
            container: null,
            position: 'top-right',
            maxNotifications: 5,
            defaultDuration: 5000,
            animationDuration: 300,
            stackSpacing: 12,
            autoInit: true
        };

        // État
        this.notifications = new Map();
        this.notificationId = 0;
        this.container = null;
        this.isInitialized = false;

        // Types d'icônes
        this.icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ',
            loading: '○'
        };

        // Sons (optionnel)
        this.sounds = {
            success: null,
            error: null,
            warning: null,
            info: null
        };

        if (this.config.autoInit) {
            this.init();
        }
    }

    /**
     * Initialisation du système
     */
    init() {
        if (this.isInitialized) return;

        this.createContainer();
        this.bindEvents();
        this.processExistingFlashMessages();
        this.isInitialized = true;

        // Événement d'initialisation
        this.dispatchEvent('initialized');
    }

    /**
     * Création du conteneur principal
     */
    createContainer() {
        // Vérifier si le conteneur existe déjà
        this.container = document.querySelector('.notifications');

        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'notifications';
            document.body.appendChild(this.container);
        }
    }

    /**
     * Liaison des événements globaux
     */
    bindEvents() {
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
    }

    /**
     * Traitement des flash messages Symfony existants
     */
    processExistingFlashMessages() {
        const flashMessages = document.querySelectorAll('.flash-message');

        if (flashMessages.length === 0) {
            return;
        }

        // Traiter les messages
        flashMessages.forEach(flash => {
            const type = this.extractFlashType(flash.className);
            const message = flash.textContent.trim();

            if (message) {
                this.show({
                    type: type,
                    message: message,
                    duration: 6000,
                    persistent: false
                });
            }

            flash.remove();
        });

        // Vider côté serveur
        this.clearServerFlashMessages();
    }

    /**
     * Vider les flash messages côté serveur
     */
    async clearServerFlashMessages() {
        try {
            await fetch('/api/clear-flash', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        } catch (error) {
            console.debug('Could not clear server flash messages:', error);
        }
    }

    /**
     * Extraction du type de flash message
     */
    extractFlashType(className) {
        if (className.includes('flash-success')) return 'success';
        if (className.includes('flash-error')) return 'error';
        if (className.includes('flash-warning')) return 'warning';
        if (className.includes('flash-info')) return 'info';
        return 'info';
    }

    /**
     * Méthode principale pour afficher une notification
     */
    show(options) {
        const notification = this.createNotification(options);
        this.addNotification(notification);
        return notification.id;
    }

    /**
     * Méthodes de convenance pour chaque type
     */
    success(message, options = {}) {
        return this.show({ ...options, type: 'success', message });
    }

    error(message, options = {}) {
        return this.show({ ...options, type: 'error', message });
    }

    warning(message, options = {}) {
        return this.show({ ...options, type: 'warning', message });
    }

    info(message, options = {}) {
        return this.show({ ...options, type: 'info', message });
    }

    loading(message, options = {}) {
        return this.show({
            ...options,
            type: 'loading',
            message,
            persistent: true,
            showClose: false
        });
    }

    /**
     * Création d'une notification
     */
    createNotification(options) {
        const id = ++this.notificationId;
        const config = {
            type: 'info',
            title: '',
            message: '',
            duration: this.config.defaultDuration,
            persistent: false,
            showClose: true,
            clickable: false,
            actions: [],
            data: {},
            onShow: null,
            onHide: null,
            onClick: null,
            onAction: null,
            ...options
        };

        return {
            id,
            config,
            element: this.createElement(id, config),
            timer: null,
            isVisible: false
        };
    }

    /**
     * Création de l'élément DOM
     */
    createElement(id, config) {
        const element = document.createElement('div');
        element.className = `notification notification--${config.type}`;
        element.setAttribute('data-notification-id', id);

        if (config.clickable) {
            element.classList.add('notification--actionable');
        }

        if (config.persistent) {
            element.classList.add('notification--persistent');
        }

        // Construction du HTML
        element.innerHTML = `
            <div class="notification__content">
                <div class="notification__icon notification__icon--${config.type}">
                </div>
                <div class="notification__text">
                    ${config.title ? `<h4 class="notification__title">${this.escapeHtml(config.title)}</h4>` : ''}
                    <p class="notification__message">${this.escapeHtml(config.message)}</p>
                    ${config.actions.length > 0 ? this.createActionsHTML(config.actions) : ''}
                </div>
            </div>
            ${config.showClose ? '<button class="notification__close" aria-label="Fermer"></button>' : ''}
        `;

        // Liaison des événements
        this.bindNotificationEvents(element, id, config);

        return element;
    }

    /**
     * Création du HTML des actions
     */
    createActionsHTML(actions) {
        const actionsHTML = actions.map(action => `
            <button class="notification__action ${action.primary ? 'notification__action--primary' : ''}" 
                    data-action="${action.id}">
                ${this.escapeHtml(action.label)}
            </button>
        `).join('');

        return `<div class="notification__actions">${actionsHTML}</div>`;
    }

    /**
     * Liaison des événements d'une notification
     */
    bindNotificationEvents(element, id, config) {
        // Bouton de fermeture
        const closeBtn = element.querySelector('.notification__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.hide(id);
            });
        }

        // Clic sur la notification
        if (config.clickable && config.onClick) {
            element.addEventListener('click', (e) => {
                if (!e.target.closest('.notification__actions, .notification__close')) {
                    config.onClick(id, config.data);
                }
            });
        }

        // Actions
        const actionButtons = element.querySelectorAll('.notification__action');
        actionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                const actionId = button.getAttribute('data-action');
                const action = config.actions.find(a => a.id === actionId);

                if (action && action.handler) {
                    action.handler(id, actionId, config.data);
                }

                if (config.onAction) {
                    config.onAction(id, actionId, config.data);
                }

                // Fermer la notification après l'action si spécifié
                if (action && action.closeAfter !== false) {
                    this.hide(id);
                }
            });
        });
    }

    /**
     * Ajout d'une notification au DOM
     */
    addNotification(notification) {
        // Limiter le nombre de notifications
        this.enforceMaxNotifications();

        // Ajouter au conteneur
        this.container.appendChild(notification.element);
        this.notifications.set(notification.id, notification);

        // Animation d'entrée
        requestAnimationFrame(() => {
            notification.element.classList.add('notification--show');
            notification.isVisible = true;

            // Callback onShow
            if (notification.config.onShow) {
                notification.config.onShow(notification.id);
            }
        });

        // Timer d'auto-dismiss
        if (!notification.config.persistent) {
            this.setAutoDismissTimer(notification);
        }

        // Événement personnalisé
        this.dispatchEvent('notificationAdded', {
            id: notification.id,
            type: notification.config.type
        });
    }

    /**
     * Timer d'auto-dismiss
     */
    setAutoDismissTimer(notification) {
        const duration = notification.config.duration;

        // Ajouter la classe pour la barre de progression
        notification.element.classList.add('notification--auto-dismiss');

        // Définir la durée de la barre de progression
        const progressBar = notification.element;
        progressBar.style.setProperty('--progress-duration', `${duration}ms`);

        // Timer principal
        notification.timer = setTimeout(() => {
            this.hide(notification.id);
        }, duration);
    }

    /**
     * Masquer une notification
     */
    hide(id) {
        const notification = this.notifications.get(id);
        if (!notification || !notification.isVisible) return;

        // Annuler le timer si actif
        if (notification.timer) {
            clearTimeout(notification.timer);
            notification.timer = null;
        }

        // Animation de sortie
        notification.element.classList.remove('notification--show');
        notification.element.classList.add('notification--hide');
        notification.isVisible = false;

        // Supprimer après l'animation
        setTimeout(() => {
            if (notification.element.parentNode) {
                notification.element.remove();
            }
            this.notifications.delete(id);

            // Callback onHide
            if (notification.config.onHide) {
                notification.config.onHide(id);
            }

            // Événement personnalisé
            this.dispatchEvent('notificationRemoved', { id });
        }, this.config.animationDuration);
    }

    /**
     * Masquer toutes les notifications
     */
    hideAll() {
        const ids = Array.from(this.notifications.keys());
        ids.forEach(id => this.hide(id));
    }

    /**
     * Masquer toutes les notifications d'un type
     */
    hideType(type) {
        this.notifications.forEach((notification, id) => {
            if (notification.config.type === type) {
                this.hide(id);
            }
        });
    }

    /**
     * Mettre à jour une notification existante
     */
    update(id, updates) {
        const notification = this.notifications.get(id);
        if (!notification) return false;

        // Mettre à jour la configuration
        Object.assign(notification.config, updates);

        // Mettre à jour le DOM
        if (updates.message) {
            const messageEl = notification.element.querySelector('.notification__message');
            if (messageEl) {
                messageEl.textContent = updates.message;
            }
        }

        if (updates.title) {
            const titleEl = notification.element.querySelector('.notification__title');
            if (titleEl) {
                titleEl.textContent = updates.title;
            }
        }

        if (updates.type) {
            // Changer le type
            notification.element.className = notification.element.className.replace(
                /notification--\w+/g,
                `notification--${updates.type}`
            );
        }

        return true;
    }

    /**
     * Appliquer la limite de notifications
     */
    enforceMaxNotifications() {
        const visibleNotifications = Array.from(this.notifications.values())
            .filter(n => n.isVisible)
            .sort((a, b) => a.id - b.id);

        while (visibleNotifications.length >= this.config.maxNotifications) {
            const oldest = visibleNotifications.shift();
            this.hide(oldest.id);
        }
    }

    /**
     * Gestion des raccourcis clavier
     */
    handleKeyDown(e) {
        if (e.key === 'Escape') {
            this.hideAll();
        }
    }

    /**
     * Gestion de la visibilité de la page
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // Pause tous les timers
            this.notifications.forEach(notification => {
                if (notification.timer) {
                    // Sauvegarder le temps restant
                    // Implementation plus complexe nécessaire pour une vraie pause
                }
            });
        } else {
            // Reprendre les timers
        }
    }

    /**
     * Utilitaires
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`notification:${eventName}`, {
            detail: {
                system: this,
                ...detail
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * API publique avancée
     */
    get(id) {
        return this.notifications.get(id);
    }

    // Obtenir toutes les notifications visibles
    getVisible() {
        return Array.from(this.notifications.values()).filter(n => n.isVisible);
    }

    // Obtenir le nombre de notifications par type
    getCountByType() {
        const counts = { success: 0, error: 0, warning: 0, info: 0, loading: 0 };
        this.notifications.forEach(notification => {
            const type = notification.config.type;
            if (counts.hasOwnProperty(type)) {
                counts[type]++;
            }
        });
        return counts;
    }

    // Vérifier si une notification existe
    exists(id) {
        return this.notifications.has(id);
    }

    // Configuration
    configure(options) {
        Object.assign(this.config, options);
    }

    // Nettoyage complet
    destroy() {
        this.hideAll();

        // Supprimer les événements
        document.removeEventListener('keydown', this.handleKeyDown);
        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);

        // Supprimer le conteneur
        if (this.container && this.container.parentNode) {
            this.container.remove();
        }

        this.isInitialized = false;
    }
}

/**
 * Instance globale du système de notifications
 */
let notificationSystem = null;

/**
 * API simplifiée pour une utilisation facile
 */
const notify = {
    init() {
        if (!notificationSystem) {
            notificationSystem = new NotificationSystem();
        }
        return notificationSystem;
    },

    success(message, options = {}) {
        return this.init().success(message, options);
    },

    error(message, options = {}) {
        return this.init().error(message, options);
    },

    warning(message, options = {}) {
        return this.init().warning(message, options);
    },

    info(message, options = {}) {
        return this.init().info(message, options);
    },

    loading(message, options = {}) {
        return this.init().loading(message, options);
    },

    hide(id) {
        return notificationSystem?.hide(id);
    },

    hideAll() {
        return notificationSystem?.hideAll();
    },

    update(id, updates) {
        return notificationSystem?.update(id, updates);
    },

    configure(options) {
        this.init().configure(options);
    }
};

/**
 * Initialisation automatique
 */
document.addEventListener('DOMContentLoaded', () => {
    // Auto-init du système
    notify.init();

    // Exemples d'utilisation pour le développement
    if (window.location.search.includes('debug=notifications')) {
        setTimeout(() => {
            notify.success('Système de notifications initialisé !');
        }, 1000);

        setTimeout(() => {
            notify.info('Ceci est une notification d\'information avec une très longue description pour tester le wrap du texte.');
        }, 2000);

        setTimeout(() => {
            const loadingId = notify.loading('Chargement en cours...');

            setTimeout(() => {
                notify.update(loadingId, {
                    type: 'success',
                    message: 'Chargement terminé avec succès !',
                    persistent: false,
                    duration: 3000
                });
            }, 3000);
        }, 3000);
    }
});

// Exposition globale
window.NotificationSystem = NotificationSystem;
window.notify = notify;

// Export CommonJS/ES6
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { NotificationSystem, notify };
}
