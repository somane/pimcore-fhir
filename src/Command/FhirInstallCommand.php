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
