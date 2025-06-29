/**
 * Register Component - BEM Architecture
 * Composant moderne pour l'inscription avec validation avancée
 */
class RegisterComponent extends AuthComponent {
    constructor() {
        super();

        this.firstNameInput = document.getElementById('registration_form_firstName');
        this.lastNameInput = document.getElementById('registration_form_lastName');
        this.companyInput = document.getElementById('registration_form_company');
        this.emailInput = document.getElementById('registration_form_email');
        this.passwordInput = document.getElementById('registration_form_plainPassword_first');
        this.confirmPasswordInput = document.getElementById('registration_form_plainPassword_second');
        this.termsCheckbox = document.getElementById('registration_form_acceptTerms');
        this.planCards = document.querySelectorAll('.plan-card');
        this.planRadios = document.querySelectorAll('.plan-card__radio');

        // Éléments de validation
        this.emailValidation = document.getElementById('emailValidation');
        this.passwordStrength = document.getElementById('passwordStrength');
        this.confirmPasswordValidation = document.getElementById('confirmPasswordValidation');

        // État spécifique à l'inscription
        this.registerState = {
            ...this.state,
            isFirstNameValid: false,
            isLastNameValid: false,
            isConfirmPasswordValid: false,
            isTermsAccepted: false,
            isPlanSelected: false,
            selectedPlan: null,
            emailAvailabilityChecked: false,
            passwordStrength: 'none'
        };

        // Configuration spécifique
        this.registerConfig = {
            ...this.config,
            emailCheckDelay: 800,
            passwordStrengthConfig: {
                minLength: 8,
                requireUppercase: true,
                requireLowercase: true,
                requireNumbers: true,
                requireSpecialChars: false
            }
        };

        this.initRegister();
    }

    /**
     * Initialisation spécifique à l'inscription
     */
    initRegister() {
        this.bindRegisterEvents();
        this.initPlanSelection();
        this.initPasswordStrength();
        this.preSelectPlan();
        this.enhanceRegistrationAccessibility();
        this.initPasswordToggle();
        this.initAnimations();
        this.enhanceAccessibility();
    }

    /**
     * Liaison des événements spécifiques à l'inscription
     */
    bindRegisterEvents() {
        // Validation des champs nom/prénom
        if (this.firstNameInput) {
            this.firstNameInput.addEventListener('input', this.debounce(() => {
                this.validateFirstName();
                this.updateSubmitButton();
            }, this.config.validationDelay));

            this.firstNameInput.addEventListener('blur', () => {
                this.registerState.hasInteracted = true;
                this.validateFirstName();
            });
        }

        if (this.lastNameInput) {
            this.lastNameInput.addEventListener('input', this.debounce(() => {
                this.validateLastName();
                this.updateSubmitButton();
            }, this.config.validationDelay));

            this.lastNameInput.addEventListener('blur', () => {
                this.registerState.hasInteracted = true;
                this.validateLastName();
            });
        }

        // Validation email avec vérification de disponibilité
        if (this.emailInput) {
            this.emailInput.addEventListener('input', this.debounce(() => {
                if (this.validateEmail()) {
                    this.checkEmailAvailability();
                }
                this.updateSubmitButton();
            }, this.registerConfig.emailCheckDelay));
        }

        // Validation confirmation mot de passe
        if (this.confirmPasswordInput) {
            this.confirmPasswordInput.addEventListener('input', this.debounce(() => {
                this.validateConfirmPassword();
                this.updateSubmitButton();
            }, this.config.validationDelay));
        }

        // Gestion des conditions d'utilisation
        if (this.termsCheckbox) {
            this.termsCheckbox.addEventListener('change', () => {
                this.validateTerms();
                this.updateSubmitButton();
            });
        }

        // Validation mot de passe avec force
        if (this.passwordInput) {
            this.passwordInput.addEventListener('input', this.debounce(() => {
                this.validatePassword();
                this.updatePasswordStrength();
                this.validateConfirmPassword(); // Re-valider la confirmation
                this.updateSubmitButton();
            }, this.config.validationDelay));
        }
    }

