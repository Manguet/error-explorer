/**
 * Contact Page - JavaScript moderne avec protection anti-spam CORRIGÉE
 * Gestion du formulaire de contact avec validation, honeypot et animations
 */
class ContactPage {
    constructor() {
        this.form = null;
        this.submitButton = null;
        this.honeypotField = null;
        this.isSubmitting = false;
        this.isSpam = false;
        this.submitStartTime = null;

        // Configuration plus permissive
        this.config = {
            charCountMax: 2000,
            charCountWarning: 0.7,
            charCountDanger: 0.9,
            submitTimeout: 10000,
            animationDelay: 100,
            minSubmitTime: 2000, // Réduit de 3s à 2s
            maxSubmitTime: 1800000, // 30 minutes au lieu de 5
            honeypotCheckInterval: 5000 // Moins fréquent
        };

        // Détection de bot plus permissive
        this.botDetection = {
            mouseMovements: 0,
            keystrokes: 0,
            focusEvents: 0,
            timeOnPage: Date.now(),
            humanActivityDetected: true // Commence par true pour être permissif
        };

        this.init();
    }

    /**
     * Initialisation de la classe
     */
    init() {
        this.bindDOM();
        this.setupAntispam();
        this.setupFormValidation();
        this.setupCharacterCount();
        this.setupFAQ();
        this.setupAnimations();
        this.setupAccessibility();
        this.setupBotDetection();
        this.handleFlashMessages();
    }

    /**
     * Liaison des éléments DOM
     */
    bindDOM() {
        this.form = document.querySelector('.contact-form__form');
        this.submitButton = document.querySelector('.contact-form__submit');
        this.honeypotField = document.querySelector('.contact-form__honeypot-input');
        this.messageTextarea = document.querySelector('textarea[name*="message"]');
        this.charCountCurrent = document.querySelector('.contact-form__char-current');
        this.faqItems = document.querySelectorAll('.faq-item');
        this.formInputs = document.querySelectorAll('.contact-form__input, .contact-form__select, .contact-form__textarea');
        this.checkboxInput = document.querySelector('.contact-form__checkbox-input');
        this.checkboxWrapper = document.querySelector('.contact-form__checkbox-wrapper');
    }

    /**
     * Configuration complète anti-spam - VERSION PERMISSIVE
     */
    setupAntispam() {
        this.setupHoneypot();
        this.setupTimingProtection();
        this.setupFieldValidation();
    }

    /**
     * Configuration du honeypot - VERSION SÉCURISÉE MAIS PERMISSIVE
     */
    setupHoneypot() {
        if (!this.honeypotField) {
            return;
        }

        // Styles de masquage
        const hiddenStyles = 'position:absolute!important;left:-9999px!important;top:-9999px!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;width:1px!important;height:1px!important;overflow:hidden!important;z-index:-1!important;';

        this.honeypotField.style.cssText = hiddenStyles;
        this.honeypotField.setAttribute('tabindex', '-1');
        this.honeypotField.setAttribute('autocomplete', 'off');
        this.honeypotField.setAttribute('aria-hidden', 'true');

        // Surveillance UNIQUEMENT du remplissage (pas du focus)
        this.honeypotField.addEventListener('input', (e) => {
            if (e.target.value.trim()) {
                this.flagAsSpam('honeypot_filled');
            }
        });

        // Vérification périodique MOINS FRÉQUENTE
        setInterval(() => {
            if (this.honeypotField.value.trim()) {
                this.flagAsSpam('honeypot_periodic_check');
            }
        }, this.config.honeypotCheckInterval);
    }

    /**
     * Protection temporelle PLUS PERMISSIVE
     */
    setupTimingProtection() {
        this.submitStartTime = Date.now();

        // Bouton activé après seulement 2 secondes
        if (this.submitButton) {
            this.submitButton.disabled = true;
            this.submitButton.textContent = 'Chargement...';

            setTimeout(() => {
                if (this.submitButton && !this.isSpam) {
                    this.submitButton.disabled = false;
                    this.submitButton.innerHTML = `
                        <span class="contact-form__submit-text">Envoyer le message</span>
                        <svg class="contact-form__submit-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    `;
                }
            }, this.config.minSubmitTime);
        }
    }

