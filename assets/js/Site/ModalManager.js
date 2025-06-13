/**
 * Modal System - Error Explorer
 * Système de modales cohérent avec l'architecture BEM
 */
class ModalManager {
    constructor() {
        // Configuration par défaut
        this.config = {
            closeOnOverlay: true,
            closeOnEscape: true,
            focusTrap: true,
            animationDuration: 300,
            backdrop: true,
            keyboard: true,
            autoFocus: true
        };

        // État
        this.modals = new Map();
        this.modalStack = [];
        this.modalId = 0;
        this.activeModal = null;
        this.focusBeforeModal = null;

        this.init();
    }

    /**
     * Initialisation
     */
    init() {
        this.bindGlobalEvents();
        this.processExistingModals();
    }

    /**
     * Traitement des modales existantes dans le DOM
     */
    processExistingModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            this.enhanceModal(modal);
        });

        // Traiter les déclencheurs avec data-modal
        document.querySelectorAll('[data-modal]').forEach(trigger => {
            this.attachModalTrigger(trigger);
        });
    }

    /**
     * Liaison des événements globaux
     */
    bindGlobalEvents() {
        // Échap pour fermer
        document.addEventListener('keydown', this.handleKeyDown.bind(this));

        // Redimensionnement
        window.addEventListener('resize', this.handleResize.bind(this));
    }

    /**
     * Créer une modale programmatiquement
     */
    create(options = {}) {
        const modalId = ++this.modalId;

        // Configuration de la modale
        const config = {
            title: '',
            content: '',
            size: 'md',
            type: 'default',
            theme: 'dark',
            animation: 'fade',
            closable: true,
            buttons: [],
            className: '',
            onShow: null,
            onHide: null,
            onConfirm: null,
            onCancel: null,
            ...options
        };

        // Créer l'élément DOM
        const modalElement = this.createModalElement(modalId, config);

        // Ajouter au DOM
        document.body.appendChild(modalElement);

        // Stocker la référence
        this.modals.set(modalId, {
            id: modalId,
            element: modalElement,
            config: config,
            isVisible: false
        });

        // Ajouter les événements
        this.bindModalEvents(modalElement, modalId);

        return modalId;
    }

    /**
     * Créer l'élément DOM de la modale
     */
    createModalElement(modalId, config) {
        const modal = document.createElement('div');
        modal.className = this.buildModalClasses(config);
        modal.id = `modal-${modalId}`;
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-hidden', 'true');

        if (config.title) {
            modal.setAttribute('aria-labelledby', `modal-title-${modalId}`);
        }

        // Structure HTML
        modal.innerHTML = `
            <div class="modal__overlay"></div>
            <div class="modal__content ${this.getContentClasses(config)}">
                ${this.buildHeader(modalId, config)}
                ${this.buildBody(config)}
                ${this.buildFooter(config)}
            </div>
        `;

        return modal;
    }

    /**
     * Construire les classes CSS de la modale
     */
    buildModalClasses(config) {
        const classes = ['modal'];

        if (config.type && config.type !== 'default') {
            classes.push(`modal--${config.type}`);
        }

        if (config.animation && config.animation !== 'fade') {
            classes.push(`modal--anim-${config.animation}`);
        }

        if (config.className) {
            classes.push(config.className);
        }

        return classes.join(' ');
    }

    /**
     * Obtenir les classes du contenu
     */
    getContentClasses(config) {
        const classes = [`modal__content--${config.size}`];

        if (config.theme && config.theme !== 'dark') {
            classes.push(`modal__content--${config.theme}`);
        }

        return classes.join(' ');
    }

    /**
     * Construire le header
     */
    buildHeader(modalId, config) {
        if (!config.title && config.closable !== false) {
            return `
                <div class="modal__header">
                    <button class="modal__close" aria-label="Fermer"></button>
                </div>
            `;
        }

        if (!config.title) return '';

        const titleClasses = config.theme !== 'dark' ? `modal__title--${config.theme}` : '';

        return `
            <div class="modal__header">
                <h2 class="modal__title ${titleClasses}" id="modal-title-${modalId}">
                    ${config.icon ? `<span class="modal__title-icon">${config.icon}</span>` : ''}
                    ${this.escapeHtml(config.title)}
                </h2>
                ${config.closable !== false ? '<button class="modal__close" aria-label="Fermer"></button>' : ''}
            </div>
        `;
    }

    /**
     * Construire le body
     */
    buildBody(config) {
        const bodyClasses = [];

        if (!config.buttons || config.buttons.length === 0) {
            bodyClasses.push('modal__body--no-footer');
        }

        if (config.centered) {
            bodyClasses.push('modal__body--centered');
        }

        return `
            <div class="modal__body ${bodyClasses.join(' ')}">
                ${typeof config.content === 'string' ? config.content : ''}
            </div>
        `;
    }

    /**
     * Construire le footer
     */
    buildFooter(config) {
        if (!config.buttons || config.buttons.length === 0) {
            return '';
        }

        const alignment = config.buttonAlignment || 'right';
        const buttonsHtml = config.buttons.map(button => this.buildButton(button)).join('');

        return `
            <div class="modal__footer modal__footer--${alignment}">
                ${buttonsHtml}
            </div>
        `;
    }

    /**
     * Construire un bouton
     */
    buildButton(button) {
        const config = {
            text: 'Button',
            type: 'secondary',
            action: null,
            className: '',
            ...button
        };

        return `
            <button class="modal__button modal__button--${config.type} ${config.className}" 
                    data-action="${config.action || ''}"
                    ${config.disabled ? 'disabled' : ''}>
                ${config.icon ? `<span class="modal__button-icon">${config.icon}</span>` : ''}
                ${this.escapeHtml(config.text)}
            </button>
        `;
    }

    /**
     * Liaison des événements d'une modale
     */
    bindModalEvents(modalElement, modalId) {
        const modal = this.modals.get(modalId);
        if (!modal) return;

        // Clic sur l'overlay
        const overlay = modalElement.querySelector('.modal__overlay');
        if (overlay && modal.config.closeOnOverlay !== false) {
            overlay.addEventListener('click', () => this.hide(modalId));
        }

        // Bouton de fermeture
        const closeBtn = modalElement.querySelector('.modal__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hide(modalId));
        }

        // Boutons d'action
        const actionButtons = modalElement.querySelectorAll('.modal__button[data-action]');
        actionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const action = button.getAttribute('data-action');
                this.handleButtonAction(modalId, action, e);
            });
        });

        // Empêcher la fermeture sur clic du contenu
        const content = modalElement.querySelector('.modal__content');
        if (content) {
            content.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    }

    /**
     * Gestion des actions de boutons
     */
    handleButtonAction(modalId, action, event) {
        const modal = this.modals.get(modalId);
        if (!modal) return;

        const { config } = modal;

        switch (action) {
            case 'confirm':
                if (config.onConfirm) {
                    const result = config.onConfirm(modalId, event);
                    if (result !== false) {
                        this.hide(modalId);
                    }
                } else {
                    this.hide(modalId);
                }
                break;

            case 'cancel':
                if (config.onCancel) {
                    config.onCancel(modalId, event);
                }
                this.hide(modalId);
                break;

            case '':
            case 'close':
                this.hide(modalId);
                break;

            default:
                // Action personnalisée
                if (config.onAction) {
                    const result = config.onAction(action, modalId, event);
                    if (result !== false) {
                        this.hide(modalId);
                    }
                }
        }
    }

    /**
     * Afficher une modale
     */
    show(modalId) {
        const modal = this.modals.get(modalId);
        if (!modal || modal.isVisible) return false;

        // Sauvegarder le focus actuel
        this.focusBeforeModal = document.activeElement;

        // Gérer la pile de modales
        this.modalStack.push(modalId);
        this.activeModal = modalId;

        // Masquer les autres modales
        this.modalStack.slice(0, -1).forEach(id => {
            const otherModal = this.modals.get(id);
            if (otherModal) {
                otherModal.element.style.zIndex = 1050 - 1;
            }
        });

        // Afficher la modale
        modal.element.style.zIndex = 1050 + this.modalStack.length;
        modal.element.classList.add('modal--show');
        modal.element.setAttribute('aria-hidden', 'false');
        modal.isVisible = true;

        // Désactiver le scroll du body
        document.body.style.overflow = 'hidden';

        // Auto focus
        if (this.config.autoFocus) {
            this.setInitialFocus(modal.element);
        }

        // Focus trap
        if (this.config.focusTrap) {
            this.setupFocusTrap(modal.element);
        }

        // Callback
        if (modal.config.onShow) {
            modal.config.onShow(modalId);
        }

        // Événement personnalisé
        this.dispatchEvent('modalShow', { modalId, modal });

        return true;
    }

    /**
     * Masquer une modale
     */
    hide(modalId) {
        const modal = this.modals.get(modalId);
        if (!modal || !modal.isVisible) return false;

        // Animation de sortie
        modal.element.classList.remove('modal--show');
        modal.element.setAttribute('aria-hidden', 'true');
        modal.isVisible = false;

        // Retirer de la pile
        const stackIndex = this.modalStack.indexOf(modalId);
        if (stackIndex > -1) {
            this.modalStack.splice(stackIndex, 1);
        }

        // Gérer le focus
        this.removeFocusTrap(modal.element);

        // Restaurer le scroll si plus de modales
        if (this.modalStack.length === 0) {
            document.body.style.overflow = '';

            // Restaurer le focus
            if (this.focusBeforeModal && this.focusBeforeModal.focus) {
                setTimeout(() => {
                    this.focusBeforeModal.focus();
                    this.focusBeforeModal = null;
                }, this.config.animationDuration);
            }
        } else {
            // Réactiver la modale précédente
            const previousModalId = this.modalStack[this.modalStack.length - 1];
            const previousModal = this.modals.get(previousModalId);
            if (previousModal) {
                previousModal.element.style.zIndex = 1050 + this.modalStack.length;
                this.activeModal = previousModalId;
            }
        }

        // Callback
        if (modal.config.onHide) {
            modal.config.onHide(modalId);
        }

        // Événement personnalisé
        this.dispatchEvent('modalHide', { modalId, modal });

        return true;
    }

    /**
     * Masquer toutes les modales
     */
    hideAll() {
        const visibleModals = Array.from(this.modals.values())
            .filter(modal => modal.isVisible)
            .map(modal => modal.id);

        visibleModals.forEach(id => this.hide(id));
    }

    /**
     * Supprimer une modale
     */
    remove(modalId) {
        const modal = this.modals.get(modalId);
        if (!modal) return false;

        // Masquer d'abord
        if (modal.isVisible) {
            this.hide(modalId);
        }

        // Supprimer du DOM après l'animation
        setTimeout(() => {
            if (modal.element.parentNode) {
                modal.element.remove();
            }
            this.modals.delete(modalId);
        }, this.config.animationDuration);

        return true;
    }

    /**
     * Mettre à jour le contenu d'une modale
     */
    updateContent(modalId, content) {
        const modal = this.modals.get(modalId);
        if (!modal) return false;

        const bodyElement = modal.element.querySelector('.modal__body');
        if (bodyElement) {
            bodyElement.innerHTML = content;
        }

        return true;
    }

    /**
     * Gestion du focus initial
     */
    setInitialFocus(modalElement) {
        // Chercher le premier élément focusable
        const focusableElements = modalElement.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );

        if (focusableElements.length > 0) {
            focusableElements[0].focus();
        } else {
            modalElement.focus();
        }
    }

    /**
     * Configuration du focus trap
     */
    setupFocusTrap(modalElement) {
        const focusableElements = modalElement.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
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

        modalElement.addEventListener('keydown', this.focusTrapHandler);
    }

    /**
     * Supprimer le focus trap
     */
    removeFocusTrap(modalElement) {
        if (this.focusTrapHandler) {
            modalElement.removeEventListener('keydown', this.focusTrapHandler);
            this.focusTrapHandler = null;
        }
    }

    /**
     * Gestion des événements globaux
     */
    handleKeyDown(e) {
        if (e.key === 'Escape' && this.activeModal && this.config.closeOnEscape) {
            const modal = this.modals.get(this.activeModal);
            if (modal && modal.config.closable !== false) {
                this.hide(this.activeModal);
            }
        }
    }

    handleResize() {
        // Repositionner les modales si nécessaire
        this.modalStack.forEach(modalId => {
            const modal = this.modals.get(modalId);
            if (modal && modal.isVisible) {
                // Logique de repositionnement si nécessaire
            }
        });
    }

    /**
     * Améliorer une modale existante
     */
    enhanceModal(modalElement) {
        const modalId = ++this.modalId;
        modalElement.id = modalElement.id || `modal-${modalId}`;

        // Configuration basée sur les classes
        const config = this.extractConfigFromClasses(modalElement.className);

        // Stocker la référence
        this.modals.set(modalId, {
            id: modalId,
            element: modalElement,
            config: config,
            isVisible: false
        });

        // Ajouter les événements
        this.bindModalEvents(modalElement, modalId);

        return modalId;
    }

    /**
     * Extraire la configuration depuis les classes CSS
     */
    extractConfigFromClasses(className) {
        const config = {
            closable: true,
            closeOnOverlay: true
        };

        // Extraire la taille
        const sizeMatch = className.match(/modal__content--(\w+)/);
        if (sizeMatch) {
            config.size = sizeMatch[1];
        }

        // Extraire le type
        if (className.includes('modal--confirm')) {
            config.type = 'confirm';
        } else if (className.includes('modal--form')) {
            config.type = 'form';
        }

        return config;
    }

    /**
     * Attacher un déclencheur de modale
     */
    attachModalTrigger(trigger) {
        const modalSelector = trigger.getAttribute('data-modal');
        const modalElement = document.querySelector(modalSelector);

        if (!modalElement) return;

        const modalId = this.enhanceModal(modalElement);

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            this.show(modalId);
        });
    }

    /**
     * Méthodes de convenance pour les types courants
     */

    // Modale de confirmation
    confirm(options = {}) {
        const config = {
            title: 'Confirmation',
            content: 'Êtes-vous sûr de vouloir continuer ?',
            type: 'confirm',
            size: 'sm',
            buttons: [
                { text: 'Annuler', type: 'secondary', action: 'cancel' },
                { text: 'Confirmer', type: 'primary', action: 'confirm' }
            ],
            ...options
        };

        const modalId = this.create(config);
        this.show(modalId);
        return modalId;
    }

    // Modale d'alerte
    alert(content, title = 'Information') {
        const config = {
            title: title,
            content: content,
            type: 'confirm',
            size: 'sm',
            buttons: [
                { text: 'OK', type: 'primary', action: 'close' }
            ]
        };

        const modalId = this.create(config);
        this.show(modalId);
        return modalId;
    }

    // Modale avec formulaire
    form(options = {}) {
        const config = {
            type: 'form',
            size: 'md',
            buttons: [
                { text: 'Annuler', type: 'secondary', action: 'cancel' },
                { text: 'Valider', type: 'primary', action: 'confirm' }
            ],
            ...options
        };

        const modalId = this.create(config);
        this.show(modalId);
        return modalId;
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
        const event = new CustomEvent(`modal:${eventName}`, {
            detail: {
                manager: this,
                ...detail
            }
        });
        document.dispatchEvent(event);
    }

    // Configuration
    configure(options) {
        Object.assign(this.config, options);
    }

    // Obtenir la modale active
    getActive() {
        return this.activeModal ? this.modals.get(this.activeModal) : null;
    }

    // Vérifier si une modale est visible
    isVisible(modalId) {
        const modal = this.modals.get(modalId);
        return modal ? modal.isVisible : false;
    }

    // Nettoyage
    destroy() {
        this.hideAll();

        // Supprimer les événements
        document.removeEventListener('keydown', this.handleKeyDown);
        window.removeEventListener('resize', this.handleResize);

        // Supprimer toutes les modales
        this.modals.forEach(modal => {
            if (modal.element.parentNode) {
                modal.element.remove();
            }
        });

        this.modals.clear();
        this.modalStack = [];

        // Restaurer le scroll
        document.body.style.overflow = '';
    }
}

