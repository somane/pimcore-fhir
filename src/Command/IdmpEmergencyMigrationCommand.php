<?php
// src/Command/IdmpEmergencyMigrationCommand.php
namespace App\Command;

use Pimcore\Db;
use Pimcore\Model\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpEmergencyMigrationCommand extends Command
{
    protected static $defaultName = 'app:idmp:emergency-migration';

    protected function configure(): void
    {
        $this->setDescription('Migration d\'urgence des données IDMP pour corriger les erreurs de type');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration d\'urgence IDMP');

        // 1. Identifier la structure des tables
        $this->analyzeTableStructure($io);

        // 2. Créer les dossiers nécessaires
        $this->createFolders($io);

        // 3. Migration des données
        $this->migrateData($io);

        $io->success('Migration d\'urgence terminée !');
        return Command::SUCCESS;
    }

    private function analyzeTableStructure(SymfonyStyle $io): void
    {
        $io->section('Analyse de la structure des tables');
        
        $db = Db::get();
        
        // Trouver l'ID de la classe MedicinalProduct
        $classId = $db->fetchOne(
            "SELECT id FROM classes WHERE name = ?",
            ['MedicinalProduct']
        );
        
        if (!$classId) {
            throw new \Exception('Classe MedicinalProduct non trouvée');
        }
        
        $io->text("ID de la classe MedicinalProduct : $classId");
        
        // Vérifier les tables
        $tables = [
            "object_store_$classId",
            "object_query_$classId",
            "object_relations_$classId"
        ];
        
        foreach ($tables as $table) {
            $exists = $db->fetchOne(
                "SELECT COUNT(*) FROM information_schema.tables 
                 WHERE table_schema = DATABASE() AND table_name = ?",
                [$table]
            );
            
            if ($exists) {
                $io->text("✓ Table $table existe");
                
                // Afficher la structure
                $columns = $db->fetchAllAssociative("SHOW COLUMNS FROM `$table`");
                $io->text("  Colonnes : " . implode(', ', array_column($columns, 'Field')));
            } else {
                $io->warning("✗ Table $table n'existe pas");
            }
        }
    }

    private function createFolders(SymfonyStyle $io): void
    {
        $folders = [
            '/IDMP/MedicinalProductNames',
            '/IDMP/Identifiers',
            '/IDMP/CodeableConcepts',
            '/IDMP/Codings',
        ];

        foreach ($folders as $path) {
            $folder = DataObject::getByPath($path);
            if (!$folder) {
                $parts = explode('/', trim($path, '/'));
                $parent = DataObject::getByPath('/' . $parts[0]);
                
                if ($parent) {
                    $folder = new DataObject\Folder();
                    $folder->setKey($parts[1]);
                    $folder->setParent($parent);
                    $folder->save();
                    $io->text("Dossier créé : $path");
                }
            }
        }
    }

    private function migrateData(SymfonyStyle $io): void
    {
        $db = Db::get();
        
        // Obtenir l'ID de la classe
        $classId = $db->fetchOne(
            "SELECT id FROM classes WHERE name = ?",
            ['MedicinalProduct']
        );
        
        if (!$classId) {
            $io->error('Classe MedicinalProduct non trouvée');
            return;
        }

        $io->section('Migration des MedicinalProduct');

        // Méthode 1 : Utiliser l'API Pimcore (plus sûr)
        $this->migrateUsingApi($io);
        
        // Méthode 2 : Si nécessaire, migration directe DB
        // $this->migrateUsingDatabase($io, $classId);
    }

    private function migrateUsingApi(SymfonyStyle $io): void
    {
        // Désactiver temporairement les events pour éviter les boucles
        \Pimcore::setEventDispatcherEnabled(false);
        
        $listing = new DataObject\MedicinalProduct\Listing();
        $listing->setUnpublished(true);
        $listing->setObjectTypes(['object', 'variant']);
        
        $total = $listing->getTotalCount();
        $io->text("Nombre de MedicinalProduct à migrer : $total");
        
        if ($total === 0) {
            $io->warning('Aucun MedicinalProduct trouvé');
            return;
        }

        $io->progressStart($total);

        foreach ($listing as $product) {
            try {
                $needsSave = false;

                // Récupérer les données brutes
                $data = [];
                
                // Vérifier le champ name
                try {
                    $currentName = $product->getName();
                    if (is_string($currentName) && !empty($currentName)) {
                        // Créer un MedicinalProductName
                        $productName = $this->createMedicinalProductName($currentName);
                        
                        // Mettre à jour directement dans la DB pour éviter la validation
                        $this->updateFieldDirectly($product->getId(), 'name', [$productName->getId()]);
                        
                        $data['name'] = $currentName;
                        $needsSave = true;
                    }
                } catch (\TypeError $e) {
                    // Récupérer via réflexion si erreur de type
                    $reflection = new \ReflectionObject($product);
                    if ($reflection->hasProperty('name')) {
                        $prop = $reflection->getProperty('name');
                        $prop->setAccessible(true);
                        $value = $prop->getValue($product);
                        
                        if (is_string($value) && !empty($value)) {
                            $productName = $this->createMedicinalProductName($value);
                            $this->updateFieldDirectly($product->getId(), 'name', [$productName->getId()]);
                            $data['name'] = $value;
                            $needsSave = true;
                        }
                    }
                }

                // Autres champs à migrer
                $fieldsToCheck = ['productType', 'domain', 'legalStatusOfSupply'];
                
                foreach ($fieldsToCheck as $field) {
                    try {
                        $getter = 'get' . ucfirst($field);
                        if (method_exists($product, $getter)) {
                            $value = $product->$getter();
                            if (is_string($value) && !empty($value)) {
                                $data[$field] = $value;
                                $needsSave = true;
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignorer les erreurs
                    }
                }

                if ($needsSave && !empty($data)) {
                    $io->text("\nMigration de : " . $product->getKey() . " - Données : " . json_encode($data));
                }

                $io->progressAdvance();

            } catch (\Exception $e) {
                $io->text("\nErreur pour le produit {$product->getId()}: " . $e->getMessage());
            }
        }

        $io->progressFinish();
        
        // Réactiver les events
        \Pimcore::setEventDispatcherEnabled(true);
    }

    private function createMedicinalProductName(string $name): DataObject\MedicinalProductName
    {
        $productName = new DataObject\MedicinalProductName();
        $productName->setKey('mpn-' . md5($name . uniqid()));
        $productName->setParent(DataObject::getByPath('/IDMP/MedicinalProductNames'));
        $productName->setProductName($name);
        $productName->setPublished(true);
        $productName->save();
        
        return $productName;
    }

    private function updateFieldDirectly(int $objectId, string $field, array $ids): void
    {
        $db = Db::get();
        
        // Trouver la table
        $classId = $db->fetchOne(
            "SELECT o_classId FROM objects WHERE o_id = ?",
            [$objectId]
        );
        
        if (!$classId) {
            return;
        }

        // Mettre à jour dans object_relations
        $tableName = "object_relations_$classId";
        
        // Supprimer les anciennes relations
        $db->executeStatement(
            "DELETE FROM `$tableName` WHERE src_id = ? AND fieldname = ?",
            [$objectId, $field]
        );
        
        // Ajouter les nouvelles relations
        foreach ($ids as $position => $id) {
            $db->insert($tableName, [
                'src_id' => $objectId,
                'dest_id' => $id,
                'type' => 'object',
                'fieldname' => $field,
                'index' => $position,
                'ownertype' => 'object',
                'ownername' => '',
                'position' => $position
            ]);
        }
    }

    private function migrateUsingDatabase(SymfonyStyle $io, int $classId): void
    {
        $db = Db::get();
        
        $tableName = "object_store_$classId";
        
        // Vérifier si la table existe et a des données
        try {
            $count = $db->fetchOne("SELECT COUNT(*) FROM `$tableName`");
            if ($count == 0) {
                $io->warning("Aucune donnée dans $tableName");
                return;
            }
        } catch (\Exception $e) {
            $io->error("Erreur accès table $tableName : " . $e->getMessage());
            return;
        }

        // Récupérer tous les enregistrements avec un name non vide
        $query = "SELECT oo_id, name FROM `$tableName` WHERE name IS NOT NULL AND name != ''";
        
        try {
            $products = $db->fetchAllAssociative($query);
        } catch (\Exception $e) {
            $io->error("Erreur requête : " . $e->getMessage());
            
            // Essayer de voir quelles colonnes existent
            $columns = $db->fetchAllAssociative("SHOW COLUMNS FROM `$tableName`");
            $io->text("Colonnes disponibles : " . implode(', ', array_column($columns, 'Field')));
            return;
        }

        $io->progressStart(count($products));

        foreach ($products as $product) {
            try {
                $productId = $product['oo_id'];
                $oldName = $product['name'];

                if (is_string($oldName) && !empty($oldName)) {
                    // Créer un MedicinalProductName
                    $productName = $this->createMedicinalProductName($oldName);

                    // Mettre à jour les relations
                    $this->updateFieldDirectly($productId, 'name', [$productName->getId()]);
                }

                $io->progressAdvance();

            } catch (\Exception $e) {
                $io->text("\nErreur pour le produit {$productId}: " . $e->getMessage());
            }
        }

        $io->progressFinish();
    }
}