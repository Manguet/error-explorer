/**
 * Tooltip System - Error Explorer
 * Système de tooltips cohérent avec l'architecture BEM
 */
class TooltipManager {
    constructor() {
        // Configuration par défaut
        this.config = {
            defaultPosition: 'bottom',
            defaultTheme: 'dark',
            showDelay: 500,
            hideDelay: 100,
            animationDuration: 200,
            maxWidth: 250,
            offset: 8,
            autoPosition: true,
            interactive: false
        };

        // État
        this.tooltips = new Map();
        this.activeTooltip = null;
        this.showTimer = null;
        this.hideTimer = null;
        this.tooltipId = 0;

        this.init();
    }

    /**
     * Initialisation
     */
    init() {
        this.bindEvents();
        this.processExistingTooltips();
    }

    /**
     * Traitement des tooltips existants dans le DOM
     */
    processExistingTooltips() {
        // Traiter les éléments avec data-tooltip
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            this.attachTooltip(element);
        });

        // Traiter les tooltips déjà structurés
        document.querySelectorAll('.tooltip').forEach(tooltip => {
            this.enhanceTooltip(tooltip);
        });
    }

    /**
     * Liaison des événements globaux
     */
    bindEvents() {
        // Gestion du clavier
        document.addEventListener('keydown', this.handleKeyDown.bind(this));

        // Gestion du scroll (masquer les tooltips)
        document.addEventListener('scroll', this.handleScroll.bind(this), { passive: true });

        // Gestion du redimensionnement
        window.addEventListener('resize', this.handleResize.bind(this));
    }

    /**
     * Attacher un tooltip à un élément
     */
    attachTooltip(element, options = {}) {
        const tooltipId = ++this.tooltipId;

        // Configuration du tooltip
        const config = {
            content: element.getAttribute('data-tooltip') || '',
            title: element.getAttribute('data-tooltip-title') || '',
            position: element.getAttribute('data-tooltip-position') || this.config.defaultPosition,
            theme: element.getAttribute('data-tooltip-theme') || this.config.defaultTheme,
            interactive: element.hasAttribute('data-tooltip-interactive'),
            showDelay: parseInt(element.getAttribute('data-tooltip-delay')) || this.config.showDelay,
            ...options
        };

        // Créer le tooltip
        const tooltipElement = this.createTooltipElement(config);

        // Ajouter au DOM (masqué)
        document.body.appendChild(tooltipElement);

        // Stocker la référence
        this.tooltips.set(tooltipId, {
            id: tooltipId,
            trigger: element,
            tooltip: tooltipElement,
            config: config
        });

        // Ajouter les événements
        this.bindTooltipEvents(element, tooltipId);

        return tooltipId;
    }

    /**
     * Créer l'élément tooltip
     */
    createTooltipElement(config) {
        const tooltip = document.createElement('div');
        tooltip.className = `tooltip__content tooltip__content--${config.position} tooltip__content--${config.theme}`;

        if (config.interactive) {
            tooltip.classList.add('tooltip__content--interactive');
        }

        // Contenu
        if (config.title) {
            tooltip.classList.add('tooltip__content--with-title');
            tooltip.innerHTML = `
                <div class="tooltip__title">${this.escapeHtml(config.title)}</div>
                <div class="tooltip__body">${this.escapeHtml(config.content)}</div>
            `;
        } else {
            tooltip.textContent = config.content;
        }

        // ID unique
        tooltip.id = `tooltip-${this.tooltipId}`;
        tooltip.setAttribute('role', 'tooltip');

        return tooltip;
    }

    /**
     * Liaison des événements d'un tooltip
     */
    bindTooltipEvents(trigger, tooltipId) {
        const tooltip = this.tooltips.get(tooltipId);
        if (!tooltip) return;

        // Événements de base
        trigger.addEventListener('mouseenter', () => this.showTooltip(tooltipId));
        trigger.addEventListener('mouseleave', () => this.hideTooltip(tooltipId));
        trigger.addEventListener('focus', () => this.showTooltip(tooltipId));
        trigger.addEventListener('blur', () => this.hideTooltip(tooltipId));

        // Événements pour tooltips interactifs
        if (tooltip.config.interactive) {
            tooltip.tooltip.addEventListener('mouseenter', () => this.showTooltip(tooltipId));
            tooltip.tooltip.addEventListener('mouseleave', () => this.hideTooltip(tooltipId));
        }

        // Accessibilité
        trigger.setAttribute('aria-describedby', tooltip.tooltip.id);
    }

    /**
     * Afficher un tooltip
     */
    showTooltip(tooltipId) {
        const tooltip = this.tooltips.get(tooltipId);
        if (!tooltip) return;

        // Annuler le timer de masquage
        if (this.hideTimer) {
            clearTimeout(this.hideTimer);
            this.hideTimer = null;
        }

        // Masquer le tooltip actuel s'il y en a un autre
        if (this.activeTooltip && this.activeTooltip !== tooltipId) {
            this.hideTooltip(this.activeTooltip, true);
        }

        // Timer d'affichage
        this.showTimer = setTimeout(() => {
            this.displayTooltip(tooltipId);
        }, tooltip.config.showDelay);
    }

    /**
     * Afficher réellement le tooltip
     */
    displayTooltip(tooltipId) {
        const tooltip = this.tooltips.get(tooltipId);
        if (!tooltip) return;

        // Positionner le tooltip
        this.positionTooltip(tooltip);

        // Afficher avec animation
        tooltip.tooltip.classList.add('tooltip__content--show');
        this.activeTooltip = tooltipId;

        // Événement personnalisé
        this.dispatchEvent('tooltipShow', { tooltipId, tooltip });
    }

    /**
     * Masquer un tooltip
     */
    hideTooltip(tooltipId, immediate = false) {
        const tooltip = this.tooltips.get(tooltipId);
        if (!tooltip) return;

        // Annuler le timer d'affichage
        if (this.showTimer) {
            clearTimeout(this.showTimer);
            this.showTimer = null;
        }

        const hideDelay = immediate ? 0 : this.config.hideDelay;

        this.hideTimer = setTimeout(() => {
            tooltip.tooltip.classList.remove('tooltip__content--show');

            if (this.activeTooltip === tooltipId) {
                this.activeTooltip = null;
            }

            // Événement personnalisé
            this.dispatchEvent('tooltipHide', { tooltipId, tooltip });
        }, hideDelay);
    }

    /**
     * Positionner un tooltip
     */
    positionTooltip(tooltip) {
        const { trigger, tooltip: tooltipEl, config } = tooltip;

        // Réinitialiser les styles de position
        tooltipEl.style.top = '';
        tooltipEl.style.left = '';
        tooltipEl.style.transform = '';

        // Obtenir les dimensions
        const triggerRect = trigger.getBoundingClientRect();
        const tooltipRect = tooltipEl.getBoundingClientRect();
        const viewport = {
            width: window.innerWidth,
            height: window.innerHeight
        };

        let position = config.position;

        // Auto-positionnement si activé
        if (this.config.autoPosition) {
            position = this.calculateOptimalPosition(triggerRect, tooltipRect, viewport, position);
        }

        // Calculer la position finale
        const coords = this.calculatePosition(triggerRect, tooltipRect, position);

        // Appliquer la position
        tooltipEl.style.position = 'fixed';
        tooltipEl.style.top = `${coords.top}px`;
        tooltipEl.style.left = `${coords.left}px`;
        tooltipEl.style.zIndex = this.getZIndex();

        // Mettre à jour la classe de position si elle a changé
        if (position !== config.position) {
            tooltipEl.className = tooltipEl.className.replace(
                /tooltip__content--\w+/g,
                `tooltip__content--${position}`
            );
        }
    }

    /**
     * Calculer la position optimale
     */
    calculateOptimalPosition(triggerRect, tooltipRect, viewport, preferredPosition) {
        const positions = ['top', 'bottom', 'left', 'right'];
        const spacing = this.config.offset;

        // Vérifier si la position préférée fonctionne
        if (this.isPositionValid(triggerRect, tooltipRect, viewport, preferredPosition, spacing)) {
            return preferredPosition;
        }

        // Essayer les autres positions
        for (const position of positions) {
            if (position !== preferredPosition &&
                this.isPositionValid(triggerRect, tooltipRect, viewport, position, spacing)) {
                return position;
            }
        }

        // Fallback sur la position préférée
        return preferredPosition;
    }

    /**
     * Vérifier si une position est valide
     */
    isPositionValid(triggerRect, tooltipRect, viewport, position, spacing) {
        const coords = this.calculatePosition(triggerRect, tooltipRect, position);

        return coords.top >= 0 &&
            coords.left >= 0 &&
            coords.top + tooltipRect.height <= viewport.height &&
            coords.left + tooltipRect.width <= viewport.width;
    }

    /**
     * Calculer les coordonnées pour une position donnée
     */
    calculatePosition(triggerRect, tooltipRect, position) {
        const spacing = this.config.offset;
        let top, left;

        switch (position) {
            case 'top':
                top = triggerRect.top - tooltipRect.height - spacing;
                left = triggerRect.left + (triggerRect.width - tooltipRect.width) / 2;
                break;
            case 'bottom':
                top = triggerRect.bottom + spacing;
                left = triggerRect.left + (triggerRect.width - tooltipRect.width) / 2;
                break;
            case 'left':
                top = triggerRect.top + (triggerRect.height - tooltipRect.height) / 2;
                left = triggerRect.left - tooltipRect.width - spacing;
                break;
            case 'right':
                top = triggerRect.top + (triggerRect.height - tooltipRect.height) / 2;
                left = triggerRect.right + spacing;
                break;
            default:
                top = triggerRect.top - tooltipRect.height - spacing;
                left = triggerRect.left + (triggerRect.width - tooltipRect.width) / 2;
        }

        // Contraintes du viewport
        left = Math.max(8, Math.min(left, window.innerWidth - tooltipRect.width - 8));
        top = Math.max(8, Math.min(top, window.innerHeight - tooltipRect.height - 8));

        return { top, left };
    }

    /**
     * Obtenir le z-index approprié
     */
    getZIndex() {
        return this.config.zIndex || 10000;
    }

    /**
     * Améliorer un tooltip existant
     */
    enhanceTooltip(tooltipElement) {
        const trigger = tooltipElement.querySelector('.tooltip__trigger');
        const content = tooltipElement.querySelector('.tooltip__content');

        if (!trigger || !content) return;

        const tooltipId = ++this.tooltipId;

        // Configuration basée sur les classes
        const config = {
            content: content.textContent,
            position: this.extractPositionFromClass(content.className),
            theme: this.extractThemeFromClass(content.className),
            interactive: content.classList.contains('tooltip__content--interactive')
        };

        // Stocker la référence
        this.tooltips.set(tooltipId, {
            id: tooltipId,
            trigger: trigger,
            tooltip: content,
            config: config
        });

        // Ajouter les événements
        this.bindTooltipEvents(trigger, tooltipId);
    }

    /**
     * Extraire la position depuis les classes CSS
     */
    extractPositionFromClass(className) {
        const match = className.match(/tooltip__content--(\w+)/);
        return match && ['top', 'bottom', 'left', 'right'].includes(match[1]) ? match[1] : 'top';
    }

    /**
     * Extraire le thème depuis les classes CSS
     */
    extractThemeFromClass(className) {
        const themes = ['primary', 'success', 'warning', 'error'];
        for (const theme of themes) {
            if (className.includes(`tooltip__content--${theme}`)) {
                return theme;
            }
        }
        return 'dark';
    }

    /**
     * Gestion des événements globaux
     */
    handleKeyDown(e) {
        if (e.key === 'Escape' && this.activeTooltip) {
            this.hideTooltip(this.activeTooltip, true);
        }
    }

    handleScroll() {
        if (this.activeTooltip) {
            // Repositionner ou masquer selon les préférences
            const tooltip = this.tooltips.get(this.activeTooltip);
            if (tooltip) {
                this.positionTooltip(tooltip);
            }
        }
    }

    handleResize() {
        if (this.activeTooltip) {
            const tooltip = this.tooltips.get(this.activeTooltip);
            if (tooltip) {
                this.positionTooltip(tooltip);
            }
        }
    }

    /**
     * API publique
     */

    // Créer un tooltip programmatiquement
    create(trigger, options = {}) {
        if (typeof trigger === 'string') {
            trigger = document.querySelector(trigger);
        }

        if (!trigger) return null;

        return this.attachTooltip(trigger, options);
    }

    // Supprimer un tooltip
    remove(tooltipId) {
        const tooltip = this.tooltips.get(tooltipId);
        if (!tooltip) return false;

        // Masquer et supprimer
        this.hideTooltip(tooltipId, true);

        setTimeout(() => {
            if (tooltip.tooltip.parentNode) {
                tooltip.tooltip.remove();
            }
            this.tooltips.delete(tooltipId);
        }, this.config.animationDuration);

        return true;
    }

    // Mettre à jour le contenu d'un tooltip
    updateContent(tooltipId, content, title = null) {
        const tooltip = this.tooltips.get(tooltipId);
        if (!tooltip) return false;

        if (title) {
            tooltip.tooltip.classList.add('tooltip__content--with-title');
            tooltip.tooltip.innerHTML = `
                <div class="tooltip__title">${this.escapeHtml(title)}</div>
                <div class="tooltip__body">${this.escapeHtml(content)}</div>
            `;
        } else {
            tooltip.tooltip.classList.remove('tooltip__content--with-title');
            tooltip.tooltip.textContent = content;
        }

        return true;
    }

    // Montrer/masquer manuellement
    show(tooltipId) {
        this.showTooltip(tooltipId);
    }

    hide(tooltipId) {
        this.hideTooltip(tooltipId, true);
    }

    // Masquer tous les tooltips
    hideAll() {
        this.tooltips.forEach((tooltip, id) => {
            this.hideTooltip(id, true);
        });
    }

    // Configuration
    configure(options) {
        Object.assign(this.config, options);
    }

    // Utilitaires
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`tooltip:${eventName}`, {
            detail: {
                manager: this,
                ...detail
            }
        });
        document.dispatchEvent(event);
    }

    // Nettoyage
    destroy() {
        this.hideAll();

        // Supprimer les événements
        document.removeEventListener('keydown', this.handleKeyDown);
        document.removeEventListener('scroll', this.handleScroll);
        window.removeEventListener('resize', this.handleResize);

        // Supprimer tous les tooltips
        this.tooltips.forEach(tooltip => {
            if (tooltip.tooltip.parentNode) {
                tooltip.tooltip.remove();
            }
        });

        this.tooltips.clear();
    }
}

