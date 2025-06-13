/**
 * Footer Component - BEM Architecture
 * Gestion du statut, des liens "coming soon" et des easter eggs
 */
class FooterComponent {
    constructor() {
        // Éléments DOM
        this.footer = document.querySelector('.footer');
        this.statusIndicator = document.querySelector('.footer__status-indicator');
        this.statusDot = document.querySelector('.footer__status-dot');
        this.statusText = document.querySelector('.footer__status-text');
        this.comingSoonLinks = document.querySelectorAll('[data-coming-soon]');

        // Configuration du statut
        this.statusConfig = {
            operational: {
                text: 'Tous systèmes opérationnels',
                modifier: '',
                color: '#10b981',
                bgColor: 'rgba(16, 185, 129, 0.08)',
                borderColor: 'rgba(16, 185, 129, 0.15)'
            },
            degraded: {
                text: 'Performances dégradées',
                modifier: 'footer__status-indicator--degraded',
                color: '#f59e0b',
                bgColor: 'rgba(245, 158, 11, 0.08)',
                borderColor: 'rgba(245, 158, 11, 0.15)'
            },
            outage: {
                text: 'Problème détecté',
                modifier: 'footer__status-indicator--outage',
                color: '#ef4444',
                bgColor: 'rgba(239, 68, 68, 0.08)',
                borderColor: 'rgba(239, 68, 68, 0.15)'
            },
            maintenance: {
                text: 'Maintenance programmée',
                modifier: 'footer__status-indicator--degraded',
                color: '#8b5cf6',
                bgColor: 'rgba(139, 92, 246, 0.08)',
                borderColor: 'rgba(139, 92, 246, 0.15)'
            }
        };

        // État
        this.currentStatus = 'operational';
        this.statusCheckInterval = null;

        // Easter egg
        this.konamiCode = [];
        this.konamiSequence = [
            'ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown',
            'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight',
            'KeyB', 'KeyA'
        ];

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.footer) return;

        this.bindEvents();
        this.initComingSoonLinks();
        this.startStatusMonitoring();
        this.observeFooterEntrance();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Clic sur l'indicateur de statut
        if (this.statusIndicator) {
            this.statusIndicator.addEventListener('click', this.handleStatusClick.bind(this));
        }

        // Easter egg - Konami code
        document.addEventListener('keydown', this.handleKonamiCode.bind(this));

