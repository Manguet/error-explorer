/**
 * Profile Component
 * Gère les fonctionnalités interactives de l'espace profil
 */
class ProfileComponent {
    constructor() {
        this.init();
    }

    init() {
        this.initPasswordStrength();
        this.initDeleteAccountForm();
        this.initProfileImageUpload();
        this.initPreferencesForm();
    }

    /**
     * Initialise l'indicateur de force du mot de passe
     */
    initPasswordStrength() {
        const passwordInput = document.getElementById('change_password_form_new_password_first');
        if (!passwordInput) return;

        const strengthBar = document.getElementById('password-strength-fill');
        const strengthText = document.getElementById('password-strength-text');
        const requirementsList = document.querySelectorAll('.password-requirements__item');

        passwordInput.addEventListener('input', (e) => {
            const password = e.target.value;
            const strength = this.calculatePasswordStrength(password);
            this.updatePasswordStrength(strength, strengthBar, strengthText);
            this.updatePasswordRequirements(password, requirementsList);
        });
    }

    /**
     * Calcule la force du mot de passe
     */
    calculatePasswordStrength(password) {
        let score = 0;
        const checks = [
            password.length >= 8,
            /[a-z]/.test(password),
            /[A-Z]/.test(password),
            /\d/.test(password),
            /[@$!%*?&]/.test(password)
        ];
        
        score = checks.filter(check => check).length;
        return { score, checks };
    }

    /**
     * Met à jour l'affichage de la force du mot de passe
     */
    updatePasswordStrength(strength, bar, text) {
        if (!bar || !text) return;

        const colors = ['#e74c3c', '#e67e22', '#f39c12', '#27ae60', '#2ecc71'];
        const texts = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
        const widths = [20, 40, 60, 80, 100];
        
        if (strength.score === 0) {
            bar.style.width = '0%';
            text.textContent = 'Entrez un mot de passe';
            text.style.color = '#6b7280';
            return;
        }
        
        bar.style.width = widths[strength.score - 1] + '%';
        bar.style.backgroundColor = colors[strength.score - 1];
        text.textContent = texts[strength.score - 1];
        text.style.color = colors[strength.score - 1];
    }

    /**
     * Met à jour l'affichage des exigences du mot de passe
     */
    updatePasswordRequirements(password, requirementsList) {
        if (!requirementsList) return;

        const requirements = [
            password.length >= 8,
            /[a-z]/.test(password),
            /[A-Z]/.test(password),
            /\d/.test(password),
            /[@$!%*?&]/.test(password)
        ];

        requirementsList.forEach((item, index) => {
            const icon = item.querySelector('i');
            if (requirements[index]) {
                item.classList.add('password-requirements__item--valid');
                icon.className = 'fas fa-check-circle';
            } else {
                item.classList.remove('password-requirements__item--valid');
                icon.className = 'fas fa-circle';
            }
        });
    }

    /**
     * Initialise le formulaire de suppression de compte
     */
    initDeleteAccountForm() {
        const passwordInput = document.getElementById('delete_account_form_password');
        const confirmationInput = document.getElementById('delete_account_form_confirmation');
        const deleteButton = document.getElementById('delete-account-btn');
        
        if (!passwordInput || !confirmationInput || !deleteButton) return;

        const checkFormValidity = () => {
            const password = passwordInput.value.trim();
            const confirmation = confirmationInput.value.trim();
            
            if (password && confirmation === 'SUPPRIMER MON COMPTE') {
                deleteButton.disabled = false;
                deleteButton.classList.remove('button--disabled');
            } else {
                deleteButton.disabled = true;
                deleteButton.classList.add('button--disabled');
            }
        };
        
        passwordInput.addEventListener('input', checkFormValidity);
        confirmationInput.addEventListener('input', checkFormValidity);
        
        // Confirmation supplémentaire avant soumission
        deleteButton.addEventListener('click', (e) => {
            if (!confirm('Êtes-vous absolument certain de vouloir supprimer votre compte ? Cette action est irréversible.')) {
                e.preventDefault();
            }
        });
    }

    /**
     * Initialise l'upload d'image de profil (fonctionnalité future)
     */
    initProfileImageUpload() {
        const uploadButton = document.getElementById('profile-image-upload');
        const fileInput = document.getElementById('profile-image-input');
        const preview = document.getElementById('profile-image-preview');
        
        if (!uploadButton || !fileInput) return;

        uploadButton.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Validation du fichier
            if (!this.validateImageFile(file)) {
                this.showNotification('Veuillez sélectionner une image valide (JPG, PNG, max 2MB)', 'error');
                return;
            }

            // Prévisualisation
            this.previewImage(file, preview);
            
            // Upload automatique (à implémenter côté serveur)
            this.uploadProfileImage(file);
        });
    }

    /**
     * Valide un fichier image
     */
    validateImageFile(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        return allowedTypes.includes(file.type) && file.size <= maxSize;
    }

    /**
     * Prévisualise une image
     */
    previewImage(file, preview) {
        if (!preview) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    /**
     * Upload d'image de profil (à implémenter)
     */
    async uploadProfileImage(file) {
        const formData = new FormData();
        formData.append('profile_image', file);

        try {
            const response = await fetch('/dashboard/profile/upload-image', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Image de profil mise à jour avec succès', 'success');
            } else {
                this.showNotification(result.message || 'Erreur lors de l\'upload', 'error');
            }
        } catch (error) {
            console.error('Erreur upload:', error);
            this.showNotification('Erreur lors de l\'upload de l\'image', 'error');
        }
    }

    /**
     * Initialise le formulaire des préférences
     */
    initPreferencesForm() {
        const form = document.querySelector('.form--preferences');
        if (!form) return;

        // Sauvegarde automatique des préférences
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.autoSavePreferences(form);
            });
        });
    }

    /**
     * Sauvegarde automatique des préférences
     */
    async autoSavePreferences(form) {
        const formData = new FormData(form);
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                this.showNotification('Préférences sauvegardées', 'success', 2000);
            }
        } catch (error) {
            console.error('Erreur sauvegarde préférences:', error);
        }
    }

    /**
     * Affiche une notification
     */
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `flash-message flash-message--${type}`;
        notification.innerHTML = `
            <span class="flash-message__content">${message}</span>
            <button class="flash-message__close" onclick="this.parentElement.remove()">&times;</button>
        `;

        const container = document.getElementById('flash-container') || document.body;
        container.appendChild(notification);

        // Auto-suppression
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    }

    /**
     * Confirme une action dangereuse
     */
    confirmDangerousAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    /**
     * Exporte les données utilisateur
     */
    async exportUserData() {
        try {
            const response = await fetch('/dashboard/profile/export-data', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'mes-donnees-error-explorer.json';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.showNotification('Données exportées avec succès', 'success');
            } else {
                throw new Error('Erreur lors de l\'export');
            }
        } catch (error) {
            console.error('Erreur export:', error);
            this.showNotification('Erreur lors de l\'export des données', 'error');
        }
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    if (document.body.classList.contains('dashboard')) {
        new ProfileComponent();
    }
});

export default ProfileComponent;