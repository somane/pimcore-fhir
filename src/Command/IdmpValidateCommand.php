<?php
// src/Command/IdmpValidateCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject;

class IdmpValidateCommand extends Command
{
    protected static $defaultName = 'app:idmp:validate';

    protected function configure(): void
    {
        $this->setDescription('Valide l\'installation IDMP et diagnostique les problèmes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Validation de l\'installation IDMP');

        $errors = [];
        $warnings = [];
        $success = [];

        // 1. Vérifier les classes IDMP
        $io->section('Vérification des classes');
        $requiredClasses = [
            'MedicinalProduct',
            'Substance',
            'ManufacturedItem',
            'PackagedProduct',
            'RegulatedAuthorization',
            'ClinicalUseDefinition'
        ];

        foreach ($requiredClasses as $className) {
            $class = ClassDefinition::getByName($className);
            if ($class) {
                $success[] = "✓ Classe $className existe";
                
                // Vérifier les champs critiques
                $this->validateClassFields($className, $class, $errors, $warnings);
            } else {
                $errors[] = "✗ Classe $className manquante";
            }
        }

        // 2. Vérifier les dossiers
        $io->section('Vérification des dossiers');
        $requiredFolders = [
            '/IDMP',
            '/IDMP/MedicinalProducts',
            '/IDMP/Substances',
            '/IDMP/ManufacturedItems',
            '/IDMP/PackagedProducts',
            '/IDMP/RegulatedAuthorizations',
            '/IDMP/ClinicalUseDefinitions'
        ];

        foreach ($requiredFolders as $folderPath) {
            $folder = DataObject::getByPath($folderPath);
            if ($folder && $folder instanceof DataObject\Folder) {
                $success[] = "✓ Dossier $folderPath existe";
            } else {
                $errors[] = "✗ Dossier $folderPath manquant";
            }
        }

        // 3. Vérifier les fichiers PHP générés
        $io->section('Vérification des classes PHP');
        $generatedClasses = [
            'var/classes/DataObject/MedicinalProduct.php',
            'var/classes/DataObject/Substance.php',
            'var/classes/DataObject/ManufacturedItem.php',
            'var/classes/DataObject/PackagedProduct.php',
            'var/classes/DataObject/RegulatedAuthorization.php',
            'var/classes/DataObject/ClinicalUseDefinition.php'
        ];

        foreach ($generatedClasses as $classFile) {
            if (file_exists($classFile)) {
                $success[] = "✓ Fichier $classFile existe";
            } else {
                $warnings[] = "⚠ Fichier $classFile manquant - Exécutez: bin/console pimcore:deployment:classes-rebuild";
            }
        }

        // 4. Vérifier les relations
        $io->section('Vérification des relations');
        $this->validateRelations($errors, $warnings, $success);

        // 5. Vérifier les extensions
        $io->section('Vérification des extensions');
        $this->validateExtensions($errors, $warnings, $success);

        // 6. Test de création d'objet
        $io->section('Test de création d\'objet');
        $this->testObjectCreation($io, $errors, $warnings, $success);

        // Afficher le résumé
        $io->section('Résumé de la validation');

        if (!empty($success)) {
            $io->success('Points validés :');
            $io->listing($success);
        }

        if (!empty($warnings)) {
            $io->warning('Avertissements :');
            $io->listing($warnings);
        }

        if (!empty($errors)) {
            $io->error('Erreurs détectées :');
            $io->listing($errors);
            
            $io->section('Actions recommandées :');
            $io->listing([
                '1. Exécutez : bin/console app:idmp:install',
                '2. Puis : bin/console pimcore:deployment:classes-rebuild',
                '3. Enfin : bin/console cache:clear',
                '4. Relancez cette commande de validation'
            ]);
            
            return Command::FAILURE;
        }

        if (!empty($warnings)) {
            $io->text('Installation IDMP fonctionnelle avec des avertissements.');
            return Command::SUCCESS;
        }

        $io->success('Installation IDMP complètement validée !');
        return Command::SUCCESS;
    }

    private function validateClassFields(string $className, ClassDefinition $class, array &$errors, array &$warnings): void
    {
        $requiredFields = [
            'MedicinalProduct' => ['mpid', 'name', 'productType', 'ingredient'],
            'Substance' => ['identifier', 'substanceName', 'substanceType'],
            'ManufacturedItem' => ['identifier', 'doseForm', 'routeOfAdministration'],
            'PackagedProduct' => ['identifier', 'packageType', 'quantity'],
            'RegulatedAuthorization' => ['identifier', 'authorizationType', 'status'],
            'ClinicalUseDefinition' => ['identifier', 'useType']
        ];

        if (isset($requiredFields[$className])) {
            $fieldDefinitions = $class->getFieldDefinitions();
            
            foreach ($requiredFields[$className] as $fieldName) {
                if (!isset($fieldDefinitions[$fieldName])) {
                    $warnings[] = "⚠ Champ '$fieldName' manquant dans la classe $className";
                }
            }
        }
    }

