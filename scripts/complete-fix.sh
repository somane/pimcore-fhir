#!/bin/bash
# complete-fix.sh

echo "=== Correction complète des permissions Pimcore ==="

# 1. Identifier l'utilisateur web
if ps aux | grep -q '[a]pache2'; then
    WEB_USER="www-data"
elif ps aux | grep -q '[n]ginx'; then
    WEB_USER="nginx"
elif ps aux | grep -q '[h]ttpd'; then
    WEB_USER="apache"
else
    WEB_USER="www-data"
fi

echo "Utilisateur web détecté : $WEB_USER"

# 2. Nettoyer les caches
echo "Nettoyage des caches..."
sudo rm -rf var/cache/*
sudo rm -rf public/var/*
sudo rm -rf var/tmp/*

# 3. Recréer la structure
echo "Recréation de la structure..."
sudo mkdir -p var/{cache,log,sessions,tmp,config,classes,versions,recyclebin,admin,email}
sudo mkdir -p public/var/{assets,cache,tmp}

# 4. Appliquer les permissions
echo "Application des permissions..."
sudo chown -R "$WEB_USER:$WEB_USER" var/ public/var/
sudo chmod -R 775 var/ public/var/

# 5. Permissions spéciales pour les logs
sudo chmod -R 777 var/log/

# 6. Régénérer le cache
echo "Régénération du cache..."
sudo -u "$WEB_USER" php bin/console cache:clear --env=dev
sudo -u "$WEB_USER" php bin/console cache:warmup --env=dev
sudo -u "$WEB_USER" php bin/console pimcore:deployment:classes-rebuild

echo "=== Correction terminée ==="