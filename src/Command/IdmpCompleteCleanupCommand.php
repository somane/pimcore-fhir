<?php
// src/Command/IdmpCompleteCleanupCommand.php
namespace App\Command;

use Doctrine\DBAL\Connection;
use Pimcore\Model\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpCompleteCleanupCommand extends Command
{
    protected static $defaultName = 'app:idmp:complete-cleanup';
    
    private Connection $db;

    public function __construct(Connection $db)
    {
        parent::__construct();
        $this->db = $db;
    }

    protected function configure(): void
    {
        $this->setDescription('Nettoie complètement toutes les données IDMP corrompues');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nettoyage complet IDMP');

        // 1. Nettoyer la base de données
        $this->cleanDatabase($io);

        // 2. Supprimer les dossiers
        $this->cleanFolders($io);

        // 3. Nettoyer les fichiers de classes
        $this->cleanClassFiles($io);

        // 4. Reconstruire les classes
        $io->section('Reconstruction des classes');
        $this->runCommand('php bin/console pimcore:deployment:classes-rebuild', $io);

        // 5. Nettoyer le cache
        $io->section('Nettoyage du cache');
        $this->runCommand('rm -rf var/cache/*', $io);
        $this->runCommand('php bin/console cache:clear', $io);
        
        $io->success('Nettoyage complet terminé !');
        
        return Command::SUCCESS;
    }

    private function cleanDatabase(SymfonyStyle $io): void
    {
        $io->section('Nettoyage de la base de données');

        // Classes IDMP à nettoyer
        $classesToClean = [
            'MedicinalProduct',
            'Substance',
            'ManufacturedItem',
            'PackagedProduct',
            'RegulatedAuthorization',
            'ClinicalUseDefinition',
            'ClinicalUse',
            'AdministrableProduct',
            'Ingredient',
            'MedicinalProductName',
            'SubstanceName',
            'Identifier',
            'CodeableConcept',
            'Coding',
            'Period',
            'Quantity',
            'Reference',
            'Attachment',
            'ContactPoint',
        ];

        foreach ($classesToClean as $className) {
            $this->cleanClass($className, $io);
        }

        $this->cleanOrphans($io);
    }

    private function cleanClass(string $className, SymfonyStyle $io): void
    {
        try {
            // Obtenir l'ID de la classe
            $classId = $this->db->fetchOne(
                "SELECT id FROM classes WHERE name = ?",
                [$className]
            );

            if ($classId) {
                $io->text("Nettoyage de $className (ID: $classId)");

                // Supprimer les objets
                $deletedObjects = $this->db->executeStatement(
                    "DELETE FROM objects WHERE o_classId = ? OR o_className = ?",
                    [$classId, $className]
                );
                $io->text("  - $deletedObjects objets supprimés");

                // Supprimer les tables
                $this->dropClassTables($classId, $io);

                // Supprimer la définition
                $this->db->executeStatement("DELETE FROM classes WHERE id = ?", [$classId]);
                $io->text("  - Définition supprimée");
            }
        } catch (\Exception $e) {
            $io->warning("Erreur pour $className : " . $e->getMessage());
        }
    }

    private function dropClassTables(int $classId, SymfonyStyle $io): void
    {
        $tables = [
            "object_store_$classId",
            "object_query_$classId",
            "object_relations_$classId",
            "object_metadata_$classId"
        ];

        foreach ($tables as $table) {
            try {
                $this->db->executeStatement("DROP TABLE IF EXISTS `$table`");
                $io->text("  - Table $table supprimée");
            } catch (\Exception $e) {
                // Ignorer
            }
        }
    }

    private function cleanOrphans(SymfonyStyle $io): void
    {
        $io->text("\nNettoyage des objets orphelins...");
        
        try {
            $count = $this->db->executeStatement(
                "DELETE o FROM objects o 
                 LEFT JOIN classes c ON o.o_classId = c.id 
                 WHERE o.o_type = 'object' AND c.id IS NULL"
            );
            $io->text("- $count objets orphelins supprimés");
        } catch (\Exception $e) {
            $io->warning("Erreur : " . $e->getMessage());
        }
    }

    private function cleanFolders(SymfonyStyle $io): void
    {
        $io->section('Suppression des dossiers IDMP');

        try {
            $folders = $this->db->fetchAllAssociative(
                "SELECT o_id, o_key, o_path FROM objects 
                 WHERE o_type = 'folder' AND o_path LIKE '%IDMP%'
                 ORDER BY LENGTH(o_path) DESC"
            );

            foreach ($folders as $folder) {
                $this->deleteFolder($folder, $io);
            }
        } catch (\Exception $e) {
            $io->warning("Erreur : " . $e->getMessage());
        }
    }

    private function deleteFolder(array $folder, SymfonyStyle $io): void
    {
        try {
            // Supprimer les enfants
            $this->db->executeStatement(
                "DELETE FROM objects WHERE o_path LIKE ?",
                [$folder['o_path'] . '%']
            );

            $io->text("✓ Supprimé : " . $folder['o_path']);
        } catch (\Exception $e) {
            $io->warning("Erreur : " . $e->getMessage());
        }
    }

    private function cleanClassFiles(SymfonyStyle $io): void
    {
        $io->section('Nettoyage des fichiers de classes');
        
        $directories = [
            'var/classes/DataObject',
            'var/classes/definition'
        ];

        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $this->runCommand("rm -rf $dir/*", $io);
                $io->text("✓ Nettoyé : $dir");
            }
        }
    }

    private function runCommand(string $command, SymfonyStyle $io): void
    {
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $io->text("✓ Exécuté : $command");
        } else {
            $io->warning("Erreur commande : $command");
            if (!empty($output)) {
                $io->text($output);
            }
        }
    }
}