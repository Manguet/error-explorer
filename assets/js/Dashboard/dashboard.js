// Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileToggle = document.getElementById('mobile-toggle');
    const sidebar = document.getElementById('dashboard-sidebar');
    const overlay = document.getElementById('mobile-overlay');

    if (mobileToggle && sidebar && overlay) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Auto-dismiss flash messages
    const flashMessages = document.querySelectorAll('[data-auto-dismiss]');
    flashMessages.forEach(function(message) {
        const delay = parseInt(message.getAttribute('data-auto-dismiss'));
        if (delay) {
            setTimeout(function() {
                message.style.animation = 'flashSlideOut 0.3s ease-out forwards';
                setTimeout(() => message.remove(), 300);
            }, delay);
        }
    });

    // Perform action utility
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
                return true;
            } else {
                showNotification(data.error || 'Une erreur est survenue', 'error');
                return false;
            }
        } catch (error) {
            showNotification('Erreur de connexion', 'error');
            return false;
        }
    };

    // Show notification utility
    window.showNotification = function(message, type = 'info') {
        const container = document.getElementById('flash-messages') || document.body;
        const notification = document.createElement('div');
        notification.className = `flash-message alert-${type} dashboard-fade-in`;
        notification.innerHTML = `
                        ${message}
                        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
                    `;
        container.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'flashSlideOut 0.3s ease-out forwards';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    };
});

// Copy to clipboard utility
window.copyToClipboard = function(text) {
    if (navigator.clipboard && window.dashboard) {
        navigator.clipboard.writeText(text).then(() => {
            window.dashboard.showNotification('Copié dans le presse-papiers !', 'success');
        }).catch(() => {
            fallbackCopyTextToClipboard(text);
        });
    } else {
        fallbackCopyTextToClipboard(text);
    }
};

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        document.execCommand('copy');
        if (window.dashboard) {
            window.dashboard.showNotification('Copié dans le presse-papiers !', 'success');
        }
    } catch (err) {
        if (window.dashboard) {
            window.dashboard.showNotification('Erreur lors de la copie', 'error');
        }
    }

    document.body.removeChild(textArea);
}
