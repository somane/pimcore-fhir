<?php
// src/Command/IdmpInstallCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Folder;

class IdmpInstallCommand extends Command
{
    protected static $defaultName = 'app:idmp:install';

    protected function configure(): void
    {
        $this->setDescription('Installe les classes IDMP dans Pimcore pour la gestion des médicaments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installation IDMP pour Pimcore');

        // Créer les dossiers racine
        $this->createRootFolders($io);
        
        // Créer les classes IDMP
        $this->createIdmpClasses($io);
        
        $io->success('Installation IDMP terminée avec succès !');
        return Command::SUCCESS;
    }

    private function createRootFolders(SymfonyStyle $io): void
    {
        $folders = [
            'IDMP' => 1,
            'IDMP/MedicinalProducts' => null,
            'IDMP/Substances' => null,
            'IDMP/ManufacturedItems' => null,
            'IDMP/PackagedProducts' => null,
            'IDMP/RegulatedAuthorizations' => null,
            'IDMP/ClinicalUseDefinitions' => null
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

    private function createIdmpClasses(SymfonyStyle $io): void
    {
        $classes = [
            'MedicinalProduct' => $this->getMedicinalProductConfig(),
            'Substance' => $this->getSubstanceConfig(),
            'ManufacturedItem' => $this->getManufacturedItemConfig(),
            'PackagedProduct' => $this->getPackagedProductConfig(),
            'RegulatedAuthorization' => $this->getRegulatedAuthorizationConfig(),
            'ClinicalUseDefinition' => $this->getClinicalUseDefinitionConfig()
        ];

        foreach ($classes as $name => $config) {
            try {
                $existingClass = ClassDefinition::getByName($name);
                if ($existingClass) {
                    $io->warning("La classe $name existe déjà");
                    continue;
                }

                $class = new ClassDefinition();
                $class->setName($name);
                $class->setGroup('IDMP');
                
                $layout = $this->buildLayout($config['layouts'][0]);
                $class->setLayoutDefinitions($layout);
                
                $class->save();
                
                $io->success("Classe $name créée");
            } catch (\Exception $e) {
                $io->error("Erreur lors de la création de $name : " . $e->getMessage());
            }
        }
    }

    private function buildLayout(array $config)
    {
        $type = $config['fieldtype'];
        
        if ($type === 'panel') {
            $layout = new ClassDefinition\Layout\Panel();
            $layout->setName($config['name'] ?? 'panel');
            
            if (isset($config['title'])) {
                $layout->setTitle($config['title']);
            }
            
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
        
        return $this->createDataField($config);
    }

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
                
            case 'multiselect':
                $field = new Data\Multiselect();
                if (isset($config['options'])) {
                    $field->setOptions($config['options']);
                }
                break;
                
            case 'numeric':
                $field = new Data\Numeric();
                if (isset($config['integer'])) {
                    $field->setInteger($config['integer']);
                }
                if (isset($config['decimalPrecision'])) {
                    $field->setDecimalPrecision($config['decimalPrecision']);
                }
                break;
                
            case 'manyToOneRelation':
                $field = new Data\ManyToOneRelation();
                if (isset($config['classes'])) {
                    $field->setClasses($config['classes']);
                }
                break;
                
            case 'manyToManyRelation':
                $field = new Data\ManyToManyRelation();
                if (isset($config['classes'])) {
                    $field->setClasses($config['classes']);
                }
                break;
                
            case 'objectbricks':
                $field = new Data\Objectbricks();
                if (isset($config['allowedTypes'])) {
                    $field->setAllowedTypes($config['allowedTypes']);
                }
                break;
        }
        
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
            
            if (isset($config['unique'])) {
                $field->setUnique($config['unique']);
            }
        }
        
        return $field;
    }