        // Événements personnalisés pour le statut
        document.addEventListener('footer:updateStatus', (e) => {
            this.updateStatus(e.detail.status);
        });
    }

    /**
     * Gestion des liens "coming soon"
     */
    initComingSoonLinks() {
        this.comingSoonLinks.forEach(link => {
            link.addEventListener('click', this.handleComingSoonClick.bind(this));
            link.setAttribute('aria-label', `${link.textContent} - Fonctionnalité en cours de développement`);
        });
    }

    /**
     * Gestion des clics sur les liens "coming soon"
     */
    handleComingSoonClick(e) {
        e.preventDefault();

        const linkText = e.target.textContent.trim();
        this.showComingSoonNotification(linkText);

        // Effet visuel sur le lien
        e.target.style.transform = 'scale(0.95)';
        setTimeout(() => {
            e.target.style.transform = 'scale(1)';
        }, 150);
    }

    /**
     * Affichage de la notification "coming soon"
     */
    showComingSoonNotification(feature) {
        // Supprimer la notification existante
        const existingNotification = document.querySelector('.footer-notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        const notification = document.createElement('div');
        notification.className = 'footer-notification';
        notification.innerHTML = `
            <div class="footer-notification__content">
                <div class="footer-notification__icon">🚧</div>
                <div class="footer-notification__text">
                    <strong>${feature}</strong> arrive bientôt !
                    <br><small>Nous travaillons dur pour vous l'apporter.</small>
                </div>
                <button class="footer-notification__close" aria-label="Fermer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        `;

        this.addNotificationStyles();

        document.body.appendChild(notification);

        // Animation d'entrée
        setTimeout(() => {
            notification.classList.add('footer-notification--show');
        }, 10);

        // Fermeture automatique après 5 secondes
        const autoClose = setTimeout(() => {
            this.closeNotification(notification);
        }, 5000);

        // Fermeture manuelle
        const closeBtn = notification.querySelector('.footer-notification__close');
        closeBtn.addEventListener('click', () => {
            clearTimeout(autoClose);
            this.closeNotification(notification);
        });

        // Fermeture au clic extérieur
        notification.addEventListener('click', (e) => {
            if (e.target === notification) {
                clearTimeout(autoClose);
                this.closeNotification(notification);
            }
        });
    }

    /**
     * Fermeture de la notification
     */
    closeNotification(notification) {
        notification.classList.remove('footer-notification--show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }

    /**
     * Surveillance du statut système
     */
    startStatusMonitoring() {
        // Vérification initiale après 2 secondes
        setTimeout(() => {
            this.checkSystemStatus();
        }, 2000);

        // Vérification périodique toutes les 30 secondes
        this.statusCheckInterval = setInterval(() => {
            this.checkSystemStatus();
        }, 30000);
    }

    /**
     * Vérification du statut système
     */
    async checkSystemStatus() {
        try {
            // En production, faire un appel API réel
            // const response = await fetch('/api/status');
            // const data = await response.json();
            // this.updateStatus(data.status);

            // Simulation pour la démo
            const statuses = ['operational', 'degraded', 'outage'];
            const weights = [0.9, 0.08, 0.02]; // 90% opérationnel, 8% dégradé, 2% problème

            const random = Math.random();
            let cumulativeWeight = 0;
            let selectedStatus = 'operational';

            for (let i = 0; i < statuses.length; i++) {
                cumulativeWeight += weights[i];
                if (random <= cumulativeWeight) {
                    selectedStatus = statuses[i];
                    break;
                }
            }

            if (selectedStatus !== this.currentStatus) {
                this.updateStatus(selectedStatus);
            }
        } catch (error) {
            console.warn('Erreur lors de la vérification du statut:', error);
        }
    }

    /**
     * Mise à jour du statut
     */
    updateStatus(newStatus = 'operational') {
        if (!this.statusIndicator || !this.statusConfig[newStatus]) return;

        const config = this.statusConfig[newStatus];
        const oldStatus = this.currentStatus;
        this.currentStatus = newStatus;

        // Animation de transition
        this.statusIndicator.style.opacity = '0.7';
        this.statusIndicator.style.transform = 'scale(0.95)';

        setTimeout(() => {
            // Supprimer les anciennes classes de modificateur
            Object.values(this.statusConfig).forEach(statusConfig => {
                if (statusConfig.modifier) {
                    this.statusIndicator.classList.remove(statusConfig.modifier);
                }
            });

            // Ajouter la nouvelle classe de modificateur
            if (config.modifier) {
                this.statusIndicator.classList.add(config.modifier);
            }

            // Mettre à jour le texte
            if (this.statusText) {
                this.statusText.textContent = config.text;
            }

            // Animation de retour
            this.statusIndicator.style.opacity = '1';
            this.statusIndicator.style.transform = 'scale(1)';

            // Événement personnalisé
            this.dispatchStatusChangeEvent(oldStatus, newStatus);

        }, 150);
    }

    /**
     * Gestion du clic sur l'indicateur de statut
     */
    handleStatusClick() {
        // Ouvrir la page de statut ou afficher plus d'informations
        const statusUrl = '/status'; // URL de votre page de statut

        // Animation de clic
        this.statusIndicator.style.transform = 'scale(0.95)';
        setTimeout(() => {
            this.statusIndicator.style.transform = 'scale(1)';
        }, 100);

        // Pour l'instant, afficher une notification
        this.showStatusDetails();
    }

    /**
     * Affichage des détails du statut
     */
    showStatusDetails() {
        const config = this.statusConfig[this.currentStatus];
        const lastCheck = new Date().toLocaleTimeString();

        this.showComingSoonNotification(`Statut: ${config.text} (Dernière vérification: ${lastCheck})`);
    }

    /**
     * Gestion du code Konami
     */
    handleKonamiCode(e) {
        this.konamiCode.push(e.code);

        if (this.konamiCode.length > this.konamiSequence.length) {
            this.konamiCode.shift();
        }

        if (this.konamiCode.length === this.konamiSequence.length) {
            if (this.konamiCode.every((code, index) => code === this.konamiSequence[index])) {
                this.activateEasterEgg();
                this.konamiCode = [];
            }
        }
    }

    /**
     * Activation de l'easter egg
     */
    activateEasterEgg() {
        // Animation rainbow sur le footer
        this.footer.style.background = 'linear-gradient(45deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #9400d3)';
        this.footer.style.backgroundSize = '400% 400%';
        this.footer.style.animation = 'rainbow 2s ease infinite';

        // Message dans la console
        console.log('🎉 Félicitations ! Vous avez trouvé l\'easter egg d\'Error Explorer !');

        // Notification spéciale
        this.showEasterEggNotification();

        // Remettre le style normal après 5 secondes
        setTimeout(() => {
            this.footer.style.background = '';
            this.footer.style.backgroundSize = '';
            this.footer.style.animation = '';
        }, 5000);
    }

    /**
     * Notification de l'easter egg
     */
    showEasterEggNotification() {
        const notification = document.createElement('div');
        notification.className = 'footer-notification footer-notification--special';
        notification.innerHTML = `
            <div class="footer-notification__content">
                <div class="footer-notification__icon">🎉</div>
                <div class="footer-notification__text">
                    <strong>Easter Egg trouvé !</strong>
                    <br><small>Code promo: <code>KONAMI2024</code></small>
                </div>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('footer-notification--show');
        }, 10);

        setTimeout(() => {
            this.closeNotification(notification);
        }, 8000);
    }

    /**
     * Observation de l'entrée du footer dans le viewport
     */
    observeFooterEntrance() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('footer--visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        observer.observe(this.footer);
    }

    /**
     * Ajout des styles de notification
     */
    addNotificationStyles() {
        if (document.getElementById('footer-notification-styles')) return;

        const style = document.createElement('style');
        style.id = 'footer-notification-styles';
        style.textContent = `
            .footer-notification {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
                background: rgba(31, 41, 55, 0.95);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(59, 130, 246, 0.3);
                border-radius: 12px;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
                transform: translateY(100px);
                opacity: 0;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                font-family: 'Inter', sans-serif;
            }
            
            .footer-notification--show {
                transform: translateY(0);
                opacity: 1;
            }
            
            .footer-notification--special {
                border-color: rgba(255, 215, 0, 0.5);
                background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 165, 0, 0.1));
            }
            
            .footer-notification__content {
                display: flex;
                align-items: flex-start;
                gap: 1rem;
                padding: 1rem 1.5rem;
                color: white;
            }
            
            .footer-notification__icon {
                font-size: 1.5rem;
                flex-shrink: 0;
            }
            
            .footer-notification__text {
                flex: 1;
                line-height: 1.4;
            }
            
            .footer-notification__text strong {
                color: #3b82f6;
                font-weight: 600;
            }
            
            .footer-notification__text code {
                background: rgba(59, 130, 246, 0.2);
                color: #60a5fa;
                padding: 2px 6px;
                border-radius: 4px;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.875rem;
            }
            
            .footer-notification__close {
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.7);
                cursor: pointer;
                padding: 0.25rem;
                border-radius: 4px;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }
            
            .footer-notification__close:hover {
                background: rgba(255, 255, 255, 0.1);
                color: white;
            }
            
            @keyframes rainbow {
                0% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }
            
            @media (max-width: 768px) {
                .footer-notification {
                    right: 10px;
                    left: 10px;
                    bottom: 10px;
                    max-width: none;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Dispatch d'événement de changement de statut
     */
    dispatchStatusChangeEvent(oldStatus, newStatus) {
        const event = new CustomEvent('footer:statusChanged', {
            detail: {
                oldStatus,
                newStatus,
                config: this.statusConfig[newStatus]
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * API publique
     */

    // Forcer une mise à jour du statut
    forceStatusUpdate(status) {
        if (this.statusConfig[status]) {
            this.updateStatus(status);
        }
    }

    // Obtenir le statut actuel
    getCurrentStatus() {
        return this.currentStatus;
    }

    // Arrêter la surveillance du statut
    stopStatusMonitoring() {
        if (this.statusCheckInterval) {
            clearInterval(this.statusCheckInterval);
            this.statusCheckInterval = null;
        }
    }

    // Reprendre la surveillance du statut
    resumeStatusMonitoring() {
        if (!this.statusCheckInterval) {
            this.startStatusMonitoring();
        }
    }

    // Nettoyage
    destroy() {
        this.stopStatusMonitoring();

        // Supprimer les événements
        document.removeEventListener('keydown', this.handleKonamiCode);

        if (this.statusIndicator) {
            this.statusIndicator.removeEventListener('click', this.handleStatusClick);
        }

        this.comingSoonLinks.forEach(link => {
            link.removeEventListener('click', this.handleComingSoonClick);
        });

        // Supprimer les notifications
        const notifications = document.querySelectorAll('.footer-notification');
        notifications.forEach(notification => notification.remove());
    }
}

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du footer
    window.footerComponent = new FooterComponent();

    // Exemples d'utilisation des événements personnalisés
    document.addEventListener('footer:statusChanged', (e) => {
        console.log(`Statut changé de ${e.detail.oldStatus} vers ${e.detail.newStatus}`);
    });

    // Test de changement de statut (pour le développement)
    if (window.location.search.includes('debug=footer')) {
        setTimeout(() => {
            window.footerComponent.forceStatusUpdate('degraded');
        }, 3000);

        setTimeout(() => {
            window.footerComponent.forceStatusUpdate('operational');
        }, 6000);
    }
});

// Export pour utilisation externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FooterComponent;
} else if (typeof window !== 'undefined') {
    window.FooterComponent = FooterComponent;
}
