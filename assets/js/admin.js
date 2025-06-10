window.performAction = async function(url, method = 'POST') {
    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message, 'success');
            return data;
        } else {
            showNotification(data.error || 'Une erreur est survenue', 'error');
            return null;
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur de connexion', 'error');
        return null;
    }
};

window.showNotification = function(message, type = 'info') {
    const alertClass = type === 'error' ? 'alert-danger' :
        type === 'warning' ? 'alert-warning' :
            type === 'success' ? 'alert-success' : 'alert-info';

    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    const container = document.querySelector('.admin-content') || document.body;
    container.insertAdjacentHTML('afterbegin', alertHtml);

    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) alert.remove();
    }, 5000);
};

import './Admin/settings.js';
import './Admin/sidebar.js';
import './Admin/user-menu.js';
