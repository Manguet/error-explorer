/**
 * Loading States Manager - Error Explorer
 * Gestion centralisée de tous les états de chargement
 */
class LoadingManager {
    constructor() {
        // Configuration par défaut
        this.config = {
            defaultSpinnerType: 'circle',
            defaultSize: 'md',
            defaultColor: 'primary',
            overlayZIndex: 9998,
            animationDuration: 300
        };

        // État
        this.activeLoadings = new Map();
        this.loadingId = 0;
        this.overlays = new Map();

        this.init();
    }

    /**
     * Initialisation
     */
    init() {
        this.createStyles();
        this.bindEvents();
    }

    /**
     * Création des styles dynamiques si nécessaire
     */
    createStyles() {
        if (document.getElementById('loading-manager-styles')) return;

        const style = document.createElement('style');
        style.id = 'loading-manager-styles';
        style.textContent = `
            .loading-transition {
                transition: all ${this.config.animationDuration}ms ease;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Gestion du focus trap pour les overlays
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
    }

    // ===== SPINNERS =====

    /**
     * Créer un spinner
     */
    createSpinner(options = {}) {
        const config = {
            type: this.config.defaultSpinnerType,
            size: this.config.defaultSize,
            color: this.config.defaultColor,
            className: '',
            ...options
        };

        const spinner = document.createElement('div');
        spinner.className = `loading__spinner loading__spinner--${config.size} ${config.className}`.trim();

        const spinnerContent = this.createSpinnerContent(config.type, config.color);
        spinner.appendChild(spinnerContent);

        return spinner;
    }

    /**
     * Créer le contenu du spinner selon le type
     */
    createSpinnerContent(type, color) {
        const element = document.createElement('div');

        switch (type) {
            case 'circle':
                element.className = `loading__circle loading__circle--${color}`;
                break;

            case 'dots':
                element.className = 'loading__dots';
                for (let i = 0; i < 3; i++) {
                    const dot = document.createElement('div');
                    dot.className = 'loading__dots-dot';
                    element.appendChild(dot);
                }
                break;

            case 'pulse':
                element.className = 'loading__pulse';
                element.style.color = this.getColorValue(color);
                break;

            case 'bars':
                element.className = 'loading__bars';
                for (let i = 0; i < 4; i++) {
                    const bar = document.createElement('div');
                    bar.className = 'loading__bars-bar';
                    element.appendChild(bar);
                }
                break;

            case 'ring':
                element.className = 'loading__ring';
                element.style.color = this.getColorValue(color);
                break;

            default:
                element.className = `loading__circle loading__circle--${color}`;
        }

        return element;
    }

    /**
     * Obtenir la valeur CSS d'une couleur
     */
    getColorValue(color) {
        const colors = {
            primary: '#3b82f6',
            success: '#10b981',
            warning: '#f59e0b',
            error: '#ef4444',
            white: '#ffffff'
        };
        return colors[color] || colors.primary;
    }

    // ===== BOUTONS =====

    /**
     * Activer l'état de chargement sur un bouton
     */
    setButtonLoading(button, options = {}) {
        if (typeof button === 'string') {
            button = document.querySelector(button);
        }

        if (!button) return null;

        const loadingId = ++this.loadingId;

        // Sauvegarder l'état original
        const originalState = {
            disabled: button.disabled,
            innerHTML: button.innerHTML,
            className: button.className
        };

        // Appliquer l'état de chargement
        button.disabled = true;
        button.classList.add('btn--loading');

        // Ajouter le spinner si demandé
        if (options.showSpinner) {
            const spinner = this.createSpinner({
                size: 'sm',
                type: 'circle',
                className: 'btn__spinner'
            });
            button.insertBefore(spinner, button.firstChild);
        }

        // Changer le texte si fourni
        if (options.text) {
            const textNode = Array.from(button.childNodes)
                .find(node => node.nodeType === Node.TEXT_NODE);
            if (textNode) {
                textNode.textContent = options.text;
            }
        }

        // Stocker l'état
        this.activeLoadings.set(loadingId, {
            type: 'button',
            element: button,
            originalState,
            options
        });

        return loadingId;
    }

    /**
     * Désactiver l'état de chargement d'un bouton
     */
    unsetButtonLoading(loadingId) {
        const loading = this.activeLoadings.get(loadingId);
        if (!loading || loading.type !== 'button') return false;

        const { element: button, originalState } = loading;

        // Restaurer l'état original
        button.disabled = originalState.disabled;
        button.className = originalState.className;
        button.innerHTML = originalState.innerHTML;

        // Nettoyer
        this.activeLoadings.delete(loadingId);
        return true;
    }

    // ===== OVERLAYS =====

    /**
     * Afficher un overlay de chargement pleine page
     */
    showPageOverlay(options = {}) {
        const config = {
            text: 'Chargement...',
            subtext: '',
            spinner: {
                type: 'circle',
                size: 'xl',
                color: 'white'
            },
            backdrop: true,
            ...options
        };

        const loadingId = ++this.loadingId;
        const overlay = this.createOverlayElement('page', config);

        document.body.appendChild(overlay);

        // Animation d'entrée
        requestAnimationFrame(() => {
            overlay.classList.add('loading-overlay--show');
        });

        // Désactiver le scroll du body
        document.body.style.overflow = 'hidden';

        this.overlays.set(loadingId, {
            type: 'page',
            element: overlay,
            config
        });

        return loadingId;
    }

    /**
     * Afficher un overlay sur un conteneur spécifique
     */
    showContainerOverlay(container, options = {}) {
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }

        if (!container) return null;

        const config = {
            text: 'Chargement...',
            spinner: {
                type: 'circle',
                size: 'md',
                color: 'primary'
            },
            dark: false,
            ...options
        };

        const loadingId = ++this.loadingId;

        // S'assurer que le conteneur est en position relative
        if (getComputedStyle(container).position === 'static') {
            container.style.position = 'relative';
        }

        // Ajouter la classe loading-container si nécessaire
        if (!container.classList.contains('loading-container')) {
            container.classList.add('loading-container');
        }

        const overlay = this.createOverlayElement('container', config);
        container.appendChild(overlay);

        // Animation d'entrée
        requestAnimationFrame(() => {
            overlay.classList.add('loading-container__overlay--show');
        });

        this.overlays.set(loadingId, {
            type: 'container',
            element: overlay,
            container,
            config
        });

        return loadingId;
    }

    /**
     * Créer un élément overlay
     */
    createOverlayElement(type, config) {
        const overlay = document.createElement('div');

        if (type === 'page') {
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div class="loading-overlay__content">
                    <div class="loading-overlay__spinner"></div>
                    <div class="loading-overlay__text">${config.text}</div>
                    ${config.subtext ? `<div class="loading-overlay__subtext">${config.subtext}</div>` : ''}
                </div>
            `;
        } else {
            overlay.className = `loading-container__overlay${config.dark ? ' loading-container__overlay--dark' : ''}`;
            overlay.innerHTML = `
                <div class="loading-container__spinner"></div>
                <div class="loading-container__text">${config.text}</div>
            `;
        }

