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
});

/**
 * Actions spécifiques aux erreurs
 */

// Résoudre une erreur
window.resolveError = async (errorId) => {
    const result = await dashboardInstance.performAction(`/dashboard/error/${errorId}/resolve`);
    if (result) {
        // Recharger la page pour mettre à jour le DataTable
        setTimeout(() => {
            window.location.reload();
        }, 1500);
        
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
        // Recharger la page pour mettre à jour le DataTable
        setTimeout(() => {
            window.location.reload();
        }, 1500);
        
        document.dispatchEvent(new CustomEvent('error:ignored', {
            detail: { errorId, newStatus: 'ignored' }
        }));
    }
};

// Rouvrir une erreur
window.reopenError = async (errorId) => {
    const result = await dashboardInstance.performAction(`/dashboard/error/${errorId}/reopen`);
    if (result) {
        // Recharger la page pour mettre à jour le DataTable
        setTimeout(() => {
            window.location.reload();
        }, 1500);
        
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
 * Initialisation pour la page de détail d'erreur
 */
document.addEventListener('DOMContentLoaded', () => {
    // Chart functionality
    const chartCanvas = document.getElementById('occurrence-chart');
    if (chartCanvas) {
        initOccurrenceChart();

        // Add resize listener to redraw chart
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                initOccurrenceChart();
            }, 250);
        });
    }

    // Enhanced stack trace functionality
    const stackTraceElement = document.getElementById('stack-trace-content');
    if (stackTraceElement) {
        initInteractiveStackTrace();
    }

    function initOccurrenceChart() {
        const statsData = chartCanvas.dataset.stats;

        if (statsData) {
            try {
                const stats = JSON.parse(statsData);
                drawOccurrenceChart(chartCanvas, stats);
            } catch (error) {
                console.error('Error parsing chart data:', error);
                drawOccurrenceChart(chartCanvas, []);
            }
        } else {
            drawOccurrenceChart(chartCanvas, []);
        }
    }

    function drawOccurrenceChart(canvas, data) {
        const ctx = canvas.getContext('2d');
        const container = canvas.parentElement;

        // Get actual container dimensions
        const containerRect = container.getBoundingClientRect();
        // Use full container width minus padding
        const width = Math.max(containerRect.width - 64, 600); // Account for 2rem padding on each side
        const height = 350;

        // Set canvas actual size and style size
        const devicePixelRatio = window.devicePixelRatio || 1;
        canvas.width = width * devicePixelRatio;
        canvas.height = height * devicePixelRatio;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        // Scale context for high DPI displays
        ctx.scale(devicePixelRatio, devicePixelRatio);

        // Clear canvas
        ctx.clearRect(0, 0, width, height);

        if (!data || data.length === 0) {
            // Draw "No data" message
            ctx.fillStyle = '#64748b';
            ctx.font = '16px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Aucune donnée disponible', width / 2, height / 2);
            return;
        }

        // Chart configuration
        const padding = 60;
        const chartWidth = width - (padding * 2);
        const chartHeight = height - (padding * 2);

        // Find data range
        const values = data.map(d => d.count);
        const maxValue = Math.max(...values, 1);

        // Draw grid lines
        ctx.strokeStyle = '#f1f5f9';
        ctx.lineWidth = 1;

        // Horizontal grid lines
        for (let i = 0; i <= 5; i++) {
            const y = padding + (chartHeight / 5) * i;
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(width - padding, y);
            ctx.stroke();
        }

        // Vertical grid lines
        const step = Math.max(1, Math.floor(data.length / 7));
        for (let i = 0; i < data.length; i += step) {
            const x = padding + (i / (data.length - 1)) * chartWidth;
            ctx.beginPath();
            ctx.moveTo(x, padding);
            ctx.lineTo(x, height - padding);
            ctx.stroke();
        }

        // Draw axes
        ctx.strokeStyle = '#d1d5db';
        ctx.lineWidth = 2;

        // Y axis
        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, height - padding);
        ctx.stroke();

        // X axis
        ctx.beginPath();
        ctx.moveTo(padding, height - padding);
        ctx.lineTo(width - padding, height - padding);
        ctx.stroke();

        // Draw area under curve
        if (data.length > 1) {
            const gradient = ctx.createLinearGradient(0, padding, 0, height - padding);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.05)');

            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.moveTo(padding, height - padding);

            data.forEach((point, index) => {
                const x = padding + (index / (data.length - 1)) * chartWidth;
                const y = height - padding - (point.count / maxValue) * chartHeight;
                ctx.lineTo(x, y);
            });

            ctx.lineTo(width - padding, height - padding);
            ctx.closePath();
            ctx.fill();
        }

        // Draw line
        if (data.length > 1) {
            ctx.strokeStyle = '#3b82f6';
            ctx.lineWidth = 3;
            ctx.beginPath();

            data.forEach((point, index) => {
                const x = padding + (index / (data.length - 1)) * chartWidth;
                const y = height - padding - (point.count / maxValue) * chartHeight;

                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });

            ctx.stroke();

            // Draw points
            ctx.fillStyle = '#3b82f6';
            data.forEach((point, index) => {
                const x = padding + (index / (data.length - 1)) * chartWidth;
                const y = height - padding - (point.count / maxValue) * chartHeight;

                ctx.beginPath();
                ctx.arc(x, y, 4, 0, 2 * Math.PI);
                ctx.fill();

                // Add white border to points
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();
                ctx.strokeStyle = '#3b82f6';
                ctx.lineWidth = 3;
            });
        }

        // Labels
        ctx.fillStyle = '#64748b';
        ctx.font = '12px Inter, sans-serif';
        ctx.textAlign = 'center';

        // X labels (dates)
        data.forEach((point, index) => {
            if (index % Math.max(1, Math.floor(data.length / 7)) === 0) {
                const x = padding + (index / (data.length - 1)) * chartWidth;
                const date = new Date(point.date);
                const label = `${date.getDate()}/${date.getMonth() + 1}`;
                ctx.fillText(label, x, height - 15);
            }
        });

        // Y labels
        ctx.textAlign = 'right';
        for (let i = 0; i <= 5; i++) {
            const value = Math.round((maxValue / 5) * (5 - i));
            const y = padding + (chartHeight / 5) * i;
            ctx.fillText(value.toString(), padding - 15, y + 4);
        }
    }

    function initInteractiveStackTrace() {
        const stackTraceElement = document.getElementById('stack-trace-content');

        if (!stackTraceElement) {
            return;
        }

        const stackTraceText = stackTraceElement.textContent;
        if (!stackTraceText) return;

        // Enhanced syntax highlighting
        stackTraceElement.innerHTML = enhanceStackTrace(stackTraceText);

        // Add keyboard shortcuts
        stackTraceElement.setAttribute('tabindex', '0');
        stackTraceElement.addEventListener('keydown', handleStackTraceKeyboard);

        // Add double-click to copy line
        stackTraceElement.addEventListener('dblclick', handleStackTraceDoubleClick);

        // Add tooltip functionality
        addStackTraceTooltips(stackTraceElement);
    }

    function enhanceStackTrace(stackTrace) {
        const lines = stackTrace.split('\n');
        let enhancedLines = [];

        lines.forEach((line, index) => {
            if (!line.trim()) {
                enhancedLines.push('<br>');
                return;
            }

            let enhancedLine = line;

            // Highlight error messages
            if (line.includes('Exception') || line.includes('Error') || line.includes('Fatal')) {
                enhancedLine = `<span class="stack-trace-line error-line">${escapeHtml(line)}</span>`;
            }
            // Highlight file paths with line numbers
            else if (line.match(/at\s+.*\s+in\s+.*\.php:\d+/)) {
                enhancedLine = line.replace(
                    /(at\s+)([^\s]+)(\s+in\s+)([^:]+\.php):(\d+)/,
                    '$1<span class="method-name">$2</span>$3<span class="file-path" data-file="$4" data-line="$5">$4</span>:<span class="line-number">$5</span>'
                );
                enhancedLine = `<span class="stack-trace-line">${enhancedLine}</span>`;
            }
            // Highlight stack trace entries
            else if (line.match(/^\s*#\d+/)) {
                enhancedLine = line.replace(
                    /^(\s*#\d+\s+)([^(]+)(\([^)]*\))?\s*(.*)?$/,
                    '$1<span class="method-name">$2</span><span class="method-args">$3</span> <span class="file-info">$4</span>'
                );
                enhancedLine = `<span class="stack-trace-line">${enhancedLine}</span>`;
            }
            // Generic file paths
            else if (line.match(/\/[^\s]*\.php/)) {
                enhancedLine = line.replace(
                    /(\/[^\s]*\.php)(:(\d+))?/g,
                    '<span class="file-path" data-file="$1" data-line="$3">$1</span>$2'
                );
                enhancedLine = `<span class="stack-trace-line">${enhancedLine}</span>`;
            }
            else {
                enhancedLine = `<span class="stack-trace-line">${escapeHtml(line)}</span>`;
            }

            enhancedLines.push(enhancedLine);
        });

        return enhancedLines.join('\n');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function handleStackTraceKeyboard(e) {
        // Ctrl+A to select all
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            e.preventDefault();
            window.getSelection().selectAllChildren(e.target);
        }

        // Ctrl+C to copy
        if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
            e.preventDefault();
            window.copyStackTrace();
        }

        // F to find/search within stack trace
        if (e.key === 'f' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            findInStackTrace();
        }
    }

    function handleStackTraceDoubleClick(e) {
        const line = e.target.closest('.stack-trace-line');
        if (line) {
            // Copy the entire line
            const lineText = line.textContent;
            dashboardInstance.copyToClipboard(lineText);

            // Visual feedback
            line.style.background = 'rgba(34, 197, 94, 0.2)';
            setTimeout(() => {
                line.style.background = '';
            }, 1000);
        }
    }

    function addStackTraceTooltips(container) {
        const filePathElements = container.querySelectorAll('.file-path');

        filePathElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                const filePath = e.target.dataset.file;
                const lineNumber = e.target.dataset.line;

                let tooltipText = `File: ${filePath}`;
                if (lineNumber) {
                    tooltipText += `\nLine: ${lineNumber}`;
                }
                tooltipText += '\nDouble-click to copy, Ctrl+Click to search';

                e.target.setAttribute('title', tooltipText);
            });

            // Add click to search functionality
            element.addEventListener('click', (e) => {
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    const fileName = e.target.dataset.file.split('/').pop();
                    const searchUrl = `/dashboard?search=${encodeURIComponent(fileName)}`;
                    window.open(searchUrl, '_blank');
                }
            });
        });
    }

    function findInStackTrace() {
        const searchTerm = prompt('Rechercher dans le stack trace:');
        if (!searchTerm) return;

        const stackTraceElement = document.getElementById('stack-trace-content');
        const text = stackTraceElement.textContent;

        if (text.toLowerCase().includes(searchTerm.toLowerCase())) {
            // Highlight found terms
            const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
            stackTraceElement.innerHTML = stackTraceElement.innerHTML.replace(
                regex,
                '<mark style="background: yellow; color: black;">$1</mark>'
            );

            // Scroll to first match
            const firstMatch = stackTraceElement.querySelector('mark');
            if (firstMatch) {
                firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            dashboardInstance.showNotification(`Trouvé: ${searchTerm}`, 'success');

            // Clear highlights after 5 seconds
            setTimeout(() => {
                initInteractiveStackTrace(); // Re-initialize to clear highlights
            }, 5000);
        } else {
            dashboardInstance.showNotification(`Terme non trouvé: ${searchTerm}`, 'warning');
        }
    }

    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
});

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