    /**
     * Configuration pour MedicinalProduct (Produit médicinal)
     */
    private function getMedicinalProductConfig(): array
    {
        return [
            "name" => "MedicinalProduct",
            "group" => "IDMP",
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "BasicInfo",
                            "title" => "Informations de base",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "mpid",
                                    "title" => "MPID (Medicinal Product Identifier)",
                                    "tooltip" => "Identifiant unique du médicament",
                                    "mandatory" => true,
                                    "unique" => true,
                                    "columnLength" => 190
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "name",
                                    "title" => "Nom commercial",
                                    "mandatory" => true,
                                    "columnLength" => 255
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "nonproprietaryName",
                                    "title" => "DCI (Dénomination Commune Internationale)",
                                    "columnLength" => 255
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "productType",
                                    "title" => "Type de produit",
                                    "options" => [
                                        ["key" => "Médicament chimique", "value" => "chemical"],
                                        ["key" => "Médicament biologique", "value" => "biological"],
                                        ["key" => "Vaccin", "value" => "vaccine"],
                                        ["key" => "Produit sanguin", "value" => "blood"],
                                        ["key" => "Radiopharmaceutique", "value" => "radiopharmaceutical"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "domain",
                                    "title" => "Domaine d'utilisation",
                                    "options" => [
                                        ["key" => "Usage humain", "value" => "human"],
                                        ["key" => "Usage vétérinaire", "value" => "veterinary"],
                                        ["key" => "Usage humain et vétérinaire", "value" => "both"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "description",
                                    "title" => "Description",
                                    "height" => 100
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Classification",
                            "title" => "Classification",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "atcCode",
                                    "title" => "Code ATC",
                                    "tooltip" => "Classification anatomique, thérapeutique et chimique",
                                    "columnLength" => 10
                                ],
                                [
                                    "fieldtype" => "multiselect",
                                    "name" => "therapeuticIndication",
                                    "title" => "Indications thérapeutiques",
                                    "options" => [
                                        ["key" => "Hypertension", "value" => "hypertension"],
                                        ["key" => "Diabète", "value" => "diabetes"],
                                        ["key" => "Infection", "value" => "infection"],
                                        ["key" => "Douleur", "value" => "pain"],
                                        ["key" => "Inflammation", "value" => "inflammation"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "legalStatusOfSupply",
                                    "title" => "Statut légal de délivrance",
                                    "options" => [
                                        ["key" => "Sur ordonnance", "value" => "prescription"],
                                        ["key" => "Sans ordonnance", "value" => "otc"],
                                        ["key" => "Usage hospitalier", "value" => "hospital"],
                                        ["key" => "Stupéfiant", "value" => "narcotic"]
                                    ]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Composition",
                            "title" => "Composition",
                            "children" => [
                                [
                                    "fieldtype" => "manyToManyRelation",
                                    "name" => "ingredient",
                                    "title" => "Substances actives",
                                    "classes" => ["Substance"]
                                ],
                                [
                                    "fieldtype" => "manyToOneRelation",
                                    "name" => "manufacturedItem",
                                    "title" => "Forme pharmaceutique",
                                    "classes" => ["ManufacturedItem"]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "MarketingInfo",
                            "title" => "Informations commerciales",
                            "children" => [
                                [
                                    "fieldtype" => "manyToOneRelation",
                                    "name" => "marketingAuthorizationHolder",
                                    "title" => "Titulaire de l'AMM",
                                    "classes" => ["Organization"]
                                ],
                                [
                                    "fieldtype" => "manyToManyRelation",
                                    "name" => "manufacturer",
                                    "title" => "Fabricants",
                                    "classes" => ["Organization"]
                                ],
                                [
                                    "fieldtype" => "manyToManyRelation",
                                    "name" => "packagedProduct",
                                    "title" => "Conditionnements",
                                    "classes" => ["PackagedProduct"]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Configuration pour Substance (Substance active)
     */
    private function getSubstanceConfig(): array
    {
        return [
            "name" => "Substance",
            "group" => "IDMP",
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
                                    "title" => "Identifiant substance",
                                    "mandatory" => true,
                                    "unique" => true,
                                    "columnLength" => 190
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "substanceName",
                                    "title" => "Nom de la substance",
                                    "mandatory" => true,
                                    "columnLength" => 255
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "inn",
                                    "title" => "DCI (International Nonproprietary Name)",
                                    "columnLength" => 255
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "casNumber",
                                    "title" => "Numéro CAS",
                                    "columnLength" => 50
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "substanceType",
                                    "title" => "Type de substance",
                                    "options" => [
                                        ["key" => "Chimique", "value" => "chemical"],
                                        ["key" => "Protéine", "value" => "protein"],
                                        ["key" => "Acide nucléique", "value" => "nucleicAcid"],
                                        ["key" => "Polymère", "value" => "polymer"],
                                        ["key" => "Mélange", "value" => "mixture"]
                                    ]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "ChemicalProperties",
                            "title" => "Propriétés chimiques",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "molecularFormula",
                                    "title" => "Formule moléculaire",
                                    "columnLength" => 255
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "molecularWeight",
                                    "title" => "Poids moléculaire",
                                    "decimalPrecision" => 2
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "structure",
                                    "title" => "Structure chimique",
                                    "tooltip" => "SMILES ou InChI",
                                    "height" => 100
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Strength",
                            "title" => "Concentration/Dosage",
                            "children" => [
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "strengthValue",
                                    "title" => "Valeur",
                                    "decimalPrecision" => 3
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "strengthUnit",
                                    "title" => "Unité",
                                    "options" => [
                                        ["key" => "mg", "value" => "mg"],
                                        ["key" => "g", "value" => "g"],
                                        ["key" => "mcg", "value" => "mcg"],
                                        ["key" => "UI", "value" => "IU"],
                                        ["key" => "mg/ml", "value" => "mg/ml"],
                                        ["key" => "%", "value" => "percent"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "role",
                                    "title" => "Rôle dans la formulation",
                                    "options" => [
                                        ["key" => "Principe actif", "value" => "active"],
                                        ["key" => "Excipient", "value" => "excipient"],
                                        ["key" => "Adjuvant", "value" => "adjuvant"]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Configuration pour ManufacturedItem (Forme pharmaceutique)
     */
    private function getManufacturedItemConfig(): array
    {
        return [
            "name" => "ManufacturedItem",
            "group" => "IDMP",
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "GeneralInfo",
                            "title" => "Informations générales",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "identifier",
                                    "title" => "Identifiant",
                                    "mandatory" => true,
                                    "unique" => true
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "doseForm",
                                    "title" => "Forme galénique",
                                    "mandatory" => true,
                                    "options" => [
                                        ["key" => "Comprimé", "value" => "tablet"],
                                        ["key" => "Gélule", "value" => "capsule"],
                                        ["key" => "Solution injectable", "value" => "injection"],
                                        ["key" => "Solution buvable", "value" => "oral_solution"],
                                        ["key" => "Crème", "value" => "cream"],
                                        ["key" => "Pommade", "value" => "ointment"],
                                        ["key" => "Suppositoire", "value" => "suppository"],
                                        ["key" => "Patch", "value" => "patch"],
                                        ["key" => "Sirop", "value" => "syrup"],
                                        ["key" => "Suspension", "value" => "suspension"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "routeOfAdministration",
                                    "title" => "Voie d'administration",
                                    "mandatory" => true,
                                    "options" => [
                                        ["key" => "Orale", "value" => "oral"],
                                        ["key" => "Intraveineuse", "value" => "intravenous"],
                                        ["key" => "Intramusculaire", "value" => "intramuscular"],
                                        ["key" => "Sous-cutanée", "value" => "subcutaneous"],
                                        ["key" => "Topique", "value" => "topical"],
                                        ["key" => "Rectale", "value" => "rectal"],
                                        ["key" => "Vaginale", "value" => "vaginal"],
                                        ["key" => "Ophtalmique", "value" => "ophthalmic"],
                                        ["key" => "Nasale", "value" => "nasal"],
                                        ["key" => "Inhalation", "value" => "inhalation"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "color",
                                    "title" => "Couleur"
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "shape",
                                    "title" => "Forme"
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "marking",
                                    "title" => "Marquage/Gravure"
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Dimensions",
                            "title" => "Dimensions",
                            "children" => [
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "width",
                                    "title" => "Largeur (mm)",
                                    "decimalPrecision" => 2
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "height",
                                    "title" => "Hauteur (mm)",
                                    "decimalPrecision" => 2
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "depth",
                                    "title" => "Profondeur (mm)",
                                    "decimalPrecision" => 2
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "weight",
                                    "title" => "Poids (mg)",
                                    "decimalPrecision" => 2
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Configuration pour PackagedProduct (Conditionnement)
     */
    private function getPackagedProductConfig(): array
    {
        return [
            "name" => "PackagedProduct",
            "group" => "IDMP",
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "PackageInfo",
                            "title" => "Informations du conditionnement",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "identifier",
                                    "title" => "Code CIP/ACL",
                                    "mandatory" => true,
                                    "unique" => true,
                                    "columnLength" => 50
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "description",
                                    "title" => "Description du conditionnement",
                                    "mandatory" => true,
                                    "columnLength" => 255
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "packageType",
                                    "title" => "Type de conditionnement",
                                    "options" => [
                                        ["key" => "Boîte", "value" => "box"],
                                        ["key" => "Flacon", "value" => "bottle"],
                                        ["key" => "Blister", "value" => "blister"],
                                        ["key" => "Tube", "value" => "tube"],
                                        ["key" => "Seringue", "value" => "syringe"],
                                        ["key" => "Ampoule", "value" => "ampoule"],
                                        ["key" => "Sachet", "value" => "sachet"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "quantity",
                                    "title" => "Quantité par conditionnement",
                                    "mandatory" => true,
                                    "integer" => true
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "gtin",
                                    "title" => "Code GTIN/EAN",
                                    "columnLength" => 14
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "batchNumber",
                                    "title" => "Numéro de lot"
                                ],
                                [
                                    "fieldtype" => "date",
                                    "name" => "expiryDate",
                                    "title" => "Date d'expiration"
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Marketing",
                            "title" => "Informations commerciales",
                            "children" => [
                                [
                                    "fieldtype" => "select",
                                    "name" => "marketingStatus",
                                    "title" => "Statut de commercialisation",
                                    "options" => [
                                        ["key" => "Commercialisé", "value" => "marketed"],
                                        ["key" => "Non commercialisé", "value" => "not_marketed"],
                                        ["key" => "Arrêt de commercialisation", "value" => "discontinued"],
                                        ["key" => "En cours d'autorisation", "value" => "pending"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "date",
                                    "name" => "marketingStartDate",
                                    "title" => "Date de commercialisation"
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "price",
                                    "title" => "Prix public (€)",
                                    "decimalPrecision" => 2
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "reimbursementRate",
                                    "title" => "Taux de remboursement (%)",
                                    "integer" => true
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Configuration pour RegulatedAuthorization (AMM)
     */
    private function getRegulatedAuthorizationConfig(): array
    {
        return [
            "name" => "RegulatedAuthorization",
            "group" => "IDMP",
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "AuthorizationInfo",
                            "title" => "Informations d'autorisation",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "identifier",
                                    "title" => "Numéro d'AMM",
                                    "mandatory" => true,
                                    "unique" => true,
                                    "columnLength" => 100
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "authorizationType",
                                    "title" => "Type d'autorisation",
                                    "options" => [
                                        ["key" => "AMM nationale", "value" => "national"],
                                        ["key" => "AMM européenne", "value" => "european"],
                                        ["key" => "AMM reconnaissance mutuelle", "value" => "mutual_recognition"],
                                        ["key" => "AMM décentralisée", "value" => "decentralized"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "status",
                                    "title" => "Statut",
                                    "options" => [
                                        ["key" => "Active", "value" => "active"],
                                        ["key" => "Suspendue", "value" => "suspended"],
                                        ["key" => "Retirée", "value" => "withdrawn"],
                                        ["key" => "En cours", "value" => "pending"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "date",
                                    "name" => "issueDate",
                                    "title" => "Date de délivrance",
                                    "mandatory" => true
                                ],
                                [
                                    "fieldtype" => "date",
                                    "name" => "expiryDate",
                                    "title" => "Date d'expiration"
                                ],
                                [
                                    "fieldtype" => "manyToOneRelation",
                                    "name" => "regulator",
                                    "title" => "Autorité régulatrice",
                                    "classes" => ["Organization"]
                                ],
                                [
                                    "fieldtype" => "manyToOneRelation",
                                    "name" => "holder",
                                    "title" => "Titulaire",
                                    "classes" => ["Organization"]
                                ],
                                [
                                    "fieldtype" => "manyToOneRelation",
                                    "name" => "subject",
                                    "title" => "Produit concerné",
                                    "classes" => ["MedicinalProduct"]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Conditions",
                            "title" => "Conditions",
                            "children" => [
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "indication",
                                    "title" => "Indications approuvées",
                                    "height" => 150
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "contraindication",
                                    "title" => "Contre-indications",
                                    "height" => 150
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "specialConditions",
                                    "title" => "Conditions spéciales",
                                    "height" => 100
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Configuration pour ClinicalUseDefinition (Indications et contre-indications)
     */
    private function getClinicalUseDefinitionConfig(): array
    {
        return [
            "name" => "ClinicalUseDefinition",
            "group" => "IDMP",
            "layouts" => [
                [
                    "fieldtype" => "panel",
                    "name" => "Layout",
                    "children" => [
                        [
                            "fieldtype" => "panel",
                            "name" => "GeneralInfo",
                            "title" => "Informations générales",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "identifier",
                                    "title" => "Identifiant",
                                    "mandatory" => true,
                                    "unique" => true
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "useType",
                                    "title" => "Type d'usage clinique",
                                    "mandatory" => true,
                                    "options" => [
                                        ["key" => "Indication", "value" => "indication"],
                                        ["key" => "Contre-indication", "value" => "contraindication"],
                                        ["key" => "Interaction", "value" => "interaction"],
                                        ["key" => "Effet indésirable", "value" => "undesirable_effect"],
                                        ["key" => "Précaution", "value" => "warning"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "manyToManyRelation",
                                    "name" => "subject",
                                    "title" => "Produits concernés",
                                    "classes" => ["MedicinalProduct"]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "ClinicalDetails",
                            "title" => "Détails cliniques",
                            "children" => [
                                [
                                    "fieldtype" => "input",
                                    "name" => "diseaseCode",
                                    "title" => "Code CIM-10",
                                    "columnLength" => 10
                                ],
                                [
                                    "fieldtype" => "input",
                                    "name" => "diseaseDisplay",
                                    "title" => "Nom de la maladie/condition",
                                    "mandatory" => true,
                                    "columnLength" => 255
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "description",
                                    "title" => "Description détaillée",
                                    "height" => 200
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "severity",
                                    "title" => "Sévérité",
                                    "options" => [
                                        ["key" => "Légère", "value" => "mild"],
                                        ["key" => "Modérée", "value" => "moderate"],
                                        ["key" => "Sévère", "value" => "severe"],
                                        ["key" => "Critique", "value" => "critical"]
                                    ]
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Population",
                            "title" => "Population concernée",
                            "children" => [
                                [
                                    "fieldtype" => "multiselect",
                                    "name" => "population",
                                    "title" => "Populations spécifiques",
                                    "options" => [
                                        ["key" => "Adultes", "value" => "adults"],
                                        ["key" => "Enfants", "value" => "pediatric"],
                                        ["key" => "Personnes âgées", "value" => "geriatric"],
                                        ["key" => "Femmes enceintes", "value" => "pregnant"],
                                        ["key" => "Femmes allaitantes", "value" => "lactating"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "ageRangeMin",
                                    "title" => "Âge minimum (années)",
                                    "integer" => true
                                ],
                                [
                                    "fieldtype" => "numeric",
                                    "name" => "ageRangeMax",
                                    "title" => "Âge maximum (années)",
                                    "integer" => true
                                ]
                            ]
                        ],
                        [
                            "fieldtype" => "panel",
                            "name" => "Interactions",
                            "title" => "Interactions (si applicable)",
                            "children" => [
                                [
                                    "fieldtype" => "manyToManyRelation",
                                    "name" => "interactingSubstance",
                                    "title" => "Substances en interaction",
                                    "classes" => ["Substance"]
                                ],
                                [
                                    "fieldtype" => "select",
                                    "name" => "interactionType",
                                    "title" => "Type d'interaction",
                                    "options" => [
                                        ["key" => "Pharmacocinétique", "value" => "pharmacokinetic"],
                                        ["key" => "Pharmacodynamique", "value" => "pharmacodynamic"],
                                        ["key" => "Inconnu", "value" => "unknown"]
                                    ]
                                ],
                                [
                                    "fieldtype" => "textarea",
                                    "name" => "management",
                                    "title" => "Conduite à tenir",
                                    "height" => 100
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}