document.addEventListener('DOMContentLoaded', function() {
    // Animation de typing pour les lignes de code
    const codeLines = document.querySelectorAll('.code-line');

    // Observer pour déclencher l'animation quand visible
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Ajouter une classe pour l'animation
                entry.target.style.opacity = '0';
                entry.target.style.transform = 'translateX(-20px)';

                // Animer chaque ligne avec un délai
                const index = Array.from(codeLines).indexOf(entry.target);
                setTimeout(() => {
                    entry.target.style.transition = 'all 0.5s ease';
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateX(0)';
                }, index * 100);

                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    codeLines.forEach(line => observer.observe(line));

    // Animation de pulsation pour les lignes d'erreur
    setInterval(() => {
        const errorLines = document.querySelectorAll('.code-line.error');
        errorLines.forEach(line => {
            line.style.transform = 'scale(1.02)';
            setTimeout(() => {
                line.style.transform = 'scale(1)';
            }, 200);
        });
    }, 3000);

    // Effet de hover sur la fenêtre de code
    const codeWindow = document.querySelector('.code-window');
    if (codeWindow) {
        codeWindow.addEventListener('mouseenter', () => {
            codeWindow.style.boxShadow = '0 25px 50px rgba(59, 130, 246, 0.2)';
        });

        codeWindow.addEventListener('mouseleave', () => {
            codeWindow.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.3)';
        });
    }
});
