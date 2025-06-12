document.addEventListener('DOMContentLoaded', function() {
    // ===== GESTION DU STATUT EN TEMPS RÉEL =====
    const statusIndicator = document.getElementById('footer-status');
    const statusDot = statusIndicator?.querySelector('.status-dot');
    const statusText = statusIndicator?.querySelector('.status-text');

    // Configuration des statuts possibles
    const statusConfig = {
        operational: {
            text: 'Tous systèmes opérationnels',
            class: '',
            color: '#10b981',
            bgColor: 'rgba(16, 185, 129, 0.08)',
            borderColor: 'rgba(16, 185, 129, 0.15)'
        },
        degraded: {
            text: 'Performances dégradées',
            class: 'status-degraded',
            color: '#f59e0b',
            bgColor: 'rgba(245, 158, 11, 0.08)',
            borderColor: 'rgba(245, 158, 11, 0.15)'
        },
        outage: {
            text: 'Problème détecté',
            class: 'status-outage',
            color: '#ef4444',
            bgColor: 'rgba(239, 68, 68, 0.08)',
            borderColor: 'rgba(239, 68, 68, 0.15)'
        },
        maintenance: {
            text: 'Maintenance programmée',
            class: 'status-degraded',
            color: '#8b5cf6',
            bgColor: 'rgba(139, 92, 246, 0.08)',
            borderColor: 'rgba(139, 92, 246, 0.15)'
        }
    };

    // Fonction pour mettre à jour le statut
    function updateStatus(newStatus = 'operational') {
        if (!statusIndicator || !statusConfig[newStatus]) return;

        const config = statusConfig[newStatus];

        // Animation de transition
        statusIndicator.style.opacity = '0.7';
        statusIndicator.style.transform = 'scale(0.95)';

        setTimeout(() => {
            // Réinitialiser les classes
            Object.values(statusConfig).forEach(s => {
                statusIndicator.classList.remove(s.class);
            });

            // Appliquer la nouvelle classe
            if (config.class) {
                statusIndicator.classList.add(config.class);
            }

            // Mettre à jour le texte
            if (statusText) {
                statusText.textContent = config.text;
            }

            // Mettre à jour les couleurs du dot
            if (statusDot) {
                statusDot.style.background = config.color;
            }

            // Animation de retour
            statusIndicator.style.opacity = '1';
            statusIndicator.style.transform = 'scale(1)';
        }, 150);
    }

    // Simulation de changements de statut (peut être connecté à une vraie API)
    function checkSystemStatus() {
        // Ici vous pourriez faire un appel API vers votre endpoint de statut
        // fetch('/api/status').then(response => response.json()).then(data => {
        //     updateStatus(data.status);
        // });

        // Simulation pour la démo (90% opérationnel, 8% dégradé, 2% problème)
        const random = Math.random();
        let newStatus = 'operational';

        if (random < 0.02) {
            newStatus = 'outage';
        } else if (random < 0.1) {
            newStatus = 'degraded';
        }

        updateStatus(newStatus);
    }

    // Vérifier le statut toutes les 30 secondes
    if (statusIndicator) {
        setInterval(checkSystemStatus, 30000);
        // Vérification initiale après 2 secondes
        setTimeout(checkSystemStatus, 2000);
    }

    // ===== EASTER EGG : KONAMI CODE =====
    let konamiCode = [];
    const konamiSequence = [
        'ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown',
        'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight',
        'KeyB', 'KeyA'
    ];

    document.addEventListener('keydown', function(e) {
        konamiCode.push(e.code);

        if (konamiCode.length > konamiSequence.length) {
            konamiCode.shift();
        }

        if (konamiCode.length === konamiSequence.length) {
            if (konamiCode.every((code, index) => code === konamiSequence[index])) {
                activateEasterEgg();
                konamiCode = [];
            }
        }
    });

    function activateEasterEgg() {
        // Animation spéciale pour l'easter egg
        const footer = document.querySelector('.footer');
        footer.style.background = 'linear-gradient(45deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #9400d3)';
        footer.style.backgroundSize = '400% 400%';
        footer.style.animation = 'rainbow 2s ease infinite';

        // Message secret
        console.log('🎉 Félicitations ! Vous avez trouvé l\'easter egg d\'Error Explorer !');

        // Remettre le style normal après 5 secondes
        setTimeout(() => {
            footer.style.background = '';
            footer.style.backgroundSize = '';
            footer.style.animation = '';
        }, 5000);
    }

    // ===== STYLES CSS DYNAMIQUES POUR LES ANIMATIONS =====
    const style = document.createElement('style');
    style.textContent = `
        @keyframes rainbow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    `;
    document.head.appendChild(style);
});
