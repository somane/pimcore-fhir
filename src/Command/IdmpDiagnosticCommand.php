<?php
// src/Command/IdmpDiagnosticCommand.php
namespace App\Command;

use Pimcore\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpDiagnosticCommand extends Command
{
    protected static $defaultName = 'app:idmp:diagnostic';

    protected function configure(): void
    {
        $this->setDescription('Diagnostic de la structure IDMP');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Diagnostic IDMP');

        $db = Db::get();

        // 1. Lister toutes les classes IDMP
        $io->section('Classes IDMP');
        $classes = $db->fetchAllAssociative(
            "SELECT id, name FROM classes WHERE name IN (?, ?, ?, ?, ?, ?)",
            ['MedicinalProduct', 'Substance', 'ManufacturedItem', 'PackagedProduct', 'RegulatedAuthorization', 'ClinicalUseDefinition']
        );

        foreach ($classes as $class) {
            $io->text(sprintf("- %s (ID: %d)", $class['name'], $class['id']));
            
            // Vérifier les tables
            $tables = [
                "object_store_{$class['id']}",
                "object_query_{$class['id']}",
                "object_relations_{$class['id']}"
            ];
            
            foreach ($tables as $table) {
                $exists = $db->fetchOne(
                    "SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = DATABASE() AND table_name = ?",
                    [$table]
                );
                
                if ($exists) {
                    $count = $db->fetchOne("SELECT COUNT(*) FROM `$table`");
                    $io->text("  ✓ $table (lignes: $count)");
                    
                    if ($table === "object_store_{$class['id']}") {
                        // Afficher quelques colonnes
                        $columns = $db->fetchAllAssociative("SHOW COLUMNS FROM `$table` LIMIT 10");
                        $columnNames = array_column($columns, 'Field');
                        $io->text("    Colonnes : " . implode(', ', array_slice($columnNames, 0, 10)) . "...");
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}