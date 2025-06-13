/**
 * Script d'inscription pour Error Explorer
 * Gestion des validations, sélection de plan et UX avancée
 */

class RegisterManager extends AuthManager {
    constructor() {
        super();

        // Éléments spécifiques à l'inscription
        this.firstNameInput = document.getElementById('first_name');
        this.lastNameInput = document.getElementById('last_name');
        this.companyInput = document.getElementById('company');
        this.confirmPasswordInput = document.getElementById('confirm_password');
        this.acceptTermsCheckbox = document.getElementById('accept_terms');

        // Éléments de plan
        this.planOptions = document.querySelectorAll('.plan-option');
        this.planRadios = document.querySelectorAll('input[name="plan_id"]');

        // Indicateurs visuels
        this.passwordStrengthElement = document.getElementById('passwordStrength');
        this.passwordMatchElement = document.getElementById('passwordMatch');

        this.initRegister();
    }

    /**
     * Initialisation spécifique à l'inscription
     */
    initRegister() {
        this.initPlanSelection();
        this.initPasswordStrength();
        this.initPasswordMatch();
        this.initNameValidation();
        this.initTermsValidation();
        this.animateRegistrationElements();
    }

    /**
     * Gestion de la sélection des plans
     */
    initPlanSelection() {
        this.planOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                const planId = option.dataset.planId;
                this.selectPlan(planId);
            });

            // Animation au hover
            option.addEventListener('mouseenter', () => {
                if (!option.classList.contains('selected')) {
                    option.style.transform = 'translateY(-3px)';
                }
            });

            option.addEventListener('mouseleave', () => {
                if (!option.classList.contains('selected')) {
                    option.style.transform = 'translateY(0)';
                }
            });
        });

        // Sélection par clavier
        this.planRadios.forEach(radio => {
            radio.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.selectPlan(radio.value);
                }
            });
        });
    }

    /**
     * Sélection d'un plan avec animations
     */
    selectPlan(planId) {
        // Désélectionner tous les plans
        this.planOptions.forEach(option => {
            option.classList.remove('selected');
            option.style.transform = 'translateY(0)';
        });

        // Sélectionner le plan choisi
        const selectedOption = document.querySelector(`[data-plan-id="${planId}"]`);
        const selectedRadio = document.querySelector(`input[value="${planId}"]`);

        if (selectedOption && selectedRadio) {
            selectedOption.classList.add('selected');
            selectedRadio.checked = true;

            // Animation de sélection
            selectedOption.style.transform = 'scale(1.02)';
            setTimeout(() => {
                selectedOption.style.transform = 'scale(1)';
            }, 200);

            // Toast de confirmation
            const planName = selectedOption.querySelector('.plan-name').textContent;
            notify.success(`Plan "${planName}" sélectionné`, {
                duration: 2000
            });
        }
    }

    /**
     * Initialisation de l'indicateur de force du mot de passe
     */
    initPasswordStrength() {
        if (this.passwordInput && this.passwordStrengthElement) {
            this.passwordInput.addEventListener('input', () => {
                this.updatePasswordStrength();
            });
        }
    }

    /**
     * Mise à jour de l'indicateur de force du mot de passe
     */
    updatePasswordStrength() {
        const password = this.passwordInput.value;
        const strength = this.calculatePasswordStrength(password);

        const strengthFill = this.passwordStrengthElement.querySelector('.strength-fill');
        const strengthText = this.passwordStrengthElement.querySelector('.strength-text');

        // Mise à jour de la barre
        strengthFill.style.width = `${strength.percentage}%`;

        // Mise à jour du texte et de la couleur
        strengthText.textContent = strength.text;
        strengthText.className = `strength-text ${strength.level}`;

        // Animation de la barre
        if (strength.percentage > 0) {
            strengthFill.style.background = strength.color;
        }
    }

    /**
     * Calcul de la force du mot de passe
     */
    calculatePasswordStrength(password) {
        let score = 0;
        const checks = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            numbers: /\d/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };

        // Calcul du score
        Object.values(checks).forEach(check => {
            if (check) score += 20;
        });

        // Bonus pour longueur
        if (password.length >= 12) score += 10;
        if (password.length >= 16) score += 10;

        // Détermination du niveau
        let level, text, color;

        if (score < 40) {
            level = 'weak';
            text = 'Mot de passe faible';
            color = 'linear-gradient(90deg, #ef4444, #dc2626)';
        } else if (score < 70) {
            level = 'medium';
            text = 'Mot de passe moyen';
            color = 'linear-gradient(90deg, #f59e0b, #d97706)';
        } else {
            level = 'strong';
            text = 'Mot de passe fort';
            color = 'linear-gradient(90deg, #10b981, #059669)';
        }

        return {
            percentage: Math.min(score, 100),
            level,
            text,
            color
        };
    }

    /**
     * Initialisation de la vérification de correspondance des mots de passe
     */
    initPasswordMatch() {
        if (this.confirmPasswordInput && this.passwordMatchElement) {
            this.confirmPasswordInput.addEventListener('input', () => {
                this.updatePasswordMatch();
            });

            this.passwordInput.addEventListener('input', () => {
                if (this.confirmPasswordInput.value) {
                    this.updatePasswordMatch();
                }
            });
        }
    }

    /**
     * Mise à jour de l'indicateur de correspondance des mots de passe
     */
    updatePasswordMatch() {
        const password = this.passwordInput.value;
        const confirmPassword = this.confirmPasswordInput.value;

        if (confirmPassword.length === 0) {
            this.passwordMatchElement.className = 'password-match-indicator';
            return;
        }

        if (password === confirmPassword) {
            this.passwordMatchElement.className = 'password-match-indicator match';
        } else {
            this.passwordMatchElement.className = 'password-match-indicator no-match';
        }
    }

    /**
     * Validation des noms et prénoms
     */
    initNameValidation() {
        [this.firstNameInput, this.lastNameInput].forEach(input => {
            if (input) {
                input.addEventListener('blur', () => this.validateName(input));
                input.addEventListener('input', () => {
                    const errorId = input.id === 'first_name' ? 'firstNameError' : 'lastNameError';
                    this.clearError(errorId);
                });
            }
        });
    }

    /**
     * Validation d'un champ nom/prénom
     */
    validateName(input) {
        const value = input.value.trim();
        const isFirstName = input.id === 'first_name';
        const errorId = isFirstName ? 'firstNameError' : 'lastNameError';
        const fieldName = isFirstName ? 'prénom' : 'nom';

        if (!value) {
            this.showError(errorId, `Le ${fieldName} est requis`);
            return false;
        }

        if (value.length < 2) {
            this.showError(errorId, `Le ${fieldName} doit contenir au moins 2 caractères`);
            return false;
        }

        if (!/^[a-zA-ZÀ-ÿ\s-']+$/.test(value)) {
            this.showError(errorId, `Le ${fieldName} contient des caractères non valides`);
            return false;
        }

        this.clearError(errorId);
        return true;
    }

    /**
     * Validation des conditions d'utilisation
     */
    initTermsValidation() {
        if (this.acceptTermsCheckbox) {
            this.acceptTermsCheckbox.addEventListener('change', () => {
                if (this.acceptTermsCheckbox.checked) {
                    this.acceptTermsCheckbox.closest('.form-section').classList.remove('error');
                }
            });
        }
    }

    /**
     * Animation des éléments spécifiques à l'inscription
     */
    animateRegistrationElements() {
        // Animation séquentielle des sections
        const sections = document.querySelectorAll('.form-section');
        sections.forEach((section, index) => {
            setTimeout(() => {
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            }, index * 150);
        });

        // Animation des plans
        const planOptions = document.querySelectorAll('.plan-option');
        planOptions.forEach((option, index) => {
            setTimeout(() => {
                option.style.opacity = '1';
                option.style.transform = 'translateY(0)';
            }, 500 + (index * 100));
        });
    }

    /**
     * Validation complète du formulaire d'inscription
     */
    validateForm() {
        let isValid = true;
        const validations = [
            () => this.validateName(this.firstNameInput),
            () => this.validateName(this.lastNameInput),
            () => this.validateEmail(),
            () => this.validateRegistrationPassword(),
            () => this.validatePasswordConfirmation(),
            () => this.validateTerms(),
            () => this.validatePlanSelection()
        ];

        validations.forEach(validation => {
            if (!validation()) {
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Validation spécifique du mot de passe pour l'inscription
     */
    validateRegistrationPassword() {
        const password = this.passwordInput.value;

        if (!password) {
            this.showError('passwordError', 'Le mot de passe est requis');
            return false;
        }

        if (password.length < 8) {
            this.showError('passwordError', 'Le mot de passe doit contenir au moins 8 caractères');
            return false;
        }

        const strength = this.calculatePasswordStrength(password);
        if (strength.percentage < 40) {
            this.showError('passwordError', 'Le mot de passe est trop faible');
            return false;
        }

        this.clearError('passwordError');
        return true;
    }

    /**
     * Validation de la confirmation de mot de passe
     */
    validatePasswordConfirmation() {
        const password = this.passwordInput.value;
        const confirmPassword = this.confirmPasswordInput.value;

        if (!confirmPassword) {
            this.showError('confirmPasswordError', 'Veuillez confirmer votre mot de passe');
            return false;
        }

        if (password !== confirmPassword) {
            this.showError('confirmPasswordError', 'Les mots de passe ne correspondent pas');
            return false;
        }

        this.clearError('confirmPasswordError');
        return true;
    }

    /**
     * Validation des conditions d'utilisation
     */
    validateTerms() {
        if (!this.acceptTermsCheckbox.checked) {
            this.acceptTermsCheckbox.closest('.form-section').classList.add('error');
            notify.error('Vous devez accepter les conditions d\'utilisation', {
                duration: 4000
            });
            return false;
        }

        this.acceptTermsCheckbox.closest('.form-section').classList.remove('error');
        return true;
    }

    /**
     * Validation de la sélection de plan
     */
    validatePlanSelection() {
        const selectedPlan = document.querySelector('input[name="plan_id"]:checked');

        if (!selectedPlan) {
            notify.error('Veuillez sélectionner un plan', {
                duration: 4000
            });
            return false;
        }

        return true;
    }

    /**
     * Gestion spécifique de la soumission d'inscription
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

            // Scroll vers la première erreur
            const firstError = document.querySelector('.form-error.show, .form-section.error');
            if (firstError) {
                firstError.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

            return;
        }

        this.isSubmitting = true;
        this.setLoadingState(true);

        // Animation du bouton
        this.submitBtn.style.transform = 'scale(0.98)';
        setTimeout(() => {
            this.submitBtn.style.transform = 'scale(1)';
        }, 100);

        // Message de confirmation
        notify.loading('Création de votre compte en cours...');
    }
}

/**
 * Gestionnaire de progression du formulaire
 */
class RegistrationProgress {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 4;
        this.createProgressIndicator();
    }

    createProgressIndicator() {
        const container = document.querySelector('.register-form-container');
        if (!container) return;

        const progressHTML = `
            <div class="registration-progress">
                <div class="progress-steps">
                    ${Array.from({length: this.totalSteps}, (_, i) => `
                        <div class="progress-step ${i === 0 ? 'active' : ''}" data-step="${i + 1}">
                            <div class="step-circle">
                                <span class="step-number">${i + 1}</span>
                                <svg class="step-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <polyline points="20,6 9,17 4,12"/>
                                </svg>
                            </div>
                            <div class="step-label">${this.getStepLabel(i + 1)}</div>
                        </div>
                    `).join('')}
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 25%"></div>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('afterbegin', progressHTML);
        this.addProgressStyles();
    }

    getStepLabel(step) {
        const labels = {
            1: 'Informations',
            2: 'Sécurité',
            3: 'Plan',
            4: 'Validation'
        };
        return labels[step] || '';
    }

    addProgressStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .registration-progress {
                margin-bottom: 2rem;
                padding: 1.5rem;
                background: rgba(255, 255, 255, 0.03);
                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .progress-steps {
                display: flex;
                justify-content: space-between;
                margin-bottom: 1rem;
            }
            
            .progress-step {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
                flex: 1;
            }
            
            .step-circle {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.1);
                border: 2px solid rgba(255, 255, 255, 0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                position: relative;
            }
            
            .progress-step.active .step-circle {
                background: #3b82f6;
                border-color: #3b82f6;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
            }
            
            .progress-step.completed .step-circle {
                background: #10b981;
                border-color: #10b981;
            }
            
            .step-number {
                color: white;
                font-size: 0.875rem;
                font-weight: 600;
            }
            
            .step-check {
                display: none;
                color: white;
            }
            
            .progress-step.completed .step-number {
                display: none;
            }
            
            .progress-step.completed .step-check {
                display: block;
            }
            
            .step-label {
                font-size: 0.75rem;
                color: rgba(255, 255, 255, 0.7);
                font-weight: 500;
                text-align: center;
            }
            
            .progress-step.active .step-label {
                color: #3b82f6;
            }
            
            .progress-bar {
                height: 4px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 2px;
                overflow: hidden;
            }
            
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #3b82f6, #1d4ed8);
                transition: width 0.5s ease;
            }
            
            @media (max-width: 768px) {
                .progress-steps {
                    gap: 0.5rem;
                }
                
                .step-circle {
                    width: 28px;
                    height: 28px;
                }
                
                .step-label {
                    font-size: 0.7rem;
                }
            }
        `;
        document.head.appendChild(style);
    }

    updateProgress(step) {
        this.currentStep = step;
        const percentage = (step / this.totalSteps) * 100;

        // Mise à jour de la barre de progression
        const progressFill = document.querySelector('.progress-fill');
        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }

        // Mise à jour des étapes
        document.querySelectorAll('.progress-step').forEach((stepEl, index) => {
            const stepNumber = index + 1;

            stepEl.classList.remove('active', 'completed');

            if (stepNumber < step) {
                stepEl.classList.add('completed');
            } else if (stepNumber === step) {
                stepEl.classList.add('active');
            }
        });
    }
}

/**
 * Gestionnaire d'auto-complétion intelligente
 */
class AutoComplete {
    constructor() {
        this.emailSuggestions = [
            'gmail.com', 'outlook.com', 'yahoo.com', 'hotmail.com',
            'orange.fr', 'free.fr', 'sfr.fr', 'wanadoo.fr'
        ];
        this.init();
    }

    init() {
        const emailInput = document.getElementById('email');
        if (emailInput) {
            this.initEmailSuggestions(emailInput);
        }

        const companyInput = document.getElementById('company');
        if (companyInput) {
            this.initCompanySuggestions(companyInput);
        }
    }

    initEmailSuggestions(input) {
        let suggestionElement;

        input.addEventListener('input', () => {
            const value = input.value;
            const atIndex = value.indexOf('@');

            if (atIndex > 0 && atIndex === value.length - 1) {
                // L'utilisateur vient de taper @
                this.showEmailSuggestions(input, value);
            } else if (suggestionElement) {
                suggestionElement.remove();
                suggestionElement = null;
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Tab' && suggestionElement) {
                e.preventDefault();
                const suggestion = suggestionElement.querySelector('.email-suggestion.active');
                if (suggestion) {
                    input.value = suggestion.dataset.email;
                    suggestionElement.remove();
                    suggestionElement = null;
                }
            }
        });
    }

    showEmailSuggestions(input, baseEmail) {
        const suggestions = this.emailSuggestions.map(domain =>
            `${baseEmail}${domain}`
        );

        const suggestionsHTML = `
            <div class="email-suggestions">
                ${suggestions.map((email, index) => `
                    <div class="email-suggestion ${index === 0 ? 'active' : ''}" 
                         data-email="${email}">
                        ${email}
                    </div>
                `).join('')}
            </div>
        `;

        input.parentElement.insertAdjacentHTML('afterend', suggestionsHTML);

        const suggestionElement = input.parentElement.nextElementSibling;

        // Gestion des clics
        suggestionElement.querySelectorAll('.email-suggestion').forEach(suggestion => {
            suggestion.addEventListener('click', () => {
                input.value = suggestion.dataset.email;
                suggestionElement.remove();
            });
        });

        // Suppression automatique après 5 secondes
        setTimeout(() => {
            if (suggestionElement && suggestionElement.parentNode) {
                suggestionElement.remove();
            }
        }, 5000);
    }

    initCompanySuggestions(input) {
        // Auto-complétion basique pour les entreprises courantes
        const companies = [
            'Google', 'Microsoft', 'Apple', 'Amazon', 'Meta',
            'Orange', 'SFR', 'Bouygues', 'Free', 'OVH'
        ];

        input.addEventListener('input', () => {
            const value = input.value.toLowerCase();
            if (value.length >= 2) {
                const matches = companies.filter(company =>
                    company.toLowerCase().includes(value)
                );

                if (matches.length > 0) {
                    this.showCompanySuggestions(input, matches.slice(0, 5));
                }
            }
        });
    }

    showCompanySuggestions(input, suggestions) {
        // Supprimer les suggestions existantes
        const existing = input.parentElement.querySelector('.company-suggestions');
        if (existing) existing.remove();

        const suggestionsHTML = `
            <div class="company-suggestions">
                ${suggestions.map(company => `
                    <div class="company-suggestion" data-company="${company}">
                        ${company}
                    </div>
                `).join('')}
            </div>
        `;

        input.parentElement.insertAdjacentHTML('afterend', suggestionsHTML);

        const suggestionElement = input.parentElement.nextElementSibling;

        suggestionElement.querySelectorAll('.company-suggestion').forEach(suggestion => {
            suggestion.addEventListener('click', () => {
                input.value = suggestion.dataset.company;
                suggestionElement.remove();
            });
        });
    }
}

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du gestionnaire d'inscription
    const registerManager = new RegisterManager();

    // Initialisation du gestionnaire de progression
    const progress = new RegistrationProgress();

    // Initialisation de l'auto-complétion
    const autoComplete = new AutoComplete();

    // Surveillance du progrès du formulaire
    const formSections = document.querySelectorAll('.form-section');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.intersectionRatio > 0.5) {
                const section = entry.target;
                const sectionIndex = Array.from(formSections).indexOf(section);
                progress.updateProgress(sectionIndex + 1);
            }
        });
    }, { threshold: 0.5 });

    formSections.forEach(section => observer.observe(section));

    // Gestion des raccourcis clavier globaux
    document.addEventListener('keydown', (e) => {
        // Ctrl/Cmd + Enter pour soumettre
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            const form = document.getElementById('registerForm');
            if (form) {
                form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        }
    });

    // Animation d'apparition progressive
    const animateOnScroll = () => {
        const elements = document.querySelectorAll('.form-section, .plan-option');
        elements.forEach(element => {
            const rect = element.getBoundingClientRect();
            const isVisible = rect.top < window.innerHeight * 0.8;

            if (isVisible && !element.classList.contains('animated')) {
                element.classList.add('animated');
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }
        });
    };

    window.addEventListener('scroll', animateOnScroll);
    animateOnScroll(); // Exécution initiale

    // Ajout des styles CSS pour les suggestions
    const style = document.createElement('style');
    style.textContent = `
        .email-suggestions,
        .company-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(31, 41, 55, 0.95);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            backdrop-filter: blur(10px);
            z-index: 1000;
            margin-top: 0.25rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .email-suggestion,
        .company-suggestion {
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .email-suggestion:last-child,
        .company-suggestion:last-child {
            border-bottom: none;
        }
        
        .email-suggestion:hover,
        .company-suggestion:hover,
        .email-suggestion.active {
            background: rgba(59, 130, 246, 0.2);
            color: white;
        }
        
        .email-suggestion.active::after {
            content: ' (Tab pour compléter)';
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .email-suggestions,
        .company-suggestions {
            animation: slideDown 0.2s ease-out;
        }
        
        /* Animation pour les éléments du formulaire */
        .form-section {
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .plan-option {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Effets de validation en temps réel */
        .form-group.validating .form-input {
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.05);
        }
        
        .form-group.valid .form-input {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.05);
        }
        
        .form-group.valid .form-label svg {
            color: #10b981;
        }
        
        /* Animation de validation */
        @keyframes validationSuccess {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .form-group.valid .form-input {
            animation: validationSuccess 0.3s ease-out;
        }
        
        /* Responsive pour les suggestions */
        @media (max-width: 768px) {
            .email-suggestions,
            .company-suggestions {
                font-size: 0.9rem;
            }
            
            .email-suggestion,
            .company-suggestion {
                padding: 0.5rem 0.75rem;
            }
        }
    `;
    document.head.appendChild(style);

    // Gestion des erreurs de validation côté serveur
    const serverErrors = document.querySelectorAll('.alert-error');
    if (serverErrors.length > 0) {
        setTimeout(() => {
            serverErrors.forEach(error => {
                const message = error.querySelector('.alert-content span').textContent;
                notify.error(message, {
                    duration: 6000,
                });
            });
        }, 500);
    }

    // Messages de succès
    const successMessages = document.querySelectorAll('.alert-success');
    if (successMessages.length > 0) {
        setTimeout(() => {
            successMessages.forEach(success => {
                const message = success.querySelector('.alert-content span').textContent;
                notify.success(message, {
                    duration: 4000,
                });
            });
        }, 500);
    }

    // Pré-remplissage intelligent basé sur l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const planParam = urlParams.get('plan');
    if (planParam) {
        const planOption = document.querySelector(`[data-plan-id="${planParam}"], input[value="${planParam}"]`);
        if (planOption) {
            setTimeout(() => {
                registerManager.selectPlan(planParam);
            }, 1000);
        }
    }

    // Sauvegarde automatique en localStorage (optionnel - pour éviter la perte de données)
    const saveFormData = () => {
        const formData = {
            first_name: document.getElementById('first_name')?.value || '',
            last_name: document.getElementById('last_name')?.value || '',
            email: document.getElementById('email')?.value || '',
            company: document.getElementById('company')?.value || '',
            timestamp: Date.now()
        };

        try {
            localStorage.setItem('error_explorer_registration', JSON.stringify(formData));
        } catch (e) {
            // localStorage non disponible
        }
    };

    // Restauration des données (si moins de 1 heure)
    const restoreFormData = () => {
        try {
            const saved = localStorage.getItem('error_explorer_registration');
            if (saved) {
                const data = JSON.parse(saved);
                const isRecent = (Date.now() - data.timestamp) < 3600000; // 1 heure

                if (isRecent) {
                    Object.keys(data).forEach(key => {
                        if (key !== 'timestamp') {
                            const input = document.getElementById(key);
                            if (input && !input.value) {
                                input.value = data[key];
                            }
                        }
                    });
                }
            }
        } catch (e) {
            // Erreur de restauration, ignorer
        }
    };

    // Sauvegarde automatique toutes les 30 secondes
    const formInputs = document.querySelectorAll('#registerForm input:not([type="password"])');
    formInputs.forEach(input => {
        input.addEventListener('input', () => {
            clearTimeout(input.saveTimeout);
            input.saveTimeout = setTimeout(saveFormData, 2000);
        });
    });

    // Restauration au chargement
    restoreFormData();

    // Nettoyage à la soumission réussie
    document.getElementById('registerForm')?.addEventListener('submit', () => {
        setTimeout(() => {
            try {
                localStorage.removeItem('error_explorer_registration');
            } catch (e) {
                // Ignorer
            }
        }, 1000);
    });
});

