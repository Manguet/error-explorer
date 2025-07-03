/**
 * Dashboard Core Class
 */
class Dashboard {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeComponents();
        this.startAutoRefresh();
    }

    setupEventListeners() {
        // Mobile menu toggle
        this.setupMobileMenu();

        // Auto-refresh toggles
        this.setupAutoRefresh();

        // Flash messages auto-dismiss
        this.setupFlashMessages();

        // Form enhancements
        this.setupForms();

        // Table enhancements
        this.setupTables();

        // Action buttons
        this.setupActionButtons();
    }

    setupMobileMenu() {
        const mobileToggle = document.getElementById('mobile-toggle');
        const sidebar = document.getElementById('dashboard-sidebar');
        const overlay = document.getElementById('mobile-overlay');

        if (mobileToggle && sidebar && overlay) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');

                // Update ARIA attributes
                const isOpen = sidebar.classList.contains('active');
                mobileToggle.setAttribute('aria-expanded', isOpen);
                sidebar.setAttribute('aria-hidden', !isOpen);
            });

            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                mobileToggle.setAttribute('aria-expanded', 'false');
                sidebar.setAttribute('aria-hidden', 'true');
            });

            // Close on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    mobileToggle.setAttribute('aria-expanded', 'false');
                    sidebar.setAttribute('aria-hidden', 'true');
                }
            });
        }
    }

    setupAutoRefresh() {
        const toggles = document.querySelectorAll('#auto-refresh-toggle');

        toggles.forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.startAutoRefresh();
                } else {
                    this.stopAutoRefresh();
                }

                // Store preference
                localStorage.setItem('dashboard-auto-refresh', e.target.checked);
            });
        });

        // Restore preference
        const savedPreference = localStorage.getItem('dashboard-auto-refresh');
        if (savedPreference !== null) {
            const isEnabled = savedPreference === 'true';
            toggles.forEach(toggle => {
                toggle.checked = isEnabled;
            });

            if (isEnabled) {
                this.startAutoRefresh();
            }
        }
    }

    setupFlashMessages() {
        const flashMessages = document.querySelectorAll('[data-auto-dismiss]');

        flashMessages.forEach(message => {
            const delay = parseInt(message.getAttribute('data-auto-dismiss'));
            if (delay) {
                setTimeout(() => {
                    this.dismissFlashMessage(message);
                }, delay);
            }

            // Setup close button
            const closeBtn = message.querySelector('.close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.dismissFlashMessage(message);
                });
            }
        });
    }

    dismissFlashMessage(message) {
        message.style.animation = 'flashSlideOut 0.3s ease-out forwards';
        setTimeout(() => {
            if (message.parentElement) {
                message.remove();
            }
        }, 300);
    }

    setupForms() {
        // Enhanced form validation
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                // Custom validation styling
                input.addEventListener('invalid', (e) => {
                    e.target.classList.add('is-invalid');
                });

                input.addEventListener('input', (e) => {
                    if (e.target.validity.valid) {
                        e.target.classList.remove('is-invalid');
                        e.target.classList.add('is-valid');
                    }
                });
            });
        });

        // Sort select handlers
        const sortSelects = document.querySelectorAll('#sort-select');
        sortSelects.forEach(select => {
            select.addEventListener('change', (e) => {
                const [sort, direction] = e.target.value.split('|');
                const url = new URL(window.location);
                url.searchParams.set('sort', sort);
                url.searchParams.set('direction', direction);
                url.searchParams.delete('page'); // Reset pagination
                window.location.href = url.toString();
            });
        });
    }

    setupTables() {
        // Enhanced table interactions
        const tables = document.querySelectorAll('.dashboard-table');

        tables.forEach(table => {
            // Row hover effects
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', () => {
                    row.style.transform = 'translateX(2px)';
                });

                row.addEventListener('mouseleave', () => {
                    row.style.transform = '';
                });
            });

            // Sortable headers (if needed)
            const headers = table.querySelectorAll('th[data-sort]');
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    this.handleSort(header.dataset.sort);
                });
            });
        });
    }

    handleSort(column) {
        const url = new URL(window.location);
        const currentSort = url.searchParams.get('sort');
        const currentDirection = url.searchParams.get('direction');

        let newDirection = 'ASC';
        if (currentSort === column && currentDirection === 'ASC') {
            newDirection = 'DESC';
        }

        url.searchParams.set('sort', column);
        url.searchParams.set('direction', newDirection);
        url.searchParams.delete('page');

        window.location.href = url.toString();
    }

    setupActionButtons() {
        // Add loading states to action buttons
        const actionButtons = document.querySelectorAll('.action-btn, .filter-btn');

        actionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                if (button.classList.contains('is-loading')) {
                    e.preventDefault();
                    return;
                }

                // Add loading state for async actions
                if (button.onclick && button.onclick.toString().includes('async')) {
                    button.classList.add('is-loading');
                    button.disabled = true;

                    // Remove loading state after 5 seconds as fallback
                    setTimeout(() => {
                        button.classList.remove('is-loading');
                        button.disabled = false;
                    }, 5000);
                }
            });
        });
    }

    initializeComponents() {
        // Initialize tooltips
        this.initTooltips();

        // Initialize charts
        this.initCharts();

        // Initialize keyboard shortcuts
        this.initKeyboardShortcuts();
    }

    initTooltips() {
        const tooltipElements = document.querySelectorAll('[title], [data-tooltip]');

        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target);
            });

            element.addEventListener('mouseleave', (e) => {
                this.hideTooltip(e.target);
            });
        });
    }

    showTooltip(element) {
        const text = element.getAttribute('data-tooltip') || element.getAttribute('title');
        if (!text) return;

        // Remove default title to prevent browser tooltip
        if (element.hasAttribute('title')) {
            element.setAttribute('data-original-title', element.getAttribute('title'));
            element.removeAttribute('title');
        }

        const tooltip = document.createElement('div');
        tooltip.className = 'dashboard-tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
        `;

        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';

        element._tooltip = tooltip;
    }

    hideTooltip(element) {
        if (element._tooltip) {
            element._tooltip.remove();
            element._tooltip = null;
        }

        // Restore original title
        if (element.hasAttribute('data-original-title')) {
            element.setAttribute('title', element.getAttribute('data-original-title'));
            element.removeAttribute('data-original-title');
        }
    }

    initCharts() {
        // Simple chart initialization
        const chartElements = document.querySelectorAll('[data-chart]');

        chartElements.forEach(element => {
            const chartType = element.getAttribute('data-chart');
            const chartData = JSON.parse(element.getAttribute('data-chart-data') || '[]');

            if (chartType === 'line') {
                this.renderLineChart(element, chartData);
            }
        });
    }

    renderLineChart(canvas, data) {
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;

        // Clear canvas
        ctx.clearRect(0, 0, width, height);

        if (!data || data.length === 0) return;

        // Chart configuration
        const padding = 40;
        const chartWidth = width - (padding * 2);
        const chartHeight = height - (padding * 2);

        // Find data range
        const values = data.map(d => d.value || d.count || d.y || 0);
        const maxValue = Math.max(...values, 1);
        const minValue = Math.min(...values, 0);
        const range = maxValue - minValue;

        // Draw axes
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;

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

        // Draw line
        if (data.length > 1) {
            ctx.strokeStyle = '#3b82f6';
            ctx.lineWidth = 2;
            ctx.beginPath();

            data.forEach((point, index) => {
                const x = padding + (index / (data.length - 1)) * chartWidth;
                const y = height - padding - ((point.value || point.count || point.y || 0) - minValue) / range * chartHeight;

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
                const y = height - padding - ((point.value || point.count || point.y || 0) - minValue) / range * chartHeight;

                ctx.beginPath();
                ctx.arc(x, y, 3, 0, 2 * Math.PI);
                ctx.fill();
            });
        }

        // Labels
        ctx.fillStyle = '#6b7280';
        ctx.font = '11px Inter, sans-serif';
        ctx.textAlign = 'center';

        // X labels
        data.forEach((point, index) => {
            if (index % Math.ceil(data.length / 6) === 0) {
                const x = padding + (index / (data.length - 1)) * chartWidth;
                const label = point.label || point.date || index.toString();
                ctx.fillText(label, x, height - 10);
            }
        });

        // Y labels
        ctx.textAlign = 'right';
        ctx.fillText(maxValue.toString(), padding - 10, padding + 5);
        if (minValue !== maxValue) {
            ctx.fillText(minValue.toString(), padding - 10, height - padding + 5);
        }
    }

    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Only handle if not in form element
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                return;
            }

            // Ctrl/Cmd + R for refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                window.location.reload();
            }

            // / for search focus (if search input exists)
            if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }

            // Escape to clear search
            if (e.key === 'Escape') {
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput && searchInput === document.activeElement) {
                    searchInput.value = '';
                    searchInput.blur();
                }
            }
        });
    }

    startAutoRefresh() {
        this.stopAutoRefresh(); // Clear existing interval

        this.refreshInterval = setInterval(() => {
            this.performAutoRefresh();
        }, 30000); // 30 seconds
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    async performAutoRefresh() {
        try {
            // Dispatch custom event for auto-refresh
            const event = new CustomEvent('dashboard:autoRefresh');
            document.dispatchEvent(event);

            // Update live status indicator
            this.updateLiveStatus();

        } catch (error) {
            console.error('Auto-refresh error:', error);
        }
    }

    updateLiveStatus() {
        const liveStatus = document.querySelector('.dashboard-live-status');
        if (liveStatus) {
            liveStatus.style.transform = 'scale(1.1)';
            setTimeout(() => {
                liveStatus.style.transform = '';
            }, 200);
        }
    }

    // Utility Methods
    showNotification(message, type = 'info', duration = 5000) {
        // Create or get the notification container
        let container = document.getElementById('dashboard-flash-messages');
        if (!container) {
            container = document.createElement('div');
            container.id = 'dashboard-flash-messages';
            container.className = 'dashboard-flash-messages';
            document.body.appendChild(container);
        }

        const notification = document.createElement('div');
        notification.className = `flash-message alert-${type}`;
        notification.innerHTML = `
            ${message}
            <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
        `;

        container.appendChild(notification);

        // Auto remove
        setTimeout(() => {
            if (notification.parentElement) {
                this.dismissFlashMessage(notification);
            }
        }, duration);

        return notification;
    }

    async performAction(url, method = 'POST', data = null) {
        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (data) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                return true;
            } else {
                this.showNotification(result.error || 'Une erreur est survenue', 'error');
                return false;
            }
        } catch (error) {
            this.showNotification('Erreur de connexion', 'error');
            console.error('Action error:', error);
            return false;
        }
    }

    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showNotification('Copié dans le presse-papiers !', 'success');
            }).catch(() => {
                this.fallbackCopyTextToClipboard(text);
            });
        } else {
            this.fallbackCopyTextToClipboard(text);
        }
    }

    fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            this.showNotification('Copié dans le presse-papiers !', 'success');
        } catch (err) {
            this.showNotification('Erreur lors de la copie', 'error');
        }

        document.body.removeChild(textArea);
    }

    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    formatDate(date) {
        if (typeof date === 'string') {
            date = new Date(date);
        }

        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        if (days === 0) {
            return 'Aujourd\'hui';
        } else if (days === 1) {
            return 'Hier';
        } else if (days < 7) {
            return `Il y a ${days} jours`;
        } else {
            return date.toLocaleDateString('fr-FR');
        }
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// Initialize Dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new Dashboard();

    // Global utility functions for backward compatibility
    window.performAction = (url, method) => window.dashboard.performAction(url, method);
    window.showNotification = (message, type) => window.dashboard.showNotification(message, type);
    window.copyToClipboard = (text) => window.dashboard.copyToClipboard(text);
});

// Page-specific functionality
document.addEventListener('DOMContentLoaded', () => {
    // Dashboard Index specific
    if (document.body.classList.contains('dashboard-index')) {
        initDashboardIndex();
    }

    // Project view specific
    if (document.body.classList.contains('dashboard-project')) {
        initDashboardProject();
    }

    // Error detail specific - check for presence of elements instead of body class
    if (document.getElementById('occurrence-chart') || document.getElementById('stack-trace-content')) {
        initErrorDetail();
    }
});

/**
 * Dashboard Index specific functionality
 */
function initDashboardIndex() {
    // Auto-refresh stats
    document.addEventListener('dashboard:autoRefresh', async () => {
        try {
            const currentUrl = new URL(window.location);
            const params = new URLSearchParams(currentUrl.search);

            const response = await fetch(`/dashboard/api/stats?${params}`);
            const stats = await response.json();

            updateStatsDisplay(stats);
        } catch (error) {
            console.error('Error refreshing stats:', error);
        }
    });

    // Error actions
    window.resolveError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/resolve`);
        if (result) {
            updateErrorStatus(errorId, 'resolved');
        }
    };

    window.ignoreError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/ignore`);
        if (result) {
            updateErrorStatus(errorId, 'ignored');
        }
    };

    window.reopenError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/reopen`);
        if (result) {
            updateErrorStatus(errorId, 'open');
        }
    };

    function updateErrorStatus(errorId, newStatus) {
        const row = document.querySelector(`tr[data-error-id="${errorId}"]`);
        if (row) {
            // Update status badge
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = `status-badge badge-${newStatus}`;
                statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            }

            // Update action buttons
            const actionsCell = row.querySelector('.table-actions');
            if (actionsCell) {
                let buttonsHtml = '';
                if (newStatus === 'open') {
                    buttonsHtml = `
                        <button class="action-btn btn-success" onclick="resolveError(${errorId})" title="Résoudre">✓</button>
                        <button class="action-btn btn-danger" onclick="ignoreError(${errorId})" title="Ignorer">✕</button>
                    `;
                } else {
                    buttonsHtml = `
                        <button class="action-btn btn-warning" onclick="reopenError(${errorId})" title="Rouvrir">↻</button>
                    `;
                }
                actionsCell.innerHTML = buttonsHtml;
            }

            // Visual feedback
            row.style.background = '#dcfce7';
            row.style.transform = 'scale(1.02)';
            setTimeout(() => {
                row.style.background = '';
                row.style.transform = '';
            }, 1000);
        }
    }

    function updateStatsDisplay(stats) {
        const statCards = document.querySelectorAll('.dashboard-stat-card-value');
        const values = [
            stats.total_errors || 0,
            (stats.total_occurrences || 0).toLocaleString(),
            stats.resolved_errors || 0,
            (stats.occurrences_this_week || 0).toLocaleString()
        ];

        statCards.forEach((card, index) => {
            if (values[index] !== undefined) {
                card.textContent = values[index];
                card.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    card.style.transform = '';
                }, 200);
            }
        });
    }
}

