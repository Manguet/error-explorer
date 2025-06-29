/**
 * EmailVerificationComponent - BEM Architecture
 * Gestion de la page de vérification d'email avec animations et interactions
 */
class EmailVerificationComponent {
    constructor() {
        // Éléments DOM
        this.container = document.querySelector('.email-verification');
        this.resendForms = document.querySelectorAll('[data-resend-form]');
        this.emailInput = document.getElementById('email');
        this.toastContainer = document.getElementById('toast-container');

        // Configuration
        this.config = {
            animationDelay: 100,
            toastDuration: 5000,
            loadingTimeout: 3000,
            retryDelay: 30000 // 30 secondes entre les renvois
        };

        // État
        this.lastResendTime = 0;
        this.animationObserver = null;

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.container) return;

        this.setupIntersectionObserver();
        this.bindEvents();
        this.initializeAnimations();
        this.focusEmailInput();
        this.checkResendCooldown();
    }

    /**
     * Configuration de l'observateur d'intersection pour les animations
     */
    setupIntersectionObserver() {
        const options = {
            root: null,
            rootMargin: '0px 0px -50px 0px',
            threshold: 0.1
        };

        this.animationObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('animate-in');
                    }, index * this.config.animationDelay);
                }
            });
        }, options);

        // Observer les éléments à animer
        const elementsToObserve = [
            '.steps-guide__item',
            '.benefit-item',
            '.help-box',
            '.flash-alert'
        ];

        elementsToObserve.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                this.animationObserver.observe(element);
            });
        });
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Gestion des formulaires de renvoi
        this.resendForms.forEach(form => {
            const submitBtn = form.querySelector('[data-resend-btn]');

            if (submitBtn) {
                form.addEventListener('submit', (e) => {
                    this.handleResendSubmit(e, form, submitBtn);
                });
            }
        });

        // Validation temps réel de l'email
        if (this.emailInput) {
            this.emailInput.addEventListener('input', this.handleEmailInput.bind(this));
            this.emailInput.addEventListener('blur', this.handleEmailBlur.bind(this));
        }

        // Gestion des raccourcis clavier
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
    }

    /**
     * Gestion de la soumission du formulaire de renvoi
     */
    handleResendSubmit(event, form, button) {
        // Vérifier le cooldown
        const now = Date.now();
        if (now - this.lastResendTime < this.config.retryDelay) {
            event.preventDefault();
            const remainingTime = Math.ceil((this.config.retryDelay - (now - this.lastResendTime)) / 1000);
            this.showToast(`Veuillez patienter ${remainingTime} secondes avant de renvoyer l'email`, 'warning');
            return;
        }

        // Validation de l'email si présent
        const emailField = form.querySelector('input[type="email"]');
        if (emailField && !this.isValidEmail(emailField.value)) {
            event.preventDefault();
            this.showToast('Veuillez entrer une adresse email valide', 'error');
            emailField.focus();
            return;
        }

        // Démarrer l'animation de chargement
        this.setButtonLoading(button, true);
        this.lastResendTime = now;

        // Timeout de sécurité
        setTimeout(() => {
            this.setButtonLoading(button, false);
        }, this.config.loadingTimeout);
    }

    /**
     * Gestion de la saisie dans le champ email
     */
    handleEmailInput(event) {
        const input = event.target;
        const isValid = this.isValidEmail(input.value);

        // Mise à jour visuelle de la validation
        if (input.value.length > 0) {
            if (isValid) {
                input.classList.remove('error');
                input.classList.add('valid');
            } else {
                input.classList.remove('valid');
                input.classList.add('error');
            }
        } else {
            input.classList.remove('valid', 'error');
        }
    }

    /**
     * Gestion de la perte de focus du champ email
     */
    handleEmailBlur(event) {
        const input = event.target;
        if (input.value.length > 0 && !this.isValidEmail(input.value)) {
            this.showValidationError(input, 'Veuillez entrer une adresse email valide');
        }
    }

    /**
     * Gestion des raccourcis clavier
     */
    handleKeyDown(event) {
        // Échap pour fermer les toasts
        if (event.key === 'Escape') {
            this.closeAllToasts();
        }

        // Entrée pour soumettre si un champ email est focusé
        if (event.key === 'Enter' && event.target.type === 'email') {
            const form = event.target.closest('form');
            if (form) {
                form.submit();
            }
        }
    }

    /**
     * Mise en état de chargement d'un bouton
     */
    setButtonLoading(button, loading) {
        const btnText = button.querySelector('.btn-primary__text, .btn-secondary__text');
        const btnLoader = button.querySelector('.btn-primary__loader, .btn-secondary__loader');
        const btnIcon = button.querySelector('.btn-primary__icon, .btn-secondary__icon');

        if (loading) {
            button.classList.add('loading');
            button.disabled = true;

            if (btnText) btnText.style.opacity = '0';
            if (btnLoader) btnLoader.style.display = 'block';
            if (btnIcon) btnIcon.style.opacity = '0';
        } else {
            button.classList.remove('loading');
            button.disabled = false;

            if (btnText) btnText.style.opacity = '1';
            if (btnLoader) btnLoader.style.display = 'none';
            if (btnIcon) btnIcon.style.opacity = '1';
        }
    }

    /**
     * Validation d'une adresse email
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email.trim());
    }

    /**
     * Affichage d'une erreur de validation
     */
    showValidationError(input, message) {
        // Supprimer l'erreur existante
        const existingError = input.parentNode.querySelector('.validation-error');
        if (existingError) {
            existingError.remove();
        }

        // Créer et afficher l'erreur
        const errorElement = document.createElement('div');
        errorElement.className = 'validation-error';
        errorElement.textContent = message;

        input.parentNode.appendChild(errorElement);

        // Animation d'apparition
        setTimeout(() => {
            errorElement.classList.add('show');
        }, 10);

        // Supprimer après 5 secondes
        setTimeout(() => {
            if (errorElement.parentNode) {
                errorElement.classList.remove('show');
                setTimeout(() => {
                    errorElement.remove();
                }, 300);
            }
        }, 5000);
    }

    /**
     * Affichage d'un toast de notification
     */
    showToast(message, type = 'info', duration = this.config.toastDuration) {
        if (!this.toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `
            <div class="toast__content">
                <span class="toast__message">${this.escapeHtml(message)}</span>
                <button class="toast__close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        this.toastContainer.appendChild(toast);

        // Animation d'entrée
        setTimeout(() => {
            toast.classList.add('toast--show');
        }, 10);

        // Suppression automatique
        setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.remove('toast--show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }, duration);

        // Dispatch d'événement personnalisé
        this.dispatchEvent('toastShown', { message, type });
    }

    /**
     * Fermeture de tous les toasts
     */
    closeAllToasts() {
        const toasts = this.toastContainer.querySelectorAll('.toast');
        toasts.forEach(toast => {
            toast.classList.remove('toast--show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        });
    }

    /**
     * Initialisation des animations d'entrée
     */
    initializeAnimations() {
        const elementsToAnimate = [
            '.email-verification__header',
            '.verification-success',
            '.verification-error',
            '.verification-pending'
        ];

        elementsToAnimate.forEach((selector, index) => {
            const element = document.querySelector(selector);
            if (element) {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';

                setTimeout(() => {
                    element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, 200 + (index * this.config.animationDelay));
            }
        });
    }

    /**
     * Focus automatique sur le champ email
     */
    focusEmailInput() {
        if (this.emailInput && window.innerWidth > 768) {
            setTimeout(() => {
                this.emailInput.focus();
            }, 500);
        }
    }

    /**
     * Vérification du cooldown de renvoi
     */
    checkResendCooldown() {
        const storedTime = localStorage.getItem('emailResendTime');
        if (storedTime) {
            const lastTime = parseInt(storedTime);
            const now = Date.now();

            if (now - lastTime < this.config.retryDelay) {
                this.lastResendTime = lastTime;

                // Désactiver temporairement les boutons de renvoi
                const resendButtons = document.querySelectorAll('[data-resend-btn]');
                const remainingTime = Math.ceil((this.config.retryDelay - (now - lastTime)) / 1000);

                resendButtons.forEach(btn => {
                    btn.disabled = true;
                    const originalText = btn.querySelector('.btn-primary__text, .btn-secondary__text').textContent;
                    btn.querySelector('.btn-primary__text, .btn-secondary__text').textContent = `Attendre ${remainingTime}s`;

                    setTimeout(() => {
                        btn.disabled = false;
                        btn.querySelector('.btn-primary__text, .btn-secondary__text').textContent = originalText;
                    }, this.config.retryDelay - (now - lastTime));
                });
            }
        }

        // Sauvegarder le temps de renvoi
        const originalHandleResendSubmit = this.handleResendSubmit;
        this.handleResendSubmit = function(event, form, button) {
            localStorage.setItem('emailResendTime', Date.now().toString());
            return originalHandleResendSubmit.call(this, event, form, button);
        };
    }

    /**
     * Échappement HTML pour éviter les injections XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Dispatch d'événements personnalisés
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`emailVerification:${eventName}`, {
            detail: {
                component: this,
                ...detail
            }
        });

        if (this.container) {
            this.container.dispatchEvent(event);
        }
    }

    /**
     * API publique - Afficher un toast depuis l'extérieur
     */
    static showToast(message, type = 'info') {
        if (window.emailVerificationComponent) {
            window.emailVerificationComponent.showToast(message, type);
        }
    }

    /**
     * API publique - Déclencher la validation d'email
     */
    static validateEmail(email) {
        if (window.emailVerificationComponent) {
            return window.emailVerificationComponent.isValidEmail(email);
        }
        return false;
    }

    /**
     * API publique - Vérifier le cooldown de renvoi
     */
    getCooldownRemaining() {
        const now = Date.now();
        const remaining = this.config.retryDelay - (now - this.lastResendTime);
        return remaining > 0 ? Math.ceil(remaining / 1000) : 0;
    }

    /**
     * Mise à jour du countdown sur les boutons
     */
    updateResendButtonCountdown() {
        const resendButtons = document.querySelectorAll('[data-resend-btn]');
        const remaining = this.getCooldownRemaining();

        resendButtons.forEach(btn => {
            const btnText = btn.querySelector('.btn-primary__text, .btn-secondary__text');

            if (remaining > 0) {
                btn.disabled = true;
                if (btnText) {
                    btnText.textContent = `Attendre ${remaining}s`;
                }
            } else {
                btn.disabled = false;
                if (btnText) {
                    const isResend = btn.closest('[data-resend-form]');
                    btnText.textContent = isResend ? 'Renvoyer l\'email' : 'Envoyer un nouveau lien';
                }
            }
        });

        // Programmer la prochaine mise à jour si nécessaire
        if (remaining > 0) {
            setTimeout(() => {
                this.updateResendButtonCountdown();
            }, 1000);
        }
    }

    /**
     * Gestion du focus et des interactions clavier
     */
    handleEmailKeyPress(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const form = event.target.closest('form');
            if (form && !form.querySelector('[data-resend-btn]').disabled) {
                form.submit();
            }
        }
    }

    /**
     * Animation des éléments de succès
     */
    animateSuccessElements() {
        const successElements = document.querySelectorAll('.benefit-item');

        successElements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';

            setTimeout(() => {
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, 500 + (index * 150));
        });
    }

    /**
     * Gestion du redimensionnement de fenêtre
     */
    handleResize() {
        // Réajuster les toasts sur mobile
        const toasts = document.querySelectorAll('.toast');
        if (window.innerWidth <= 768 && toasts.length > 0) {
            toasts.forEach(toast => {
                toast.style.width = 'calc(100vw - 2rem)';
            });
        }
    }

    /**
     * Vérification de l'état de la page
     */
    checkPageVisibility() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.emailInput) {
                // Redonner le focus au champ email quand la page redevient visible
                setTimeout(() => {
                    if (window.innerWidth > 768) {
                        this.emailInput.focus();
                    }
                }, 100);
            }
        });
    }

    /**
     * Amélioration de l'accessibilité
     */
    enhanceAccessibility() {
        // Ajouter des labels ARIA appropriés
        const resendButtons = document.querySelectorAll('[data-resend-btn]');
        resendButtons.forEach(btn => {
            btn.setAttribute('aria-describedby', 'resend-help-text');
        });

        // Créer un texte d'aide pour les lecteurs d'écran
        if (resendButtons.length > 0 && !document.getElementById('resend-help-text')) {
            const helpText = document.createElement('div');
            helpText.id = 'resend-help-text';
            helpText.className = 'sr-only';
            helpText.textContent = 'Cliquez pour renvoyer l\'email de vérification. Vous devez attendre 30 secondes entre chaque envoi.';
            document.body.appendChild(helpText);
        }

        // Gérer la navigation au clavier
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                // Gérer le focus trap si nécessaire
                this.handleTabKeyPress(e);
            }
        });
    }

    /**
     * Gestion de la touche Tab pour l'accessibilité
     */
    handleTabKeyPress(event) {
        const focusableElements = this.container.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );

        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey) {
            if (document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            }
        } else {
            if (document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        }
    }

    /**
     * Détection automatique du provider email
     */
    detectEmailProvider(email) {
        const providers = {
            'gmail.com': {
                name: 'Gmail',
                url: 'https://mail.google.com/',
                icon: '📧'
            },
            'outlook.com': {
                name: 'Outlook',
                url: 'https://outlook.live.com/',
                icon: '📨'
            },
            'yahoo.com': {
                name: 'Yahoo Mail',
                url: 'https://mail.yahoo.com/',
                icon: '📬'
            },
            'hotmail.com': {
                name: 'Hotmail',
                url: 'https://outlook.live.com/',
                icon: '📭'
            }
        };

        const domain = email.split('@')[1];
        return providers[domain] || null;
    }

    /**
     * Affichage d'un lien vers le provider email
     */
    showEmailProviderLink(email) {
        const provider = this.detectEmailProvider(email);
        if (!provider) return;

        const existingLink = document.querySelector('.email-provider-link');
        if (existingLink) existingLink.remove();

        const linkElement = document.createElement('div');
        linkElement.className = 'email-provider-link';
        linkElement.innerHTML = `
            <a href="${provider.url}" target="_blank" rel="noopener" class="provider-link">
                <span class="provider-link__icon">${provider.icon}</span>
                <span class="provider-link__text">Ouvrir ${provider.name}</span>
                <svg class="provider-link__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M7 17L17 7M17 7H7M17 7V17"/>
                </svg>
            </a>
        `;

        // Insérer après la description pending
        const pendingDescription = document.querySelector('.verification-pending__description');
        if (pendingDescription) {
            pendingDescription.insertAdjacentElement('afterend', linkElement);
        }
    }

    /**
     * Gestion de l'historique du navigateur
     */
    handleBrowserHistory() {
        // Éviter le retour en arrière accidentel pendant le processus
        window.addEventListener('beforeunload', (e) => {
            const isFormSubmitting = document.querySelector('.btn-primary.loading, .btn-secondary.loading');
            if (isFormSubmitting) {
                e.preventDefault();
                e.returnValue = 'Un email est en cours d\'envoi. Êtes-vous sûr de vouloir quitter ?';
                return e.returnValue;
            }
        });
    }

    /**
     * Statistiques d'utilisation (analytics)
     */
    trackUserAction(action, data = {}) {
        // Intégration avec votre système d'analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', action, {
                event_category: 'email_verification',
                event_label: data.label || '',
                value: data.value || 1
            });
        }

        // Dispatch d'événement personnalisé pour tracking interne
        this.dispatchEvent('userAction', { action, data });
    }

    /**
     * Nettoyage des ressources
     */
    destroy() {
        // Déconnecter l'observateur d'intersection
        if (this.animationObserver) {
            this.animationObserver.disconnect();
        }

        // Supprimer les écouteurs d'événements
        if (this.emailInput) {
            this.emailInput.removeEventListener('input', this.handleEmailInput);
            this.emailInput.removeEventListener('blur', this.handleEmailBlur);
        }

        document.removeEventListener('keydown', this.handleKeyDown);

        // Nettoyer le localStorage
        localStorage.removeItem('emailResendTime');

        // Supprimer les toasts
        this.closeAllToasts();

        // Nettoyer les éléments ajoutés dynamiquement
        const helpText = document.getElementById('resend-help-text');
        if (helpText) helpText.remove();

        const providerLink = document.querySelector('.email-provider-link');
        if (providerLink) providerLink.remove();
    }
}

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser le composant
    window.emailVerificationComponent = new EmailVerificationComponent();

    // Ajouter les styles CSS manquants
    if (!document.getElementById('email-verification-dynamic-styles')) {
        const style = document.createElement('style');
        style.id = 'email-verification-dynamic-styles';
        style.textContent = `
            .validation-error {
                color: #f87171;
                font-size: 0.8rem;
                margin-top: 0.5rem;
                opacity: 0;
                transform: translateY(-10px);
                transition: all 0.3s ease;
            }

            .validation-error.show {
                opacity: 1;
                transform: translateY(0);
            }

            .form-field__input.valid {
                border-color: #10b981;
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            }

            .form-field__input.error {
                border-color: #ef4444;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
            }

            .email-provider-link {
                margin: 1.5rem 0;
                text-align: center;
            }

            .provider-link {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.75rem 1.5rem;
                background: rgba(59, 130, 246, 0.1);
                border: 1px solid rgba(59, 130, 246, 0.3);
                border-radius: 8px;
                color: #60a5fa;
                text-decoration: none;
                font-size: 0.875rem;
                font-weight: 500;
                transition: all 0.3s ease;
            }

            .provider-link:hover {
                background: rgba(59, 130, 246, 0.15);
                border-color: rgba(59, 130, 246, 0.4);
                transform: translateY(-1px);
            }

            .provider-link__icon {
                font-size: 1.125rem;
            }

            .provider-link__arrow {
                transition: transform 0.3s ease;
            }

            .provider-link:hover .provider-link__arrow {
                transform: translate(2px, -2px);
            }

            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }

            @media (max-width: 640px) {
                .provider-link {
                    padding: 1rem;
                    width: 100%;
                    justify-content: center;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Détecter le provider email si un email est présent
    const emailInput = document.getElementById('email');
    if (emailInput && emailInput.value) {
        window.emailVerificationComponent.showEmailProviderLink(emailInput.value);
    }
});

// Export pour utilisation externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EmailVerificationComponent;
} else if (typeof window !== 'undefined') {
    window.EmailVerificationComponent = EmailVerificationComponent;
}
