<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
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
                // Vérifier si la classe existe déjà
                $existingClass = ClassDefinition::getByName($name);
                if ($existingClass) {
                    $io->warning("La classe $name existe déjà");
                    continue;
                }

                // Créer la nouvelle classe
                $class = new ClassDefinition();
                $class->setName($name);
                $class->setGroup('FHIR');
                
                // Construire la structure de layout
                $layout = $this->buildLayout($config['layouts'][0]);
                $class->setLayoutDefinitions($layout);
                
                // Sauvegarder la classe
                $class->save();
                
                $io->success("Classe $name créée");
            } catch (\Exception $e) {
                $io->error("Erreur lors de la création de $name : " . $e->getMessage());
            }
        }
    }

    /**
     * Construit récursivement le layout à partir de la configuration
     */
    private function buildLayout(array $config)
    {
        $type = $config['fieldtype'];
        
        // Pour les panels et autres containers
        if ($type === 'panel') {
            $layout = new ClassDefinition\Layout\Panel();
            $layout->setName($config['name'] ?? 'panel');
            
            if (isset($config['title'])) {
                $layout->setTitle($config['title']);
            }
            
            // Ajouter les enfants récursivement
            if (isset($config['children']) && is_array($config['children'])) {
                foreach ($config['children'] as $childConfig) {
                    $child = $this->buildLayout($childConfig);
                    if ($child) {
                        $layout->addChild($child);
                    }
                }
            }
            
            return $layout;
        }
        
        // Pour les champs de données
        return $this->createDataField($config);
    }

    /**
     * Crée un champ de données selon son type
     */
    private function createDataField(array $config)
    {
        $type = $config['fieldtype'];
        $field = null;
        
        switch ($type) {
            case 'input':
                $field = new Data\Input();
                if (isset($config['columnLength'])) {
                    $field->setColumnLength($config['columnLength']);
                }
                break;
                
            case 'email':
                $field = new Data\Email();
                break;
                
            case 'textarea':
                $field = new Data\Textarea();
                if (isset($config['height'])) {
                    $field->setHeight($config['height']);
                }
                break;
                
            case 'date':
                $field = new Data\Date();
                break;
                
            case 'datetime':
                $field = new Data\Datetime();
                break;
                
            case 'select':
                $field = new Data\Select();
                if (isset($config['options'])) {
                    $field->setOptions($config['options']);
                }
                break;
                
            case 'numeric':
                $field = new Data\Numeric();
                break;
                
            case 'manyToOneRelation':
                $field = new Data\ManyToOneRelation();
                if (isset($config['classes'])) {
                    $field->setClasses($config['classes']);
                }
                break;
                
            default:
                return null;
        }
        
        // Propriétés communes
        if ($field) {
            $field->setName($config['name']);
            
            if (isset($config['title'])) {
                $field->setTitle($config['title']);
            }
            
            if (isset($config['mandatory'])) {
                $field->setMandatory($config['mandatory']);
            }
            
            if (isset($config['tooltip'])) {
                $field->setTooltip($config['tooltip']);
            }
            
            if (isset($config['noteditable'])) {
                $field->setNoteditable($config['noteditable']);
            }
            
            if (isset($config['index'])) {
                $field->setIndex($config['index']);
            }
            
            if (isset($config['unique'])) {
                $field->setUnique($config['unique']);
            }
        }
        
        return $field;
    }

    private function getPatientConfig(): array
    {
        return [
            "name" => "Patient",
            "group" => "FHIR",
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