/**
 * Cookie Manager RGPD - Error Explorer
 * Gestion complète et conforme RGPD des cookies
 *
 * @author Error Explorer Team
 * @version 1.0.0
 * @license MIT
 */
export default class CookieManager {
    /**
     * Configuration par défaut
     */
    static get DEFAULTS() {
        return {
            showDelay: 1500,
            autoHide: false,
            position: 'bottom',
            theme: 'error-explorer',
            debugMode: false,
            consentDuration: 365,
            checkInterval: 30,
            apiEndpoint: '/api/cookies',
            enableAnalytics: true,
            enableMarketing: false
        };
    }

    /**
     * Catégories de cookies supportées
     */
    static get CATEGORIES() {
        return {
            ESSENTIAL: 'essential',
            PERFORMANCE: 'performance',
            FUNCTIONAL: 'functional',
            MARKETING: 'marketing'
        };
    }

    /**
     * Types de notifications
     */
    static get NOTIFICATION_TYPES() {
        return {
            SUCCESS: 'success',
            ERROR: 'error',
            WARNING: 'warning',
            INFO: 'info'
        };
    }

    /**
     * Constructeur
     * @param {Object} options - Configuration personnalisée
     */
    constructor(options = {}) {
        this.options = { ...CookieManager.DEFAULTS, ...options };
        this.isInitialized = false;

        // État des préférences
        this.cookiePreferences = {
            [CookieManager.CATEGORIES.ESSENTIAL]: true,
            [CookieManager.CATEGORIES.PERFORMANCE]: false,
            [CookieManager.CATEGORIES.FUNCTIONAL]: false,
            [CookieManager.CATEGORIES.MARKETING]: false
        };

        // État du consentement
        this.consentState = {
            given: false,
            date: null,
            version: '1.0.0',
            preferences: { ...this.cookiePreferences }
        };

        // Éléments DOM
        this.elements = {
            banner: null,
            modal: null,
            settingsBtn: null,
            notification: null
        };

        // Bind des méthodes
        this.handleAction = this.handleAction.bind(this);
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.handleVisibilityChange = this.handleVisibilityChange.bind(this);
        this.handleBeforeUnload = this.handleBeforeUnload.bind(this);

        // Initialisation
        this.init();
    }

    /**
     * Initialisation du gestionnaire
     */
    async init() {
        try {
            this.log('Initialisation du Cookie Manager...');

            // Vérifier si le DOM est prêt
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.initializeComponents());
            } else {
                this.initializeComponents();
            }