    private function validateRelations(array &$errors, array &$warnings, array &$success): void
    {
        // Vérifier MedicinalProduct -> Substance
        $mpClass = ClassDefinition::getByName('MedicinalProduct');
        if ($mpClass) {
            $fields = $mpClass->getFieldDefinitions();
            if (isset($fields['ingredient'])) {
                $ingredientField = $fields['ingredient'];
                if ($ingredientField instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyRelation) {
                    $classes = $ingredientField->getClasses();
                    $hasSubstance = false;
                    foreach ($classes as $class) {
                        if ($class['classes'] === 'Substance') {
                            $hasSubstance = true;
                            break;
                        }
                    }
                    if ($hasSubstance) {
                        $success[] = '✓ Relation MedicinalProduct -> Substance configurée';
                    } else {
                        $errors[] = '✗ Relation ingredient ne pointe pas vers Substance';
                    }
                } else {
                    $errors[] = '✗ Le champ ingredient n\'est pas une ManyToManyRelation';
                }
            }
        }
    }

    private function validateExtensions(array &$errors, array &$warnings, array &$success): void
    {
        // Vérifier l'extension Organization
        $orgClass = ClassDefinition::getByName('Organization');
        if ($orgClass) {
            $fields = $orgClass->getFieldDefinitions();
            $idmpFields = ['manufacturerId', 'idmpOrganizationType', 'manufacturedProducts'];
            $hasIdmpFields = false;
            
            foreach ($idmpFields as $fieldName) {
                if (isset($fields[$fieldName])) {
                    $hasIdmpFields = true;
                    break;
                }
            }
            
            if ($hasIdmpFields) {
                $success[] = '✓ Classe Organization étendue pour IDMP';
            } else {
                $warnings[] = '⚠ Classe Organization non étendue - Exécutez: bin/console app:idmp:extend-organization';
            }
        }

        // Vérifier l'extension Practitioner
        $practClass = ClassDefinition::getByName('Practitioner');
        if ($practClass) {
            $fields = $practClass->getFieldDefinitions();
            if (isset($fields['rppsNumber']) || isset($fields['prescriptionRights'])) {
                $success[] = '✓ Classe Practitioner étendue pour la prescription';
            } else {
                $warnings[] = '⚠ Classe Practitioner non étendue - Exécutez: bin/console app:idmp:extend-practitioner';
            }
        }
    }

    private function testObjectCreation(SymfonyStyle $io, array &$errors, array &$warnings, array &$success): void
    {
        try {
            // Test de création d'une substance
            $substanceClass = 'Pimcore\\Model\\DataObject\\Substance';
            if (class_exists($substanceClass)) {
                $testSubstance = new $substanceClass();
                $testSubstance->setKey('test-validation-' . uniqid());
                $testSubstance->setParent(DataObject::getByPath('/IDMP/Substances'));
                
                // Vérifier que les setters existent
                if (method_exists($testSubstance, 'setIdentifier')) {
                    $testSubstance->setIdentifier('TEST-VAL');
                    $success[] = '✓ Test de création de Substance réussi';
                } else {
                    $errors[] = '✗ Méthode setIdentifier manquante sur Substance';
                }
                
                // Ne pas sauvegarder l'objet de test
                unset($testSubstance);
            } else {
                $errors[] = '✗ Classe Pimcore\\Model\\DataObject\\Substance non trouvée';
            }

            // Test de création d'un médicament
            $productClass = 'Pimcore\\Model\\DataObject\\MedicinalProduct';
            if (class_exists($productClass)) {
                $testProduct = new $productClass();
                $testProduct->setKey('test-validation-' . uniqid());
                $testProduct->setParent(DataObject::getByPath('/IDMP/MedicinalProducts'));
                
                if (method_exists($testProduct, 'setMpid')) {
                    $testProduct->setMpid('TEST-MP');
                    $success[] = '✓ Test de création de MedicinalProduct réussi';
                } else {
                    $errors[] = '✗ Méthode setMpid manquante sur MedicinalProduct';
                }
                
                unset($testProduct);
            } else {
                $errors[] = '✗ Classe Pimcore\\Model\\DataObject\\MedicinalProduct non trouvée';
            }
            
        } catch (\Exception $e) {
            $errors[] = '✗ Erreur lors du test de création : ' . $e->getMessage();
        }
    }
}