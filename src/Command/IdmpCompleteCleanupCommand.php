<?php
// src/Command/IdmpCompleteCleanupCommand.php
namespace App\Command;

use Pimcore\Db;
use Pimcore\Model\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpCompleteCleanupCommand extends Command
{
    protected static $defaultName = 'app:idmp:complete-cleanup';

    protected function configure(): void
    {
        $this->setDescription('Nettoie complètement toutes les données IDMP corrompues');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nettoyage complet IDMP');

        $db = Db::get();

        // 1. D'abord, nettoyer directement dans la base de données
        $this->cleanDatabase($db, $io);

        // 2. Ensuite, supprimer les dossiers
        $this->cleanFolders($db, $io);

        // 3. Reconstruire les classes
        $io->section('Reconstruction des classes');
        exec('php bin/console pimcore:deployment:classes-rebuild 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            $io->success('Classes reconstruites');
        }

        // 4. Nettoyer le cache
        $io->section('Nettoyage du cache');
        exec('rm -rf var/cache/* 2>&1');
        exec('php bin/console cache:clear 2>&1');
        
        $io->success('Nettoyage complet terminé !');
        
        return Command::SUCCESS;
    }

    private function cleanDatabase(Db $db, SymfonyStyle $io): void
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
            try {
                // Obtenir l'ID de la classe si elle existe
                $classId = $db->fetchOne(
                    "SELECT id FROM classes WHERE name = ?",
                    [$className]
                );

                if ($classId) {
                    $io->text("Nettoyage de $className (ID: $classId)");

                    // Supprimer les objets de la table objects
                    $deletedObjects = $db->executeStatement(
                        "DELETE FROM objects WHERE o_classId = ? OR o_className = ?",
                        [$classId, $className]
                    );
                    $io->text("  - $deletedObjects objets supprimés");

                    // Supprimer les tables spécifiques si elles existent
                    $tables = [
                        "object_store_$classId",
                        "object_query_$classId",
                        "object_relations_$classId",
                        "object_metadata_$classId"
                    ];

                    foreach ($tables as $table) {
                        try {
                            $db->executeStatement("DROP TABLE IF EXISTS `$table`");
                            $io->text("  - Table $table supprimée");
                        } catch (\Exception $e) {
                            // Ignorer si la table n'existe pas
                        }
                    }

                    // Supprimer la définition de classe
                    $db->executeStatement("DELETE FROM classes WHERE id = ?", [$classId]);
                    $io->text("  - Définition de classe supprimée");
                }
            } catch (\Exception $e) {
                $io->warning("Erreur lors du nettoyage de $className : " . $e->getMessage());
            }
        }

        // Nettoyer les objets orphelins
        $io->text("\nNettoyage des objets orphelins...");
        
        // Supprimer tous les objets avec une classe qui n'existe plus
        $orphanClasses = $db->executeStatement(
            "DELETE o FROM objects o 
             LEFT JOIN classes c ON o.o_classId = c.id 
             WHERE o.o_type = 'object' AND c.id IS NULL"
        );
        $io->text("- $orphanClasses objets orphelins supprimés");

        // Nettoyer les références dans object_relations générales
        foreach ($classesToClean as $className) {
            $db->executeStatement(
                "DELETE FROM object_relations WHERE 
                 (src_id IN (SELECT o_id FROM objects WHERE o_className = ?)) OR
                 (dest_id IN (SELECT o_id FROM objects WHERE o_className = ?))",
                [$className, $className]
            );
        }
    }

    private function cleanFolders(Db $db, SymfonyStyle $io): void
    {
        $io->section('Suppression des dossiers IDMP');

        // Méthode directe par SQL pour éviter les erreurs de chargement
        $folders = $db->fetchAllAssociative(
            "SELECT o_id, o_key, o_path FROM objects 
             WHERE o_type = 'folder' AND (o_path LIKE '/IDMP%' OR o_key = 'IDMP')
             ORDER BY LENGTH(o_path) DESC"
        );

        foreach ($folders as $folder) {
            try {
                // Supprimer d'abord tous les enfants
                $db->executeStatement(
                    "DELETE FROM objects WHERE o_path LIKE ?",
                    [$folder['o_path'] . '/%']
                );

                // Puis supprimer le dossier
                $db->executeStatement(
                    "DELETE FROM objects WHERE o_id = ?",
                    [$folder['o_id']]
                );

                $io->text("✓ Supprimé : " . $folder['o_path']);
            } catch (\Exception $e) {
                $io->warning("Impossible de supprimer " . $folder['o_path']);
            }
        }
    }
}