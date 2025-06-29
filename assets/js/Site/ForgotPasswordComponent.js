/**
 * Forgot Password Component - BEM Architecture
 * Gestion du formulaire de récupération de mot de passe
 */
class ForgotPasswordComponent {
    constructor() {
        // Éléments DOM principaux
        this.form = document.getElementById('forgotPasswordForm');
        this.submitBtn = document.getElementById('submitBtn');
        this.emailInput = document.getElementById('email');

        // État du composant
        this.state = {
            isSubmitting: false,
            isEmailValid: false,
            hasInteracted: false,
            cooldownActive: false
        };

        // Configuration
        this.config = {
            cooldownTime: 60, // 60 secondes
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

        this.bindEvents();
        this.initFormValidation();
        this.initAnimations();
        this.enhanceAccessibility();
        this.checkCooldown();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Soumission du formulaire
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Validation de l'email en temps réel
        if (this.emailInput) {
            this.emailInput.addEventListener('input', this.debounce(() => {
                this.validateEmail();
                this.updateSubmitButton();
            }, this.config.validationDelay));

            this.emailInput.addEventListener('blur', () => {
                this.state.hasInteracted = true;
                this.validateEmail();
            });

            this.emailInput.addEventListener('focus', () => this.clearError('emailError'));
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
     * Initialisation de la validation du formulaire
     */
    initFormValidation() {
        this.form.setAttribute('novalidate', 'true');

        // Validation personnalisée
        this.emailInput?.addEventListener('invalid', (e) => {
            e.preventDefault();
            this.showFieldError(this.emailInput);
        });
    }

    /**
     * Initialisation des animations
     */
    initAnimations() {
        // Animation progressive des éléments
        const animatedElements = document.querySelectorAll('.form-group, .security-info, .instructions-section, .security-details');
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
        const errorElement = document.getElementById('emailError');
        if (errorElement) {
            errorElement.setAttribute('aria-live', 'polite');
            errorElement.setAttribute('aria-atomic', 'true');
        }

        // Association label/input renforcée
        if (this.emailInput) {
            const label = document.querySelector(`label[for="${this.emailInput.id}"]`);
            if (label && !this.emailInput.getAttribute('aria-describedby')) {
                this.emailInput.setAttribute('aria-describedby', 'emailError form-help');
            }
        }
    }

    /**
     * Vérification du cooldown
     */
    checkCooldown() {
        const lastSent = localStorage.getItem('forgot_password_sent');
        if (!lastSent) return;

        const timeDiff = (Date.now() - parseInt(lastSent)) / 1000;
        const remaining = this.config.cooldownTime - timeDiff;

        if (remaining > 0) {
            this.startCooldownTimer(Math.ceil(remaining));
        }
    }

    /**
     * Démarrage du timer de cooldown
     */
    startCooldownTimer(seconds) {
        this.state.cooldownActive = true;
        this.submitBtn.disabled = true;
        const originalText = this.submitBtn.querySelector('.btn-text').textContent;

        const updateTimer = () => {
            if (seconds > 0) {
                this.submitBtn.querySelector('.btn-text').textContent = `Patientez ${seconds}s`;
                seconds--;
                setTimeout(updateTimer, 1000);
            } else {
                this.state.cooldownActive = false;
                this.submitBtn.disabled = false;
                this.submitBtn.querySelector('.btn-text').textContent = originalText;
                localStorage.removeItem('forgot_password_sent');
            }
        };

        updateTimer();
    }

    /**
     * Gestion de la soumission du formulaire
     */
    async handleSubmit(e) {
        e.preventDefault();

        if (this.state.isSubmitting || this.state.cooldownActive) return;

        // Validation finale
        if (!this.validateForm()) {
            this.shakeForm();
            return;
        }

        this.state.isSubmitting = true;
        this.setLoadingState(true);

        try {
            // Enregistrer le timestamp pour le cooldown
            localStorage.setItem('forgot_password_sent', Date.now().toString());

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
        return this.validateEmail();
    }

    /**
     * Validation de l'email
     */
    validateEmail() {
        if (!this.emailInput) return false;

        const email = this.emailInput.value.trim();

        if (!email) {
            if (this.state.hasInteracted) {
                this.showError('emailError', 'L\'adresse email est requise');
            }
            this.state.isEmailValid = false;
            return false;
        }

        if (!this.config.emailRegex.test(email)) {
            if (this.state.hasInteracted) {
                this.showError('emailError', 'Veuillez saisir une adresse email valide');
            }
            this.state.isEmailValid = false;
            return false;
        }

        this.clearError('emailError');
        this.state.isEmailValid = true;
        
        // Marquer visuellement comme valide
        this.emailInput.closest('.form-group').classList.add('valid');
        
        return true;
    }

    /**
     * Mise à jour de l'état du bouton submit
     */
    updateSubmitButton() {
        if (!this.submitBtn || this.state.cooldownActive) return;

        if (this.state.isEmailValid) {
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

        if (this.emailInput) {
            this.emailInput.classList.add('error');
            this.emailInput.closest('.form-group').classList.add('shake');

            setTimeout(() => {
                this.emailInput.closest('.form-group').classList.remove('shake');
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

        if (this.emailInput) {
            this.emailInput.classList.remove('error');
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
        if (e.key === 'Enter' && e.target === this.emailInput && !this.state.isSubmitting) {
            this.handleSubmit(e);
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
            hasInteracted: false,
            cooldownActive: false
        };
        this.clearError('emailError');
        this.setLoadingState(false);
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
    window.forgotPasswordComponent = new ForgotPasswordComponent();

    // Animation des instructions
    const instructions = document.querySelectorAll('.instructions-list li');
    instructions.forEach((instruction, index) => {
        instruction.style.animationDelay = `${index * 0.15}s`;
        instruction.classList.add('animate-in');
    });

    // Gestion des messages flash
    const flashMessages = document.querySelectorAll('.alert');
    flashMessages.forEach((alert, index) => {
        alert.style.animationDelay = `${index * 0.1}s`;
        
        if (alert.classList.contains('alert-success') && window.notify) {
            setTimeout(() => {
                window.notify.success('Email envoyé avec succès. Vérifiez votre boîte de réception.', {
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
window.ForgotPasswordComponent = ForgotPasswordComponent;