/**
 * API simplifiée
 */
let modal = {
    manager: null,

    init() {
        if (!this.manager) {
            this.manager = new ModalManager();
        }
        return this.manager;
    },

    // Créer une modale
    create(options = {}) {
        return this.init().create(options);
    },

    // Afficher une modale
    show(modalId) {
        return this.manager?.show(modalId);
    },

    // Masquer une modale
    hide(modalId) {
        return this.manager?.hide(modalId);
    },

    // Masquer toutes les modales
    hideAll() {
        return this.manager?.hideAll();
    },

    // Supprimer une modale
    remove(modalId) {
        return this.manager?.remove(modalId);
    },

    // Méthodes de convenance
    confirm(options = {}) {
        return this.init().confirm(options);
    },

    alert(content, title = 'Information') {
        return this.init().alert(content, title);
    },

    form(options = {}) {
        return this.init().form(options);
    },

    // Mettre à jour le contenu
    updateContent(modalId, content) {
        return this.manager?.updateContent(modalId, content);
    },

    // Configuration
    configure(options) {
        this.init().configure(options);
    }
};

/**
 * Initialisation automatique
 */
document.addEventListener('DOMContentLoaded', () => {
    // Auto-init
    modal.init();

    // Dispatcher un événement
    document.dispatchEvent(new CustomEvent('modal:ready'));
});

// Exposition globale
window.ModalManager = ModalManager;
window.modal = modal;

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ModalManager, modal };
}
