<?php
// src/Command/IdmpMigrateToFhirCommand.php
namespace App\Command;

use App\FhirDefinitions\ClassDefinitions;
use App\FhirDefinitions\FieldHelpers;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpMigrateToFhirCommand extends Command
{
    protected static $defaultName = 'app:idmp:migrate-to-fhir';

    protected function configure(): void
    {
        $this->setDescription('Migre les classes IDMP existantes vers le format FHIR 6.0.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration IDMP vers FHIR 6.0.0');

        $fieldHelpers = new FieldHelpers();
        $classDefinitions = new ClassDefinitions($fieldHelpers);

        // 1. Sauvegarder les données existantes
        $io->section('Sauvegarde des données existantes');
        $this->backupExistingData($io);

        // 2. Mettre à jour les classes existantes
        $io->section('Mise à jour des classes IDMP');
        
        $classesToMigrate = [
            'MedicinalProduct' => 'createMedicinalProductClass',
            'Substance' => 'createSubstanceClass',
            'ManufacturedItem' => 'createManufacturedItemClass',
            'PackagedProduct' => 'createPackagedProductClass',
            'RegulatedAuthorization' => 'createRegulatedAuthorizationClass',
        ];

        foreach ($classesToMigrate as $className => $methodName) {
            try {
                $io->text("Migration de $className...");
                
                // Renommer l'ancienne classe
                $oldClass = ClassDefinition::getByName($className);
                if ($oldClass) {
                    $oldClass->setName($className . '_OLD');
                    $oldClass->save();
                    $io->text("Classe $className renommée en {$className}_OLD");
                }
                
                // Créer la nouvelle classe FHIR
                $classDefinitions->$methodName($io);
                
            } catch (\Exception $e) {
                $io->error("Erreur lors de la migration de $className : " . $e->getMessage());
            }
        }

        // 3. Créer les nouvelles classes
        $io->section('Création des nouvelles classes IDMP');
        
        $newClasses = [
            'AdministrableProduct' => 'createAdministrableProductClass',
            'Ingredient' => 'createIngredientClass',
            'ClinicalUse' => 'createClinicalUseClass',
        ];

        foreach ($newClasses as $className => $methodName) {
            try {
                $io->text("Création de $className...");
                $classDefinitions->$methodName($io);
            } catch (\Exception $e) {
                $io->error("Erreur lors de la création de $className : " . $e->getMessage());
            }
        }

        $io->success('Migration terminée !');
        $io->note([
            'Prochaines étapes :',
            '1. Exécutez : bin/console pimcore:deployment:classes-rebuild',
            '2. Migrez les données avec : bin/console app:idmp:migrate-data',
            '3. Testez avec : bin/console app:idmp:validate --fhir'
        ]);

        return Command::SUCCESS;
    }

    private function backupExistingData(SymfonyStyle $io): void
    {
        $backupDir = PIMCORE_PROJECT_ROOT . '/var/idmp-backup-' . date('Y-m-d-H-i-s');
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        // Export des définitions de classes
        $classes = ['MedicinalProduct', 'Substance', 'ManufacturedItem', 'PackagedProduct', 'RegulatedAuthorization'];
        
        foreach ($classes as $className) {
            $class = ClassDefinition::getByName($className);
            if ($class) {
                $json = json_encode($class->getObjectVars(), JSON_PRETTY_PRINT);
                file_put_contents($backupDir . '/' . $className . '.json', $json);
                $io->text("Sauvegarde de $className");
            }
        }

        $io->success("Sauvegarde effectuée dans : $backupDir");
    }
}