/**
 * API simplifiée
 */
let tooltip = {
    manager: null,

    init() {
        if (!this.manager) {
            this.manager = new TooltipManager();
        }
        return this.manager;
    },

    // Créer un tooltip
    create(element, options = {}) {
        return this.init().create(element, options);
    },

    // Attacher via data-tooltip
    attach(element, content, options = {}) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }

        if (element) {
            element.setAttribute('data-tooltip', content);
            return this.init().attachTooltip(element, options);
        }
        return null;
    },

    // Supprimer
    remove(id) {
        return this.manager?.remove(id);
    },

    // Mettre à jour
    update(id, content, title = null) {
        return this.manager?.updateContent(id, content, title);
    },

    // Contrôle manuel
    show(id) {
        return this.manager?.show(id);
    },

    hide(id) {
        return this.manager?.hide(id);
    },

    hideAll() {
        return this.manager?.hideAll();
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
    tooltip.init();

    // Dispatcher un événement
    document.dispatchEvent(new CustomEvent('tooltip:ready'));

    // Test en mode debug
    if (window.location.search.includes('debug=tooltip')) {
        console.log('Tooltip system ready');
    }
});

// Exposition globale
window.TooltipManager = TooltipManager;
window.tooltip = tooltip;

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { TooltipManager, tooltip };
}