        // Ajouter le spinner
        const spinnerContainer = overlay.querySelector(
            type === 'page' ? '.loading-overlay__spinner' : '.loading-container__spinner'
        );

        if (spinnerContainer) {
            const spinner = this.createSpinner(config.spinner);
            spinnerContainer.appendChild(spinner);
        }

        return overlay;
    }

    /**
     * Masquer un overlay
     */
    hideOverlay(loadingId) {
        const overlay = this.overlays.get(loadingId);
        if (!overlay) return false;

        const { element, type, container } = overlay;

        // Animation de sortie
        if (type === 'page') {
            element.classList.remove('loading-overlay--show');
            document.body.style.overflow = '';
        } else {
            element.classList.remove('loading-container__overlay--show');
        }

        // Supprimer après l'animation
        setTimeout(() => {
            if (element.parentNode) {
                element.remove();
            }

            // Nettoyer la classe du conteneur si plus d'overlay
            if (type === 'container' && container) {
                const remainingOverlays = container.querySelectorAll('.loading-container__overlay');
                if (remainingOverlays.length === 0) {
                    container.classList.remove('loading-container');
                }
            }
        }, this.config.animationDuration);

        this.overlays.delete(loadingId);
        return true;
    }

    // ===== SKELETON LOADERS =====

    /**
     * Créer un skeleton loader
     */
    createSkeleton(type, options = {}) {
        const element = document.createElement('div');
        element.className = `skeleton skeleton--${type}`;

        if (options.className) {
            element.className += ` ${options.className}`;
        }

        if (options.style) {
            Object.assign(element.style, options.style);
        }

        return element;
    }

    /**
     * Remplacer un élément par un skeleton
     */
    skeletonize(element, skeletonType = 'text') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }

        if (!element) return null;

        const loadingId = ++this.loadingId;
        const originalElement = element.cloneNode(true);

        // Créer le skeleton avec les mêmes dimensions
        const skeleton = this.createSkeleton(skeletonType, {
            style: {
                width: element.offsetWidth + 'px',
                height: element.offsetHeight + 'px'
            }
        });

        // Remplacer l'élément
        element.parentNode.replaceChild(skeleton, element);

        this.activeLoadings.set(loadingId, {
            type: 'skeleton',
            skeleton,
            originalElement,
            parent: skeleton.parentNode
        });

        return loadingId;
    }

    /**
     * Restaurer un élément depuis son skeleton
     */
    unskeleton(loadingId) {
        const loading = this.activeLoadings.get(loadingId);
        if (!loading || loading.type !== 'skeleton') return false;

        const { skeleton, originalElement, parent } = loading;

        // Restaurer l'élément original
        if (parent && skeleton.parentNode === parent) {
            parent.replaceChild(originalElement, skeleton);
        }

        this.activeLoadings.delete(loadingId);
        return true;
    }

    // ===== PROGRESS BARS =====

    /**
     * Créer une barre de progression
     */
    createProgressBar(options = {}) {
        const config = {
            progress: 0,
            color: 'primary',
            indeterminate: false,
            className: '',
            ...options
        };

        const container = document.createElement('div');
        container.className = `progress ${config.className}`.trim();

        if (config.indeterminate) {
            container.classList.add('progress--indeterminate');
        }

        const bar = document.createElement('div');
        bar.className = `progress__bar progress__bar--${config.color}`;
        bar.style.width = `${config.progress}%`;

        container.appendChild(bar);

        return {
            element: container,
            bar,
            setProgress: (progress) => {
                bar.style.width = `${Math.max(0, Math.min(100, progress))}%`;
            },
            setColor: (color) => {
                bar.className = bar.className.replace(/progress__bar--\w+/, `progress__bar--${color}`);
            }
        };
    }

    // ===== UTILITAIRES =====

    /**
     * Gestion des touches clavier pour les overlays
     */
    handleKeyDown(e) {
        if (e.key === 'Escape' && this.overlays.size > 0) {
            // Permettre de fermer le dernier overlay avec Échap
            const lastOverlay = Array.from(this.overlays.entries()).pop();
            if (lastOverlay && lastOverlay[1].config.closable !== false) {
                this.hideOverlay(lastOverlay[0]);
            }
        }
    }

    /**
     * Masquer tous les états de chargement
     */
    hideAll() {
        // Masquer tous les overlays
        const overlayIds = Array.from(this.overlays.keys());
        overlayIds.forEach(id => this.hideOverlay(id));

        // Désactiver tous les boutons en loading
        const buttonIds = Array.from(this.activeLoadings.entries())
            .filter(([, loading]) => loading.type === 'button')
            .map(([id]) => id);
        buttonIds.forEach(id => this.unsetButtonLoading(id));

        // Restaurer tous les skeletons
        const skeletonIds = Array.from(this.activeLoadings.entries())
            .filter(([, loading]) => loading.type === 'skeleton')
            .map(([id]) => id);
        skeletonIds.forEach(id => this.unskeleton(id));
    }

    /**
     * Obtenir l'état des chargements actifs
     */
    getActiveLoadings() {
        return {
            buttons: Array.from(this.activeLoadings.entries())
                .filter(([, loading]) => loading.type === 'button')
                .length,
            overlays: this.overlays.size,
            skeletons: Array.from(this.activeLoadings.entries())
                .filter(([, loading]) => loading.type === 'skeleton')
                .length
        };
    }

    /**
     * Configuration
     */
    configure(options) {
        Object.assign(this.config, options);
    }

    /**
     * Nettoyage
     */
    destroy() {
        this.hideAll();
        document.removeEventListener('keydown', this.handleKeyDown);

        const styles = document.getElementById('loading-manager-styles');
        if (styles) styles.remove();
    }
}

