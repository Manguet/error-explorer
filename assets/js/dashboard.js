/**
 * Dashboard Entry Point - BEM Architecture
 * Point d'entrée principal pour le dashboard
 */

// Import des composants
import './Dashboard/DashboardComponent.js';
import './Dashboard/ProfileComponent.js';

// Variables globales
let dashboardInstance = null;

/**
 * Initialisation du dashboard
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser le composant principal
    dashboardInstance = new DashboardComponent();
    
    // Exposer globalement pour compatibilité
    window.dashboard = dashboardInstance;
    
    // Fonctions utilitaires globales pour compatibilité backward
    window.performAction = (url, method) => dashboardInstance.performAction(url, method);
    window.showNotification = (message, type) => dashboardInstance.showNotification(message, type);
    window.copyToClipboard = (text) => dashboardInstance.copyToClipboard(text);
    
    console.log('Dashboard initialized successfully');
});

/**
 * Actions spécifiques aux erreurs
 */

// Résoudre une erreur
window.resolveError = async (errorId) => {
    const result = await dashboardInstance.performAction(`/dashboard/error/${errorId}/resolve`);
    if (result) {
        updateErrorStatus(errorId, 'resolved');
        // Dispatch event pour mettre à jour l'UI
        document.dispatchEvent(new CustomEvent('error:resolved', { 
            detail: { errorId, newStatus: 'resolved' } 
        }));
    }
};

// Ignorer une erreur
window.ignoreError = async (errorId) => {
    const result = await dashboardInstance.performAction(`/dashboard/error/${errorId}/ignore`);
    if (result) {
        updateErrorStatus(errorId, 'ignored');
        document.dispatchEvent(new CustomEvent('error:ignored', { 
            detail: { errorId, newStatus: 'ignored' } 
        }));
    }
};

// Rouvrir une erreur
window.reopenError = async (errorId) => {
    const result = await dashboardInstance.performAction(`/dashboard/error/${errorId}/reopen`);
    if (result) {
        updateErrorStatus(errorId, 'open');
        document.dispatchEvent(new CustomEvent('error:reopened', { 
            detail: { errorId, newStatus: 'open' } 
        }));
    }
};

/**
 * Mettre à jour le status d'une erreur dans l'UI
 */
function updateErrorStatus(errorId, newStatus) {
    const row = document.querySelector(`tr[data-error-id="${errorId}"]`);
    if (!row) return;

    // Mettre à jour le badge de status
    const statusBadge = row.querySelector('.status-badge');
    if (statusBadge) {
        statusBadge.className = `status-badge badge-${newStatus}`;
        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    }

    // Mettre à jour les boutons d'action
    const actionsCell = row.querySelector('.table-actions');
    if (actionsCell) {
        updateActionButtons(actionsCell, errorId, newStatus);
    }

    // Effet visuel de feedback
    animateRowUpdate(row);
}

/**
 * Mettre à jour les boutons d'action
 */
function updateActionButtons(actionsCell, errorId, newStatus) {
    let buttonsHtml = '';
    
    if (newStatus === 'open') {
        buttonsHtml = `
            <button class="action-btn btn--success" onclick="resolveError(${errorId})" title="Résoudre">
                <svg class="btn__icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </button>
            <button class="action-btn btn--danger" onclick="ignoreError(${errorId})" title="Ignorer">
                <svg class="btn__icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        `;
    } else {
        buttonsHtml = `
            <button class="action-btn btn--warning" onclick="reopenError(${errorId})" title="Rouvrir">
                <svg class="btn__icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        `;
    }
    
    actionsCell.innerHTML = buttonsHtml;
}

/**
 * Animation de mise à jour de ligne
 */
function animateRowUpdate(row) {
    row.style.background = 'rgba(34, 197, 94, 0.1)'; // success green
    row.style.transform = 'scale(1.02)';
    row.style.transition = 'all 0.3s ease';
    
    setTimeout(() => {
        row.style.background = '';
        row.style.transform = '';
        row.style.transition = '';
    }, 1000);
}

/**
 * Gestion de l'auto-refresh pour les pages spécifiques
 */
document.addEventListener('dashboard:autoRefresh', async (e) => {
    try {
        const currentPage = detectCurrentPage();
        
        switch (currentPage) {
            case 'dashboard-index':
                await refreshDashboardStats();
                break;
            case 'dashboard-project':
                await refreshProjectStats();
                break;
            case 'error-detail':
                await refreshErrorDetail();
                break;
        }
    } catch (error) {
        console.error('Auto-refresh error:', error);
    }
});