            this.isInitialized = true;
            this.log('Cookie Manager initialisé avec succès');
        } catch (error) {
            this.error('Erreur lors de l\'initialisation:', error);
        }
    }

    /**
     * Initialisation des composants
     */
    initializeComponents() {
        this.findElements();
        this.loadStoredPreferences();
        this.bindEvents();
        this.checkConsentValidity();

        if (!this.hasValidConsent()) {
            this.showBanner();
        } else {
            this.showSettingsButton();
            this.applyPreferences();
        }

        this.trackInitialization();
    }

    /**
     * Récupération des éléments DOM
     */
    findElements() {
        this.elements = {
            banner: document.getElementById('cookieBanner'),
            modal: document.getElementById('cookieModal'),
            settingsBtn: document.getElementById('cookieSettingsBtn'),
            notification: document.getElementById('cookieNotification')
        };

        // Vérifier que les éléments existent
        Object.entries(this.elements).forEach(([key, element]) => {
            if (!element) {
                this.warn(`Élément ${key} non trouvé dans le DOM`);
            }
        });
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Événements de clic pour les actions
        document.addEventListener('click', (event) => {
            const action = event.target.closest('[data-cookie-action]')?.dataset.cookieAction;
            if (action) {
                event.preventDefault();
                this.handleAction(action, event);
            }
        });

        // Événements pour les toggles de catégories
        document.addEventListener('change', (event) => {
            const category = event.target.dataset.cookieCategory;
            if (category && event.target.type === 'checkbox') {
                this.updateCategoryPreference(category, event.target.checked);
            }
        });

        // Événements clavier
        document.addEventListener('keydown', this.handleKeyDown);

        // Événements de visibilité
        document.addEventListener('visibilitychange', this.handleVisibilityChange);

        // Événement avant fermeture
        window.addEventListener('beforeunload', this.handleBeforeUnload);

        // Événements personnalisés
        this.setupCustomEvents();
    }

    /**
     * Configuration des événements personnalisés
     */
    setupCustomEvents() {
        // Écouter les changements de préférences depuis d'autres scripts
        window.addEventListener('cookiePreferencesChange', (event) => {
            this.log('Changement de préférences externe détecté:', event.detail);
            this.updatePreferences(event.detail);
        });

        // Écouter les demandes d'ouverture de la modal
        window.addEventListener('openCookieSettings', () => {
            this.openModal();
        });
    }

    /**
     * Gestionnaire d'actions
     * @param {string} action - Action à effectuer
     * @param {Event} event - Événement déclencheur
     */
    async handleAction(action, event) {
        this.log(`Action déclenchée: ${action}`);

        switch (action) {
            case 'accept-all':
                await this.acceptAllCookies();
                break;
            case 'reject-all':
                await this.rejectAllCookies();
                break;
            case 'customize':
            case 'open-settings':
                this.openModal();
                break;
            case 'save-preferences':
                await this.saveCustomPreferences();
                break;
            case 'close-modal':
                this.closeModal();
                break;
            case 'close-notification':
                this.closeNotification();
                break;
            default:
                this.warn(`Action non reconnue: ${action}`);
        }
    }

    /**
     * Gestionnaire d'événements clavier
     * @param {KeyboardEvent} event - Événement clavier
     */
    handleKeyDown(event) {
        // Fermer la modal avec Escape
        if (event.key === 'Escape' && this.isModalOpen()) {
            this.closeModal();
        }

        // Accessibility: Tab navigation dans la modal
        if (event.key === 'Tab' && this.isModalOpen()) {
            this.handleTabNavigation(event);
        }
    }

    /**
     * Gestion de la navigation Tab dans la modal
     * @param {KeyboardEvent} event - Événement clavier
     */
    handleTabNavigation(event) {
        const modal = this.elements.modal;
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey) {
            if (document.activeElement === firstElement) {
                lastElement.focus();
                event.preventDefault();
            }
        } else {
            if (document.activeElement === lastElement) {
                firstElement.focus();
                event.preventDefault();
            }
        }
    }

    /**
     * Gestionnaire de changement de visibilité
     */
    handleVisibilityChange() {
        if (document.hidden) {
            this.log('Page cachée - pause des timers');
        } else {
            this.log('Page visible - reprise des timers');
            this.checkConsentValidity();
        }
    }

    /**
     * Gestionnaire avant fermeture de page
     */
    handleBeforeUnload() {
        // Sauvegarder l'état si nécessaire
        if (this.hasUnsavedChanges()) {
            this.savePreferences();
        }
    }

    /**
     * Affichage du banner
     */
    showBanner() {
        if (!this.elements.banner) return;

        setTimeout(() => {
            this.elements.banner.classList.add('show');
            this.elements.banner.setAttribute('aria-hidden', 'false');

            // Focus sur le premier bouton pour l'accessibilité
            const firstButton = this.elements.banner.querySelector('button');
            if (firstButton) {
                firstButton.focus();
            }

            this.trackEvent('banner_shown');
        }, this.options.showDelay);
    }

    /**
     * Masquage du banner
     */
    hideBanner() {
        if (!this.elements.banner) return;

        this.elements.banner.classList.remove('show');
        this.elements.banner.setAttribute('aria-hidden', 'true');
        this.showSettingsButton();
        this.trackEvent('banner_hidden');
    }

    /**
     * Affichage du bouton de paramètres
     */
    showSettingsButton() {
        if (!this.elements.settingsBtn) return;

        this.elements.settingsBtn.style.display = 'flex';
        setTimeout(() => {
            this.elements.settingsBtn.classList.add('show');
        }, 300);
    }

    /**
     * Ouverture de la modal
     */
    openModal() {
        if (!this.elements.modal) return;

        this.elements.modal.classList.add('show');
        this.elements.modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        // Mettre à jour les toggles
        this.updateToggleStates();

        // Focus sur le premier élément focusable
        const firstFocusable = this.elements.modal.querySelector('button, input, select, textarea');
        if (firstFocusable) {
            firstFocusable.focus();
        }

        this.trackEvent('modal_opened');
    }

    /**
     * Fermeture de la modal
     */
    closeModal() {
        if (!this.elements.modal) return;

        this.elements.modal.classList.remove('show');
        this.elements.modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        this.trackEvent('modal_closed');
    }

    /**
     * Vérification si la modal est ouverte
     * @returns {boolean}
     */
    isModalOpen() {
        return this.elements.modal?.classList.contains('show') || false;
    }

    /**
     * Mise à jour des états des toggles
     */
    updateToggleStates() {
        Object.entries(this.cookiePreferences).forEach(([category, enabled]) => {
            const toggle = document.getElementById(`${category}-cookies`);
            if (toggle) {
                toggle.checked = enabled;
            }
        });
    }

    /**
     * Mise à jour d'une préférence de catégorie
     * @param {string} category - Catégorie de cookie
     * @param {boolean} enabled - État activé/désactivé
     */
    updateCategoryPreference(category, enabled) {
        if (category === CookieManager.CATEGORIES.ESSENTIAL) {
            this.warn('Les cookies essentiels ne peuvent pas être désactivés');
            return;
        }

        this.cookiePreferences[category] = enabled;
        this.log(`Préférence mise à jour: ${category} = ${enabled}`);

        // Émettre un événement personnalisé
        this.dispatchEvent('categoryChanged', { category, enabled });
    }

    /**
     * Accepter tous les cookies
     */
    async acceptAllCookies() {
        this.cookiePreferences = {
            [CookieManager.CATEGORIES.ESSENTIAL]: true,
            [CookieManager.CATEGORIES.PERFORMANCE]: true,
            [CookieManager.CATEGORIES.FUNCTIONAL]: true,
            [CookieManager.CATEGORIES.MARKETING]: true
        };

        await this.savePreferences();
        this.applyPreferences();
        this.hideBanner();
        this.closeModal();

        this.showNotification(
            'Tous les cookies ont été acceptés',
            CookieManager.NOTIFICATION_TYPES.SUCCESS
        );

        this.trackEvent('accept_all');
    }

    /**
     * Refuser tous les cookies optionnels
     */
    async rejectAllCookies() {
        this.cookiePreferences = {
            [CookieManager.CATEGORIES.ESSENTIAL]: true,
            [CookieManager.CATEGORIES.PERFORMANCE]: false,
            [CookieManager.CATEGORIES.FUNCTIONAL]: false,
            [CookieManager.CATEGORIES.MARKETING]: false
        };

        await this.savePreferences();
        this.applyPreferences();
        this.removeNonEssentialCookies();
        this.hideBanner();
        this.closeModal();

        this.showNotification(
            'Seuls les cookies essentiels sont actifs',
            CookieManager.NOTIFICATION_TYPES.INFO
        );

        this.trackEvent('reject_all');
    }

    /**
     * Sauvegarder les préférences personnalisées
     */
    async saveCustomPreferences() {
        await this.savePreferences();
        this.applyPreferences();
        this.closeModal();
        this.hideBanner();

        this.showNotification(
            'Vos préférences ont été sauvegardées',
            CookieManager.NOTIFICATION_TYPES.SUCCESS
        );

        this.trackEvent('save_custom', { preferences: this.cookiePreferences });
    }

    /**
     * Sauvegarde des préférences
     */
    async savePreferences() {
        this.consentState = {
            given: true,
            date: new Date().toISOString(),
            version: '1.0.0',
            preferences: { ...this.cookiePreferences }
        };

        try {
            // Sauvegarde locale
            localStorage.setItem('cookie-consent', JSON.stringify(this.consentState));
            localStorage.setItem('cookie-preferences', JSON.stringify(this.cookiePreferences));

            // Optionnel: Sauvegarde serveur
            if (this.options.apiEndpoint) {
                await this.savePreferencesToServer();
            }

            this.log('Préférences sauvegardées avec succès');
        } catch (error) {
            this.error('Erreur lors de la sauvegarde:', error);
        }
    }

    /**
     * Sauvegarde des préférences sur le serveur
     */
    async savePreferencesToServer() {
        try {
            const response = await fetch(this.options.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'save_preferences',
                    preferences: this.cookiePreferences,
                    consent: this.consentState
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            this.log('Préférences sauvegardées sur le serveur:', result);
        } catch (error) {
            this.warn('Impossible de sauvegarder sur le serveur:', error);
        }
    }

    /**
     * Chargement des préférences stockées
     */
    loadStoredPreferences() {
        try {
            // Charger le consentement
            const consentData = localStorage.getItem('cookie-consent');
            if (consentData) {
                this.consentState = { ...this.consentState, ...JSON.parse(consentData) };
            }

            // Charger les préférences
            const preferencesData = localStorage.getItem('cookie-preferences');
            if (preferencesData) {
                this.cookiePreferences = { ...this.cookiePreferences, ...JSON.parse(preferencesData) };
            }

            this.log('Préférences chargées:', this.cookiePreferences);
        } catch (error) {
            this.error('Erreur lors du chargement des préférences:', error);
        }
    }

    /**
     * Vérification de la validité du consentement
     */
    checkConsentValidity() {
        if (!this.consentState.given || !this.consentState.date) {
            return false;
        }

        const consentDate = new Date(this.consentState.date);
        const now = new Date();
        const daysDiff = Math.floor((now - consentDate) / (1000 * 60 * 60 * 24));

        if (daysDiff > this.options.consentDuration) {
            this.log('Consentement expiré, redemande nécessaire');
            this.resetConsent();
            return false;
        }

        return true;
    }

    /**
     * Vérification si le consentement est valide
     * @returns {boolean}
     */
    hasValidConsent() {
        return this.consentState.given && this.checkConsentValidity();
    }

    /**
     * Application des préférences de cookies
     */
    applyPreferences() {
        this.log('Application des préférences:', this.cookiePreferences);

        // Cookies de performance (Analytics)
        if (this.cookiePreferences[CookieManager.CATEGORIES.PERFORMANCE]) {
            this.enableGoogleAnalytics();
        } else {
            this.disableGoogleAnalytics();
        }

        // Cookies de fonctionnalité
        if (this.cookiePreferences[CookieManager.CATEGORIES.FUNCTIONAL]) {
            this.enableFunctionalCookies();
        } else {
            this.disableFunctionalCookies();
        }

        // Cookies marketing
        if (this.cookiePreferences[CookieManager.CATEGORIES.MARKETING]) {
            this.enableMarketingCookies();
        } else {
            this.disableMarketingCookies();
        }

        // Émettre un événement personnalisé
        this.dispatchEvent('preferencesApplied', {
            preferences: this.cookiePreferences,
            timestamp: new Date().toISOString()
        });
    }

    /**
     * Activation de Google Analytics
     */
    enableGoogleAnalytics() {
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'update', {
                'analytics_storage': 'granted'
            });
            this.log('Google Analytics activé');
        } else if (typeof ga !== 'undefined') {
            // Support Google Analytics Universal
            ga('set', 'anonymizeIp', false);
            this.log('Google Analytics Universal activé');
        }
    }

    /**
     * Désactivation de Google Analytics
     */
    disableGoogleAnalytics() {
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'update', {
                'analytics_storage': 'denied'
            });
            this.log('Google Analytics désactivé');
        }
    }

    /**
     * Activation des cookies de fonctionnalité
     */
    enableFunctionalCookies() {
        this.log('Cookies de fonctionnalité activés');

        // Exemples d'activation
        this.enableChatSupport();
        this.enableInteractiveMaps();
        this.enableUserPreferences();
    }

    /**
     * Désactivation des cookies de fonctionnalité
     */
    disableFunctionalCookies() {
        this.log('Cookies de fonctionnalité désactivés');

        // Exemples de désactivation
        this.disableChatSupport();
        this.disableInteractiveMaps();
    }

    /**
     * Activation des cookies marketing
     */
    enableMarketingCookies() {
        this.log('Cookies marketing activés');

        // Facebook Pixel
        if (typeof fbq !== 'undefined') {
            fbq('consent', 'grant');
        }

        // Google Ads
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'update', {
                'ad_storage': 'granted',
                'ad_user_data': 'granted',
                'ad_personalization': 'granted'
            });
        }
    }

    /**
     * Désactivation des cookies marketing
     */
    disableMarketingCookies() {
        this.log('Cookies marketing désactivés');

        // Facebook Pixel
        if (typeof fbq !== 'undefined') {
            fbq('consent', 'revoke');
        }

        // Google Ads
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'update', {
                'ad_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied'
            });
        }
    }

    /**
     * Exemples de services de fonctionnalité
     */
    enableChatSupport() {
        // Exemple d'activation du chat
        if (window.Intercom) {
            window.Intercom('boot', { app_id: 'your-app-id' });
        }
    }

    disableChatSupport() {
        // Exemple de désactivation du chat
        if (window.Intercom) {
            window.Intercom('shutdown');
        }
    }

    enableInteractiveMaps() {
        // Activation des cartes interactives
        document.querySelectorAll('.interactive-map').forEach(map => {
            map.style.display = 'block';
        });
    }

    disableInteractiveMaps() {
        // Désactivation des cartes interactives
        document.querySelectorAll('.interactive-map').forEach(map => {
            map.style.display = 'none';
        });
    }

    enableUserPreferences() {
        // Activation des préférences utilisateur avancées
        this.log('Préférences utilisateur avancées activées');
    }

    /**
     * Suppression des cookies non essentiels
     */
    removeNonEssentialCookies() {
        const cookiesToRemove = [
            // Google Analytics
            '_ga', '_gid', '_gat', '_gat_UA-', '_gat_gtag_UA_',
            // Google Analytics 4
            '_ga_', '_gac_', '_gac_UA-',
            // Facebook
            '_fbp', '_fbc', 'fr', 'tr',
            // Google Ads
            '_gcl_au', '_gcl_aw', '_gcl_dc', '_gcl_gb', '_gcl_gf', '_gcl_ha',
            // Google DoubleClick
            'IDE', 'DSID', 'FLC', '1P_JAR', 'AID', 'TAID', 'exchange_uid',
            // Autres services
            '__utma', '__utmb', '__utmc', '__utmz', '__utmv', '__utmt',
            'collect', '_collect'
        ];

        cookiesToRemove.forEach(cookieName => {
            this.removeCookie(cookieName);
        });

        this.log('Cookies non essentiels supprimés');
    }

    /**
     * Suppression d'un cookie spécifique
     * @param {string} name - Nom du cookie
     */
    removeCookie(name) {
        const domains = [
            '',
            `.${window.location.hostname}`,
            window.location.hostname
        ];

        const paths = ['/', '/dashboard', '/admin'];

        domains.forEach(domain => {
            paths.forEach(path => {
                document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=${path}${domain ? `; domain=${domain}` : ''}`;
            });
        });
    }

    /**
     * Affichage d'une notification
     * @param {string} message - Message à afficher
     * @param {string} type - Type de notification
     * @param {number} duration - Durée d'affichage en ms
     */
    showNotification(message, type = CookieManager.NOTIFICATION_TYPES.INFO, duration = 4000) {
        if (!this.elements.notification) {
            this.createNotificationElement();
        }

        const notification = this.elements.notification;
        const iconElement = notification.querySelector('.cookie-notification__icon');
        const messageElement = notification.querySelector('.cookie-notification__message');

        // Définir l'icône selon le type
        const icons = {
            [CookieManager.NOTIFICATION_TYPES.SUCCESS]: '✓',
            [CookieManager.NOTIFICATION_TYPES.ERROR]: '✕',
            [CookieManager.NOTIFICATION_TYPES.WARNING]: '⚠',
            [CookieManager.NOTIFICATION_TYPES.INFO]: 'ℹ'
        };

        iconElement.textContent = icons[type] || icons.info;
        messageElement.textContent = message;

        // Appliquer la classe de type
        notification.className = `cookie-notification cookie-notification--${type}`;

        // Afficher la notification
        notification.style.display = 'flex';
        setTimeout(() => notification.classList.add('show'), 10);

        // Masquer automatiquement
        setTimeout(() => {
            this.closeNotification();
        }, duration);

        this.trackEvent('notification_shown', { message, type });
    }

    /**
     * Création de l'élément de notification s'il n'existe pas
     */
    createNotificationElement() {
        if (this.elements.notification) return;

        const notification = document.createElement('div');
        notification.id = 'cookieNotification';
        notification.className = 'cookie-notification';
        notification.style.display = 'none';
        notification.setAttribute('role', 'alert');
        notification.setAttribute('aria-live', 'polite');

        notification.innerHTML = `
            <div class="cookie-notification__content">
                <span class="cookie-notification__icon"></span>
                <span class="cookie-notification__message"></span>
            </div>
            <button type="button" 
                    class="cookie-notification__close" 
                    data-cookie-action="close-notification"
                    aria-label="Fermer la notification">
                &times;
            </button>
        `;

        document.body.appendChild(notification);
        this.elements.notification = notification;
    }

    /**
     * Fermeture de la notification
     */
    closeNotification() {
        if (!this.elements.notification) return;

        this.elements.notification.classList.remove('show');
        setTimeout(() => {
            this.elements.notification.style.display = 'none';
        }, 300);
    }

    /**
     * Vérification des changements non sauvegardés
     * @returns {boolean}
     */
    hasUnsavedChanges() {
        const stored = localStorage.getItem('cookie-preferences');
        if (!stored) return true;

        try {
            const storedPrefs = JSON.parse(stored);
            return JSON.stringify(storedPrefs) !== JSON.stringify(this.cookiePreferences);
        } catch {
            return true;
        }
    }

    /**
     * Réinitialisation du consentement
     */
    resetConsent() {
        localStorage.removeItem('cookie-consent');
        localStorage.removeItem('cookie-preferences');
        this.removeNonEssentialCookies();

        this.consentState = {
            given: false,
            date: null,
            version: '1.0.0',
            preferences: {
                [CookieManager.CATEGORIES.ESSENTIAL]: true,
                [CookieManager.CATEGORIES.PERFORMANCE]: false,
                [CookieManager.CATEGORIES.FUNCTIONAL]: false,
                [CookieManager.CATEGORIES.MARKETING]: false
            }
        };

        this.cookiePreferences = { ...this.consentState.preferences };
        this.log('Consentement réinitialisé');
    }

    /**
     * Mise à jour des préférences depuis l'externe
     * @param {Object} newPreferences - Nouvelles préférences
     */
    updatePreferences(newPreferences) {
        this.cookiePreferences = { ...this.cookiePreferences, ...newPreferences };
        this.updateToggleStates();
        this.applyPreferences();
        this.savePreferences();
    }

    /**
     * Émission d'un événement personnalisé
     * @param {string} eventName - Nom de l'événement
     * @param {Object} detail - Détails de l'événement
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`cookie${eventName.charAt(0).toUpperCase() + eventName.slice(1)}`, {
            detail: {
                ...detail,
                preferences: this.cookiePreferences,
                consentState: this.consentState,
                timestamp: new Date().toISOString()
            }
        });

        window.dispatchEvent(event);
        this.log(`Événement émis: ${event.type}`, event.detail);
    }

    /**
     * Suivi des événements analytics
     * @param {string} eventName - Nom de l'événement
     * @param {Object} data - Données supplémentaires
     */
    trackEvent(eventName, data = {}) {
        if (!this.options.enableAnalytics) return;

        const eventData = {
            event: `cookie_${eventName}`,
            cookie_manager_version: '1.0.0',
            ...data
        };

        // Google Analytics 4
        if (typeof gtag !== 'undefined' && this.cookiePreferences[CookieManager.CATEGORIES.PERFORMANCE]) {
            gtag('event', eventData.event, eventData);
        }

        // Événement personnalisé pour d'autres trackers
        this.dispatchEvent('tracked', eventData);

        this.log(`Événement tracké: ${eventName}`, eventData);
    }

    /**
     * Suivi de l'initialisation
     */
    trackInitialization() {
        this.trackEvent('initialized', {
            has_consent: this.hasValidConsent(),
            preferences: this.cookiePreferences,
            user_agent: navigator.userAgent,
            timestamp: new Date().toISOString()
        });
    }

    /**
     * API publique - Vérification du consentement
     * @param {string} category - Catégorie spécifique (optionnel)
     * @returns {boolean}
     */
    hasConsent(category = null) {
        if (!this.hasValidConsent()) return false;

        if (category) {
            return this.cookiePreferences[category] === true;
        }

        return this.consentState.given;
    }

    /**
     * API publique - Obtenir les préférences
     * @returns {Object}
     */
    getPreferences() {
        return { ...this.cookiePreferences };
    }

    /**
     * API publique - Obtenir l'état du consentement
     * @returns {Object}
     */
    getConsentState() {
        return { ...this.consentState };
    }

    /**
     * API publique - Forcer la réouverture du banner
     */
    showConsentBanner() {
        this.hideBanner();
        setTimeout(() => this.showBanner(), 100);
    }

    /**
     * API publique - Destruction propre
     */
    destroy() {
        // Supprimer les événements
        document.removeEventListener('click', this.handleAction);
        document.removeEventListener('keydown', this.handleKeyDown);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        window.removeEventListener('beforeunload', this.handleBeforeUnload);

        // Supprimer les éléments DOM créés dynamiquement
        if (this.elements.notification && !document.getElementById('cookieNotification')) {
            this.elements.notification.remove();
        }

        // Nettoyer les références
        this.elements = {};
        this.isInitialized = false;

        this.log('Cookie Manager détruit');
    }

    /**
     * Méthodes de logging
     */
    log(message, data = null) {
        if (this.options.debugMode) {
            console.log(`[CookieManager] ${message}`, data || '');
        }
    }

    warn(message, data = null) {
        if (this.options.debugMode) {
            console.warn(`[CookieManager] ${message}`, data || '');
        }
    }

    error(message, data = null) {
        console.error(`[CookieManager] ${message}`, data || '');
    }

    /**
     * Méthodes statiques utilitaires
     */

    /**
     * Vérification du support des cookies
     * @returns {boolean}
     */
    static isCookieSupported() {
        try {
            document.cookie = 'cookietest=1';
            const supported = document.cookie.indexOf('cookietest=') !== -1;
            document.cookie = 'cookietest=1; expires=Thu, 01-Jan-1970 00:00:01 GMT';
            return supported;
        } catch {
            return false;
        }
    }

    /**
     * Vérification du support du localStorage
     * @returns {boolean}
     */
    static isLocalStorageSupported() {
        try {
            const test = 'localStorage-test';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return true;
        } catch {
            return false;
        }
    }

    /**
     * Obtention de la version du navigateur
     * @returns {Object}
     */
    static getBrowserInfo() {
        const ua = navigator.userAgent;
        const browsers = {
            chrome: /chrome/i,
            firefox: /firefox/i,
            safari: /safari/i,
            edge: /edge/i,
            ie: /trident/i
        };

        for (const [name, regex] of Object.entries(browsers)) {
            if (regex.test(ua)) {
                return { name, userAgent: ua };
            }
        }

        return { name: 'unknown', userAgent: ua };
    }

    /**
     * Vérification de la conformité RGPD
     * @returns {Object}
     */
    static checkGDPRCompliance() {
        return {
            cookieSupport: CookieManager.isCookieSupported(),
            localStorageSupport: CookieManager.isLocalStorageSupported(),
            browserInfo: CookieManager.getBrowserInfo(),
            doNotTrack: navigator.doNotTrack === '1',
            timestamp: new Date().toISOString()
        };
    }
}

// Export de la classe
export { CookieManager };

// Auto-initialisation si on est dans un environnement de navigateur
if (typeof window !== 'undefined') {
    // Rendre disponible globalement
    window.CookieManager = CookieManager;

    // Auto-initialisation avec configuration par défaut
    document.addEventListener('DOMContentLoaded', () => {
        // Vérifier si l'auto-initialisation est désactivée
        if (document.body.dataset.cookieAutoInit !== 'false') {
            window.cookieManager = new CookieManager({
                debugMode: document.body.dataset.cookieDebug === 'true'
            });
        }
    });
}

/**
 * Fonctions utilitaires globales pour faciliter l'utilisation
 */

/**
 * Vérifier si un type de cookie est autorisé
 * @param {string} category - Catégorie de cookie
 * @returns {boolean}
 */
window.cookieAllowed = function(category) {
    return window.cookieManager?.hasConsent(category) || false;
};

/**
 * Obtenir toutes les préférences de cookies
 * @returns {Object}
 */
window.getCookiePreferences = function() {
    return window.cookieManager?.getPreferences() || {};
};

/**
 * Ouvrir les paramètres de cookies
 */
window.openCookieSettings = function() {
    if (window.cookieManager) {
        window.cookieManager.openModal();
    }
};

/**
 * Réinitialiser le consentement des cookies
 */
window.resetCookieConsent = function() {
    if (window.cookieManager) {
        window.cookieManager.resetConsent();
        location.reload();
    }
};