    /**
     * Validation des champs MOINS STRICTE
     */
    setupFieldValidation() {
        const spamPatterns = [
            /\b(viagra|cialis|pharmacy|casino|lottery|winner|congratulations)\b/i,
            /\b(click here|visit now|buy now|limited time)\b/i,
            /(.)\1{20,}/, // Répétition excessive réduite à 20 caractères
        ];

        this.formInputs.forEach(input => {
            input.addEventListener('input', () => {
                const value = input.value.toLowerCase();

                // Vérifier SEULEMENT les patterns de spam évidents
                spamPatterns.forEach(pattern => {
                    if (pattern.test(value)) {
                        this.flagAsSpam('spam_pattern');
                    }
                });

                // Vérifier SEULEMENT les longueurs vraiment excessives
                if (value.length > 10000) {
                    this.flagAsSpam('excessive_length');
                }
            });
        });
    }

    /**
     * Détection comportementale TRÈS PERMISSIVE
     */
    setupBotDetection() {
        // Marquer immédiatement comme activité humaine détectée
        this.botDetection.humanActivityDetected = true;

        // Traquer les mouvements de souris
        document.addEventListener('mousemove', () => {
            this.botDetection.mouseMovements++;
            this.botDetection.humanActivityDetected = true;
        });

        // Traquer les frappes clavier
        document.addEventListener('keydown', () => {
            this.botDetection.keystrokes++;
            this.botDetection.humanActivityDetected = true;
        });

        // Traquer les événements de focus
        this.formInputs.forEach(input => {
            input.addEventListener('focus', () => {
                this.botDetection.focusEvents++;
                this.botDetection.humanActivityDetected = true;
            });
        });

        // Traquer les clics
        document.addEventListener('click', () => {
            this.botDetection.humanActivityDetected = true;
        });

        // Traquer le scroll
        document.addEventListener('scroll', () => {
            this.botDetection.humanActivityDetected = true;
        });

        // Traquer la saisie dans les champs
        this.formInputs.forEach(input => {
            input.addEventListener('input', () => {
                this.botDetection.keystrokes++;
                this.botDetection.humanActivityDetected = true;
            });
        });

        // Auto-détection après 3 secondes (fallback)
        setTimeout(() => {
            if (!this.botDetection.humanActivityDetected) {
                this.botDetection.humanActivityDetected = true;
            }
        }, 3000);
    }

    /**
     * Marquer comme spam avec plus de logging
     */
    flagAsSpam(reason = 'unknown') {
        this.isSpam = true;

        if (this.submitButton) {
            this.submitButton.disabled = true;
            this.submitButton.textContent = 'Soumission bloquée';
        }

        // Afficher une notification explicative
        this.showNotification(
            `Soumission bloquée (${reason}). Si vous pensez que c'est une erreur, rechargez la page.`,
            'error',
            10000
        );

        this.logSpamAttempt(reason);
    }

    /**
     * Logger les tentatives de spam avec plus de détails
     */
    logSpamAttempt(reason) {
        const spamData = {
            reason,
            timestamp: Date.now(),
            userAgent: navigator.userAgent,
            url: window.location.href,
            botDetection: { ...this.botDetection },
            timeOnPage: Date.now() - this.botDetection.timeOnPage,
            honeypotValue: this.honeypotField?.value || '',
            formData: this.getFormData()
        };

        localStorage.setItem('lastSpamAttempt', JSON.stringify(spamData));
    }

    /**
     * Obtenir les données du formulaire pour debug
     */
    getFormData() {
        const formData = {};
        this.formInputs.forEach(input => {
            formData[input.name || input.id] = input.value?.substring(0, 50) || ''; // Limité à 50 chars pour la sécurité
        });
        return formData;
    }

    /**
     * Méthode pour débloquer manuellement (utile pour debug)
     */
    unblock() {
        this.isSpam = false;
        if (this.submitButton) {
            this.submitButton.disabled = false;
            this.submitButton.innerHTML = `
                <span class="contact-form__submit-text">Envoyer le message</span>
                <svg class="contact-form__submit-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            `;
        }
        this.showNotification('Formulaire débloqué', 'success');
    }

    /**
     * Configuration de la validation du formulaire
     */
    setupFormValidation() {
        if (!this.form) return;

        // Validation en temps réel UNIQUEMENT au blur
        this.formInputs.forEach(input => {
            input.addEventListener('blur', () => {
                if (input.value.trim() || input.dataset.touched) {
                    this.validateField(input);
                }
            });

            input.addEventListener('input', () => {
                input.dataset.touched = 'true';
                this.clearFieldError(input);
            });

            input.addEventListener('focus', () => {
                input.dataset.touched = 'true';
            });
        });

        // Validation de la checkbox
        if (this.checkboxInput) {
            this.checkboxInput.addEventListener('change', () => {
                this.clearFieldError(this.checkboxInput);
            });
        }

        this.setupCheckboxInteraction();
        this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
    }

