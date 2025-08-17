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

        // 1. Créer les dossiers nécessaires
        $this->createFolders($io);

        // 2. Migration directe dans la base de données
        $this->migrateDatabase($io);

        // 3. Rebuild des classes
        $io->section('Reconstruction des classes');
        exec('php bin/console pimcore:deployment:classes-rebuild', $output, $returnCode);
        
        if ($returnCode === 0) {
            $io->success('Classes reconstruites');
        } else {
            $io->warning('Erreur lors de la reconstruction des classes');
        }

        // 4. Clear cache
        exec('php bin/console cache:clear', $output, $returnCode);

        $io->success('Migration d\'urgence terminée !');
        return Command::SUCCESS;
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

    private function migrateDatabase(SymfonyStyle $io): void
    {
        $db = Db::get();
        
        $io->section('Migration des MedicinalProduct');

        // Récupérer tous les MedicinalProduct avec un name de type string
        $query = "SELECT o_id, name FROM object_store_6 WHERE name IS NOT NULL AND name != ''";
        $products = $db->fetchAllAssociative($query);

        $io->progressStart(count($products));

        foreach ($products as $product) {
            try {
                $productId = $product['o_id'];
                $oldName = $product['name'];

                // Créer un MedicinalProductName
                $productName = new DataObject\MedicinalProductName();
                $productName->setKey('mpn-' . $productId . '-' . time());
                $productName->setParent(DataObject::getByPath('/IDMP/MedicinalProductNames'));
                $productName->setProductName($oldName);
                $productName->setPublished(true);
                $productName->save();

                // Mettre à jour la relation
                $updateQuery = "UPDATE object_store_6 SET name = :name WHERE o_id = :id";
                $db->executeStatement($updateQuery, [
                    'name' => ',' . $productName->getId() . ',',
                    'id' => $productId
                ]);

                $io->progressAdvance();

            } catch (\Exception $e) {
                $io->text("\nErreur pour le produit {$productId}: " . $e->getMessage());
            }
        }

        $io->progressFinish();

        // Faire la même chose pour les autres champs si nécessaire
        $this->migrateOtherFields($db, $io);
    }

    private function migrateOtherFields($db, SymfonyStyle $io): void
    {
        // Mapping des champs à migrer
        $fieldsToMigrate = [
            'productType' => 'medicinalProductType',
            'identifier' => 'identifier', // Celui-ci est déjà un array
        ];

        foreach ($fieldsToMigrate as $oldField => $newField) {
            $io->text("Migration du champ $oldField vers $newField...");
            
            // Vérifier si les colonnes existent
            $columns = $db->fetchAllAssociative("SHOW COLUMNS FROM object_store_6");
            $columnNames = array_column($columns, 'Field');
            
            if (in_array($oldField, $columnNames) && !in_array($newField, $columnNames)) {
                // Renommer la colonne
                try {
                    $db->executeStatement("ALTER TABLE object_store_6 CHANGE `$oldField` `$newField` TEXT");
                    $io->text("✓ Colonne $oldField renommée en $newField");
                } catch (\Exception $e) {
                    $io->warning("Impossible de renommer $oldField: " . $e->getMessage());
                }
            }
        }
    }
}