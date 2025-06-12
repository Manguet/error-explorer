// Initialisation des DataTables pour les projets
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier que jQuery et DataTables sont chargés
    if (typeof $ === 'undefined') {
        console.error('jQuery n\'est pas chargé');
        return;
    }

    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables n\'est pas chargé');
        return;
    }

    // Vérifier que les dépendances du dashboard sont disponibles
    if (typeof window.datatableSettings === 'undefined') {
        console.warn('datatableSettings non défini, utilisation des paramètres par défaut');
        window.datatableSettings = {};
    }

    // Initialiser les actions des projets
    if (typeof ProjectActions !== 'undefined') {
        window.projectActions = new ProjectActions();
    }

    // Initialiser le DataTable des projets
    if (typeof ProjectsDataTable !== 'undefined' && document.getElementById('table')) {
        window.projectsDataTable = new ProjectsDataTable();
    }

    // Initialiser les routes pour les actions AJAX
    initializeRoutes();
});

// Fonction pour initialiser les routes depuis les attributs data du template
function initializeRoutes() {
    // Les routes peuvent être définies dans le template via des variables JavaScript
    // ou récupérées depuis des attributs data-* sur des éléments
    const routesElement = document.querySelector('[data-routes]');
    if (routesElement) {
        try {
            window.routes = JSON.parse(routesElement.dataset.routes);
        } catch (e) {
            console.warn('Impossible de parser les routes:', e);
        }
    }

    // Fallback: définir les routes manuellement si elles ne sont pas disponibles
    if (!window.routes) {
        window.routes = {
            projects_create: '/projects/create',
            projects_toggle_status: '/projects/__ID__/toggle-status',
            projects_delete: '/projects/__ID__/delete'
        };
    }
}