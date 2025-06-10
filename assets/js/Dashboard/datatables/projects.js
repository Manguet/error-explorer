// Gestion des projets dans DataTables
class ProjectsDataTable {
    constructor() {
        this.initializeDataTable();
        this.setupEventListeners();
    }

    initializeDataTable() {
        $('#table').initDataTables(window.datatableSettings, {
            searching: true,
            dom: 'lTfgtpi',
            order: [[0, 'asc']], // Trier par nom de projet par défaut
            pageLength: 25,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.16/i18n/French.json'
            }
        }).then((dt) => {
            this.dataTable = dt;
            dt.on('draw', () => {
                // Réactiver les tooltips après chaque redraw si nécessaire
                this.onTableDraw();
            });
        });
    }

    onTableDraw() {
        // Actions à effectuer après chaque redraw de la table
        console.log('Table redrawn');
    }

    reloadTable() {
        if (this.dataTable) {
            this.dataTable.ajax.reload();
        }
    }

    setupEventListeners() {
        // Raccourcis clavier
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey) {
                switch(e.key) {
                    case 'n':
                        e.preventDefault();
                        window.location.href = window.routes.projects_create;
                        break;
                    case 'r':
                        e.preventDefault();
                        this.reloadTable();
                        break;
                }
            }
        });
    }
}

// Export pour utilisation globale
window.ProjectsDataTable = ProjectsDataTable;