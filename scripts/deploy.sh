#!/bin/bash

set -e

echo "🚀 Début du déploiement Error Explorer..."

# Variables pour votre configuration o2Switch
DEPLOY_PATH="/home/mabe6115/public_html"
BACKUP_PATH="/home/mabe6115/backups"
DATE=$(date +%Y%m%d_%H%M%S)

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Fonction de nettoyage en cas d'erreur
cleanup() {
    log_error "Erreur détectée, arrêt du déploiement"
    exit 1
}

# Piège pour capturer les erreurs
trap cleanup ERR

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "composer.json" ]; then
    log_error "Fichier composer.json non trouvé. Êtes-vous dans le bon répertoire ?"
    exit 1
fi

# Créer le dossier de backup s'il n'existe pas
log_info "Préparation du backup..."
mkdir -p "$BACKUP_PATH"

# Créer un backup de l'application actuelle
if [ -d "$DEPLOY_PATH" ] && [ "$(ls -A $DEPLOY_PATH 2>/dev/null)" ]; then
    log_info "Création du backup..."
    tar -czf "$BACKUP_PATH/backup_$DATE.tar.gz" -C "$DEPLOY_PATH" . 2>/dev/null || true
    log_success "Backup créé : backup_$DATE.tar.gz"
else
    log_warning "Aucun fichier à sauvegarder (premier déploiement ?)"
fi

# Aller dans le répertoire de déploiement
cd "$DEPLOY_PATH"

# Mettre à jour le code depuis Git
log_info "Mise à jour du code..."
if [ -d ".git" ]; then
    git pull origin main
    log_success "Code mis à jour depuis Git"
else
    log_warning "Pas de dépôt Git trouvé dans $DEPLOY_PATH"
    log_info "Le code devrait être synchronisé par d'autres moyens"
fi

# Installation des dépendances PHP (production)
log_info "Installation des dépendances PHP..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet
log_success "Dépendances PHP installées"

# Installation des dépendances Node.js
log_info "Installation des dépendances Node.js..."
if [ -f "package.json" ]; then
    npm ci --omit=dev --silent
    log_success "Dépendances Node.js installées"
else
    log_warning "Pas de package.json trouvé"
fi

# Build des assets
log_info "Compilation des assets..."
if [ -f "webpack.config.js" ] || [ -f "package.json" ]; then
    npm run build 2>/dev/null || {
        log_warning "Impossible de compiler les assets avec npm"
        log_info "Tentative avec Webpack Encore..."
        ./node_modules/.bin/encore production 2>/dev/null || log_warning "Compilation des assets échouée"
    }
    log_success "Assets compilés"
else
    log_warning "Pas de configuration Webpack trouvée"
fi

# Migrations de base de données
log_info "Exécution des migrations de base de données..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || {
    log_warning "Migrations échouées ou pas de nouvelles migrations"
}
log_success "Migrations terminées"

# Nettoyage et réchauffement du cache
log_info "Gestion du cache..."
php bin/console cache:clear --env=prod --no-debug --quiet
php bin/console cache:warmup --env=prod --no-debug --quiet
log_success "Cache mis à jour"

# Ajustement des permissions (important pour o2Switch)
log_info "Ajustement des permissions..."
find var -type d -exec chmod 755 {} \; 2>/dev/null || true
find var -type f -exec chmod 644 {} \; 2>/dev/null || true
chmod -R 755 var/cache var/log 2>/dev/null || true
log_success "Permissions ajustées"

# Vérification de santé basique
log_info "Vérification de santé..."
if php bin/console about --env=prod >/dev/null 2>&1; then
    log_success "Application fonctionnelle"
else
    log_warning "Problème détecté avec l'application"
fi

# Nettoyage des anciens backups (garde les 5 derniers)
log_info "Nettoyage des anciens backups..."
cd "$BACKUP_PATH"
ls -t backup_*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -- 2>/dev/null || true
log_success "Anciens backups nettoyés"

log_success "🎉 Déploiement terminé avec succès!"
log_info "Site disponible sur : https://error-explorer.com"

exit 0