/**
 * Dashboard Project specific functionality
 */
function initDashboardProject() {
    // Similar functionality to dashboard index but project-specific
    document.addEventListener('dashboard:autoRefresh', async () => {
        try {
            const currentUrl = new URL(window.location);
            const params = new URLSearchParams(currentUrl.search);

            const response = await fetch(`/dashboard/api/stats?${params}`);
            const stats = await response.json();

            updateProjectStatsDisplay(stats);
        } catch (error) {
            console.error('Error refreshing project stats:', error);
        }
    });

    // Reuse error action functions
    window.resolveError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/resolve`);
        if (result) {
            updateErrorStatus(errorId, 'resolved');
        }
    };

    window.ignoreError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/ignore`);
        if (result) {
            updateErrorStatus(errorId, 'ignored');
        }
    };

    window.reopenError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/reopen`);
        if (result) {
            updateErrorStatus(errorId, 'open');
        }
    };

    function updateErrorStatus(errorId, newStatus) {
        const row = document.querySelector(`tr[data-error-id="${errorId}"]`);
        if (row) {
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = `status-badge badge-${newStatus}`;
                statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            }

            const actionsCell = row.querySelector('.table-actions');
            if (actionsCell) {
                let buttonsHtml = '';
                if (newStatus === 'open') {
                    buttonsHtml = `
                        <button class="action-btn btn-success" onclick="resolveError(${errorId})" title="Résoudre">✓</button>
                        <button class="action-btn btn-danger" onclick="ignoreError(${errorId})" title="Ignorer">✕</button>
                    `;
                } else {
                    buttonsHtml = `
                        <button class="action-btn btn-warning" onclick="reopenError(${errorId})" title="Rouvrir">↻</button>
                    `;
                }
                actionsCell.innerHTML = buttonsHtml;
            }

            row.style.background = '#dcfce7';
            setTimeout(() => {
                row.style.background = '';
            }, 1000);
        }
    }

    function updateProjectStatsDisplay(stats) {
        const statCards = document.querySelectorAll('.dashboard-stat-card-value');
        const values = [
            stats.total_errors || 0,
            (stats.total_occurrences || 0).toLocaleString(),
            stats.resolved_errors || 0,
            (stats.occurrences_this_week || 0).toLocaleString()
        ];

        statCards.forEach((card, index) => {
            if (values[index] !== undefined) {
                card.textContent = values[index];
                card.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    card.style.transform = '';
                }, 200);
            }
        });
    }
}

/**
 * Error Detail specific functionality
 */
function initErrorDetail() {
    // Error actions
    window.resolveError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/resolve`);
        if (result) {
            location.reload();
        }
    };

    window.ignoreError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/ignore`);
        if (result) {
            location.reload();
        }
    };

    window.reopenError = async (errorId) => {
        const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/reopen`);
        if (result) {
            location.reload();
        }
    };

    // Toggle occurrence details
    window.toggleOccurrenceDetails = (occurrenceId) => {
        const detailsRow = document.getElementById(`details-${occurrenceId}`);
        if (detailsRow) {
            const isVisible = detailsRow.style.display !== 'none';
            detailsRow.style.display = isVisible ? 'none' : 'table-row';

            // Update button icon or text if needed
            const button = document.querySelector(`[onclick="toggleOccurrenceDetails(${occurrenceId})"]`);
            if (button) {
                const icon = button.querySelector('svg');
                if (icon) {
                    icon.style.transform = isVisible ? '' : 'rotate(180deg)';
                }
            }
        }
    };

    // Copy functions
    window.copyStackTrace = () => {
        const stackTrace = document.getElementById('stack-trace-content');
        if (stackTrace) {
            window.dashboard.copyToClipboard(stackTrace.textContent);
        }
    };

    // Enhanced stack trace functionality
    initInteractiveStackTrace();

    window.copyFingerprint = () => {
        const fingerprint = document.querySelector('[data-fingerprint]')?.dataset.fingerprint;
        if (fingerprint) {
            window.dashboard.copyToClipboard(fingerprint);
        }
    };

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

    // Refresh chart function
    window.refreshOccurrenceChart = async () => {
        try {
            const errorId = window.location.pathname.split('/').pop();
            const response = await fetch(`/dashboard/api/occurrence-chart/${errorId}`);
            const data = await response.json();

            drawOccurrenceChart(chartCanvas, data);
            window.dashboard.showNotification('Graphique mis à jour', 'success');
        } catch (error) {
            console.error('Error refreshing chart:', error);
            window.dashboard.showNotification('Erreur lors de la mise à jour', 'error');
        }
    };

    // Additional actions
    window.exportOccurrences = () => {
        window.dashboard.showNotification('Export en cours de développement', 'warning');
    };

    window.findSimilarErrors = () => {
        const exceptionClass = document.querySelector('[data-exception-class]')?.dataset.exceptionClass;
        if (exceptionClass) {
            const searchUrl = `/dashboard?search=${encodeURIComponent(exceptionClass)}`;
            window.open(searchUrl, '_blank');
        }
    };

    // Interactive stack trace enhancement
    function initInteractiveStackTrace() {
        const stackTraceElement = document.getElementById('stack-trace-content');

        if (!stackTraceElement) {
            return;
        }

        const stackTraceText = stackTraceElement.textContent;
        if (!stackTraceText) return;

        // Enhanced syntax highlighting
        const enhancedStackTrace = enhanceStackTrace(stackTraceText);
        stackTraceElement.innerHTML = enhancedStackTrace;

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
                    /(at\s+)([^\\s]+)(\s+in\s+)([^:]+\.php):(\d+)/,
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
            else if (line.match(/\/[^\\s]*\.php/)) {
                enhancedLine = line.replace(
                    /(\/[^\\s]*\.php)(:(\d+))?/g,
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
            window.dashboard.copyToClipboard(lineText);

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
            const highlighted = stackTraceElement.innerHTML.replace(
                regex,
                '<mark style="background: yellow; color: black;">$1</mark>'
            );
            stackTraceElement.innerHTML = highlighted;

            // Scroll to first match
            const firstMatch = stackTraceElement.querySelector('mark');
            if (firstMatch) {
                firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            window.dashboard.showNotification(`Trouvé: ${searchTerm}`, 'success');

            // Clear highlights after 5 seconds
            setTimeout(() => {
                initInteractiveStackTrace(); // Re-initialize to clear highlights
            }, 5000);
        } else {
            window.dashboard.showNotification(`Terme non trouvé: ${searchTerm}`, 'warning');
        }
    }

    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\\]\\]/g, '\\\\$&');
    }
}

