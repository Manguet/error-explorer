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
        this.initFrameworkTabs();
        this.initCollapsibles();
        this.initProgressBar();
        this.initQuickActions();
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
     * Initialisation des onglets de frameworks
     */
    initFrameworkTabs() {
        console.log('🔍 Initializing framework tabs...');
        const tabButtons = document.querySelectorAll('.docs__tab-btn');
        const tabPanels = document.querySelectorAll('.docs__tab-panel');
        
        console.log('📊 Found', tabButtons.length, 'tab buttons and', tabPanels.length, 'tab panels');
        
        if (tabButtons.length === 0) {
            console.warn('⚠️ No tab buttons found');
            return;
        }
        
        // État initial depuis l'URL
        const urlParams = new URLSearchParams(window.location.search);
        const activeFramework = urlParams.get('framework') || 'symfony';
        
        tabButtons.forEach(button => {
            console.log('🎯 Adding click listener to button:', button.dataset.framework);
            button.addEventListener('click', (e) => {
                const framework = e.currentTarget.dataset.framework;
                console.log('🔄 Tab clicked:', framework);
                this.switchFrameworkTab(framework);
            });
            
            // Navigation clavier
            button.addEventListener('keydown', (e) => {
                const buttons = Array.from(tabButtons);
                const currentIndex = buttons.indexOf(e.currentTarget);
                
                if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    e.preventDefault();
                    const nextIndex = e.key === 'ArrowLeft' 
                        ? (currentIndex - 1 + buttons.length) % buttons.length
                        : (currentIndex + 1) % buttons.length;
                    buttons[nextIndex].focus();
                    buttons[nextIndex].click();
                }
            });
        });
        
        // Activer l'onglet initial
        this.switchFrameworkTab(activeFramework);
    }
    
    /**
     * Changer d'onglet de framework
     */
    switchFrameworkTab(framework) {
        console.log('🔄 Switching to framework tab:', framework);
        
        // Mettre à jour les boutons
        const tabButtons = document.querySelectorAll('.docs__tab-btn');
        console.log('📋 Updating', tabButtons.length, 'tab buttons');
        
        tabButtons.forEach(btn => {
            btn.classList.remove('docs__tab-btn--active');
            btn.setAttribute('aria-selected', 'false');
        });
        
        const activeButton = document.querySelector(`[data-framework="${framework}"]`);
        if (activeButton) {
            activeButton.classList.add('docs__tab-btn--active');
            activeButton.setAttribute('aria-selected', 'true');
            console.log('✅ Activated button for:', framework);
        } else {
            console.error('❌ Button not found for framework:', framework);
        }
        
        // Mettre à jour les panneaux
        const tabPanels = document.querySelectorAll('.docs__tab-panel');
        console.log('📋 Updating', tabPanels.length, 'tab panels');
        
        tabPanels.forEach(panel => {
            panel.classList.remove('docs__tab-panel--active');
        });
        
        const activePanel = document.querySelector(`.docs__tab-panel[data-framework="${framework}"]`);
        if (activePanel) {
            activePanel.classList.add('docs__tab-panel--active');
            console.log('✅ Activated panel for:', framework);
        } else {
            console.error('❌ Panel not found for framework:', framework);
        }
        
        // Mettre à jour l'URL
        const url = new URL(window.location);
        url.searchParams.set('framework', framework);
        window.history.replaceState({}, '', url);
        
        // Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'framework_tab_change', {
                'framework': framework,
                'page_title': document.title
            });
        }
    }
    
    /**
     * Initialisation des sections pliables
     */
    initCollapsibles() {
        const collapsibleHeaders = document.querySelectorAll('.docs__collapsible-header');
        
        collapsibleHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const content = header.nextElementSibling;
                const isActive = header.classList.contains('docs__collapsible-header--active');
                
                // Fermer les autres dans le même groupe
                const group = header.closest('.docs__collapsible-group');
                if (group) {
                    group.querySelectorAll('.docs__collapsible-header').forEach(h => {
                        if (h !== header) {
                            h.classList.remove('docs__collapsible-header--active');
                            h.nextElementSibling.classList.remove('docs__collapsible-content--active');
                            h.setAttribute('aria-expanded', 'false');
                        }
                    });
                }
                
                // Toggle current
                header.classList.toggle('docs__collapsible-header--active');
                content.classList.toggle('docs__collapsible-content--active');
                header.setAttribute('aria-expanded', !isActive);
            });
            
            // Accessibilité
            header.setAttribute('role', 'button');
            header.setAttribute('tabindex', '0');
            header.setAttribute('aria-expanded', 'false');
            
            // Support clavier
            header.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    header.click();
                }
            });
        });
    }
    
    /**
     * Initialisation de la barre de progression
     */
    initProgressBar() {
        // Créer la barre de progression
        const progressBar = document.createElement('div');
        progressBar.className = 'docs__progress';
        progressBar.innerHTML = '<div class="docs__progress-bar"></div>';
        document.body.appendChild(progressBar);
        
        const updateProgress = () => {
            const scrolled = window.pageYOffset;
            const total = document.documentElement.scrollHeight - window.innerHeight;
            const progress = (scrolled / total) * 100;
            
            const bar = progressBar.querySelector('.docs__progress-bar');
            if (bar) {
                bar.style.width = `${Math.min(100, Math.max(0, progress))}%`;
            }
        };
        
        window.addEventListener('scroll', updateProgress);
        updateProgress();
    }
    
    /**
     * Initialisation des actions rapides
     */
    initQuickActions() {
        const quickActions = document.createElement('div');
        quickActions.className = 'docs__quick-actions';
        quickActions.innerHTML = `
            <button class="docs__quick-actions-btn docs__quick-actions-btn--primary" 
                    id="scroll-to-top" 
                    aria-label="Retour en haut de page"
                    title="Retour en haut">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 15l-6-6-6 6"/>
                </svg>
            </button>
            <button class="docs__quick-actions-btn" 
                    id="toggle-theme" 
                    aria-label="Changer le thème"
                    title="Basculer le thème">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/>
                    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
            </button>
        `;
        
        document.body.appendChild(quickActions);
        
        // Scroll to top
        const scrollToTopBtn = quickActions.querySelector('#scroll-to-top');
        scrollToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Theme toggle (pour future implémentation)
        const themeToggle = quickActions.querySelector('#toggle-theme');
        themeToggle.addEventListener('click', () => {
            // Future: toggle entre dark/light theme
            console.log('Theme toggle clicked');
        });
        
        // Montrer/cacher selon la position de scroll
        let isVisible = false;
        window.addEventListener('scroll', () => {
            const shouldShow = window.pageYOffset > 300;
            
            if (shouldShow !== isVisible) {
                isVisible = shouldShow;
                quickActions.style.opacity = shouldShow ? '1' : '0';
                quickActions.style.visibility = shouldShow ? 'visible' : 'hidden';
                quickActions.style.transform = shouldShow ? 'translateY(0)' : 'translateY(20px)';
            }
        });
        
        // État initial
        quickActions.style.opacity = '0';
        quickActions.style.visibility = 'hidden';
        quickActions.style.transform = 'translateY(20px)';
        quickActions.style.transition = 'all 0.3s ease';
    }
    
    /**
     * Amélioration de la recherche avec support des onglets
     */
    performSearch(query, resultsContainer) {
        const sections = document.querySelectorAll('.docs__section, .docs__tab-panel');
        const results = [];

        sections.forEach(section => {
            const title = section.querySelector('.docs__section-title, h3')?.textContent || '';
            const content = section.textContent.toLowerCase();
            const queryLower = query.toLowerCase();

            if (content.includes(queryLower)) {
                const titleClean = title.replace(/^[🚀📦⚙️🎼🅻⚛️💚🅰️🐍📝📚🔗💡🔧]\s*/, '');
                const framework = section.dataset.framework || null;

                results.push({
                    id: section.id || framework,
                    title: titleClean,
                    excerpt: this.getSearchExcerpt(section, query),
                    framework: framework
                });
            }
        });

        this.displaySearchResults(results, resultsContainer, query);
    }
    
    /**
     * Affichage amélioré des résultats de recherche
     */
    displaySearchResults(results, container, query) {
        if (results.length === 0) {
            container.innerHTML = `
                <div class="docs__search-no-results">
                    <div style="margin-bottom: 0.5rem;">Aucun résultat pour "${query}"</div>
                    <div style="font-size: 0.75rem; opacity: 0.7;">Essayez des mots-clés différents</div>
                </div>
            `;
            container.style.display = 'block';
            return;
        }

        const resultsHTML = results.map(result => {
            const frameworkBadge = result.framework ? 
                `<span class="docs__search-result-framework">${result.framework}</span>` : '';
            
            return `
                <a href="#${result.id}" class="docs__search-result" data-framework="${result.framework || ''}">
                    <div class="docs__search-result-header">
                        <div class="docs__search-result-title">${this.highlightQuery(result.title, query)}</div>
                        ${frameworkBadge}
                    </div>
                    ${result.excerpt ? `<div class="docs__search-result-excerpt">${this.highlightQuery(result.excerpt, query)}</div>` : ''}
                </a>
            `;
        }).join('');

        container.innerHTML = resultsHTML;
        container.style.display = 'block';

        // Ajouter les écouteurs avec support des onglets
        container.querySelectorAll('.docs__search-result').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const framework = e.currentTarget.dataset.framework;
                const targetId = e.currentTarget.getAttribute('href').substring(1);
                
                // Switch tab si nécessaire
                if (framework) {
                    this.switchFrameworkTab(framework);
                }
                
                // Navigation après un court délai pour laisser l'onglet se charger
                setTimeout(() => {
                    this.handleSearchResultClick(e);
                }, framework ? 100 : 0);
            });
        });
    }
    
    /**
     * Surligner les termes de recherche
     */
    highlightQuery(text, query) {
        if (!query || !text) return text;
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<mark style="background: rgba(59, 130, 246, 0.3); color: inherit; padding: 0.1em 0.2em; border-radius: 2px;">$1</mark>');
    }
    
    /**
     * API publique - Aller à un framework spécifique
     */
    goToFramework(framework) {
        if (document.querySelector(`[data-framework="${framework}"]`)) {
            this.switchFrameworkTab(framework);
            
            // Scroll vers la section des frameworks
            const frameworkSection = document.getElementById('framework-integrations');
            if (frameworkSection) {
                this.goToSection('framework-integrations');
            }
        }
    }

    /**
     * Nettoyage
     */
    destroy() {
        // Supprimer les écouteurs d'événements
        if (this.mobileToggle) {
            this.mobileToggle.removeEventListener('click', this.toggleSidebar);
        }

        // Supprimer les éléments créés dynamiquement
        const progressBar = document.querySelector('.docs__progress');
        const quickActions = document.querySelector('.docs__quick-actions');
        
        if (progressBar) progressBar.remove();
        if (quickActions) quickActions.remove();

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

.docs__search-result-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.docs__search-result-title {
    font-weight: 600;
    color: #3b82f6;
    flex: 1;
}

.docs__search-result-framework {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
    padding: 0.125rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: capitalize;
    white-space: nowrap;
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
    console.log('🚀 DocumentationComponent starting initialization...');
    
    // Ajouter les styles de recherche
    const styleSheet = document.createElement('style');
    styleSheet.textContent = searchStyles;
    document.head.appendChild(styleSheet);

    // Initialiser le composant
    console.log('🔧 Creating DocumentationComponent instance...');
    window.docsComponent = new DocumentationComponent();
    console.log('✅ DocumentationComponent initialized successfully');

    // Exposer des méthodes globales pour les tests
    window.docsAPI = {
        goToSection: (id) => window.docsComponent.goToSection(id),
        getCurrentSection: () => window.docsComponent.getCurrentSection(),
        toggleSidebar: () => window.docsComponent.toggleSidebar(),
        goToFramework: (framework) => window.docsComponent.goToFramework(framework),
        switchFrameworkTab: (framework) => window.docsComponent.switchFrameworkTab(framework)
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
