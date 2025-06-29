/**
 * Documentation Component - Error Explorer
 * Gestion de la navigation, du TOC et des interactions
 */
class DocumentationComponent {
    constructor() {
        // Éléments DOM
        this.sidebar = document.getElementById('docs-sidebar');
        this.mobileToggle = document.getElementById('mobile-toggle');
        this.sidebarToggle = document.getElementById('sidebar-toggle');
        this.toc = document.getElementById('docs-toc');
        this.tocList = document.getElementById('toc-list');
        this.navLinks = document.querySelectorAll('.docs__nav-link');
        this.copyButtons = document.querySelectorAll('.docs__copy-btn');

        // État
        this.isSidebarOpen = false;
        this.currentSection = 'getting-started';
        this.tocItems = [];

        this.init();
    }

    /**
     * Initialisation du composant
     */
    init() {
        this.bindEvents();
        this.generateTOC();
        this.initScrollSpy();
        this.initSmoothScrolling();
        this.initCopyButtons();
        this.initSearchFunctionality();
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Toggle mobile sidebar
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        // Toggle sidebar depuis l'intérieur
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => {
                this.closeSidebar();
            });
        }

        // Fermer sidebar sur clic extérieur
        document.addEventListener('click', (e) => {
            if (this.isSidebarOpen && !this.sidebar.contains(e.target) && !this.mobileToggle.contains(e.target)) {
                this.closeSidebar();
            }
        });

        // Redimensionnement de fenêtre
        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                this.closeSidebar();
            }
        });

        // Gestion des touches clavier
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isSidebarOpen) {
                this.closeSidebar();
            }
        });
    }

    /**
     * Toggle sidebar mobile
     */
    toggleSidebar() {
        if (this.isSidebarOpen) {
            this.closeSidebar();
        } else {
            this.openSidebar();
        }
    }

    /**
     * Ouvrir la sidebar
     */
    openSidebar() {
        this.sidebar.classList.add('active');
        this.isSidebarOpen = true;
        document.body.style.overflow = 'hidden';
    }

    /**
     * Fermer la sidebar
     */
    closeSidebar() {
        if (!this.isSidebarOpen) return;

        this.sidebar.classList.remove('active');
        this.isSidebarOpen = false;
        document.body.style.overflow = '';
    }

    /**
     * Génération automatique du Table of Contents
     */
    generateTOC() {
        if (!this.tocList) return;

        const headings = document.querySelectorAll('.docs__section h2, .docs__section h3');
        this.tocItems = [];

        headings.forEach((heading, index) => {
            // Créer un ID si il n'existe pas
            if (!heading.id) {
                heading.id = `heading-${index}`;
            }

            // Déterminer le niveau
            const level = heading.tagName.toLowerCase() === 'h2' ? 1 : 2;
            const text = heading.textContent.replace(/^[🚀📦⚙️🎼🅻⚛️💚🅰️🐍📝📚🔗💡🔧]\s*/, '');

            this.tocItems.push({
                id: heading.id,
                text: text,
                level: level,
                element: heading
            });
        });

        // Générer le HTML du TOC
        this.renderTOC();
    }

    /**
     * Rendu du Table of Contents
     */
    renderTOC() {
        if (!this.tocList || this.tocItems.length === 0) return;

        const tocHTML = this.tocItems.map(item => {
            const indent = item.level === 2 ? 'style="padding-left: 1rem;"' : '';
            return `
                <li>
                    <a href="#${item.id}" ${indent} data-toc-link="${item.id}">
                        ${item.text}
                    </a>
                </li>
            `;
        }).join('');

        this.tocList.innerHTML = tocHTML;

        // Ajouter les écouteurs pour le TOC
        this.tocList.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => {
                this.handleTOCClick(e);
            });
        });
    }

    /**
     * Gestion des clics sur le TOC
     */
    handleTOCClick(e) {
        e.preventDefault();
        const targetId = e.target.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);

        if (targetElement) {
            const headerOffset = 100;
            const elementPosition = targetElement.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });

            // Fermer la sidebar mobile si ouverte
            if (this.isSidebarOpen) {
                this.closeSidebar();
            }
        }
    }

    /**
     * Initialisation du scroll spy
     */
    initScrollSpy() {
        const observerOptions = {
            rootMargin: '-20% 0px -70% 0px',
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.setActiveSection(entry.target.id);
                }
            });
        }, observerOptions);

        // Observer toutes les sections
        document.querySelectorAll('.docs__section').forEach(section => {
            observer.observe(section);
        });
    }

    /**
     * Définir la section active
     */
    setActiveSection(sectionId) {
        if (this.currentSection === sectionId) return;

        this.currentSection = sectionId;

        // Mettre à jour la navigation sidebar
        this.navLinks.forEach(link => {
            link.classList.remove('docs__nav-link--active');
            if (link.getAttribute('href') === `#${sectionId}`) {
                link.classList.add('docs__nav-link--active');
            }
        });

        // Mettre à jour le TOC
        if (this.tocList) {
            this.tocList.querySelectorAll('a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('data-toc-link') === sectionId) {
                    link.classList.add('active');
                }
            });
        }
    }

    /**
     * Initialisation du smooth scrolling
     */
    initSmoothScrolling() {
        // Liens de navigation
        this.navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                this.handleNavClick(e);
            });
        });

        // Liens rapides dans le hero
        document.querySelectorAll('.docs__hero-link').forEach(link => {
            link.addEventListener('click', (e) => {
                this.handleNavClick(e);
            });
        });
    }

    /**
     * Gestion des clics de navigation
     */
    handleNavClick(e) {
        const href = e.target.getAttribute('href');

        if (href && href.startsWith('#')) {
            e.preventDefault();
            const targetId = href.substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                const headerOffset = 100;
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });

                // Fermer la sidebar mobile
                if (this.isSidebarOpen) {
                    this.closeSidebar();
                }
            }
        }
    }

    /**
     * Initialisation des boutons de copie
     */
    initCopyButtons() {
        this.copyButtons.forEach(button => {
            button.addEventListener('click', () => {
                this.handleCopyClick(button);
            });
        });
    }

    /**
     * Gestion des clics sur les boutons de copie
     */
    async handleCopyClick(button) {
        const textToCopy = button.getAttribute('data-copy');

        if (!textToCopy) {
            // Si pas de data-copy, copier le contenu du code block parent
            const codeBlock = button.closest('.docs__code-block');
            if (codeBlock) {
                const code = codeBlock.querySelector('code');
                if (code) {
                    await this.copyToClipboard(code.textContent, button);
                }
            }
            return;
        }

        await this.copyToClipboard(textToCopy, button);
    }

    /**
     * Copier dans le presse-papiers
     */
    async copyToClipboard(text, button) {
        try {
            await navigator.clipboard.writeText(text);
            this.showCopySuccess(button);
        } catch (err) {
            // Fallback pour les navigateurs plus anciens
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();

            try {
                document.execCommand('copy');
                this.showCopySuccess(button);
            } catch (fallbackErr) {
                console.error('Erreur lors de la copie:', fallbackErr);
                this.showCopyError(button);
            }

            document.body.removeChild(textArea);
        }
    }

    /**
     * Afficher le succès de copie
     */
    showCopySuccess(button) {
        const originalHTML = button.innerHTML;
        button.classList.add('copied');
        button.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20,6 9,17 4,12"></polyline>
            </svg>
        `;

        setTimeout(() => {
            button.classList.remove('copied');
            button.innerHTML = originalHTML;
        }, 2000);
    }

    /**
     * Afficher l'erreur de copie
     */
    showCopyError(button) {
        const originalHTML = button.innerHTML;
        button.style.color = '#ef4444';

        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.style.color = '';
        }, 2000);
    }

    /**
     * Initialisation de la fonctionnalité de recherche
     */
    initSearchFunctionality() {
        // Créer un champ de recherche si nécessaire
        this.createSearchBox();
    }

    /**
     * Créer la boîte de recherche
     */
    createSearchBox() {
        const searchContainer = document.createElement('div');
        searchContainer.className = 'docs__search';
        searchContainer.innerHTML = `
            <div class="docs__search-input-container">
                <input type="text" 
                       class="docs__search-input" 
                       placeholder="Rechercher dans la documentation..."
                       id="docs-search">
                <svg class="docs__search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </div>
            <div class="docs__search-results" id="search-results"></div>
        `;

        // Insérer après le header de la sidebar
        const sidebarHeader = this.sidebar?.querySelector('.docs__sidebar-header');
        if (sidebarHeader) {
            sidebarHeader.insertAdjacentElement('afterend', searchContainer);
            this.initSearch();
        }
    }

    /**
     * Initialisation de la recherche
     */
    initSearch() {
        const searchInput = document.getElementById('docs-search');
        const searchResults = document.getElementById('search-results');

        if (!searchInput || !searchResults) return;

        let searchTimeout;

        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();

            if (query.length < 2) {
                this.clearSearchResults();
                return;
            }

            searchTimeout = setTimeout(() => {
                this.performSearch(query, searchResults);
            }, 300);
        });

        // Fermer les résultats sur clic extérieur
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                this.clearSearchResults();
            }
        });
    }

    /**
     * Effectuer la recherche
     */
    performSearch(query, resultsContainer) {
        const sections = document.querySelectorAll('.docs__section');
        const results = [];

        sections.forEach(section => {
            const title = section.querySelector('.docs__section-title')?.textContent || '';
            const content = section.textContent.toLowerCase();
            const queryLower = query.toLowerCase();

            if (content.includes(queryLower)) {
                const titleClean = title.replace(/^[🚀📦⚙️🎼🅻⚛️💚🅰️🐍📝📚🔗💡🔧]\s*/, '');

                results.push({
                    id: section.id,
                    title: titleClean,
                    excerpt: this.getSearchExcerpt(section, query)
                });
            }
        });

        this.displaySearchResults(results, resultsContainer, query);
    }

    /**
     * Obtenir un extrait de recherche
     */
    getSearchExcerpt(section, query) {
        const paragraphs = section.querySelectorAll('p');
        const queryLower = query.toLowerCase();

        for (const p of paragraphs) {
            const text = p.textContent;
            const textLower = text.toLowerCase();

            if (textLower.includes(queryLower)) {
                const index = textLower.indexOf(queryLower);
                const start = Math.max(0, index - 50);
                const end = Math.min(text.length, index + query.length + 50);

                let excerpt = text.substring(start, end);
                if (start > 0) excerpt = '...' + excerpt;
                if (end < text.length) excerpt += '...';

                return excerpt;
            }
        }

        return '';
    }

    /**
     * Afficher les résultats de recherche
     */
    displaySearchResults(results, container, query) {
        if (results.length === 0) {
            container.innerHTML = `
                <div class="docs__search-no-results">
                    Aucun résultat pour "${query}"
                </div>
            `;
            container.style.display = 'block';
            return;
        }

        const resultsHTML = results.map(result => `
            <a href="#${result.id}" class="docs__search-result">
                <div class="docs__search-result-title">${result.title}</div>
                ${result.excerpt ? `<div class="docs__search-result-excerpt">${result.excerpt}</div>` : ''}
            </a>
        `).join('');

        container.innerHTML = resultsHTML;
        container.style.display = 'block';

        // Ajouter les écouteurs sur les résultats
        container.querySelectorAll('.docs__search-result').forEach(link => {
            link.addEventListener('click', (e) => {
                this.handleSearchResultClick(e);
            });
        });
    }

    /**
     * Gestion des clics sur les résultats de recherche
     */
    handleSearchResultClick(e) {
        e.preventDefault();
        const href = e.currentTarget.getAttribute('href');
        const targetId = href.substring(1);

        // Effacer la recherche
        this.clearSearchResults();
        document.getElementById('docs-search').value = '';

        // Naviguer vers la section
        const targetElement = document.getElementById(targetId);
        if (targetElement) {
            const headerOffset = 100;
            const elementPosition = targetElement.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });

            // Fermer la sidebar mobile
            if (this.isSidebarOpen) {
                this.closeSidebar();
            }
        }
    }

    /**
     * Effacer les résultats de recherche
     */
    clearSearchResults() {
        const searchResults = document.getElementById('search-results');
        if (searchResults) {
            searchResults.innerHTML = '';
            searchResults.style.display = 'none';
        }
    }

    /**
     * API publique - Aller à une section
     */
    goToSection(sectionId) {
        const element = document.getElementById(sectionId);
        if (element) {
            const headerOffset = 100;
            const elementPosition = element.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    }

    /**
     * API publique - Obtenir la section courante
     */
    getCurrentSection() {
        return this.currentSection;
    }

    /**
     * Nettoyage
     */
    destroy() {
        // Supprimer les écouteurs d'événements
        if (this.mobileToggle) {
            this.mobileToggle.removeEventListener('click', this.toggleSidebar);
        }

        // Restaurer le scroll du body
        document.body.style.overflow = '';
    }
}

/**
 * Styles CSS additionnels pour la recherche
 */
const searchStyles = `
.docs__search {
    margin: 1rem 0 2rem 0;
    position: relative;
}

.docs__search-input-container {
    position: relative;
}

.docs__search-input {
    width: 100%;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: #ffffff;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.docs__search-input:focus {
    outline: none;
    border-color: #3b82f6;
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.docs__search-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.docs__search-icon {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.5);
    pointer-events: none;
}

.docs__search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: rgba(15, 23, 42, 0.98);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    margin-top: 0.5rem;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.docs__search-result {
    display: block;
    padding: 0.75rem 1rem;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.2s ease;
}

.docs__search-result:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.docs__search-result:last-child {
    border-bottom: none;
}

.docs__search-result-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #3b82f6;
}

.docs__search-result-excerpt {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.4;
}

.docs__search-no-results {
    padding: 1rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
}

@media (max-width: 1024px) {
    .docs__search-results {
        position: fixed;
        top: auto;
        left: 1rem;
        right: 1rem;
        bottom: 1rem;
        max-height: 50vh;
    }
}
`;

/**
 * Initialisation automatique au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', () => {
    // Ajouter les styles de recherche
    const styleSheet = document.createElement('style');
    styleSheet.textContent = searchStyles;
    document.head.appendChild(styleSheet);

    // Initialiser le composant
    window.docsComponent = new DocumentationComponent();

    // Exposer des méthodes globales pour les tests
    window.docsAPI = {
        goToSection: (id) => window.docsComponent.goToSection(id),
        getCurrentSection: () => window.docsComponent.getCurrentSection(),
        toggleSidebar: () => window.docsComponent.toggleSidebar()
    };

    // Animation d'entrée des sections
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    // Observer toutes les sections pour les animations
    document.querySelectorAll('.docs__section').forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(30px)';
        section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(section);
    });
});

// Export pour utilisation externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DocumentationComponent;
} else if (typeof window !== 'undefined') {
    window.DocumentationComponent = DocumentationComponent;
}
