/**
 * Script d'authentification moderne pour Error Explorer
 * Gestion des interactions, validations et UX
 */

class AuthManager {
    constructor() {
        this.form = document.getElementById('loginForm');
        this.submitBtn = document.getElementById('submitBtn');
        this.emailInput = document.getElementById('inputEmail');
        this.passwordInput = document.getElementById('inputPassword');
        this.passwordToggle = document.querySelector('.password-toggle');

        this.isSubmitting = false;
        this.init();
    }

    init() {
        this.bindEvents();
        this.initPasswordToggle();
        this.initFormValidation();
        this.animateFormElements();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Soumission du formulaire
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        }

        // Validation en temps réel
        if (this.emailInput) {
            this.emailInput.addEventListener('blur', () => this.validateEmail());
            this.emailInput.addEventListener('input', () => this.clearError('emailError'));
        }

        if (this.passwordInput) {
            this.passwordInput.addEventListener('blur', () => this.validatePassword());
            this.passwordInput.addEventListener('input', () => this.clearError('passwordError'));
        }

        // Effets de focus améliorés
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', (e) => this.handleInputFocus(e));
            input.addEventListener('blur', (e) => this.handleInputBlur(e));
        });

        // Gestion des raccourcis clavier
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));
    }

    /**
     * Initialisation du toggle de mot de passe
     */
    initPasswordToggle() {
        if (this.passwordToggle) {
            this.passwordToggle.addEventListener('click', () => {
                const isPassword = this.passwordInput.type === 'password';
                this.passwordInput.type = isPassword ? 'text' : 'password';

                const eyeOpen = this.passwordToggle.querySelector('.eye-open');
                const eyeClosed = this.passwordToggle.querySelector('.eye-closed');

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
            });
        }
    }

    /**
     * Initialisation de la validation du formulaire
     */
    initFormValidation() {
        // Validation native HTML5 personnalisée
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('invalid', (e) => {
                e.preventDefault();
                this.showFieldError(input);
            });
        });
    }

    /**
     * Animation des éléments du formulaire au chargement
     */
    animateFormElements() {
        // Animation séquentielle des groupes de formulaire
        const formGroups = document.querySelectorAll('.form-group');
        formGroups.forEach((group, index) => {
            setTimeout(() => {
                group.style.opacity = '1';
                group.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Animation du logo
        const logoEmoji = document.querySelector('.logo-emoji');
        if (logoEmoji) {
            let bounceCount = 0;
            const bounceInterval = setInterval(() => {
                logoEmoji.style.animationDelay = '0s';
                bounceCount++;
                if (bounceCount >= 3) {
                    clearInterval(bounceInterval);
                }
            }, 2000);
        }
    }

    /**
     * Gestion de la soumission du formulaire
     */
    async handleSubmit(e) {
        if (this.isSubmitting) {
            e.preventDefault();
            return;
        }

        // Validation avant soumission
        if (!this.validateForm()) {
            e.preventDefault();
            this.shakeForm();
            return;
        }

        this.isSubmitting = true;
        this.setLoadingState(true);

        // Animation du bouton
        this.submitBtn.style.transform = 'scale(0.98)';
        setTimeout(() => {
            this.submitBtn.style.transform = 'scale(1)';
        }, 100);

    }

    /**
     * Validation complète du formulaire
     */
    validateForm() {
        let isValid = true;

        if (!this.validateEmail()) {
            isValid = false;
        }

        if (!this.validatePassword()) {
            isValid = false;
        }

        return isValid;
    }

    /**
     * Validation de l'email
     */
    validateEmail() {
        const email = this.emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!email) {
            this.showError('emailError', 'L\'adresse email est requise');
            return false;
        }

        if (!emailRegex.test(email)) {
            this.showError('emailError', 'Veuillez saisir une adresse email valide');
            return false;
        }

        this.clearError('emailError');
        return true;
    }

    /**
     * Validation du mot de passe
     */
    validatePassword() {
        const password = this.passwordInput.value;

        if (!password) {
            this.showError('passwordError', 'Le mot de passe est requis');
            return false;
        }

        if (password.length < 6) {
            this.showError('passwordError', 'Le mot de passe doit contenir au moins 6 caractères');
            return false;
        }

        this.clearError('passwordError');
        return true;
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
            input.parentElement.classList.add('shake');

            setTimeout(() => {
                input.parentElement.classList.remove('shake');
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
            this.isSubmitting = false;
        }
    }

    /**
     * Gestion du focus des inputs
     */
    handleInputFocus(e) {
        const label = e.target.closest('.form-group').querySelector('.form-label');
        if (label) {
            label.style.color = '#3b82f6';
            const svg = label.querySelector('svg');
            if (svg) {
                svg.style.color = '#3b82f6';
            }
        }
    }

    /**
     * Gestion de la perte de focus des inputs
     */
    handleInputBlur(e) {
        const label = e.target.closest('.form-group').querySelector('.form-label');
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
        if (e.key === 'Escape' && this.isSubmitting) {
            this.setLoadingState(false);
        }

        // Entrée pour soumettre si focus sur un input
        if (e.key === 'Enter' && (e.target === this.emailInput || e.target === this.passwordInput)) {
            if (!this.isSubmitting) {
                this.form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        }
    }
}

/**
 * Utilitaires pour les effets visuels
 */
class VisualEffects {
    static createRipple(element, event) {
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
        `;

        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    static addHoverGlow(element) {
        element.addEventListener('mouseenter', () => {
            element.style.filter = 'drop-shadow(0 0 10px rgba(59, 130, 246, 0.3))';
        });

        element.addEventListener('mouseleave', () => {
            element.style.filter = '';
        });
    }
}

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du gestionnaire d'authentification
    const authManager = new AuthManager();

    // Ajout d'effets visuels aux boutons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            VisualEffects.createRipple(btn, e);
        });
        VisualEffects.addHoverGlow(btn);
    });

    // Gestion des erreurs de connexion existantes
    const errorAlert = document.querySelector('.alert-error');
    if (errorAlert) {
        setTimeout(() => {
            notify.error('Erreur de connexion. Vérifiez vos identifiants.', {
                duration: 6000
            });
        }, 500);
    }

    // Animation fluide de la page
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.3s ease';

    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);

    // Ajout de styles CSS pour les animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: translate(var(--x), var(--y)) scale(2);
                opacity: 0;
            }
        }
        
        .form-input:not(:placeholder-shown) + .input-focus-ring {
            border-color: #10b981;
        }
        
        .form-group:hover .form-label {
            color: #60a5fa !important;
        }
    `;
    document.head.appendChild(style);
});

/**
 * Export pour utilisation externe
 */
window.AuthManager = AuthManager;
window.VisualEffects = VisualEffects;