/**
 * API simplifiée pour une utilisation facile
 */
let loading = {
    manager: null,

    init() {
        if (!this.manager) {
            this.manager = new LoadingManager();
        }
        return this.manager;
    },

    // Boutons
    button(button, options = {}) {
        return this.init().setButtonLoading(button, options);
    },

    unbutton(id) {
        return this.manager?.unsetButtonLoading(id);
    },

    // Overlays
    page(options = {}) {
        return this.init().showPageOverlay(options);
    },

    container(container, options = {}) {
        return this.init().showContainerOverlay(container, options);
    },

    hide(id) {
        return this.manager?.hideOverlay(id);
    },

    // Skeletons
    skeleton(element, type = 'text') {
        return this.init().skeletonize(element, type);
    },

    unskeleton(id) {
        return this.manager?.unskeleton(id);
    },

    // Progress
    progress(options = {}) {
        return this.init().createProgressBar(options);
    },

    // Spinners
    spinner(options = {}) {
        return this.init().createSpinner(options);
    },

    // Utilitaires
    hideAll() {
        return this.manager?.hideAll();
    },

    configure(options) {
        this.init().configure(options);
    }
};

/**
 * Initialisation automatique
 */
document.addEventListener('DOMContentLoaded', () => {
    // Auto-init si pas déjà fait
    loading.init();

    // Test en mode debug
    if (window.location.search.includes('debug=loading')) {
        console.log('Loading Manager initialized');

        // Démonstration
        setTimeout(() => {
            const pageId = loading.page({
                text: 'Chargement de la démo...',
                subtext: 'Veuillez patienter'
            });

            setTimeout(() => {
                loading.hide(pageId);
                notify.success('Démo de loading terminée !');
            }, 3000);
        }, 1000);
    }
});

// Exposition globale
window.LoadingManager = LoadingManager;
window.loading = loading;

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { LoadingManager, loading };
}
