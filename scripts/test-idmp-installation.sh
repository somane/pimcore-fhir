#!/bin/bash
# test-idmp-installation.sh
# Script de test pour l'installation IDMP

echo "========================================="
echo "Test de l'installation IDMP pour Pimcore"
echo "========================================="

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonction pour afficher les résultats
print_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}"
    else
        echo -e "${RED}✗ $2${NC}"
        return 1
    fi
}

# Fonction pour afficher les avertissements
print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

echo ""
echo "1. Vérification des commandes..."
echo "--------------------------------"

# Vérifier l'existence des commandes
bin/console list app:idmp > /dev/null 2>&1
print_result $? "Commandes IDMP disponibles"

echo ""
echo "2. Validation de l'installation..."
echo "----------------------------------"

# Exécuter la validation
bin/console app:idmp:validate --no-interaction
VALIDATION_RESULT=$?

if [ $VALIDATION_RESULT -ne 0 ]; then
    echo ""
    print_warning "L'installation nécessite des corrections."
    echo ""
    echo "3. Tentative de correction automatique..."
    echo "-----------------------------------------"
    
    # Reconstruire les classes
    echo "Reconstruction des classes..."
    bin/console pimcore:deployment:classes-rebuild
    
    # Vider le cache
    echo "Nettoyage du cache..."
    bin/console cache:clear
    
    # Revalider
    echo ""
    echo "4. Nouvelle validation..."
    echo "-------------------------"
    bin/console app:idmp:validate --no-interaction
    VALIDATION_RESULT=$?
fi

echo ""
echo "5. Test de l'API..."
echo "-------------------"

# Test de l'API (nécessite que le serveur soit lancé)
if command -v curl &> /dev/null; then
    # Tester l'endpoint MedicinalProduct
    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/fhir/MedicinalProduct)
    if [ "$RESPONSE" = "200" ]; then
        print_result 0 "API MedicinalProduct accessible"
    else
        print_result 1 "API MedicinalProduct inaccessible (HTTP $RESPONSE)"
    fi
    
    # Tester l'endpoint Substance
    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/fhir/Substance)
    if [ "$RESPONSE" = "200" ]; then
        print_result 0 "API Substance accessible"
    else
        print_result 1 "API Substance inaccessible (HTTP $RESPONSE)"
    fi
else
    print_warning "curl non installé - tests API ignorés"
fi

echo ""
echo "========================================="
if [ $VALIDATION_RESULT -eq 0 ]; then
    echo -e "${GREEN}Installation IDMP validée avec succès !${NC}"
else
    echo -e "${RED}Installation IDMP incomplète.${NC}"
    echo ""
    echo "Actions recommandées :"
    echo "1. Exécutez : bin/console app:idmp:install"
    echo "2. Puis : bin/console pimcore:deployment:classes-rebuild"
    echo "3. Enfin : bin/console cache:clear"
    echo "4. Relancez ce script de test"
fi
echo "========================================="

exit $VALIDATION_RESULT