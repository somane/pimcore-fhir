#!/bin/bash

# Script d'installation automatique FHIR pour Pimcore
# Usage: ./install-fhir.sh

set -e

echo "=================================="
echo "Installation FHIR pour Pimcore"
echo "=================================="

# Vérification des prérequis
echo "1. Vérification des prérequis..."

# Vérifier PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP n'est pas installé"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "✓ PHP $PHP_VERSION détecté"

# Vérifier Composer
if ! command -v composer &> /dev/null; then
    echo "❌ Composer n'est pas installé"
    exit 1
fi
echo "✓ Composer détecté"

# Vérifier qu'on est dans un projet Pimcore
if [ ! -f "composer.json" ] || ! grep -q "pimcore/pimcore" composer.json; then
    echo "❌ Ce script doit être exécuté à la racine d'un projet Pimcore"
    exit 1
fi
echo "✓ Projet Pimcore détecté"

# Installation des dépendances
echo -e "\n2. Installation des dépendances..."
composer require symfony/serializer symfony/validator symfony/uid --no-scripts

# Création de la structure des dossiers
echo -e "\n3. Création de la structure des dossiers..."
mkdir -p src/Command
mkdir -p src/Controller/Admin
mkdir -p src/Model/DataObject/Traits
mkdir -p src/Service
mkdir -p templates/fhir/portal
mkdir -p templates/admin/fhir
mkdir -p config/fhir/profiles
mkdir -p config/routes
mkdir -p public/bundles/app/js/pimcore
mkdir -p public/bundles/app/css

echo "✓ Structure des dossiers créée"

# Création du fichier de commande d'installation
echo -e "\n4. Création des fichiers de base..."

cat > src/Command/FhirInstallCommand.php << 'EOF'
<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Folder;

class FhirInstallCommand extends Command
{
    protected static $defaultName = 'app:fhir:install';

    protected function configure(): void
    {
        $this->setDescription('Installe les classes FHIR dans Pimcore');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installation FHIR pour Pimcore');

        // Créer les dossiers racine
        $this->createRootFolders($io);
        
        // Créer les classes
        $this->createFhirClasses($io);
        
        $io->success('Installation FHIR terminée avec succès !');
        return Command::SUCCESS;
    }

    private function createRootFolders(SymfonyStyle $io): void
    {
        $folders = [
            'FHIR' => 1,
            'FHIR/Patients' => null,
            'FHIR/Practitioners' => null,
            'FHIR/Observations' => null,
            'FHIR/Organizations' => null
        ];

        foreach ($folders as $path => $parentId) {
            $parts = explode('/', $path);
            $key = end($parts);
            
            $folder = Folder::getByPath('/'. $path);
            if (!$folder) {
                $folder = new Folder();
                $folder->setKey($key);
                $folder->setParentId($parentId ?: Folder::getByPath('/' . dirname($path))->getId());
                $folder->save();
                $io->text("Dossier créé : $path");
            }
        }
    }

    private function createFhirClasses(SymfonyStyle $io): void
    {
        // Configuration des classes FHIR
        $classes = [
            'Patient' => $this->getPatientConfig(),
            'Practitioner' => $this->getPractitionerConfig(),
            'Observation' => $this->getObservationConfig(),
            'Organization' => $this->getOrganizationConfig()
        ];

        foreach ($classes as $name => $config) {
            try {
                $classDefinition = ClassDefinition::getByName($name);
                if ($classDefinition) {
                    $io->warning("La classe $name existe déjà");
                    continue;
                }

                $class = new ClassDefinition();
                $class->setName($name);
                $class->setGroup('FHIR');
                
                // Importer la configuration JSON
                $json = json_encode($config);
                $class = ClassDefinition::getByName($name);
                if (!$class) {
                    ClassDefinition\Service::importClassDefinitionFromJson($json, true);
                }
                
                $io->success("Classe $name créée");
            } catch (\Exception $e) {
                $io->error("Erreur lors de la création de $name : " . $e->getMessage());
            }
        }
    }