/**
 * Enhanced table functionality
 */
class TableEnhancer {
    constructor(table) {
        this.table = table;
        this.init();
    }

    init() {
        this.addRowNumbers();
        this.addSelectAllFunctionality();
        this.addBulkActions();
        this.makeResponsive();
    }

    addRowNumbers() {
        const rows = this.table.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            const numberCell = document.createElement('td');
            numberCell.textContent = index + 1;
            numberCell.style.cssText = `
                width: 40px;
                text-align: center;
                font-size: 0.75rem;
                color: #9ca3af;
                font-weight: 500;
            `;
            row.insertBefore(numberCell, row.firstChild);
        });

        // Add header for row numbers
        const headerRow = this.table.querySelector('thead tr');
        if (headerRow) {
            const numberHeader = document.createElement('th');
            numberHeader.textContent = '#';
            numberHeader.style.width = '40px';
            headerRow.insertBefore(numberHeader, headerRow.firstChild);
        }
    }

    addSelectAllFunctionality() {
        const headerRow = this.table.querySelector('thead tr');
        const bodyRows = this.table.querySelectorAll('tbody tr');

        if (headerRow && bodyRows.length > 0) {
            // Add select all checkbox to header
            const selectAllTh = document.createElement('th');
            selectAllTh.style.width = '40px';
            selectAllTh.innerHTML = '<input type="checkbox" class="select-all-checkbox">';
            headerRow.insertBefore(selectAllTh, headerRow.firstChild);

            // Add individual checkboxes to each row
            bodyRows.forEach(row => {
                const selectTd = document.createElement('td');
                selectTd.style.width = '40px';
                selectTd.innerHTML = '<input type="checkbox" class="row-checkbox">';
                row.insertBefore(selectTd, row.firstChild);
            });

            // Handle select all functionality
            const selectAllCheckbox = this.table.querySelector('.select-all-checkbox');
            const rowCheckboxes = this.table.querySelectorAll('.row-checkbox');

            selectAllCheckbox.addEventListener('change', (e) => {
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
                this.updateBulkActions();
            });

            rowCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const checkedCount = Array.from(rowCheckboxes).filter(cb => cb.checked).length;
                    selectAllCheckbox.checked = checkedCount === rowCheckboxes.length;
                    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
                    this.updateBulkActions();
                });
            });
        }
    }

    addBulkActions() {
        const bulkActionsHtml = `
            <div class="bulk-actions" style="display: none; padding: 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span class="selected-count">0 éléments sélectionnés</span>
                    <button class="bulk-action-btn" data-action="resolve">Résoudre</button>
                    <button class="bulk-action-btn" data-action="ignore">Ignorer</button>
                    <button class="bulk-action-btn" data-action="reopen">Rouvrir</button>
                </div>
            </div>
        `;

        this.table.insertAdjacentHTML('beforebegin', bulkActionsHtml);
        this.bulkActionsContainer = this.table.previousElementSibling;

        // Handle bulk actions
        this.bulkActionsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('bulk-action-btn')) {
                const action = e.target.dataset.action;
                this.performBulkAction(action);
            }
        });
    }

    updateBulkActions() {
        const selectedCheckboxes = this.table.querySelectorAll('.row-checkbox:checked');
        const count = selectedCheckboxes.length;

        if (count > 0) {
            this.bulkActionsContainer.style.display = 'block';
            this.bulkActionsContainer.querySelector('.selected-count').textContent =
                `${count} élément${count > 1 ? 's' : ''} sélectionné${count > 1 ? 's' : ''}`;
        } else {
            this.bulkActionsContainer.style.display = 'none';
        }
    }

    async performBulkAction(action) {
        const selectedRows = this.table.querySelectorAll('.row-checkbox:checked');
        const errorIds = Array.from(selectedRows).map(checkbox => {
            return checkbox.closest('tr').dataset.errorId;
        }).filter(id => id);

        if (errorIds.length === 0) return;

        const actionName = {
            resolve: 'résoudre',
            ignore: 'ignorer',
            reopen: 'rouvrir'
        }[action];

        if (confirm(`Êtes-vous sûr de vouloir ${actionName} ${errorIds.length} erreur(s) ?`)) {
            let successCount = 0;

            for (const errorId of errorIds) {
                try {
                    const result = await window.dashboard.performAction(`/dashboard/error/${errorId}/${action}`);
                    if (result) {
                        successCount++;
                        // Update row status
                        const row = this.table.querySelector(`tr[data-error-id="${errorId}"]`);
                        if (row) {
                            this.updateRowStatus(row, action);
                        }
                    }
                } catch (error) {
                    console.error(`Error ${action}ing ${errorId}:`, error);
                }
            }

            window.dashboard.showNotification(
                `${successCount} erreur(s) ${actionName}(s) avec succès`,
                'success'
            );

            // Clear selections
            this.table.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            this.table.querySelector('.select-all-checkbox').checked = false;
            this.updateBulkActions();
        }
    }

    updateRowStatus(row, action) {
        const statusBadge = row.querySelector('.status-badge');
        if (statusBadge) {
            const statusMap = {
                resolve: 'resolved',
                ignore: 'ignored',
                reopen: 'open'
            };
            const newStatus = statusMap[action];
            statusBadge.className = `status-badge badge-${newStatus}`;
            statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
        }

        // Visual feedback
        row.style.background = '#dcfce7';
        setTimeout(() => {
            row.style.background = '';
        }, 1000);
    }

    makeResponsive() {
        // Add responsive wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive-wrapper';
        wrapper.style.cssText = `
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        `;

        this.table.parentNode.insertBefore(wrapper, this.table);
        wrapper.appendChild(this.table);

        // Add responsive behavior
        const updateTableView = () => {
            const isMobile = window.innerWidth < 768;

            if (isMobile) {
                this.table.classList.add('mobile-view');
                this.addMobileCards();
            } else {
                this.table.classList.remove('mobile-view');
                this.removeMobileCards();
            }
        };

        window.addEventListener('resize', window.dashboard.debounce(updateTableView, 250));
        updateTableView();
    }

    addMobileCards() {
        // Convert table to card layout on mobile
        const rows = this.table.querySelectorAll('tbody tr');
        const headers = Array.from(this.table.querySelectorAll('thead th')).map(th => th.textContent);

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if (headers[index] && !cell.dataset.label) {
                    cell.dataset.label = headers[index];
                }
            });
        });
    }

    removeMobileCards() {
        // Remove mobile-specific attributes
        const cells = this.table.querySelectorAll('td[data-label]');
        cells.forEach(cell => {
            delete cell.dataset.label;
        });
    }
}

// Initialize enhanced tables
document.addEventListener('DOMContentLoaded', () => {
    const tables = document.querySelectorAll('.dashboard-table');
    tables.forEach(table => {
        new TableEnhancer(table);
    });
});

// Export for potential external use
window.Dashboard = Dashboard;
window.TableEnhancer = TableEnhancer;