    /**
     * Initialisation de la sélection de plan
     */
    initPlanSelection() {
        this.planCards.forEach((card, index) => {
            card.addEventListener('click', () => {
                this.selectPlan(index);
            });

            // Accessibilité clavier
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.selectPlan(index);
                }
            });

            // Rendre focusable
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'radio');
        });
    }

    /**
     * Sélection d'un plan
     */
    selectPlan(index) {
        // Désélectionner tous les plans
        this.planCards.forEach(card => {
            card.classList.remove('plan-card--selected');
            card.setAttribute('aria-checked', 'false');
        });

        // Sélectionner le plan choisi
        const selectedCard = this.planCards[index];
        const selectedRadio = this.planRadios[index];

        if (selectedCard && selectedRadio) {
            selectedCard.classList.add('plan-card--selected');
            selectedCard.setAttribute('aria-checked', 'true');
            selectedRadio.checked = true;

            // Mettre à jour l'état
            this.registerState.isPlanSelected = true;
            this.registerState.selectedPlan = {
                id: selectedRadio.value,
                name: selectedCard.querySelector('.plan-card__name').textContent,
                price: selectedCard.querySelector('.plan-card__amount').textContent
            };

            // Effet visuel
            this.createPlanSelectEffect(selectedCard);

            this.updateSubmitButton();
        }
    }

    /**
     * Présélection du plan basé sur l'URL ou le défaut
     */
    preSelectPlan() {
        const urlParams = new URLSearchParams(window.location.search);
        const planParam = urlParams.get('plan');

        // Chercher un plan déjà sélectionné ou le plan gratuit par défaut
        let selectedIndex = 0;
        this.planCards.forEach((card, index) => {
            if (card.classList.contains('plan-card--selected') ||
                (planParam && card.dataset.planId === planParam)) {
                selectedIndex = index;
            }
        });

        this.selectPlan(selectedIndex);
    }

    /**
     * Effet visuel lors de la sélection d'un plan
     */
    createPlanSelectEffect(card) {
        // Créer des particules autour de la card
        const rect = card.getBoundingClientRect();

        for (let i = 0; i < 6; i++) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: #3b82f6;
                border-radius: 50%;
                pointer-events: none;
                z-index: 1000;
                left: ${rect.left + rect.width / 2}px;
                top: ${rect.top + rect.height / 2}px;
                animation: planSelectParticle 1s ease-out forwards;
                animation-delay: ${i * 0.1}s;
            `;

            const angle = (360 / 6) * i;
            particle.style.setProperty('--angle', `${angle}deg`);

            document.body.appendChild(particle);

            setTimeout(() => {
                particle.remove();
            }, 1000);
        }
    }

    /**
     * Initialisation de l'indicateur de force du mot de passe
     */
    initPasswordStrength() {
        if (!this.passwordStrength) return;

        // Ajouter les styles CSS pour les particules si pas déjà fait
        if (!document.getElementById('register-particles-styles')) {
            const style = document.createElement('style');
            style.id = 'register-particles-styles';
            style.textContent = `
                @keyframes planSelectParticle {
                    to {
                        transform: rotate(var(--angle)) translateX(30px) scale(0);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * Mise à jour de l'indicateur de force du mot de passe
     */
    updatePasswordStrength() {
        if (!this.passwordInput || !this.passwordStrength) return;

        const password = this.passwordInput.value;
        const strength = this.calculatePasswordStrength(password);

        const fill = this.passwordStrength.querySelector('.password-strength__fill');
        const text = this.passwordStrength.querySelector('.password-strength__text');

        if (fill && text) {
            // Supprimer les anciennes classes
            fill.className = 'password-strength__fill';
            text.className = 'password-strength__text';

            if (password.length === 0) {
                text.textContent = 'Saisissez votre mot de passe';
                this.registerState.passwordStrength = 'none';
            } else {
                fill.classList.add(`password-strength__fill--${strength.level}`);
                text.classList.add(`password-strength__text--${strength.level}`);
                text.textContent = strength.message;
                this.registerState.passwordStrength = strength.level;
            }
        }
    }

    /**
     * Calcul de la force du mot de passe
     */
    calculatePasswordStrength(password) {
        if (!password) return { level: 'none', message: 'Saisissez votre mot de passe', score: 0 };

        let score = 0;
        let checks = [];

        // Longueur
        if (password.length >= 8) {
            score += 25;
            checks.push('longueur suffisante');
        } else {
            checks.push('au moins 8 caractères');
        }

        // Minuscules
        if (/[a-z]/.test(password)) {
            score += 25;
            checks.push('minuscules');
        } else {
            checks.push('lettres minuscules');
        }

        // Majuscules
        if (/[A-Z]/.test(password)) {
            score += 25;
            checks.push('majuscules');
        } else {
            checks.push('lettres majuscules');
        }

        // Chiffres
        if (/\d/.test(password)) {
            score += 25;
            checks.push('chiffres');
        } else {
            checks.push('chiffres');
        }

        // Caractères spéciaux (bonus)
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            score += 10;
        }

        // Déterminer le niveau
        let level, message;

        if (score < 25) {
            level = 'weak';
            message = 'Très faible - Ajoutez ' + checks.slice(-3).join(', ');
        } else if (score < 50) {
            level = 'fair';
            message = 'Faible - Ajoutez ' + checks.slice(-2).join(' et ');
        } else if (score < 75) {
            level = 'good';
            message = 'Correct - Presque parfait !';
        } else {
            level = 'strong';
            message = 'Excellent - Mot de passe sécurisé !';
        }

        return { level, message, score };
    }

    /**
     * Vérification de la disponibilité de l'email
     */
    async checkEmailAvailability() {
        if (!this.emailValidation) return;

        const email = this.emailInput.value.trim();
        if (!email || !this.config.emailRegex.test(email)) return;

        // Afficher l'état de vérification
        this.emailValidation.className = 'input-validation input-validation--checking';

        try {
            const response = await fetch('/api/validate-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'text/plain',
                },
                body: email
            });

            const result = await response.json();

            if (result.valid) {
                this.emailValidation.className = 'input-validation input-validation--valid';
                this.registerState.emailAvailabilityChecked = true;
            } else {
                this.emailValidation.className = 'input-validation input-validation--invalid';
                this.showError('emailError', result.message || 'Cet email est déjà utilisé');
                this.registerState.emailAvailabilityChecked = false;
            }

        } catch (error) {
            console.warn('Erreur lors de la vérification email:', error);
            this.emailValidation.className = 'input-validation';
            this.registerState.emailAvailabilityChecked = true; // Ne pas bloquer en cas d'erreur réseau
        }

        this.updateSubmitButton();
    }

    /**
     * Validation du prénom
     */
    validateFirstName() {
        if (!this.firstNameInput) return false;

        const firstName = this.firstNameInput.value.trim();

        if (!firstName) {
            if (this.registerState.hasInteracted) {
                this.showError('firstNameError', 'Le prénom est requis');
            }
            this.registerState.isFirstNameValid = false;
            return false;
        }

        if (firstName.length < 2) {
            if (this.registerState.hasInteracted) {
                this.showError('firstNameError', 'Le prénom doit contenir au moins 2 caractères');
            }
            this.registerState.isFirstNameValid = false;
            return false;
        }

        this.clearError('firstNameError');
        this.registerState.isFirstNameValid = true;
        return true;
    }

    /**
     * Validation du nom
     */
    validateLastName() {
        if (!this.lastNameInput) return false;

        const lastName = this.lastNameInput.value.trim();

        if (!lastName) {
            if (this.registerState.hasInteracted) {
                this.showError('lastNameError', 'Le nom est requis');
            }
            this.registerState.isLastNameValid = false;
            return false;
        }

        if (lastName.length < 2) {
            if (this.registerState.hasInteracted) {
                this.showError('lastNameError', 'Le nom doit contenir au moins 2 caractères');
            }
            this.registerState.isLastNameValid = false;
            return false;
        }

        this.clearError('lastNameError');
        this.registerState.isLastNameValid = true;
        return true;
    }

    /**
     * Validation de la confirmation du mot de passe
     */
    validateConfirmPassword() {
        if (!this.confirmPasswordInput || !this.passwordInput) return false;

        const password = this.passwordInput.value;
        const confirmPassword = this.confirmPasswordInput.value;

        if (!confirmPassword) {
            if (this.registerState.hasInteracted) {
                this.showError('confirmPasswordError', 'Veuillez confirmer votre mot de passe');
            }
            this.updateConfirmPasswordValidation('');
            this.registerState.isConfirmPasswordValid = false;
            return false;
        }

        if (password !== confirmPassword) {
            if (this.registerState.hasInteracted) {
                this.showError('confirmPasswordError', 'Les mots de passe ne correspondent pas');
            }
            this.updateConfirmPasswordValidation('invalid');
            this.registerState.isConfirmPasswordValid = false;
            return false;
        }

        this.clearError('confirmPasswordError');
        this.updateConfirmPasswordValidation('valid');
        this.registerState.isConfirmPasswordValid = true;
        return true;
    }

    /**
     * Mise à jour de l'indicateur de validation de confirmation
     */
    updateConfirmPasswordValidation(state) {
        if (!this.confirmPasswordValidation) return;

        this.confirmPasswordValidation.className = 'input-validation';
        if (state) {
            this.confirmPasswordValidation.classList.add(`input-validation--${state}`);
        }
    }

    /**
     * Validation des conditions d'utilisation
     */
    validateTerms() {
        if (!this.termsCheckbox) return false;

        const isAccepted = this.termsCheckbox.checked;

        if (!isAccepted) {
            this.showError('termsError', 'Vous devez accepter les conditions d\'utilisation');
            this.registerState.isTermsAccepted = false;
            return false;
        }

        this.clearError('termsError');
        this.registerState.isTermsAccepted = true;
        return true;
    }

    /**
     * Validation complète du formulaire d'inscription
     */
    validateForm() {
        const emailValid = this.validateEmail();
        const firstNameValid = this.validateFirstName();
        const lastNameValid = this.validateLastName();
        const passwordValid = this.validatePassword();
        const confirmPasswordValid = this.validateConfirmPassword();
        const termsValid = this.validateTerms();
        const planValid = this.registerState.isPlanSelected;
        const emailAvailable = this.registerState.emailAvailabilityChecked;

        if (!planValid) {
            this.showError('planError', 'Veuillez sélectionner un plan');
        } else {
            this.clearError('planError');
        }

        return emailValid && emailAvailable && firstNameValid && lastNameValid &&
            passwordValid && confirmPasswordValid && termsValid && planValid;
    }

    /**
     * Mise à jour de l'état du bouton submit
     */
    updateSubmitButton() {
        if (!this.submitBtn) return;

        const isValid = this.registerState.emailAvailabilityChecked &&
            this.registerState.isFirstNameValid &&
            this.registerState.isLastNameValid &&
            this.registerState.isConfirmPasswordValid &&
            this.registerState.isTermsAccepted &&
            this.registerState.isPlanSelected
        ;

        if (isValid) {
            this.submitBtn.style.opacity = '1';
            this.submitBtn.style.transform = 'scale(1)';
            this.submitBtn.disabled = false;
        } else {
            this.submitBtn.style.opacity = '0.7';
            this.submitBtn.disabled = true;
        }
    }

    /**
     * Amélioration de l'accessibilité pour l'inscription
     */
    enhanceRegistrationAccessibility() {
        // ARIA labels pour les sections
        document.querySelectorAll('.form-section').forEach((section, index) => {
            const title = section.querySelector('.form-section__title');
            if (title) {
                const id = `form-section-${index}`;
                title.id = id;
                section.setAttribute('aria-labelledby', id);
            }
        });

        // Descriptions pour les champs complexes
        if (this.passwordStrength) {
            this.passwordInput.setAttribute('aria-describedby', 'passwordStrength');
        }

        // Groupe de boutons radio pour les plans
        const planSelector = document.querySelector('.plan-selector');
        if (planSelector) {
            planSelector.setAttribute('role', 'radiogroup');
            planSelector.setAttribute('aria-labelledby', 'plan-section-title');
        }
    }

    /**
     * API publique - Obtenir l'état de l'inscription
     */
    getRegisterState() {
        return { ...this.registerState };
    }

    /**
     * API publique - Réinitialiser le formulaire d'inscription
     */
    resetRegister() {
        super.reset();

        this.registerState = {
            ...this.state,
            isFirstNameValid: false,
            isLastNameValid: false,
            isConfirmPasswordValid: false,
            isTermsAccepted: false,
            isPlanSelected: false,
            selectedPlan: null,
            emailAvailabilityChecked: false,
            passwordStrength: 'none'
        };

        // Réinitialiser les indicateurs visuels
        if (this.emailValidation) {
            this.emailValidation.className = 'input-validation';
        }

        if (this.confirmPasswordValidation) {
            this.confirmPasswordValidation.className = 'input-validation';
        }

        // Réinitialiser la force du mot de passe
        this.updatePasswordStrength();

        // Désélectionner tous les plans
        this.planCards.forEach(card => {
            card.classList.remove('plan-card--selected');
            card.setAttribute('aria-checked', 'false');
        });

        this.planRadios.forEach(radio => {
            radio.checked = false;
        });
    }
}