    private function getPatientConfig(): array
    {
        return [
            "name" => "Patient",
            "group" => "FHIR",
            "showAppLoggerTab" => true,
            "linkGeneratorReference" => "@App\\Model\\DataObject\\Patient\\LinkGenerator",
            "allowInherit" => false,
            "allowVariants" => false,
            "showFieldLookup" => false,
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "type" => null,
                    "region" => null,
                    "title" => null,
                    "width" => null,
                    "height" => null,
                    "collapsible" => false,
                    "collapsed" => false,
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "Identification",
                            "type" => null,
                            "region" => null,
                            "title" => "Identification",
                            "width" => null,
                            "height" => null,
                            "collapsible" => false,
                            "collapsed" => false,
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "identifier",
                                    "title" => "Identifiant",
                                    "tooltip" => "Identifiant unique du patient",
                                    "mandatory" => true,
                                    "noteditable" => false,
                                    "index" => true,
                                    "unique" => true,
                                    "columnLength" => 190
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "familyName",
                                    "title" => "Nom de famille",
                                    "mandatory" => true,
                                    "index" => true,
                                    "columnLength" => 190
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "givenName",
                                    "title" => "Prénom",
                                    "mandatory" => true,
                                    "columnLength" => 190
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Demographics",
                            "title" => "Démographie",
                            "children" => [
                                [
                                    "fieldtype" => "date",
                                    "name" => "birthDate",
                                    "title" => "Date de naissance"
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "gender",
                                    "title" => "Genre",
                                    "options" => [
                                        ["key" => "Masculin", "value" => "male"],
                                        ["key" => "Féminin", "value" => "female"],
                                        ["key" => "Autre", "value" => "other"],
                                        ["key" => "Inconnu", "value" => "unknown"]
                                    ]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Contact",
                            "title" => "Contact",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "phone",
                                    "title" => "Téléphone"
                                ],
                                [
                                    "fieldtype" => "email",
                                    "name" => "email",
                                    "title" => "Email"
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "address",
                                    "title" => "Adresse",
                                    "height" => 100
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function getPractitionerConfig(): array
    {
        return [
            "name" => "Practitioner",
            "group" => "FHIR",
            "showAppLoggerTab" => true,
            "allowInherit" => false,
            "allowVariants" => false,
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "Identification",
                            "title" => "Identification",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "professionalId",
                                    "title" => "Numéro professionnel",
                                    "mandatory" => true,
                                    "unique" => true,
                                    "columnLength" => 190
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "familyName",
                                    "title" => "Nom",
                                    "mandatory" => true,
                                    "columnLength" => 190
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "givenName",
                                    "title" => "Prénom",
                                    "mandatory" => true,
                                    "columnLength" => 190
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "title",
                                    "title" => "Titre",
                                    "columnLength" => 50
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Qualification",
                            "title" => "Qualification",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "specialty",
                                    "title" => "Spécialité"
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "specialtyCode",
                                    "title" => "Code spécialité"
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Contact",
                            "title" => "Contact",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "phone",
                                    "title" => "Téléphone"
                                ],
                                [
                                    "fieldtype" => "email",
                                    "name" => "email",
                                    "title" => "Email"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function getObservationConfig(): array
    {
        return [
            "name" => "Observation",
            "group" => "FHIR",
            "showAppLoggerTab" => true,
            "allowInherit" => false,
            "allowVariants" => false,
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "General",
                            "title" => "Informations générales",
                            "children" => [
                                [
                                    "fieldtype" => "select",
                                    "name" => "status",
                                    "title" => "Statut",
                                    "mandatory" => true,
                                    "options" => [
                                        ["key" => "Enregistré", "value" => "registered"],
                                        ["key" => "Préliminaire", "value" => "preliminary"],
                                        ["key" => "Final", "value" => "final"],
                                        ["key" => "Modifié", "value" => "amended"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "category",
                                    "title" => "Catégorie",
                                    "options" => [
                                        ["key" => "Signes vitaux", "value" => "vital-signs"],
                                        ["key" => "Laboratoire", "value" => "laboratory"],
                                        ["key" => "Imagerie", "value" => "imaging"],
                                        ["key" => "Procédure", "value" => "procedure"]
                                    ]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Code",
                            "title" => "Codification",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "loincCode",
                                    "title" => "Code LOINC",
                                    "mandatory" => true
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "display",
                                    "title" => "Description",
                                    "mandatory" => true
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Subject",
                            "title" => "Sujet",
                            "children" => [
                                [
                                    "fieldtype" => "manyToOneRelation",
                                    "name" => "patient",
                                    "title" => "Patient",
                                    "classes" => ["Patient"]
                                ],
                                [
                                    "fieldtype" => "datetime",
                                    "name" => "effectiveDateTime",
                                    "title" => "Date et heure"
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Value",
                            "title" => "Valeur",
                            "children" => [
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "valueQuantity",
                                    "title" => "Valeur numérique"
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "unit",
                                    "title" => "Unité"
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "unitCode",
                                    "title" => "Code unité"
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "valueString",
                                    "title" => "Valeur texte"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function getOrganizationConfig(): array
    {
        return [
            "name" => "Organization",
            "group" => "FHIR",
            "showAppLoggerTab" => true,
            "allowInherit" => false,
            "allowVariants" => false,
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "Identification",
                            "title" => "Identification",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "identifier",
                                    "title" => "Identifiant",
                                    "mandatory" => true,
                                    "unique" => true
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "name",
                                    "title" => "Nom",
                                    "mandatory" => true
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "type",
                                    "title" => "Type",
                                    "options" => [
                                        ["key" => "Hôpital", "value" => "hospital"],
                                        ["key" => "Clinique", "value" => "clinic"],
                                        ["key" => "Cabinet", "value" => "practice"],
                                        ["key" => "Laboratoire", "value" => "laboratory"]
                                    ]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Contact",
                            "title" => "Contact",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "phone",
                                    "title" => "Téléphone"
                                ],
                                [
                                    "fieldtype" => "email",
                                    "name" => "email",
                                    "title" => "Email"
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "address",
                                    "title" => "Adresse"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
EOF

# Création du trait FHIR
cat > src/Model/DataObject/Traits/FhirResourceTrait.php << 'EOF'
<?php
namespace App\Model\DataObject\Traits;

trait FhirResourceTrait
{
    public function toFhir(): array
    {
        return [
            'resourceType' => $this->getResourceType(),
            'id' => (string) $this->getId(),
            'meta' => [
                'lastUpdated' => $this->getModificationDate()->format('c'),
                'versionId' => (string) $this->getVersionCount()
            ],
            ...$this->toFhirArray()
        ];
    }
    
    abstract protected function getResourceType(): string;
    abstract protected function toFhirArray(): array;
}
EOF

# Création du controller API de base
cat > src/Controller/FhirController.php << 'EOF'
<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject;

/**
 * @Route("/api/fhir", name="fhir_api_")
 */
class FhirController extends AbstractController
{
    /**
     * @Route("/metadata", name="metadata", methods={"GET"})
     */
    public function metadata(): JsonResponse
    {
        return $this->json([
            'resourceType' => 'CapabilityStatement',
            'status' => 'active',
            'date' => (new \DateTime())->format('c'),
            'kind' => 'instance',
            'software' => [
                'name' => 'Pimcore FHIR Server',
                'version' => '1.0.0'
            ],
            'fhirVersion' => '4.0.1',
            'format' => ['json'],
            'rest' => [[
                'mode' => 'server',
                'resource' => [
                    ['type' => 'Patient'],
                    ['type' => 'Practitioner'],
                    ['type' => 'Observation'],
                    ['type' => 'Organization']
                ]
            ]]
        ]);
    }

    /**
     * @Route("/{resourceType}", name="list", methods={"GET"})
     */
    public function list(string $resourceType, Request $request): JsonResponse
    {
        $className = $this->getClassName($resourceType);
        if (!$className) {
            return $this->createOperationOutcome('not-found', "Resource type '$resourceType' not supported", 404);
        }

        try {
            $list = $className::getList([
                'limit' => min((int) $request->get('_count', 20), 100),
                'offset' => (int) $request->get('_offset', 0)
            ]);

            $bundle = [
                'resourceType' => 'Bundle',
                'type' => 'searchset',
                'total' => $list->getTotalCount(),
                'entry' => []
            ];

            foreach ($list as $object) {
                if (method_exists($object, 'toFhir')) {
                    $bundle['entry'][] = [
                        'fullUrl' => $request->getSchemeAndHttpHost() . '/api/fhir/' . $resourceType . '/' . $object->getId(),
                        'resource' => $object->toFhir()
                    ];
                }
            }

            return $this->json($bundle);
        } catch (\Exception $e) {
            return $this->createOperationOutcome('exception', $e->getMessage(), 500);
        }
    }

    private function getClassName(string $resourceType): ?string
    {
        $map = [
            'Patient' => DataObject\Patient::class,
            'Practitioner' => DataObject\Practitioner::class,
            'Observation' => DataObject\Observation::class,
            'Organization' => DataObject\Organization::class
        ];

        return $map[$resourceType] ?? null;
    }

    private function createOperationOutcome(string $code, string $diagnostics, int $status): JsonResponse
    {
        return $this->json([
            'resourceType' => 'OperationOutcome',
            'issue' => [[
                'severity' => 'error',
                'code' => $code,
                'diagnostics' => $diagnostics
            ]]
        ], $status);
    }
}
EOF

# Création du template de base
cat > templates/fhir/portal/index.html.twig << 'EOF'
{% extends 'layout.html.twig' %}

{% block content %}
<div class="container mt-5">
    <h1>Portail FHIR</h1>
    <p>Bienvenue sur le portail FHIR de votre établissement.</p>
    
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5>Patients</h5>
                    <p class="display-4" id="count-patients">-</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5>Praticiens</h5>
                    <p class="display-4" id="count-practitioners">-</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5>Observations</h5>
                    <p class="display-4" id="count-observations">-</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5>Organisations</h5>
                    <p class="display-4" id="count-organizations">-</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Charger les statistiques
['Patient', 'Practitioner', 'Observation', 'Organization'].forEach(resource => {
    fetch(`/api/fhir/${resource}?_count=0`)
        .then(r => r.json())
        .then(data => {
            const id = `count-${resource.toLowerCase()}s`;
            document.getElementById(id).textContent = data.total || 0;
        })
        .catch(() => {
            console.error(`Erreur lors du chargement de ${resource}`);
        });
});
</script>
{% endblock %}
EOF

# Création de la configuration des routes
cat > config/routes/fhir.yaml << 'EOF'
fhir_api:
    resource: ../../src/Controller/FhirController.php
    type: annotation
    prefix: /api/fhir

fhir_admin:
    resource: ../../src/Controller/Admin/FhirController.php
    type: annotation
    prefix: /admin/fhir

fhir_frontend:
    resource: ../../src/Controller/FhirPortalController.php
    type: annotation
    prefix: /fhir
EOF

# Enregistrement de la commande
echo -e "\n5. Configuration des services..."
if ! grep -q "App\\\\Command\\\\FhirInstallCommand" config/services.yaml; then
    cat >> config/services.yaml << 'EOF'

    App\Command\FhirInstallCommand:
        tags: ['console.command']
EOF
fi

# Création d'un fichier de documentation rapide
cat > FHIR_README.md << 'EOF'
# Installation FHIR pour Pimcore

## Installation effectuée

Les composants suivants ont été installés :

### Classes FHIR
- Patient
- Practitioner
- Observation
- Organization

### Structure des dossiers
- `/src/Command` : Commandes CLI
- `/src/Controller` : Controllers API et Web
- `/src/Model/DataObject` : Extensions des classes
- `/src/Service` : Services métier
- `/templates/fhir` : Templates Twig

### Endpoints API
- `GET /api/fhir/metadata` : Métadonnées du serveur
- `GET /api/fhir/{resourceType}` : Liste des ressources
- `GET /api/fhir/{resourceType}/{id}` : Détail d'une ressource
- `POST /api/fhir/{resourceType}` : Création d'une ressource