/**
 * Détecter la page actuelle
 */
function detectCurrentPage() {
    if (document.body.classList.contains('dashboard-index')) {
        return 'dashboard-index';
    } else if (document.body.classList.contains('dashboard-project')) {
        return 'dashboard-project';
    } else if (document.body.classList.contains('dashboard-error-detail')) {
        return 'error-detail';
    }
    return 'unknown';
}

/**
 * Refresh des stats du dashboard principal
 */
async function refreshDashboardStats() {
    try {
        const currentUrl = new URL(window.location);
        const params = new URLSearchParams(currentUrl.search);

        const response = await fetch(`/dashboard/api/stats?${params}`);
        if (!response.ok) throw new Error('Failed to fetch stats');
        
        const stats = await response.json();
        updateStatsDisplay(stats);
        
    } catch (error) {
        console.error('Error refreshing dashboard stats:', error);
    }
}

/**
 * Refresh des stats de projet
 */
async function refreshProjectStats() {
    try {
        const currentUrl = new URL(window.location);
        const params = new URLSearchParams(currentUrl.search);

        const response = await fetch(`/dashboard/api/project-stats?${params}`);
        if (!response.ok) throw new Error('Failed to fetch project stats');
        
        const stats = await response.json();
        updateProjectStatsDisplay(stats);
        
    } catch (error) {
        console.error('Error refreshing project stats:', error);
    }
}

/**
 * Refresh des détails d'erreur
 */
async function refreshErrorDetail() {
    // Implementation spécifique pour la page de détail d'erreur
    console.log('Refreshing error detail...');
}

/**
 * Mettre à jour l'affichage des stats
 */
function updateStatsDisplay(stats) {
    const statCards = document.querySelectorAll('.stat-card__value');
    const values = [
        stats.total_errors || 0,
        (stats.total_occurrences || 0).toLocaleString(),
        stats.resolved_errors || 0,
        (stats.occurrences_this_week || 0).toLocaleString()
    ];

    statCards.forEach((card, index) => {
        if (values[index] !== undefined) {
            animateValueChange(card, values[index]);
        }
    });
}

/**
 * Mettre à jour l'affichage des stats de projet
 */
function updateProjectStatsDisplay(stats) {
    updateStatsDisplay(stats); // Même logique pour l'instant
}

/**
 * Animation de changement de valeur
 */
function animateValueChange(element, newValue) {
    element.style.transform = 'scale(1.05)';
    element.style.transition = 'transform 0.2s ease';
    
    setTimeout(() => {
        element.textContent = newValue;
        element.style.transform = '';
    }, 100);
    
    setTimeout(() => {
        element.style.transition = '';
    }, 300);
}

/**
 * Fonctions utilitaires pour les détails d'erreur
 */

// Toggle occurrence details
window.toggleOccurrenceDetails = (occurrenceId) => {
    const detailsRow = document.getElementById(`details-${occurrenceId}`);
    if (!detailsRow) return;

    const isVisible = detailsRow.style.display !== 'none';
    detailsRow.style.display = isVisible ? 'none' : 'table-row';

    // Mettre à jour l'icône du bouton
    const button = document.querySelector(`[onclick="toggleOccurrenceDetails(${occurrenceId})"]`);
    if (button) {
        const icon = button.querySelector('svg');
        if (icon) {
            icon.style.transform = isVisible ? '' : 'rotate(180deg)';
        }
    }
};

// Copy stack trace
window.copyStackTrace = () => {
    const stackTrace = document.getElementById('stack-trace-content');
    if (stackTrace) {
        dashboardInstance.copyToClipboard(stackTrace.textContent);
    }
};

// Copy fingerprint
window.copyFingerprint = () => {
    const fingerprint = document.querySelector('[data-fingerprint]')?.dataset.fingerprint;
    if (fingerprint) {
        dashboardInstance.copyToClipboard(fingerprint);
    }
};

/**
 * Nettoyage lors du déchargement de la page
 */
window.addEventListener('beforeunload', () => {
    if (dashboardInstance) {
        dashboardInstance.destroy();
    }
});

/**
 * Export pour tests ou usage externe
 */
export { dashboardInstance, updateErrorStatus };
