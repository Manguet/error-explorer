document.addEventListener('DOMContentLoaded', function() {
    // ===== GESTION DU FORMULAIRE DE CONTACT =====
    const contactForm = document.querySelector('.contact-form');
    const submitButton = document.querySelector('.contact-submit-btn');
    const messageTextarea = document.querySelector('textarea[name="contact[message]"]');
    const charCountSpan = document.querySelector('.char-count');

    if (contactForm) {
        // Compteur de caractères pour le message
        if (messageTextarea && charCountSpan) {
            messageTextarea.addEventListener('input', function() {
                const currentLength = this.value.length;
                const maxLength = 2000;

                charCountSpan.textContent = currentLength;

                // Changer la couleur selon la proximité de la limite
                if (currentLength > maxLength * 0.9) {
                    charCountSpan.style.color = '#ef4444';
                } else if (currentLength > maxLength * 0.7) {
                    charCountSpan.style.color = '#f59e0b';
                } else {
                    charCountSpan.style.color = '#6b7280';
                }
            });

            // Initialiser le compteur
            charCountSpan.textContent = messageTextarea.value.length;
        }

        // Animation du bouton de soumission
        contactForm.addEventListener('submit', function(e) {
            if (submitButton) {
                submitButton.classList.add('loading');
                submitButton.disabled = true;

                // Protection contre la double soumission
                setTimeout(() => {
                    if (submitButton.classList.contains('loading')) {
                        submitButton.classList.remove('loading');
                        submitButton.disabled = false;
                    }
                }, 10000); // 10 secondes de timeout
            }
        });

        // Validation en temps réel
        const inputs = contactForm.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });

            input.addEventListener('input', function() {
                // Supprimer les erreurs lors de la saisie
                const errorElement = this.parentElement.querySelector('.form-error');
                if (errorElement && errorElement.textContent) {
                    errorElement.textContent = '';
                    this.classList.remove('error');
                }
            });
        });

        // Validation du formulaire avant soumission
        contactForm.addEventListener('submit', function(e) {
            let isValid = true;
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                if (submitButton) {
                    submitButton.classList.remove('loading');
                    submitButton.disabled = false;
                }
            }
        });
    }

    // ===== FONCTION DE VALIDATION =====
    function validateField(field) {
        const errorElement = field.parentElement.querySelector('.form-error');
        let isValid = true;
        let errorMessage = '';

        // Réinitialiser
        field.classList.remove('error');
        if (errorElement) {
            errorElement.textContent = '';
        }

        // Validation selon le type de champ
        if (field.hasAttribute('required') && !field.value.trim()) {
            errorMessage = 'Ce champ est requis';
            isValid = false;
        } else if (field.type === 'email' && field.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                errorMessage = 'Veuillez entrer un email valide';
                isValid = false;
            }
        } else if (field.name && field.name.includes('phone') && field.value) {
            const phoneRegex = /^[\+]?[0-9\s\-\(\)\.]{8,15}$/;
            if (!phoneRegex.test(field.value)) {
                errorMessage = 'Veuillez entrer un numéro de téléphone valide';
                isValid = false;
            }
        } else if (field.name && field.name.includes('message') && field.value) {
            if (field.value.length < 10) {
                errorMessage = 'Le message doit contenir au moins 10 caractères';
                isValid = false;
            } else if (field.value.length > 2000) {
                errorMessage = 'Le message ne peut pas dépasser 2000 caractères';
                isValid = false;
            }
        }

        // Afficher l'erreur
        if (!isValid) {
            field.classList.add('error');
            if (errorElement) {
                errorElement.textContent = errorMessage;
            }
        }

        return isValid;
    }

    // ===== GESTION DE LA FAQ =====
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');

        question.addEventListener('click', function() {
            const isActive = item.classList.contains('active');

            // Fermer tous les autres items
            faqItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });

            // Toggle l'item actuel
            if (isActive) {
                item.classList.remove('active');
            } else {
                item.classList.add('active');
            }
        });
    });

    // ===== ANIMATIONS AU SCROLL =====
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';

                // Animation séquentielle pour les éléments de la FAQ
                if (entry.target.classList.contains('faq-grid')) {
                    const faqItems = entry.target.querySelectorAll('.faq-item');
                    faqItems.forEach((item, index) => {
                        setTimeout(() => {
                            item.style.opacity = '1';
                            item.style.transform = 'translateY(0)';
                        }, index * 100);
                    });
                }
            }
        });
    }, observerOptions);

    // Observer les éléments à animer
    const elementsToAnimate = document.querySelectorAll('.fade-in-up');
    elementsToAnimate.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        observer.observe(el);
    });

    // ===== GESTION DES ALERTES =====
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Auto-dismiss après 5 secondes pour les success
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        }
    });

    // ===== HONEYPOT SÉCURISÉ =====
    const honeypotField = document.querySelector('input[name="contact[website]"]');
    if (honeypotField) {
        // S'assurer que le champ reste invisible et vide
        honeypotField.style.cssText = 'position:absolute!important;left:-9999px!important;top:-9999px!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;';
        honeypotField.setAttribute('tabindex', '-1');
        honeypotField.setAttribute('autocomplete', 'off');

        // Détecter les tentatives de remplissage du honeypot
        honeypotField.addEventListener('input', function() {
            console.warn('Bot détecté - honeypot rempli');
        });
    }

    // ===== AMÉLIORATION DE L'ACCESSIBILITÉ =====

    // Gestion du clavier pour la FAQ
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        question.setAttribute('role', 'button');
        question.setAttribute('tabindex', '0');
        question.setAttribute('aria-expanded', 'false');

        question.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });

        // Mettre à jour aria-expanded
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const isActive = item.classList.contains('active');
                    question.setAttribute('aria-expanded', isActive.toString());
                }
            });
        });

        observer.observe(item, { attributes: true });
    });

    // ===== GESTION DES ERREURS =====
    window.addEventListener('error', function(e) {
        console.warn('Erreur capturée sur la page contact:', e.error);
    });

    // ===== FONCTIONS UTILITAIRES =====

    // Fonction pour smooth scroll vers un élément
    window.scrollToElement = function(selector) {
        const element = document.querySelector(selector);
        if (element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    };

    // Fonction pour afficher une notification
    window.showNotification = function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.innerHTML = `
            <div class="alert-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</div>
            <div class="alert-content">${message}</div>
        `;

        // Insérer la notification en haut du formulaire
        const formContainer = document.querySelector('.contact-form-container');
        if (formContainer) {
            formContainer.insertBefore(notification, formContainer.firstChild);

            // Auto-remove après 5 secondes
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
    };
});