/**
 * Fonction utilitaire pour debounce
 */
function debounce(func, wait) {
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
 * Validation en temps réel améliorée
 */
class RealTimeValidation {
    constructor() {
        this.validators = new Map();
        this.init();
    }

    init() {
        // Configuration des validateurs
        this.validators.set('first_name', {
            rules: [
                { test: (v) => v.length >= 2, message: 'Minimum 2 caractères' },
                { test: (v) => /^[a-zA-ZÀ-ÿ\s-']+$/.test(v), message: 'Caractères non valides' }
            ]
        });

        this.validators.set('last_name', {
            rules: [
                { test: (v) => v.length >= 2, message: 'Minimum 2 caractères' },
                { test: (v) => /^[a-zA-ZÀ-ÿ\s-']+$/.test(v), message: 'Caractères non valides' }
            ]
        });

        this.validators.set('email', {
            rules: [
                { test: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), message: 'Format email invalide' }
            ]
        });

        // Liaison des événements
        this.validators.forEach((config, fieldName) => {
            const input = document.getElementById(fieldName);
            if (input) {
                input.addEventListener('input', debounce(() => {
                    this.validateField(fieldName, input.value.trim());
                }, 300));
            }
        });
    }

    validateField(fieldName, value) {
        const config = this.validators.get(fieldName);
        const input = document.getElementById(fieldName);
        const formGroup = input?.closest('.form-group');

        if (!config || !input || !formGroup) return;

        // État de validation
        formGroup.classList.add('validating');

        setTimeout(() => {
            formGroup.classList.remove('validating');

            if (!value) {
                formGroup.classList.remove('valid', 'invalid');
                return;
            }

            const isValid = config.rules.every(rule => rule.test(value));

            if (isValid) {
                formGroup.classList.add('valid');
                formGroup.classList.remove('invalid');
            } else {
                formGroup.classList.add('invalid');
                formGroup.classList.remove('valid');
            }
        }, 300);
    }
}

// Initialisation de la validation en temps réel
document.addEventListener('DOMContentLoaded', () => {
    new RealTimeValidation();
});

/**
 * Export pour utilisation externe
 */
window.RegisterManager = RegisterManager;
window.RegistrationProgress = RegistrationProgress;
window.AutoComplete = AutoComplete;
window.RealTimeValidation = RealTimeValidation;
