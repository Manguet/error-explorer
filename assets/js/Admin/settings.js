class SettingsManager {
    constructor() {
        this.currentSection = 'general';
        this.hasUnsavedChanges = false;
        this.saveTimeout = null;

        this.init();
    }

    init() {
        this.bindEvents();
        this.loadInitialSection();
        this.setupAutoSave();
        this.setupBeforeUnload();
    }

    bindEvents() {
        // Navigation entre sections
        document.querySelectorAll('.settings-nav-item a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionId = link.getAttribute('href').substring(1);
                this.switchSection(sectionId, link);
            });
        });

        // Détection des changements dans les formulaires
        document.querySelectorAll('.settings-section input, .settings-section textarea, .settings-section select').forEach(field => {
            field.addEventListener('change', () => {
                this.markAsChanged();
            });
        });

        // Bouton de sauvegarde global
        document.getElementById('save-all-settings')?.addEventListener('click', () => {
            this.saveCurrentSection();
        });

        // Actions spéciales
        this.bindSpecialActions();
    }

    bindSpecialActions() {
        // Test SMTP
        document.querySelector('[data-action="test-smtp"]')?.addEventListener('click', () => {
            this.testSmtpConfiguration();
        });

        // Copier dans le presse-papier
        document.querySelectorAll('[data-action="copy"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const input = e.target.previousElementSibling;
                this.copyToClipboard(input.value);
            });
        });

        // Générer nouvelle clé API
        document.querySelector('[data-action="generate-api-key"]')?.addEventListener('click', () => {
            this.generateNewApiKey();
        });

        // Reset section
        document.querySelectorAll('[data-action="reset-section"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const section = e.target.dataset.section;
                this.resetSection(section);
            });
        });

        // Import/Export
        document.getElementById('export-settings')?.addEventListener('click', () => {
            this.exportSettings();
        });

        document.getElementById('import-settings-file')?.addEventListener('change', (e) => {
            this.importSettings(e.target.files[0]);
        });
    }

    loadInitialSection() {
        const hash = window.location.hash.substring(1) || 'general';
        const link = document.querySelector(`a[href="#${hash}"]`);
        if (link) {
            this.switchSection(hash, link);
        }
    }

    switchSection(sectionId, linkElement) {
        // Sauvegarder la section courante si des changements existent
        if (this.hasUnsavedChanges) {
            if (confirm('Vous avez des modifications non sauvegardées. Voulez-vous les sauvegarder avant de changer de section ?')) {
                this.saveCurrentSection().then(() => {
                    this.doSwitchSection(sectionId, linkElement);
                });
                return;
            }
        }

        this.doSwitchSection(sectionId, linkElement);
    }

    doSwitchSection(sectionId, linkElement) {
        // Masquer toutes les sections
        document.querySelectorAll('.settings-section').forEach(section => {
            section.style.display = 'none';
        });

        // Afficher la section sélectionnée
        const targetSection = document.getElementById('section-' + sectionId);
        if (targetSection) {
            targetSection.style.display = 'block';
        }

        // Mettre à jour la navigation active
        document.querySelectorAll('.settings-nav-item a').forEach(link => {
            link.classList.remove('active');
        });
        linkElement.classList.add('active');

        // Mettre à jour l'état
        this.currentSection = sectionId;
        this.hasUnsavedChanges = false;

        // Mettre à jour l'URL
        window.history.pushState({}, '', '#' + sectionId);

        // Charger les données de la section si nécessaire
        this.loadSectionData(sectionId);
    }

    async loadSectionData(sectionId) {
        try {
            const response = await fetch(`/admin/settings/get-section/${sectionId}`);
            const data = await response.json();

            if (data.success) {
                this.populateForm(sectionId, data.settings);
            }
        } catch (error) {
            console.error('Erreur lors du chargement de la section:', error);
        }
    }

    populateForm(sectionId, settings) {
        const section = document.getElementById('section-' + sectionId);
        if (!section) return;

        // Remplir les champs du formulaire
        Object.entries(settings).forEach(([key, value]) => {
            const field = section.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = Boolean(value);
                } else {
                    field.value = value || '';
                }
            }
        });
    }

    markAsChanged() {
        this.hasUnsavedChanges = true;
        this.showUnsavedIndicator();

        // Auto-save après 2 secondes d'inactivité
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        this.saveTimeout = setTimeout(() => {
            this.autoSave();
        }, 2000);
    }

    showUnsavedIndicator() {
        // Ajouter un indicateur visuel
        const activeLink = document.querySelector('.settings-nav-item a.active');
        if (activeLink && !activeLink.querySelector('.unsaved-dot')) {
            const dot = document.createElement('span');
            dot.className = 'unsaved-dot';
            dot.innerHTML = '●';
            dot.style.color = '#f59e0b';
            dot.style.marginLeft = '5px';
            activeLink.appendChild(dot);
        }
    }

    hideUnsavedIndicator() {
        const dot = document.querySelector('.unsaved-dot');
        if (dot) {
            dot.remove();
        }
    }

    async saveCurrentSection() {
        const sectionForm = document.querySelector(`#section-${this.currentSection} form`);
        if (!sectionForm) {
            this.showNotification('Aucun formulaire trouvé pour cette section', 'warning');
            return false;
        }

        try {
            this.showSaveLoader();

            const formData = new FormData(sectionForm);
            formData.append('section', this.currentSection);

            const response = await fetch('/admin/settings/update', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.hasUnsavedChanges = false;
                this.hideUnsavedIndicator();
                this.showNotification(result.message, 'success');
                return true;
            } else {
                this.showNotification(result.error, 'error');
                return false;
            }

        } catch (error) {
            console.error('Erreur lors de la sauvegarde:', error);
            this.showNotification('Erreur de communication avec le serveur', 'error');
            return false;
        } finally {
            this.hideSaveLoader();
        }
    }

    async autoSave() {
        if (this.hasUnsavedChanges) {
            const success = await this.saveCurrentSection();
            if (success) {
                this.showNotification('Sauvegarde automatique effectuée', 'info', 2000);
            }
        }
    }

    setupAutoSave() {
        // Auto-save toutes les 5 minutes si des changements existent
        setInterval(() => {
            if (this.hasUnsavedChanges) {
                this.autoSave();
            }
        }, 5 * 60 * 1000);
    }

    setupBeforeUnload() {
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
                return e.returnValue;
            }
        });
    }

    async resetSection(sectionId) {
        if (!confirm(`Êtes-vous sûr de vouloir réinitialiser la section "${sectionId}" aux valeurs par défaut ?`)) {
            return;
        }

        try {
            const response = await fetch(`/admin/settings/reset-section/${sectionId}`, {
                method: 'POST'
            });

            const result = await response.json();

            if (result.success) {
                this.populateForm(sectionId, result.settings);
                this.hasUnsavedChanges = false;
                this.hideUnsavedIndicator();
                this.showNotification(result.message, 'success');
            } else {
                this.showNotification(result.error, 'error');
            }
        } catch (error) {
            console.error('Erreur lors de la réinitialisation:', error);
            this.showNotification('Erreur de communication', 'error');
        }
    }

    async testSmtpConfiguration() {
        try {
            // Sauvegarder d'abord les paramètres email actuels
            await this.saveCurrentSection();

            this.showActionLoader('test-smtp');

            const response = await fetch('/admin/settings/test-smtp', {
                method: 'POST'
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Test SMTP réussi !', 'success');
            } else {
                this.showNotification(`Test SMTP échoué : ${result.error}`, 'error');
            }
        } catch (error) {
            console.error('Erreur lors du test SMTP:', error);
            this.showNotification('Erreur lors du test SMTP', 'error');
        } finally {
            this.hideActionLoader('test-smtp');
        }
    }

    async generateNewApiKey() {
        if (!confirm('Générer une nouvelle clé API invalidera l\'ancienne. Continuer ?')) {
            return;
        }

        try {
            // Générer une nouvelle clé
            const newKey = 'ee_sk_' + this.generateRandomString(32);

            // Mettre à jour le champ
            const keyField = document.querySelector('input[name="main_api_key"]');
            if (keyField) {
                keyField.value = newKey;
                this.markAsChanged();
            }

            this.showNotification('Nouvelle clé API générée. N\'oubliez pas de sauvegarder !', 'warning');
        } catch (error) {
            console.error('Erreur lors de la génération de clé:', error);
            this.showNotification('Erreur lors de la génération', 'error');
        }
    }

    generateRandomString(length) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    async exportSettings() {
        try {
            const response = await fetch('/admin/settings/export');
            const blob = await response.blob();

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `error-explorer-settings-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            this.showNotification('Paramètres exportés avec succès', 'success');
        } catch (error) {
            console.error('Erreur lors de l\'export:', error);
            this.showNotification('Erreur lors de l\'export', 'error');
        }
    }

    async importSettings(file) {
        if (!file) return;

        if (!confirm('L\'import remplacera tous les paramètres actuels. Continuer ?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('settings_file', file);

            const response = await fetch('/admin/settings/import', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Paramètres importés avec succès. Rechargement...', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                this.showNotification(result.error, 'error');
            }
        } catch (error) {
            console.error('Erreur lors de l\'import:', error);
            this.showNotification('Erreur lors de l\'import', 'error');
        }
    }

    copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            this.showNotification('Copié dans le presse-papier !', 'success', 2000);
        }).catch(err => {
            console.error('Erreur lors de la copie:', err);
            // Fallback pour navigateurs anciens
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.showNotification('Copié !', 'success', 2000);
        });
    }

    showNotification(message, type = 'info', duration = 5000) {
        // Créer la notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        // Styles inline pour la notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            max-width: 500px;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: ${this.getNotificationColor(type)};
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 14px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;

        document.body.appendChild(notification);

        // Animer l'entrée
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Auto-suppression
        if (duration > 0) {
            setTimeout(() => {
                this.hideNotification(notification);
            }, duration);
        }
    }

    getNotificationColor(type) {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        return colors[type] || colors.info;
    }

    hideNotification(notification) {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentElement) {
                notification.parentElement.removeChild(notification);
            }
        }, 300);
    }

    showSaveLoader() {
        const saveBtn = document.getElementById('save-all-settings');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = `
                <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 11-6.219-8.56"/>
                </svg>
                Sauvegarde...
            `;
        }
    }

    hideSaveLoader() {
        const saveBtn = document.getElementById('save-all-settings');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17,21 17,13 7,13 7,21"></polyline>
                    <polyline points="7,3 7,8 15,8"></polyline>
                </svg>
                Enregistrer les modifications
            `;
        }
    }

    showActionLoader(action) {
        const btn = document.querySelector(`[data-action="${action}"]`);
        if (btn) {
            btn.disabled = true;
            btn.dataset.originalText = btn.innerHTML;
            btn.innerHTML = `
                <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 11-6.219-8.56"/>
                </svg>
                Traitement...
            `;
        }
    }

    hideActionLoader(action) {
        const btn = document.querySelector(`[data-action="${action}"]`);
        if (btn && btn.dataset.originalText) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText;
            delete btn.dataset.originalText;
        }
    }
}

// Initialiser le gestionnaire de paramètres
document.addEventListener('DOMContentLoaded', function() {
    window.settingsManager = new SettingsManager();
});

// Fonction globale pour la compatibilité
function saveCurrentSection() {
    if (window.settingsManager) {
        window.settingsManager.saveCurrentSection();
    }
}
