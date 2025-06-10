let userMenuOpen = false;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    setupHeaderEvents();
    checkNotifications();
});

function setupHeaderEvents() {
    // User menu toggle
    const userMenuToggle = document.getElementById('user-menu-toggle');

    userMenuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleUserMenu();
    });

    // Fermer au clic dehors
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-menu')) {
            closeUserMenu();
        }
    });
}

function toggleUserMenu() {
    const toggle = document.getElementById('user-menu-toggle');
    const dropdown = document.getElementById('user-menu-dropdown');

    if (userMenuOpen) {
        closeUserMenu();
    } else {
        dropdown.classList.add('show');
        toggle.classList.add('open');
        userMenuOpen = true;
    }
}

function closeUserMenu() {
    const toggle = document.getElementById('user-menu-toggle');
    const dropdown = document.getElementById('user-menu-dropdown');

    dropdown.classList.remove('show');
    toggle.classList.remove('open');
    userMenuOpen = false;
}

// Notifications
async function checkNotifications() {
    try {
        const notifications = await getNotificationsCount();
        const badge = document.getElementById('notification-badge');

        if (notifications > 0) {
            badge.textContent = notifications;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    } catch (error) {
        console.error('Erreur lors de la vérification des notifications:', error);
    }
}

async function getNotificationsCount() {
    // TODO: Implémenter l'appel API
    return Math.floor(Math.random() * 5);
}

// Actions utilisateur
function editProfile() {
    console.log('Édition du profil...');
    closeUserMenu();
}
