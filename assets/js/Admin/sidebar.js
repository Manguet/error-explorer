let sidebarOpen = false;
const mobileToggleButton = document.getElementById('mobileToggle');
const sidebarOverlay = document.getElementById('sidebar-overlay');

mobileToggleButton.addEventListener('click', function() {
    toggleSidebar();
});

sidebarOverlay.addEventListener('click', function() {
    closeSidebar();
});

// Fonctions de la sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (sidebarOpen) {
        closeSidebar();
    } else {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        sidebarOpen = true;
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    sidebarOpen = false;
}

// Fermeture automatique sur desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) {
        closeSidebar();
    }
});
