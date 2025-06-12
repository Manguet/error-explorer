document.addEventListener('DOMContentLoaded', function() {
    // ===== GESTION DES ONGLETS TECHNOLOGIQUES =====
    const techTabs = document.querySelectorAll('.tech-tab');
    const techPanels = document.querySelectorAll('.tech-panel');

    function switchTech(targetTech) {
        // Désactiver tous les onglets et panneaux
        techTabs.forEach(tab => tab.classList.remove('active'));
        techPanels.forEach(panel => {
            panel.classList.remove('active');
            panel.style.display = 'none';
        });

        // Activer l'onglet et le panneau sélectionnés
        const selectedTab = document.querySelector(`[data-tech="${targetTech}"]`);
        const selectedPanel = document.querySelector(`.tech-panel[data-tech="${targetTech}"]`);

        if (selectedTab && selectedPanel) {
            selectedTab.classList.add('active');
            selectedPanel.style.display = 'block';

            // Petit délai pour l'animation
            setTimeout(() => {
                selectedPanel.classList.add('active');
            }, 10);
        }
    }

    // Gestionnaire d'événements pour les onglets
    techTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tech = this.dataset.tech;
            switchTech(tech);

            // Feedback visuel
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });

    // ===== FONCTIONNALITÉ DE COPIE DE CODE =====
    const copyButtons = document.querySelectorAll('.copy-btn');

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        } else {
            // Fallback pour les navigateurs plus anciens
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            return new Promise((resolve, reject) => {
                if (document.execCommand('copy')) {
                    textArea.remove();
                    resolve();
                } else {
                    textArea.remove();
                    reject();
                }
            });
        }
    }

    function showCopyFeedback(button, success = true) {
        const originalHTML = button.innerHTML;
        const originalClass = button.className;

        if (success) {
            button.classList.add('copied');
            button.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 6L9 17l-5-5"/>
                </svg>
            `;

            // Notification toast
            showToast('Code copié !', 'success');
        } else {
            button.style.background = 'rgba(239, 68, 68, 0.2)';
            button.style.borderColor = 'rgba(239, 68, 68, 0.3)';
            button.style.color = '#ef4444';

            showToast('Erreur lors de la copie', 'error');
        }

        // Restaurer l'état original après 2 secondes
        setTimeout(() => {
            button.className = originalClass;
            button.innerHTML = originalHTML;
            button.style.background = '';
            button.style.borderColor = '';
            button.style.color = '';
        }, 2000);
    }

    copyButtons.forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();

            const codeToShow = this.dataset.code;
            if (!codeToShow) {
                // Si pas de data-code, chercher le code dans le bloc suivant
                const codeBlock = this.parentElement.querySelector('pre code');
                if (codeBlock) {
                    const textToCopy = codeBlock.textContent;
                    try {
                        await copyToClipboard(textToCopy);
                        showCopyFeedback(this, true);
                    } catch (err) {
                        showCopyFeedback(this, false);
                        console.error('Erreur lors de la copie:', err);
                    }
                }
            } else {
                try {
                    await copyToClipboard(codeToShow);
                    showCopyFeedback(this, true);
                } catch (err) {
                    showCopyFeedback(this, false);
                    console.error('Erreur lors de la copie:', err);
                }
            }
        });
    });

    // ===== SYSTÈME DE NOTIFICATIONS TOAST =====
    function showToast(message, type = 'info') {
        // Supprimer le toast existant s'il y en a un
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">
                    ${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}
                </div>
                <span class="toast-message">${message}</span>
            </div>
        `;

        // Styles dynamiques
        const toastStyles = `
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                min-width: 300px;
                padding: 1rem 1.5rem;
                border-radius: 12px;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                backdrop-filter: blur(10px);
                transform: translateX(100%);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                font-family: 'Inter', sans-serif;
                font-weight: 500;
            }
            
            .toast-success {
                background: rgba(16, 185, 129, 0.9);
                color: white;
                border: 1px solid rgba(16, 185, 129, 0.3);
            }
            
            .toast-error {
                background: rgba(239, 68, 68, 0.9);
                color: white;
                border: 1px solid rgba(239, 68, 68, 0.3);
            }
            
            .toast-info {
                background: rgba(59, 130, 246, 0.9);
                color: white;
                border: 1px solid rgba(59, 130, 246, 0.3);
            }
            
            .toast-content {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            
            .toast-icon {
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 0.875rem;
            }
            
            .toast-message {
                flex: 1;
            }
            
            .toast.show {
                transform: translateX(0);
            }
            
            @media (max-width: 768px) {
                .toast {
                    right: 10px;
                    left: 10px;
                    min-width: auto;
                }
            }
        `;

        // Ajouter les styles si pas déjà présents
        if (!document.querySelector('#toast-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'toast-styles';
            styleSheet.textContent = toastStyles;
            document.head.appendChild(styleSheet);
        }

        document.body.appendChild(toast);

        // Animation d'entrée
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Suppression automatique après 3 secondes
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }, 3000);
    }

    // ===== NAVIGATION AU CLAVIER =====
    document.addEventListener('keydown', function(e) {
        // Échap pour fermer les notifications
        if (e.key === 'Escape') {
            const toast = document.querySelector('.toast');
            if (toast) {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }
        }

        // Navigation dans les onglets avec les flèches
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            const activeTab = document.querySelector('.tech-tab.active');
            if (activeTab && document.activeElement === activeTab) {
                e.preventDefault();
                const tabs = Array.from(techTabs);
                const currentIndex = tabs.indexOf(activeTab);
                let newIndex;

                if (e.key === 'ArrowLeft') {
                    newIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
                } else {
                    newIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
                }

                const newTab = tabs[newIndex];
                newTab.focus();
                switchTech(newTab.dataset.tech);
            }
        }
    });

    // ===== OBSERVER POUR LES ANIMATIONS AU SCROLL =====
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';

                // Animation spéciale pour les éléments de la grille
                if (entry.target.classList.contains('integration-item')) {
                    const items = entry.target.parentElement.children;
                    Array.from(items).forEach((item, index) => {
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
    const elementsToAnimate = document.querySelectorAll('.integration-category, .manual-step, .step');
    elementsToAnimate.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        observer.observe(el);
    });

    // ===== AMÉLIORATION DE L'ACCESSIBILITÉ =====

    // Support des préférences de mouvement réduit
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (prefersReducedMotion.matches) {
        // Désactiver les animations complexes
        const style = document.createElement('style');
        style.textContent = `
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        `;
        document.head.appendChild(style);
    }

    // Améliorer la navigation au clavier
    techTabs.forEach(tab => {
        tab.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // ===== GESTION DES ERREURS =====
    window.addEventListener('error', function(e) {
        console.warn('Erreur capturée sur la page intégrations:', e.error);
        // Ne pas afficher de toast d'erreur pour ne pas perturber l'UX
    });

    // ===== ANALYTICS ET TRACKING =====
    function trackEvent(action, tech = null) {
        // Ici vous pouvez ajouter votre code d'analytics
        // Par exemple : gtag('event', action, { tech: tech });
        console.log('Event tracked:', action, tech ? `for ${tech}` : '');
    }

    // Tracker les changements d'onglets
    techTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            trackEvent('tech_tab_clicked', this.dataset.tech);
        });
    });

    // Tracker les copies de code
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const activeTab = document.querySelector('.tech-tab.active');
            const tech = activeTab ? activeTab.dataset.tech : 'unknown';
            trackEvent('code_copied', tech);
        });
    });

    // ===== OPTIMISATIONS PERFORMANCES =====

    // Lazy loading des icônes de technologies
    const techIcons = document.querySelectorAll('.tech-icon');
    const iconObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
                iconObserver.unobserve(img);
            }
        });
    });

    techIcons.forEach(icon => {
        if (icon.dataset.src) {
            iconObserver.observe(icon);
        }
    });

    // ===== INITIALISATION =====
    console.log('🚀 Page intégrations chargée avec succès');

    // Charger l'onglet par défaut si aucun n'est actif
    const activeTab = document.querySelector('.tech-tab.active');
    if (activeTab) {
        switchTech(activeTab.dataset.tech);
    }

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

    // Fonction pour obtenir les infos sur la tech active
    window.getActiveTech = function() {
        const activeTab = document.querySelector('.tech-tab.active');
        return activeTab ? activeTab.dataset.tech : null;
    };

    // Export des fonctions pour usage externe si nécessaire
    window.IntegrationPageAPI = {
        switchTech,
        showToast,
        getActiveTech: window.getActiveTech,
        scrollToElement: window.scrollToElement
    };
});
