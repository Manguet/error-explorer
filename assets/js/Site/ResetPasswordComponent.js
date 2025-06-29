/**
 * Reset Password Component - BEM Architecture
 * Gestion du formulaire de réinitialisation de mot de passe
 */
class ResetPasswordComponent {
    constructor() {
        // Éléments DOM principaux
        this.form = document.getElementById('resetPasswordForm');
        this.submitBtn = document.getElementById('submitBtn');
        this.passwordInput = document.getElementById('password');
        this.confirmPasswordInput = document.getElementById('confirm_password');
        this.passwordToggle = document.querySelector('.password-toggle');
        this.requirementElements = document.querySelectorAll('.requirement');

        // État du composant
        this.state = {
            isSubmitting: false,
            isPasswordValid: false,
            isConfirmValid: false,
            hasInteracted: false,
            requirements: {
                length: false,
                uppercase: false,
                lowercase: false,
                number: false
            }
        };

        // Configuration
        this.config = {
            minPasswordLength: 8,
            validationDelay: 300,
            animationDuration: 300
        };

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.form) return;

        this.bindEvents();
        this.initPasswordToggle();
        this.initFormValidation();
        this.initAnimations();
        this.enhanceAccessibility();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Soumission du formulaire
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Validation du mot de passe en temps réel
        if (this.passwordInput) {
            this.passwordInput.addEventListener('input', this.debounce(() => {
                this.validatePasswordStrength();
                this.checkPasswordMatch();
                this.updateSubmitButton();
            }, this.config.validationDelay));

            this.passwordInput.addEventListener('blur', () => {
                this.state.hasInteracted = true;
                this.validatePasswordStrength();
            });

            this.passwordInput.addEventListener('focus', () => this.clearError('passwordError'));
        }