/**
 * Gestionnaire d'effets visuels spécifiques à l'inscription
 */
class RegisterVisualEffects extends AuthVisualEffects {
    static addPlanHoverEffects() {
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                if (!card.classList.contains('plan-card--selected')) {
                    card.style.transform = 'translateY(-4px) scale(1.02)';
                    card.style.boxShadow = '0 8px 25px rgba(59, 130, 246, 0.15)';
                }
            });

            card.addEventListener('mouseleave', () => {
                if (!card.classList.contains('plan-card--selected')) {
                    card.style.transform = '';
                    card.style.boxShadow = '';
                }
            });
        });
    }

    static addPasswordStrengthAnimations() {
        const strengthBar = document.querySelector('.password-strength__fill');
        if (strengthBar) {
            // Observer les changements de classe pour animer la barre
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        strengthBar.style.transform = 'scaleX(0)';
                        requestAnimationFrame(() => {
                            strengthBar.style.transform = 'scaleX(1)';
                        });
                    }
                });
            });

            observer.observe(strengthBar, { attributes: true });
        }
    }
}

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du composant d'inscription
    window.registerComponent = new RegisterComponent();

    // Ajout d'effets visuels
    RegisterVisualEffects.addPlanHoverEffects();
    RegisterVisualEffects.addPasswordStrengthAnimations();

    // Animation du badge
    const badge = document.querySelector('.hero-badge');
    if (badge) {
        AuthVisualEffects.addFloatingAnimation(badge);
    }

    // Animation du point de status
    const badgeDot = document.querySelector('.badge-dot');
    if (badgeDot) {
        AuthVisualEffects.addPulseAnimation(badgeDot);
    }
});

// Export pour utilisation externe
window.RegisterComponent = RegisterComponent;
window.RegisterVisualEffects = RegisterVisualEffects;
