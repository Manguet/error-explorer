// Actions pour les projets (activer/désactiver, supprimer, etc.)
class ProjectActions {
    constructor() {
        this.setupRoutes();
    }

    setupRoutes() {
        // Routes définies dans le template
        this.routes = window.routes || {};
    }

    // Activer/désactiver un projet
    async toggleProjectStatus(projectId) {
        try {
            const url = this.routes.projects_toggle_status?.replace('__ID__', projectId);
            if (!url) {
                throw new Error('Route projects_toggle_status non définie');
            }

            const result = await this.performAction(url);
            if (result && window.projectsDataTable) {
                window.projectsDataTable.reloadTable();
            }
            return result;
        } catch (error) {
            console.error('Erreur lors du changement de statut:', error);
            this.showNotification('Erreur lors du changement de statut', 'error');
            return false;
        }
    }

    // Supprimer un projet
    async deleteProject(projectId, projectName) {
        if (!confirm(`⚠️ Êtes-vous sûr de vouloir supprimer le projet "${projectName}" ?\n\nCette action est irréversible.`)) {
            return false;
        }

        try {
            const url = this.routes.projects_delete?.replace('__ID__', projectId);
            if (!url) {
                throw new Error('Route projects_delete non définie');
            }

            const result = await this.performAction(url);
            if (result) {
                if (window.projectsDataTable) {
                    window.projectsDataTable.reloadTable();
                }
                this.showNotification(`Projet "${projectName}" supprimé`, 'success');
            }
            return result;
        } catch (error) {
            console.error('Erreur lors de la suppression:', error);
            this.showNotification('Erreur lors de la suppression', 'error');
            return false;
        }
    }

    // Exporter la liste des projets
    exportProjects() {
        // Placeholder pour l'export - vous pouvez implémenter CSV, Excel, etc.
        this.showNotification('Export en cours de développement', 'warning');
    }

    // Méthode utilitaire pour effectuer des actions AJAX
    async performAction(url, method = 'POST') {
        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || `HTTP error! status: ${response.status}`);
            }

            if (data.message) {
                this.showNotification(data.message, data.success ? 'success' : 'warning');
            }

            return data.success;
        } catch (error) {
            console.error('Erreur AJAX:', error);
            throw error;
        }
    }

    // Méthode utilitaire pour afficher des notifications
    showNotification(message, type = 'info') {
        // Utilise le système de notification global s'il existe
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            // Fallback simple
            alert(message);
        }
    }
}

// Fonctions globales pour compatibilité avec les templates
window.toggleProjectStatus = async function(projectId) {
    if (!window.projectActions) {
        window.projectActions = new ProjectActions();
    }
    return await window.projectActions.toggleProjectStatus(projectId);
};

window.deleteProject = async function(projectId, projectName) {
    if (!window.projectActions) {
        window.projectActions = new ProjectActions();
    }
    return await window.projectActions.deleteProject(projectId, projectName);
};

window.exportProjects = function() {
    if (!window.projectActions) {
        window.projectActions = new ProjectActions();
    }
    return window.projectActions.exportProjects();
};

// Export pour utilisation modulaire
window.ProjectActions = ProjectActions;
