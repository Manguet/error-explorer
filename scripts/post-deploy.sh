#!/bin/bash

echo "🧹 Post-déploiement Error Explorer..."

# Variables
DEPLOY_PATH="/home/mabe6115/public_html"

# Aller dans le répertoire de l'application
cd "$DEPLOY_PATH" || exit 1

# 1. Supprimer complètement le cache
echo "🗑️ Suppression du cache..."
rm -rf var/cache/prod/
rm -rf var/cache/dev/
mkdir -p var/cache/prod
mkdir -p var/cache/dev

# 2. Ajuster les permissions
echo "🔐 Ajustement des permissions..."
chmod -R 755 var/cache/
chmod -R 755 var/log/
chmod -R 644 var/cache/*
chmod -R 644 var/log/*

# 3. Vérifier que les dossiers existent
echo "📁 Vérification des dossiers..."
mkdir -p var/cache/prod
mkdir -p var/log
mkdir -p public/build

# 4. Test de l'application
echo "🔍 Test de l'application..."
if php bin/console about --env=prod >/dev/null 2>&1; then
    echo "✅ Application fonctionnelle en mode production"
else
    echo "❌ Erreur en mode production"
    echo "🔧 Génération du cache..."
    php bin/console cache:clear --env=prod 2>/dev/null || echo "⚠️ Impossible de vider le cache"
    php bin/console cache:warmup --env=prod 2>/dev/null || echo "⚠️ Impossible de réchauffer le cache"
fi

# 5. Migrations (si nécessaires)
echo "🗄️ Vérification des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || echo "⚠️ Pas de nouvelles migrations"

echo "✅ Post-déploiement terminé!"
exit 0