        // Validation de la confirmation
        if (this.confirmPasswordInput) {
            this.confirmPasswordInput.addEventListener('input', this.debounce(() => {
                this.checkPasswordMatch();
                this.updateSubmitButton();
            }, this.config.validationDelay));

            this.confirmPasswordInput.addEventListener('blur', () => {
                this.state.hasInteracted = true;
                this.checkPasswordMatch();
            });

            this.confirmPasswordInput.addEventListener('focus', () => this.clearError('confirmPasswordError'));
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

            // Focus maintenu
            this.passwordInput.focus();
        });
    }

    /**
     * Initialisation de la validation du formulaire
     */
    initFormValidation() {
        this.form.setAttribute('novalidate', 'true');

        // Validation personnalisée pour chaque input
        [this.passwordInput, this.confirmPasswordInput].forEach(input => {
            input?.addEventListener('invalid', (e) => {
                e.preventDefault();
                this.showFieldError(input);
            });
        });
    }

    /**
     * Initialisation des animations
     */
    initAnimations() {
        // Animation progressive des éléments
        const animatedElements = document.querySelectorAll('.form-group, .security-info, .user-info, .password-requirements');
        animatedElements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            element.style.animation = `fadeInUp 0.6s ease-out ${0.1 * index}s forwards`;
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

        // Description des champs
        if (this.passwordInput) {
            this.passwordInput.setAttribute('aria-describedby', 'passwordError passwordStrength password-requirements');
        }

        if (this.confirmPasswordInput) {
            this.confirmPasswordInput.setAttribute('aria-describedby', 'confirmPasswordError passwordMatch');
        }

        // État des requirements
        this.requirementElements.forEach(element => {
            element.setAttribute('aria-live', 'polite');
        });
    }

    /**
     * Validation de la force du mot de passe
     */
    validatePasswordStrength() {
        if (!this.passwordInput) return false;

        const password = this.passwordInput.value;

        // Mise à jour des requirements
        this.state.requirements = {
            length: password.length >= this.config.minPasswordLength,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /\d/.test(password)
        };

        let validCount = 0;

        // Mettre à jour visuellement les critères
        this.requirementElements.forEach(element => {
            const requirement = element.dataset.requirement;
            if (this.state.requirements[requirement]) {
                element.classList.add('valid');
                validCount++;
            } else {
                element.classList.remove('valid');
            }
        });

        // Calculer le score
        const score = (validCount / 4) * 100;
        this.updatePasswordStrengthBar(score, validCount);

        // Valider si tous les critères sont remplis
        this.state.isPasswordValid = validCount === 4;

        if (this.state.hasInteracted && !this.state.isPasswordValid) {
            this.showError('passwordError', 'Le mot de passe ne respecte pas tous les critères');
        } else {
            this.clearError('passwordError');
        }

        return this.state.isPasswordValid;
    }

    /**
     * Mise à jour de la barre de force
     */
    updatePasswordStrengthBar(score, validCount) {
        const strengthFill = document.querySelector('.strength-fill');
        const strengthText = document.querySelector('.strength-text');

        if (!strengthFill || !strengthText) return;

        strengthFill.style.width = `${score}%`;

        // Déterminer la couleur et le texte
        let color, text;
        if (validCount === 0) {
            color = 'rgba(255, 255, 255, 0.1)';
            text = 'Entrez un mot de passe';
        } else if (validCount === 1) {
            color = '#ef4444';
            text = 'Très faible';
        } else if (validCount === 2) {
            color = '#f59e0b';
            text = 'Faible';
        } else if (validCount === 3) {
            color = '#eab308';
            text = 'Moyen';
        } else {
            color = '#10b981';
            text = 'Fort';
        }

        strengthFill.style.background = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
    }

    /**
     * Vérification de la correspondance des mots de passe
     */
    checkPasswordMatch() {
        if (!this.confirmPasswordInput || !this.passwordInput) return false;

        const password = this.passwordInput.value;
        const confirmPassword = this.confirmPasswordInput.value;
        const matchIndicator = document.getElementById('passwordMatch');

        if (!matchIndicator) return false;

        if (confirmPassword.length === 0) {
            matchIndicator.className = 'password-match-indicator';
            this.state.isConfirmValid = false;
            return false;
        }

        if (password === confirmPassword) {
            matchIndicator.className = 'password-match-indicator match';
            matchIndicator.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/></svg>';
            this.clearError('confirmPasswordError');
            this.state.isConfirmValid = true;
            return true;
        } else {
            matchIndicator.className = 'password-match-indicator no-match';
            matchIndicator.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>';
            if (this.state.hasInteracted) {
                this.showError('confirmPasswordError', 'Les mots de passe ne correspondent pas');
            }
            this.state.isConfirmValid = false;
            return false;
        }
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
        const passwordValid = this.validatePasswordStrength();
        const matchValid = this.checkPasswordMatch();

        return passwordValid && matchValid;
    }

    /**
     * Mise à jour de l'état du bouton submit
     */
    updateSubmitButton() {
        if (!this.submitBtn) return;

        const isValid = this.state.isPasswordValid && this.state.isConfirmValid;

        if (isValid) {
            this.submitBtn.style.opacity = '1';
            this.submitBtn.style.transform = 'scale(1)';
        } else {
            this.submitBtn.style.opacity = '0.7';
        }
    }

    /**
     * Affichage d'une erreur
     */
    showError(errorId, message) {
        const errorElement = document.getElementById(errorId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }

        const input = errorId === 'passwordError' ? this.passwordInput : this.confirmPasswordInput;
        if (input) {
            input.classList.add('error');
            input.closest('.form-group').classList.add('shake');

            setTimeout(() => {
                input.closest('.form-group').classList.remove('shake');
            }, 500);
        }
    }

    /**
     * Suppression d'une erreur
     */
    clearError(errorId) {
        const errorElement = document.getElementById(errorId);
        if (errorElement) {
            errorElement.classList.remove('show');
            errorElement.textContent = '';
        }

        const input = errorId === 'passwordError' ? this.passwordInput : this.confirmPasswordInput;
        if (input) {
            input.classList.remove('error');
        }
    }

    /**
     * Affichage d'une erreur sur un champ
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
     * Gestion de la perte de focus
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

        // Entrée pour soumettre
        if (e.key === 'Enter' && (e.target === this.passwordInput || e.target === this.confirmPasswordInput)) {
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
            isPasswordValid: false,
            isConfirmValid: false,
            hasInteracted: false,
            requirements: {
                length: false,
                uppercase: false,
                lowercase: false,
                number: false
            }
        };
        this.clearError('passwordError');
        this.clearError('confirmPasswordError');
        this.setLoadingState(false);
        
        // Réinitialiser les indicateurs visuels
        this.requirementElements.forEach(element => {
            element.classList.remove('valid');
        });
        
        const matchIndicator = document.getElementById('passwordMatch');
        if (matchIndicator) {
            matchIndicator.className = 'password-match-indicator';
        }
    }

    /**
     * API publique - Obtenir l'état
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Nettoyage
     */
    destroy() {
        this.form?.removeEventListener('submit', this.handleSubmit);
        document.removeEventListener('keydown', this.handleKeyDown);
    }
}

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du composant
    window.resetPasswordComponent = new ResetPasswordComponent();

    // Gestion des messages flash
    const flashMessages = document.querySelectorAll('.alert');
    flashMessages.forEach((alert, index) => {
        alert.style.animationDelay = `${index * 0.1}s`;
        
        if (alert.classList.contains('alert-success') && window.notify) {
            setTimeout(() => {
                window.notify.success('Mot de passe modifié avec succès. Vous pouvez maintenant vous connecter.', {
                    duration: 8000
                });
            }, 500);
        }
    });

    // Animation de la badge
    const badge = document.querySelector('.hero-badge');
    if (badge) {
        badge.style.animation = 'float 3s ease-in-out infinite';
    }

    // Animation du point de status
    const badgeDot = document.querySelector('.badge-dot');
    if (badgeDot) {
        badgeDot.style.animation = 'pulse 2s infinite';
    }
});

// Export pour utilisation externe
window.ResetPasswordComponent = ResetPasswordComponent;