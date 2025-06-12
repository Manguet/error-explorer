#!/bin/bash

# Variables
PROJECT_DIR="/home/mabe6115/www"
LOG_FILE="$PROJECT_DIR/var/log/messenger.log"
LOCK_FILE="/tmp/messenger_worker.lock"

# Créer le dossier de log s'il n'existe pas
mkdir -p "$PROJECT_DIR/var/log"

# Vérifier qu'un worker n'est pas déjà en cours
if [ -f "$LOCK_FILE" ]; then
    echo "$(date): Worker déjà en cours" >> "$LOG_FILE"
    exit 0
fi

# Créer le fichier de verrouillage
touch "$LOCK_FILE"

# Aller dans le répertoire du projet
cd "$PROJECT_DIR"

# Log de début
echo "$(date): Début traitement messenger" >> "$LOG_FILE"

# Exécuter la commande messenger avec timeout
timeout 240 php bin/console messenger:consume async --limit=20 --time-limit=180 --memory-limit=128M --no-interaction >> "$LOG_FILE" 2>&1

# Code de sortie
EXIT_CODE=$?
echo "$(date): Fin traitement messenger (code: $EXIT_CODE)" >> "$LOG_FILE"

# Supprimer le fichier de verrouillage
rm -f "$LOCK_FILE"
