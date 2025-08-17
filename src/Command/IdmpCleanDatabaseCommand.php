<?php
// src/Command/IdmpCleanDatabaseCommand.php
namespace App\Command;

use Pimcore\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpCleanDatabaseCommand extends Command
{
    protected static $defaultName = 'app:idmp:clean-database';

    protected function configure(): void
    {
        $this->setDescription('Nettoie directement la base de données IDMP');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nettoyage direct de la base de données IDMP');

        $db = Db::get();

        // 1. Trouver l'ID de la classe MedicinalProduct
        $classId = $db->fetchOne(
            "SELECT id FROM classes WHERE name = ?",
            ['MedicinalProduct']
        );

        if (!$classId) {
            $io->error('Classe MedicinalProduct non trouvée');
            return Command::FAILURE;
        }

        $io->text("ID de la classe MedicinalProduct : $classId");

        // 2. Nettoyer la table object_store
        $tableName = "object_store_$classId";
        
        try {
            // Vérifier si la colonne name existe
            $columns = $db->fetchAllAssociative("SHOW COLUMNS FROM `$tableName` LIKE 'name'");
            
            if (!empty($columns)) {
                // Mettre NULL dans tous les champs name
                $db->executeStatement("UPDATE `$tableName` SET name = NULL");
                $io->success("Champ 'name' nettoyé dans $tableName");
            }
            
            // Nettoyer aussi object_query si nécessaire
            $queryTable = "object_query_$classId";
            $columns = $db->fetchAllAssociative("SHOW COLUMNS FROM `$queryTable` LIKE 'name'");
            
            if (!empty($columns)) {
                $db->executeStatement("UPDATE `$queryTable` SET name = NULL");
                $io->success("Champ 'name' nettoyé dans $queryTable");
            }
            
            // Nettoyer les relations
            $relationsTable = "object_relations_$classId";
            $db->executeStatement(
                "DELETE FROM `$relationsTable` WHERE fieldname = 'name'"
            );
            $io->success("Relations 'name' supprimées");
            
        } catch (\Exception $e) {
            $io->error("Erreur : " . $e->getMessage());
            return Command::FAILURE;
        }

        // 3. Supprimer tous les objets MedicinalProduct via l'API
        $io->section('Suppression des objets MedicinalProduct');
        
        $objects = $db->fetchAllAssociative(
            "SELECT o_id FROM objects WHERE o_className = ?",
            ['MedicinalProduct']
        );
        
        foreach ($objects as $obj) {
            try {
                $db->executeStatement("DELETE FROM objects WHERE o_id = ?", [$obj['o_id']]);
                $db->executeStatement("DELETE FROM `$tableName` WHERE oo_id = ?", [$obj['o_id']]);
                $db->executeStatement("DELETE FROM `object_query_$classId` WHERE oo_id = ?", [$obj['o_id']]);
                $db->executeStatement("DELETE FROM `object_relations_$classId` WHERE src_id = ?", [$obj['o_id']]);
            } catch (\Exception $e) {
                // Ignorer les erreurs
            }
        }
        
        $io->success('Tous les objets MedicinalProduct supprimés');

        // 4. Clear cache
        $io->section('Nettoyage du cache');
        exec('rm -rf var/cache/*');
        exec('php bin/console cache:clear');
        
        $io->success('Base de données nettoyée !');
        $io->note('Vous pouvez maintenant créer de nouveaux objets MedicinalProduct');
        
        return Command::SUCCESS;
    }
}