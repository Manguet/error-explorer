/**
 * Auth Component - BEM Architecture
 * Gestion moderne de l'authentification avec BEM
 */
class AuthComponent {
    constructor() {
        // Éléments DOM principaux
        this.form = document.getElementById('authForm');
        this.submitBtn = document.getElementById('submitBtn');
        this.emailInput = document.getElementById('inputEmail');
        this.passwordInput = document.getElementById('inputPassword');
        this.passwordToggle = document.querySelector('.password-toggle');

        // État du composant
        this.state = {
            isSubmitting: false,
            isEmailValid: false,
            isPasswordValid: false,
            hasInteracted: false
        };

        // Configuration
        this.config = {
            minPasswordLength: 6,
            validationDelay: 300,
            animationDuration: 300,
            emailRegex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        };

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.form) return;

        // Pas de désactivation du bouton - on fait confiance au HTML5 validation

        this.bindEvents();
        this.initPasswordToggle();
        this.initFormValidation();
        this.initAnimations();
        this.enhanceAccessibility();
        
        // Plus besoin de validation initiale compliquée
    }


    /**
     * Liaison des événements
     */
    bindEvents() {
        // Soumission du formulaire
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Validation en temps réel avec debouncing
        if (this.emailInput) {
            this.emailInput.addEventListener('input', this.debounce(() => {
                this.validateEmail();
                // Pas d'updateSubmitButton() - bouton toujours actif
            }, this.config.validationDelay));

            this.emailInput.addEventListener('blur', () => {
                this.state.hasInteracted = true;
                this.validateEmail();
            });

            this.emailInput.addEventListener('focus', () => this.clearError('emailError'));
        }

        if (this.passwordInput) {
            this.passwordInput.addEventListener('input', this.debounce(() => {
                this.validatePassword();
                // Pas d'updateSubmitButton() - bouton toujours actif
            }, this.config.validationDelay));

            this.passwordInput.addEventListener('blur', () => {
                this.state.hasInteracted = true;
                this.validatePassword();
            });

            this.passwordInput.addEventListener('focus', () => this.clearError('passwordError'));
        }

        // Effets de focus améliorés
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', (e) => this.handleInputFocus(e));
            input.addEventListener('blur', (e) => this.handleInputBlur(e));
        });

        // Gestion des raccourcis clavier
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));

        // Effets visuels sur les boutons
        document.querySelectorAll('.auth-submit-btn, .auth-link').forEach(btn => {
            btn.addEventListener('click', (e) => this.createRippleEffect(btn, e));
        });
    }

    /**
     * Initialisation du toggle de mot de passe
     */
    initPasswordToggle() {
        if (!this.passwordToggle) return;

        this.passwordToggle.addEventListener('click', () => {
            const isPassword = this.passwordInput.type === 'password';
            const eyeOpen = this.passwordToggle.querySelector('.eye-open');
            const eyeClosed = this.passwordToggle.querySelector('.eye-closed');

            // Changer le type d'input
            this.passwordInput.type = isPassword ? 'text' : 'password';

            // Changer l'icône
            if (isPassword) {
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
            } else {
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
            }

            // Animation du bouton
            this.passwordToggle.style.transform = 'scale(0.9)';
            setTimeout(() => {
                this.passwordToggle.style.transform = 'scale(1)';
            }, 100);

            // Focus maintenu sur l'input
            this.passwordInput.focus();
        });
    }

    /**
     * Initialisation de la validation du formulaire
     */
    initFormValidation() {
        // Désactiver la validation HTML5 native
        this.form.setAttribute('novalidate', 'true');

        // Validation personnalisée
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('invalid', (e) => {
                e.preventDefault();
                this.showFieldError(input);
            });
        });
    }

    /**
     * Initialisation des animations
     */
    initAnimations() {
        // Animation progressive des éléments de formulaire
        const formGroups = document.querySelectorAll('.form-group');
        formGroups.forEach((group, index) => {
            group.style.animationDelay = `${0.1 * index}s`;
        });

        // Animation de la page
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.3s ease';

        requestAnimationFrame(() => {
            document.body.style.opacity = '1';
        });
    }

    /**
     * Amélioration de l'accessibilité
     */
    enhanceAccessibility() {
        // ARIA live regions pour les erreurs
        document.querySelectorAll('.form-error').forEach(errorElement => {
            errorElement.setAttribute('aria-live', 'polite');
            errorElement.setAttribute('aria-atomic', 'true');
        });

        // Association label/input renforcée
        document.querySelectorAll('.form-label').forEach(label => {
            const input = label.nextElementSibling.querySelector('.form-input');
            if (input && !input.getAttribute('aria-describedby')) {
                const errorElement = input.closest('.form-group').querySelector('.form-error');
                if (errorElement) {
                    input.setAttribute('aria-describedby', errorElement.id);
                }
            }
        });
    }

    /**
     * Gestion de la soumission du formulaire
     */
    async handleSubmit(e) {
        e.preventDefault();

        if (this.state.isSubmitting) return;

        // Validation finale
        if (!this.validateForm()) {
            this.shakeForm();
            return;
        }

        this.state.isSubmitting = true;
        this.setLoadingState(true);

        try {
            // Animation du bouton
            this.submitBtn.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.submitBtn.style.transform = 'scale(1)';
            }, 100);

            // Soumettre le formulaire
            this.form.submit();

        } catch (error) {
            console.error('Erreur lors de la soumission:', error);
            this.setLoadingState(false);
            this.showToast('Une erreur est survenue. Veuillez réessayer.', 'error');
        }
    }

    /**
     * Validation complète du formulaire
     */
    validateForm() {
        const emailValid = this.validateEmail();
        const passwordValid = this.validatePassword();

        return emailValid && passwordValid;
    }

    /**
     * Validation de l'email
     */
    validateEmail() {
        if (!this.emailInput) return false;

        const email = this.emailInput.value.trim();


        // Validation de base sans affichage d'erreur
        if (!email) {
            this.state.isEmailValid = false;
            if (this.state.hasInteracted) {
                this.showError('emailError', 'L\'adresse email est requise');
            }
            return false;
        }

        if (!this.config.emailRegex.test(email)) {
            this.state.isEmailValid = false;
            if (this.state.hasInteracted) {
                this.showError('emailError', 'Veuillez saisir une adresse email valide');
            }
            return false;
        }

        // Email valide
        this.clearError('emailError');
        this.state.isEmailValid = true;
        return true;
    }

    /**
     * Validation du mot de passe
     */
    validatePassword() {
        if (!this.passwordInput) return false;

        const password = this.passwordInput.value;


        // Validation de base sans affichage d'erreur
        if (!password) {
            this.state.isPasswordValid = false;
            if (this.state.hasInteracted) {
                this.showError('passwordError', 'Le mot de passe est requis');
            }
            return false;
        }

        if (password.length < this.config.minPasswordLength) {
            this.state.isPasswordValid = false;
            if (this.state.hasInteracted) {
                this.showError('passwordError', `Le mot de passe doit contenir au moins ${this.config.minPasswordLength} caractères`);
            }
            return false;
        }

        // Mot de passe valide
        this.clearError('passwordError');
        this.state.isPasswordValid = true;
        return true;
    }

    /**
     * Mise à jour de l'état du bouton submit (version simplifiée)
     */
    updateSubmitButton() {
        // Le bouton reste TOUJOURS activé - pas de disabled, pas de logique
        if (!this.submitBtn) return;
        
        // Apparence normale constante
        this.submitBtn.style.opacity = '1';
        this.submitBtn.style.transform = 'scale(1)';
        // S'assurer que disabled n'est jamais appliqué
        this.submitBtn.disabled = false;
    }

    /**
     * Affichage d'une erreur de champ
     */
    showError(errorId, message) {
        const errorElement = document.getElementById(errorId);
        const input = errorId === 'emailError' ? this.emailInput : this.passwordInput;

        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }

        if (input) {
            input.classList.add('error');
            input.closest('.form-group').classList.add('shake');

            setTimeout(() => {
                input.closest('.form-group').classList.remove('shake');
            }, 500);
        }
    }

    /**
     * Suppression d'une erreur de champ
     */
    clearError(errorId) {
        const errorElement = document.getElementById(errorId);
        const input = errorId === 'emailError' ? this.emailInput : this.passwordInput;

        if (errorElement) {
            errorElement.classList.remove('show');
            errorElement.textContent = '';
        }

        if (input) {
            input.classList.remove('error');
        }
    }

    /**
     * Affichage d'une erreur générale dans un champ
     */
    showFieldError(input) {
        const formGroup = input.closest('.form-group');
        if (formGroup) {
            formGroup.classList.add('shake');
            setTimeout(() => {
                formGroup.classList.remove('shake');
            }, 500);
        }
    }

    /**
     * Animation de secousse du formulaire
     */
    shakeForm() {
        this.form.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            this.form.style.animation = '';
        }, 500);
    }

    /**
     * Gestion de l'état de chargement
     */
    setLoadingState(loading) {
        if (loading) {
            this.submitBtn.classList.add('loading');
            this.submitBtn.disabled = true;
        } else {
            this.submitBtn.classList.remove('loading');
            this.submitBtn.disabled = false;
            this.state.isSubmitting = false;
        }
    }

    /**
     * Gestion du focus des inputs
     */
    handleInputFocus(e) {
        const formGroup = e.target.closest('.form-group');
        const label = formGroup?.querySelector('.form-label');

        if (label) {
            label.style.color = '#3b82f6';
            const svg = label.querySelector('svg');
            if (svg) {
                svg.style.color = '#3b82f6';
            }
        }

        // Animation de l'input
        e.target.style.transform = 'scale(1.02)';
        setTimeout(() => {
            e.target.style.transform = 'scale(1)';
        }, 200);
    }

    /**
     * Gestion de la perte de focus des inputs
     */
    handleInputBlur(e) {
        const formGroup = e.target.closest('.form-group');
        const label = formGroup?.querySelector('.form-label');

        if (label) {
            label.style.color = '#ffffff';
            const svg = label.querySelector('svg');
            if (svg) {
                svg.style.color = '';
            }
        }
    }

    /**
     * Gestion des raccourcis clavier
     */
    handleKeyDown(e) {
        // Échap pour annuler le chargement
        if (e.key === 'Escape' && this.state.isSubmitting) {
            this.setLoadingState(false);
        }

        // Entrée pour soumettre si focus sur un input
        if (e.key === 'Enter' && (e.target === this.emailInput || e.target === this.passwordInput)) {
            if (!this.state.isSubmitting) {
                this.handleSubmit(e);
            }
        }
    }

    /**
     * Création d'un effet ripple
     */
    createRippleEffect(element, event) {
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(${x}px, ${y}px) scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
            z-index: 1;
        `;

        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    /**
     * Affichage d'un toast
     */
    showToast(message, type = 'info') {
        // Utiliser le système de notification global si disponible
        if (window.notify) {
            window.notify[type](message);
            return;
        }

        // Fallback simple
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.textContent = message;

        const container = document.getElementById('toast-container');
        if (container) {
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
    }

    /**
     * Fonction utilitaire de debouncing
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
     * API publique - Réinitialiser le formulaire
     */
    reset() {
        this.form.reset();
        this.state = {
            isSubmitting: false,
            isEmailValid: false,
            isPasswordValid: false,
            hasInteracted: false
        };
        this.clearError('emailError');
        this.clearError('passwordError');
        this.setLoadingState(false);
        // Pas d'updateSubmitButton() - bouton toujours actif
    }

    /**
     * API publique - Obtenir l'état du formulaire
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Nettoyage des event listeners
     */
    destroy() {
        // Retirer tous les event listeners
        // (implémentation simplifiée)
        this.form?.removeEventListener('submit', this.handleSubmit);
        document.removeEventListener('keydown', this.handleKeyDown);
    }
}

/**
 * Gestionnaire d'effets visuels
 */
class AuthVisualEffects {
    static addHoverGlow(element) {
        element.addEventListener('mouseenter', () => {
            element.style.filter = 'drop-shadow(0 0 10px rgba(59, 130, 246, 0.3))';
        });

        element.addEventListener('mouseleave', () => {
            element.style.filter = '';
        });
    }

    static addFloatingAnimation(element) {
        element.style.animation = 'float 3s ease-in-out infinite';
    }

    static addPulseAnimation(element) {
        element.style.animation = 'pulse 2s infinite';
    }
}

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du composant d'authentification
    window.authComponent = new AuthComponent();

    // Ajout d'effets visuels
    document.querySelectorAll('.auth-link').forEach(link => {
        AuthVisualEffects.addHoverGlow(link);
    });

    // Gestion des erreurs de connexion existantes
    const errorAlert = document.querySelector('.alert--error');
    if (errorAlert && window.notify) {
        setTimeout(() => {
            window.notify.error('Erreur de connexion. Vérifiez vos identifiants.', {
                duration: 6000
            });
        }, 500);
    }

    // Animation de la badge
    const badge = document.querySelector('.hero-badge');
    if (badge) {
        AuthVisualEffects.addFloatingAnimation(badge);
    }

    // Animation du point de status
    const badgeDot = document.querySelector('.badge-dot');
    if (badgeDot) {
        AuthVisualEffects.addPulseAnimation(badgeDot);
    }

    // Ajout de styles CSS pour les nouvelles animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .toast {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            pointer-events: auto;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast--error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        
        .toast--success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        
        .toast--info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
        }
    `;
    document.head.appendChild(style);
});

// Export pour utilisation externe
window.AuthComponent = AuthComponent;
window.AuthVisualEffects = AuthVisualEffects;