    /**
     * Configurer l'interaction de la checkbox
     */
    setupCheckboxInteraction() {
        if (!this.checkboxInput || !this.checkboxWrapper) return;

        this.checkboxWrapper.addEventListener('click', (e) => {
            if (e.target.tagName === 'A') {
                return;
            }

            e.preventDefault();
            this.checkboxInput.checked = !this.checkboxInput.checked;
            this.checkboxInput.dispatchEvent(new Event('change'));
        });

        this.checkboxWrapper.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.checkboxInput.checked = !this.checkboxInput.checked;
                this.checkboxInput.dispatchEvent(new Event('change'));
            }
        });

        this.checkboxWrapper.setAttribute('tabindex', '0');
        this.checkboxWrapper.setAttribute('role', 'checkbox');
        this.checkboxWrapper.setAttribute('aria-checked', 'false');

        this.checkboxInput.addEventListener('change', () => {
            this.checkboxWrapper.setAttribute('aria-checked', this.checkboxInput.checked.toString());
        });
    }

    /**
     * Gestion de la soumission du formulaire - VERSION PERMISSIVE
     */
    handleFormSubmit(e) {
        e.preventDefault();

        // Vérifications anti-spam
        if (this.isSpam) {
            this.showNotification('Soumission bloquée pour des raisons de sécurité. Rechargez la page si vous pensez que c\'est une erreur.', 'error');
            return false;
        }

        // Vérifier le honeypot
        if (this.honeypotField && this.honeypotField.value.trim()) {
            this.flagAsSpam('honeypot_filled_on_submit');
            return false;
        }

        // Vérifier le timing
        const timeSpent = Date.now() - this.submitStartTime;
        if (timeSpent < this.config.minSubmitTime) {
            this.showNotification(`Veuillez patienter encore ${Math.ceil((this.config.minSubmitTime - timeSpent) / 1000)} secondes.`, 'warning');
            return false;
        }

        if (timeSpent > this.config.maxSubmitTime) {
            this.showNotification('Session expirée. Veuillez recharger la page.', 'error');
            return false;
        }

        // Vérifier l'activité humaine - TRÈS PERMISSIF
        if (!this.botDetection.humanActivityDetected) {
            this.showNotification('Activité humaine non détectée, mais soumission autorisée pour debug.', 'warning', 3000);
            // Ne pas bloquer, juste avertir
        }

        // Validation des champs
        if (!this.validateForm()) {
            this.hideSubmitLoader();
            return false;
        }

        // Enregistrer la tentative de soumission
        localStorage.setItem('lastContactSubmit', Date.now().toString());

        this.showSubmitLoader();
        this.form.submit();

        return true;
    }

    /**
     * Validation complète du formulaire
     */
    validateForm() {
        let isValid = true;

        this.formInputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        if (this.checkboxInput && !this.checkboxInput.checked) {
            this.showFieldError(this.checkboxInput, 'Vous devez accepter les conditions d\'utilisation');
            isValid = false;
        }

        return isValid;
    }

    /**
     * Validation d'un champ individuel
     */
    validateField(field) {
        if (!field) return true;

        const value = field.value.trim();
        const fieldName = field.name || field.id || '';
        const isRequired = field.hasAttribute('required') ||
            field.closest('.contact-form__group')?.querySelector('label')?.textContent?.includes('*');

        let errorMessage = '';

        this.clearFieldError(field);

        if (isRequired && !value) {
            errorMessage = 'Ce champ est requis';
        } else if (field.type === 'email' && value) {
            if (!this.isValidEmail(value)) {
                errorMessage = 'Veuillez entrer un email valide';
            }
        } else if (fieldName.includes('phone') && value) {
            if (!this.isValidPhone(value)) {
                errorMessage = 'Veuillez entrer un numéro de téléphone valide';
            }
        } else if (fieldName.includes('message') && value) {
            if (value.length < 10) {
                errorMessage = 'Le message doit contenir au moins 10 caractères';
            } else if (value.length > this.config.charCountMax) {
                errorMessage = `Le message ne peut pas dépasser ${this.config.charCountMax} caractères`;
            }
        } else if (fieldName.includes('name') && value) {
            if (value.length < 2) {
                errorMessage = 'Le nom doit contenir au moins 2 caractères';
            } else if (!/^[a-zA-ZÀ-ÿ\s\-']+$/.test(value)) {
                errorMessage = 'Le nom ne peut contenir que des lettres, espaces et tirets';
            }
        }

        if (errorMessage) {
            this.showFieldError(field, errorMessage);
            return false;
        } else {
            this.showFieldSuccess(field);
            return true;
        }
    }

    /**
     * Afficher une erreur sur un champ
     */
    showFieldError(field, message) {
        field.classList.add('error');
        field.classList.remove('success');

        const group = field.closest('.contact-form__group');
        let errorElement = group?.querySelector('.contact-form__error');

        if (!errorElement && group) {
            errorElement = document.createElement('div');
            errorElement.className = 'contact-form__error';
            group.appendChild(errorElement);
        }

        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }

        if (group) {
            group.classList.add('shake');
            setTimeout(() => group.classList.remove('shake'), 500);
        }

        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', errorElement?.id || '');
    }

    /**
     * Afficher le succès sur un champ
     */
    showFieldSuccess(field) {
        if (field.value.trim()) {
            field.classList.add('success');
            field.classList.remove('error');
            field.setAttribute('aria-invalid', 'false');
        }
    }

    /**
     * Effacer l'erreur d'un champ
     */
    clearFieldError(field) {
        field.classList.remove('error');
        field.removeAttribute('aria-invalid');

        const group = field.closest('.contact-form__group');
        const errorElement = group?.querySelector('.contact-form__error');

        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.remove('show');
        }
    }

    /**
     * Validation email
     */
    isValidEmail(email) {
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        return emailRegex.test(email) && email.length <= 254;
    }

    /**
     * Validation téléphone
     */
    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[(]?[0-9\s\-\(\)\.]{8,15}$/;
        return phoneRegex.test(phone);
    }

    /**
     * Configuration du compteur de caractères
     */
    setupCharacterCount() {
        if (!this.messageTextarea || !this.charCountCurrent) return;

        const updateCount = () => {
            const currentLength = this.messageTextarea.value.length;
            const maxLength = this.config.charCountMax;

            this.charCountCurrent.textContent = currentLength;

            this.charCountCurrent.classList.remove('warning', 'danger');

            if (currentLength > maxLength * this.config.charCountDanger) {
                this.charCountCurrent.classList.add('danger');
            } else if (currentLength > maxLength * this.config.charCountWarning) {
                this.charCountCurrent.classList.add('warning');
            }

            if (this.messageTextarea.dataset.touched) {
                this.validateField(this.messageTextarea);
            }
        };

        this.messageTextarea.addEventListener('input', updateCount);
        this.messageTextarea.addEventListener('paste', () => {
            setTimeout(updateCount, 10);
        });

        updateCount();
    }

    /**
     * Gestion des loaders
     */
    showSubmitLoader() {
        if (!this.submitButton) return;
        this.submitButton.classList.add('loading');
        this.submitButton.disabled = true;
    }

    hideSubmitLoader() {
        if (!this.submitButton) return;
        this.submitButton.classList.remove('loading');
        if (!this.isSpam) {
            this.submitButton.disabled = false;
        }
    }

    /**
     * Configuration de la FAQ interactive
     */
    setupFAQ() {
        this.faqItems.forEach((item, index) => {
            const question = item.querySelector('.faq-item__question');
            const answer = item.querySelector('.faq-item__answer');

            if (!question || !answer) return;

            question.setAttribute('role', 'button');
            question.setAttribute('tabindex', '0');
            question.setAttribute('aria-expanded', 'false');
            question.setAttribute('aria-controls', `faq-answer-${index}`);
            answer.setAttribute('id', `faq-answer-${index}`);

            question.addEventListener('click', () => this.toggleFAQItem(item, question, answer));
            question.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.toggleFAQItem(item, question, answer);
                }
            });
        });
    }

    /**
     * Toggle d'un item FAQ
     */
    toggleFAQItem(item, question, answer) {
        const isActive = item.classList.contains('active');

        this.faqItems.forEach(otherItem => {
            if (otherItem !== item && otherItem.classList.contains('active')) {
                otherItem.classList.remove('active');
                const otherQuestion = otherItem.querySelector('.faq-item__question');
                if (otherQuestion) {
                    otherQuestion.setAttribute('aria-expanded', 'false');
                }
            }
        });

        if (isActive) {
            item.classList.remove('active');
            question.setAttribute('aria-expanded', 'false');
        } else {
            item.classList.add('active');
            question.setAttribute('aria-expanded', 'true');

            setTimeout(() => {
                const rect = item.getBoundingClientRect();
                if (rect.bottom > window.innerHeight) {
                    item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 300);
        }
    }

    /**
     * Configuration des animations au scroll
     */
    setupAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        const animatedElements = document.querySelectorAll('.contact-hero__stat, .contact-method');
        animatedElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            observer.observe(el);
        });
    }

    /**
     * Configuration de l'accessibilité
     */
    setupAccessibility() {
        this.formInputs.forEach((input, index) => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && input.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    const nextInput = this.formInputs[index + 1];
                    if (nextInput) {
                        nextInput.focus();
                    } else if (this.submitButton && !this.submitButton.disabled) {
                        this.submitButton.focus();
                    }
                }
            });
        });

        this.formInputs.forEach(input => {
            const group = input.closest('.contact-form__group');
            const label = group?.querySelector('.contact-form__label');
            if (label && !input.getAttribute('aria-label')) {
                input.setAttribute('aria-label', label.textContent.trim());
            }
        });

        this.formInputs.forEach(input => {
            const group = input.closest('.contact-form__group');
            let errorElement = group?.querySelector('.contact-form__error');

            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'contact-form__error';
                group?.appendChild(errorElement);
            }

            if (errorElement) {
                errorElement.setAttribute('role', 'alert');
                errorElement.setAttribute('aria-live', 'polite');
            }
        });
    }

    /**
     * Gestion des messages flash existants
     */
    handleFlashMessages() {
        const alerts = document.querySelectorAll('.contact-alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('contact-alert--success')) {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }

            const closeButton = document.createElement('button');
            closeButton.className = 'contact-alert__close';
            closeButton.innerHTML = '×';
            closeButton.setAttribute('aria-label', 'Fermer le message');
            closeButton.addEventListener('click', () => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            });

            alert.appendChild(closeButton);
        });
    }

    /**
     * Afficher une notification
     */
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `contact-alert contact-alert--${type}`;
        notification.innerHTML = `
            <div class="contact-alert__icon">${this.getNotificationIcon(type)}</div>
            <div class="contact-alert__content">
                <strong>${this.getNotificationTitle(type)}</strong>
                <span>${message}</span>
            </div>
        `;

        const formHeader = document.querySelector('.contact-form__header');
        if (formHeader) {
            formHeader.insertAdjacentElement('afterend', notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }
    }

    /**
     * Obtenir l'icône de notification
     */
    getNotificationIcon(type) {
        const icons = {
            success: '✓',
            error: '✗',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    /**
     * Obtenir le titre de notification
     */
    getNotificationTitle(type) {
        const titles = {
            success: 'Succès !',
            error: 'Erreur',
            warning: 'Attention',
            info: 'Information'
        };
        return titles[type] || titles.info;
    }

    /**
     * Méthodes utilitaires publiques
     */
    static getInstance() {
        if (!ContactPage.instance) {
            ContactPage.instance = new ContactPage();
        }
        return ContactPage.instance;
    }

    scrollToElement(selector) {
        const element = document.querySelector(selector);
        if (element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    resetForm() {
        if (this.form) {
            this.form.reset();
            this.formInputs.forEach(input => {
                this.clearFieldError(input);
                delete input.dataset.touched;
            });
            this.isSpam = false;
            this.submitStartTime = Date.now();
            this.botDetection = {
                mouseMovements: 0,
                keystrokes: 0,
                focusEvents: 0,
                timeOnPage: Date.now(),
                humanActivityDetected: false
            };
            if (this.submitButton) {
                this.submitButton.disabled = false;
            }
        }
    }
}

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    const contactPage = new ContactPage();
    window.ContactPage = contactPage;

    // Exposer la méthode de déblocage globalement (pour debug)
    window.unblockContactForm = () => contactPage.unblock();
});

// Styles CSS additionnels
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    .contact-form__group.shake {
        animation: shake 0.5s ease-in-out;
    }
    
    .contact-form__char-current.warning {
        color: #f59e0b !important;
        font-weight: 600;
    }
    
    .contact-form__char-current.danger {
        color: #ef4444 !important;
        font-weight: 600;
    }
    
    .contact-alert__close {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: none;
        border: none;
        color: currentColor;
        cursor: pointer;
        font-size: 1.25rem;
        line-height: 1;
        opacity: 0.7;
        transition: opacity 0.2s ease;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }
    
    .contact-alert__close:hover {
        opacity: 1;
        background: rgba(255, 255, 255, 0.1);
    }
    
    .contact-alert {
        position: relative;
        padding-right: 3rem;
    }
    
    .contact-form__checkbox-wrapper {
        cursor: pointer;
        user-select: none;
    }
    
    .contact-form__checkbox-wrapper:focus {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
        border-radius: 4px;
    }
    
    .contact-form__checkbox-input:checked + .contact-form__checkbox-wrapper .contact-form__checkbox-custom {
        background: #3b82f6;
        border-color: #3b82f6;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }
    
    .contact-form__checkbox-input:checked + .contact-form__checkbox-wrapper .contact-form__checkbox-custom svg {
        opacity: 1;
        transform: scale(1);
    }
`;
document.head.appendChild(style);
