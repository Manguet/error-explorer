/**
 * Changelog Component - BEM Architecture
 * Gestion de la page changelog avec filtres, recherche et animations
 */
class ChangelogComponent {
    constructor() {
        // Éléments DOM
        this.changelog = document.querySelector('.changelog');
        this.filters = document.querySelectorAll('.changelog__filter');
        this.searchInput = document.querySelector('.changelog__search-input');
        this.entries = document.querySelectorAll('.changelog__entry');
        this.toggleButtons = document.querySelectorAll('.changelog__entry-toggle');
        this.newsletterForm = document.querySelector('.changelog__newsletter-form');

        // État
        this.activeFilter = 'all';
        this.searchTerm = '';
        this.visibleEntries = new Set();
        this.animationObserver = null;
        this.isScrolling = false;

        // Configuration
        this.config = {
            animationDelay: 100,
            searchDelay: 300,
            scrollThreshold: 100,
            filterAnimationDuration: 400
        };

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        if (!this.changelog) return;

        this.setupIntersectionObserver();
        this.bindEvents();
        this.initializeFilters();
        this.animateInitialEntries();
        this.setupKeyboardNavigation();
    }

    /**
     * Configuration de l'observateur d'intersection pour les animations
     */
    setupIntersectionObserver() {
        const options = {
            root: null,
            rootMargin: '0px 0px -100px 0px',
            threshold: 0.1
        };

        this.animationObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('fade-in-up');
                    }, index * this.config.animationDelay);
                }
            });
        }, options);

        // Observer toutes les entrées
        this.entries.forEach(entry => {
            this.animationObserver.observe(entry);
        });
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Filtres
        this.filters.forEach(filter => {
            filter.addEventListener('click', this.handleFilterClick.bind(this));
        });

        // Recherche avec debouncing
        if (this.searchInput) {
            let searchTimeout;
            this.searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.handleSearch(e.target.value);
                }, this.config.searchDelay);
            });

            // Gestion de la touche Échap
            this.searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.clearSearch();
                }
            });
        }

        // Boutons de toggle
        this.toggleButtons.forEach(button => {
            button.addEventListener('click', this.handleToggleClick.bind(this));
        });

        // Formulaire newsletter
        if (this.newsletterForm) {
            this.newsletterForm.addEventListener('submit', this.handleNewsletterSubmit.bind(this));
        }

        // Scroll events
        this.setupScrollEvents();
    }

    /**
     * Configuration des événements de scroll
     */
    setupScrollEvents() {
        let scrollTimeout;

        window.addEventListener('scroll', () => {
            if (!this.isScrolling) {
                this.isScrolling = true;
                document.body.classList.add('is-scrolling');
            }

            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.isScrolling = false;
                document.body.classList.remove('is-scrolling');
            }, 150);
        }, { passive: true });
    }

    /**
     * Initialisation des filtres
     */
    initializeFilters() {
        // Marquer le filtre par défaut comme actif
        const defaultFilter = document.querySelector('.changelog__filter[data-filter="all"]');
        if (defaultFilter) {
            defaultFilter.classList.add('changelog__filter--active');
        }

        // Compter les entrées por chaque type
        this.updateFilterCounts();
    }

    /**
     * Mise à jour des compteurs de filtres
     */
    updateFilterCounts() {
        const counts = {
            all: this.entries.length,
            major: 0,
            minor: 0,
            patch: 0
        };

        this.entries.forEach(entry => {
            const type = entry.dataset.type;
            if (counts.hasOwnProperty(type)) {
                counts[type]++;
            }
        });

        // Mettre à jour les filtres avec les compteurs
        this.filters.forEach(filter => {
            const filterType = filter.dataset.filter;
            if (counts.hasOwnProperty(filterType)) {
                const count = counts[filterType];
                const countElement = filter.querySelector('.changelog__filter-count');

                if (countElement) {
                    countElement.textContent = count;
                } else if (count > 0) {
                    // Ajouter le compteur s'il n'existe pas
                    const countSpan = document.createElement('span');
                    countSpan.className = 'changelog__filter-count';
                    countSpan.textContent = count;
                    filter.appendChild(countSpan);
                }
            }
        });
    }

    /**
     * Gestion des clics sur les filtres
     */
    handleFilterClick(e) {
        const filter = e.currentTarget;
        const filterType = filter.dataset.filter;

        // Éviter les clics répétés
        if (this.activeFilter === filterType) return;

        // Mettre à jour l'état visuel
        this.updateActiveFilter(filter);

        // Filtrer les entrées
        this.filterEntries(filterType);

        // Mettre à jour l'état
        this.activeFilter = filterType;

        // Analytique
        this.trackFilterUsage(filterType);
    }

    /**
     * Mise à jour du filtre actif
     */
    updateActiveFilter(activeFilter) {
        this.filters.forEach(filter => {
            filter.classList.remove('changelog__filter--active');
        });
        activeFilter.classList.add('changelog__filter--active');
    }

    /**
     * Filtrage des entrées
     */
    filterEntries(filterType) {
        const visibleEntries = [];

        this.entries.forEach((entry, index) => {
            const entryType = entry.dataset.type;
            const matchesFilter = filterType === 'all' || entryType === filterType;
            const matchesSearch = this.matchesSearch(entry);
            const shouldShow = matchesFilter && matchesSearch;

            if (shouldShow) {
                visibleEntries.push(entry);
                this.showEntry(entry);
            } else {
                this.hideEntry(entry);
            }
        });

        // Animer les entrées visibles
        this.animateVisibleEntries(visibleEntries);

        // Mettre à jour l'état
        this.updateVisibleEntriesState(visibleEntries);
    }

    /**
     * Affichage d'une entrée
     */
    showEntry(entry) {
        entry.style.display = 'block';
        entry.classList.remove('changelog__entry--hidden');

        // Réanimer l'entrée
        setTimeout(() => {
            entry.classList.add('fade-in-up');
        }, 50);
    }

    /**
     * Masquage d'une entrée
     */
    hideEntry(entry) {
        entry.classList.add('changelog__entry--hidden');
        entry.classList.remove('fade-in-up');

        setTimeout(() => {
            entry.style.display = 'none';
        }, this.config.filterAnimationDuration);
    }

    /**
     * Animation des entrées visibles
     */
    animateVisibleEntries(entries) {
        entries.forEach((entry, index) => {
            setTimeout(() => {
                entry.classList.add('fade-in-up');
            }, index * this.config.animationDelay);
        });
    }

    /**
     * Gestion de la recherche
     */
    handleSearch(searchTerm) {
        this.searchTerm = searchTerm.toLowerCase().trim();

        // Filtrer avec le terme de recherche
        this.filterEntries(this.activeFilter);

        // Mettre à jour l'état de l'interface
        this.updateSearchState();
    }

    /**
     * Vérification si une entrée correspond à la recherche
     */
    matchesSearch(entry) {
        if (!this.searchTerm) return true;

        const searchableContent = [
            entry.querySelector('.changelog__entry-title')?.textContent || '',
            entry.querySelector('.changelog__entry-description')?.textContent || '',
            entry.querySelector('.changelog__entry-version')?.textContent || '',
            ...Array.from(entry.querySelectorAll('.changelog__feature-title')).map(el => el.textContent),
            ...Array.from(entry.querySelectorAll('.changelog__feature-description')).map(el => el.textContent)
        ].join(' ').toLowerCase();

        return searchableContent.includes(this.searchTerm);
    }

    /**
     * Mise à jour de l'état de recherche
     */
    updateSearchState() {
        const searchContainer = this.searchInput?.closest('.changelog__search');
        if (!searchContainer) return;

        if (this.searchTerm) {
            searchContainer.classList.add('changelog__search--active');
        } else {
            searchContainer.classList.remove('changelog__search--active');
        }
    }

    /**
     * Effacement de la recherche
     */
    clearSearch() {
        if (this.searchInput) {
            this.searchInput.value = '';
            this.handleSearch('');
        }
    }

    /**
     * Gestion des boutons de toggle
     */
    handleToggleClick(e) {
        const button = e.currentTarget;
        const targetId = button.dataset.target;
        const target = document.querySelector(targetId);

        if (!target) return;

        const isExpanded = button.getAttribute('aria-expanded') === 'true';

        // Mettre à jour l'attribut ARIA
        button.setAttribute('aria-expanded', !isExpanded);

        // Alterner l'affichage
        if (isExpanded) {
            this.collapseSection(target);
        } else {
            this.expandSection(target);
        }
    }

    /**
     * Expansion d'une section
     */
    expandSection(section) {
        section.style.maxHeight = section.scrollHeight + 'px';
        section.classList.add('changelog__section--expanded');
    }

    /**
     * Contraction d'une section
     */
    collapseSection(section) {
        section.style.maxHeight = '0';
        section.classList.remove('changelog__section--expanded');
    }

    /**
     * Gestion du formulaire newsletter
     */
    handleNewsletterSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const email = formData.get('email') || e.target.querySelector('input[type="email"]')?.value;

        if (!email || !this.isValidEmail(email)) {
            this.showNewsletterMessage('Veuillez entrer une adresse email valide.', 'error');
            return;
        }

        // Simuler l'envoi (à remplacer par un vrai appel API)
        this.subscribeToNewsletter(email);
    }

    /**
     * Validation d'email
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Abonnement à la newsletter
     */
    async subscribeToNewsletter(email) {
        const form = this.newsletterForm;
        const button = form.querySelector('.changelog__newsletter-button');
        const originalText = button.querySelector('.changelog__newsletter-button-text').textContent;

        try {
            // État de chargement
            button.disabled = true;
            button.querySelector('.changelog__newsletter-button-text').textContent = 'Inscription...';
            form.classList.add('changelog__newsletter-form--loading');

            // Simuler l'appel API (remplacer par un vrai appel)
            await this.mockApiCall();

            // Succès
            this.showNewsletterMessage('Inscription réussie ! Merci de votre intérêt.', 'success');
            form.reset();

            // Analytique
            this.trackNewsletterSubscription(email);

        } catch (error) {
            console.error('Erreur lors de l\'inscription:', error);
            this.showNewsletterMessage('Une erreur est survenue. Veuillez réessayer.', 'error');
        } finally {
            // Restaurer l'état du bouton
            button.disabled = false;
            button.querySelector('.changelog__newsletter-button-text').textContent = originalText;
            form.classList.remove('changelog__newsletter-form--loading');
        }
    }

    /**
     * Simulation d'appel API
     */
    mockApiCall() {
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                // Simuler une réussite 90% du temps
                if (Math.random() > 0.1) {
                    resolve();
                } else {
                    reject(new Error('Erreur simulée'));
                }
            }, 1500);
        });
    }

    /**
     * Affichage des messages newsletter
     */
    showNewsletterMessage(message, type = 'info') {
        // Supprimer le message existant
        const existingMessage = document.querySelector('.changelog__newsletter-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        // Créer le nouveau message
        const messageElement = document.createElement('div');
        messageElement.className = `changelog__newsletter-message changelog__newsletter-message--${type}`;
        messageElement.innerHTML = `
            <div class="changelog__newsletter-message-content">
                <span class="changelog__newsletter-message-icon">
                    ${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}
                </span>
                <span class="changelog__newsletter-message-text">${message}</span>
            </div>
        `;

        // Insérer après le formulaire
        this.newsletterForm.insertAdjacentElement('afterend', messageElement);

        // Animation d'apparition
        setTimeout(() => {
            messageElement.classList.add('changelog__newsletter-message--visible');
        }, 50);

        // Suppression automatique après 5 secondes
        setTimeout(() => {
            if (messageElement.parentNode) {
                messageElement.classList.remove('changelog__newsletter-message--visible');
                setTimeout(() => {
                    messageElement.remove();
                }, 300);
            }
        }, 5000);
    }

    /**
     * Configuration de la navigation au clavier
     */
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Raccourcis clavier
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'f':
                        e.preventDefault();
                        this.focusSearch();
                        break;
                    case 'k':
                        e.preventDefault();
                        this.focusSearch();
                        break;
                }
            }

            // Navigation avec les flèches
            if (e.target === this.searchInput) {
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.focusFirstVisibleEntry();
                        break;
                    case 'Escape':
                        this.clearSearch();
                        break;
                }
            }
        });
    }

    /**
     * Focus sur le champ de recherche
     */
    focusSearch() {
        if (this.searchInput) {
            this.searchInput.focus();
            this.searchInput.select();
        }
    }

    /**
     * Focus sur la première entrée visible
     */
    focusFirstVisibleEntry() {
        const firstVisible = document.querySelector('.changelog__entry:not(.changelog__entry--hidden)');
        if (firstVisible) {
            firstVisible.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    /**
     * Animation initiale des entrées
     */
    animateInitialEntries() {
        // Délai pour permettre au CSS de se charger
        setTimeout(() => {
            this.entries.forEach((entry, index) => {
                if (this.isElementInViewport(entry)) {
                    setTimeout(() => {
                        entry.classList.add('fade-in-up');
                    }, index * this.config.animationDelay);
                }
            });
        }, 100);
    }

    /**
     * Vérification si un élément est dans le viewport
     */
    isElementInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    /**
     * Mise à jour de l'état des entrées visibles
     */
    updateVisibleEntriesState(visibleEntries) {
        this.visibleEntries.clear();
        visibleEntries.forEach((entry, index) => {
            this.visibleEntries.add(entry);
        });

        // Émettre un événement personnalisé
        this.dispatchEvent('changelog:entriesFiltered', {
            count: visibleEntries.length,
            filter: this.activeFilter,
            searchTerm: this.searchTerm
        });
    }

    /**
     * Suivi de l'utilisation des filtres
     */
    trackFilterUsage(filterType) {
        this.dispatchEvent('changelog:filterUsed', {
            filter: filterType,
            timestamp: new Date().toISOString()
        });

        // Intégration avec Google Analytics ou autre
        if (typeof gtag !== 'undefined') {
            gtag('event', 'changelog_filter_used', {
                event_category: 'changelog',
                event_label: filterType
            });
        }
    }

    /**
     * Suivi des abonnements newsletter
     */
    trackNewsletterSubscription(email) {
        this.dispatchEvent('changelog:newsletterSubscribed', {
            email: email, // En production, ne pas logger l'email complet
            timestamp: new Date().toISOString()
        });

        // Intégration avec Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'newsletter_subscription', {
                event_category: 'engagement',
                event_label: 'changelog'
            });
        }
    }

    /**
     * Émission d'événements personnalisés
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, {
            detail: detail,
            bubbles: true,
            cancelable: true
        });

        if (this.changelog) {
            this.changelog.dispatchEvent(event);
        }
    }

    /**
     * Réinitialisation des filtres
     */
    resetFilters() {
        this.activeFilter = 'all';
        this.searchTerm = '';

        if (this.searchInput) {
            this.searchInput.value = '';
        }

        // Réinitialiser les filtres visuels
        this.filters.forEach(filter => {
            filter.classList.remove('changelog__filter--active');
        });

        const defaultFilter = document.querySelector('.changelog__filter[data-filter="all"]');
        if (defaultFilter) {
            defaultFilter.classList.add('changelog__filter--active');
        }

        // Afficher toutes les entrées
        this.filterEntries('all');
    }

    /**
     * Obtenir les statistiques d'utilisation
     */
    getUsageStats() {
        return {
            totalEntries: this.entries.length,
            visibleEntries: this.visibleEntries.size,
            activeFilter: this.activeFilter,
            searchTerm: this.searchTerm,
            hasActiveSearch: this.searchTerm.length > 0
        };
    }

    /**
     * Nettoyage des ressources
     */
    destroy() {
        // Déconnecter l'observateur d'intersection
        if (this.animationObserver) {
            this.animationObserver.disconnect();
        }

        // Supprimer les écouteurs d'événements
        this.filters.forEach(filter => {
            filter.removeEventListener('click', this.handleFilterClick);
        });

        if (this.searchInput) {
            this.searchInput.removeEventListener('input', this.handleSearch);
        }

        this.toggleButtons.forEach(button => {
            button.removeEventListener('click', this.handleToggleClick);
        });

        if (this.newsletterForm) {
            this.newsletterForm.removeEventListener('submit', this.handleNewsletterSubmit);
        }

        // Nettoyer les messages
        const messages = document.querySelectorAll('.changelog__newsletter-message');
        messages.forEach(message => message.remove());
    }
}

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du composant changelog
    window.changelogComponent = new ChangelogComponent();

    // Raccourci clavier pour les développeurs
    if (window.location.search.includes('debug=changelog')) {
        console.log('Mode debug changelog activé');
        console.log('Stats:', window.changelogComponent.getUsageStats());

        // Ajouter des commandes debug
        window.debugChangelog = {
            resetFilters: () => window.changelogComponent.resetFilters(),
            getStats: () => window.changelogComponent.getUsageStats(),
            filterByType: (type) => window.changelogComponent.filterEntries(type)
        };
    }
});

// Export pour utilisation externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChangelogComponent;
} else if (typeof window !== 'undefined') {
    window.ChangelogComponent = ChangelogComponent;
}
