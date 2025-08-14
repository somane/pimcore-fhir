#!/bin/bash
# fix-routes.sh - Script pour corriger l'erreur de routes

echo "Correction de l'erreur de routes FHIR..."

# Option 1 : Utiliser les routes YAML (recommandé)
echo "1. Création des routes YAML..."

# Sauvegarder l'ancienne configuration si elle existe
if [ -f "config/routes/fhir.yaml" ]; then
    cp config/routes/fhir.yaml config/routes/fhir.yaml.bak
    echo "   Sauvegarde de l'ancienne configuration dans fhir.yaml.bak"
fi

# Créer la nouvelle configuration de routes
cat > config/routes/fhir.yaml << 'EOF'
# API FHIR
fhir_api_metadata:
    path: /api/fhir/metadata
    controller: App\Controller\FhirController::metadata
    methods: GET

fhir_api_list:
    path: /api/fhir/{resourceType}
    controller: App\Controller\FhirController::list
    methods: GET
    requirements:
        resourceType: 'Patient|Practitioner|Observation|Organization'

fhir_api_read:
    path: /api/fhir/{resourceType}/{id}
    controller: App\Controller\FhirController::read
    methods: GET
    requirements:
        resourceType: 'Patient|Practitioner|Observation|Organization'
        id: '\d+'

fhir_api_create:
    path: /api/fhir/{resourceType}
    controller: App\Controller\FhirController::create
    methods: POST
    requirements:
        resourceType: 'Patient|Practitioner|Observation|Organization'

fhir_api_update:
    path: /api/fhir/{resourceType}/{id}
    controller: App\Controller\FhirController::update
    methods: PUT
    requirements:
        resourceType: 'Patient|Practitioner|Observation|Organization'
        id: '\d+'

# Frontend FHIR
fhir_portal_index:
    path: /fhir
    controller: App\Controller\FhirPortalController::index
    methods: GET

fhir_portal_patient:
    path: /fhir/patient/{id}
    controller: App\Controller\FhirPortalController::patientDetail
    methods: GET
    requirements:
        id: '\d+'

# Admin FHIR
admin_fhir_stats:
    path: /admin/fhir/stats
    controller: App\Controller\Admin\FhirAdminController::stats
    methods: GET

admin_fhir_import:
    path: /admin/fhir/import
    controller: App\Controller\Admin\FhirAdminController::import
    methods: POST
EOF

echo "✓ Routes YAML créées"

# Option 2 : Si l'utilisateur veut utiliser les annotations
echo -e "\n2. Configuration optionnelle pour les annotations..."

# Vérifier si les packages nécessaires sont installés
if ! grep -q "doctrine/annotations" composer.json; then
    echo "   Pour utiliser les annotations, exécutez :"
    echo "   composer require doctrine/annotations"
    echo "   composer require sensio/framework-extra-bundle"
else
    echo "✓ Packages d'annotations déjà installés"
fi

# Créer le controller s'il n'existe pas
if [ ! -f "src/Controller/FhirController.php" ]; then
    echo -e "\n3. Le controller FhirController.php n'existe pas. Création..."
    mkdir -p src/Controller
    
    # Le code du controller est trop long pour être inclus ici
    # On indique à l'utilisateur de le créer
    echo "   Créez le fichier src/Controller/FhirController.php avec le code fourni"
else
    echo -e "\n✓ Controller FhirController.php existe déjà"
fi

# Créer le controller Portal s'il n'existe pas
if [ ! -f "src/Controller/FhirPortalController.php" ]; then
    echo -e "\n4. Création du FhirPortalController..."
    
cat > src/Controller/FhirPortalController.php << 'EOF'
<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Pimcore\Model\DataObject\Patient;

class FhirPortalController extends AbstractController
{
    public function index(): Response
    {
        return $this->render('fhir/portal/index.html.twig');
    }

    public function patientDetail(int $id): Response
    {
        $patient = Patient::getById($id);
        
        if (!$patient || !$patient->isPublished()) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        return $this->render('fhir/portal/patient-detail.html.twig', [
            'patient' => $patient
        ]);
    }
}
EOF
    echo "✓ FhirPortalController créé"
fi

# Créer le controller Admin s'il n'existe pas
if [ ! -f "src/Controller/Admin/FhirAdminController.php" ]; then
    echo -e "\n5. Création du FhirAdminController..."
    mkdir -p src/Controller/Admin
    
cat > src/Controller/Admin/FhirAdminController.php << 'EOF'
<?php
namespace App\Controller\Admin;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Pimcore\Model\DataObject;

class FhirAdminController extends AdminController
{
    public function stats(): JsonResponse
    {
        $this->checkPermission('objects');

        return $this->json([
            'success' => true,
            'patients' => DataObject\Patient::getList()->getTotalCount() ?? 0,
            'practitioners' => DataObject\Practitioner::getList()->getTotalCount() ?? 0,
            'observations' => DataObject\Observation::getList()->getTotalCount() ?? 0,
            'organizations' => DataObject\Organization::getList()->getTotalCount() ?? 0
        ]);
    }

    public function import(): JsonResponse
    {
        $this->checkPermission('objects');
        
        // TODO: Implémenter l'import
        return $this->json(['success' => true, 'message' => 'Import functionality to be implemented']);
    }
}
EOF
    echo "✓ FhirAdminController créé"
fi

# Vider le cache
echo -e "\n6. Nettoyage du cache..."
php bin/console cache:clear

# Vérifier les routes
echo -e "\n7. Vérification des routes..."
php bin/console debug:router | grep fhir || echo "Aucune route FHIR trouvée - vérifiez la configuration"

echo -e "\n✅ Correction terminée !"
echo ""
echo "Pour tester :"
echo "  curl http://localhost/api/fhir/metadata"
echo ""
echo "Si l'erreur persiste :"
echo "1. Vérifiez que le fichier config/routes.yaml inclut bien les routes FHIR"
echo "2. Assurez-vous que les controllers existent dans src/Controller/"
echo "3. Relancez : php bin/console cache:clear"