/**
 * JavaScript pour la gestion des erreurs dans les DataTables
 */

// Fonction helper pour les actions sur les erreurs
async function performAction(url) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            return true;
        } else {
            showNotification(result.error || 'Une erreur est survenue', 'error');
            return false;
        }
    } catch (error) {
        console.error('Erreur lors de l\'action:', error);
        showNotification('Erreur de connexion', 'error');
        return false;
    }
}

// Fonction pour afficher les notifications
function showNotification(message, type = 'info') {
    // Utilise le système de notifications existant ou crée une notification simple
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        // Fallback simple
        const notificationClass = type === 'success' ? 'alert-success' : 
                                 type === 'error' ? 'alert-danger' : 'alert-info';
        
        const notification = document.createElement('div');
        notification.className = `alert ${notificationClass}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 12px 20px;
            border-radius: 4px;
            background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
            color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : '#bee5eb'};
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}