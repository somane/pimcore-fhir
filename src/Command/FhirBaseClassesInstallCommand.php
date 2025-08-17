<?php
// src/Command/FhirBaseClassesInstallCommand.php
namespace App\Command;

use App\FhirDefinitions\ClassDefinitions;
use App\FhirDefinitions\FieldHelpers;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FhirBaseClassesInstallCommand extends Command
{
    protected static $defaultName = 'app:fhir:install-base-classes';

    protected function configure(): void
    {
        $this->setDescription('Installe les classes de base FHIR 6.0.0 nécessaires pour IDMP');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installation des classes de base FHIR 6.0.0');

        $fieldHelpers = new FieldHelpers();
        $classDefinitions = new ClassDefinitions($fieldHelpers);

        $io->section('Création des types de données complexes FHIR');

        // Classes de base essentielles
        $baseClasses = [
            'Coding' => 'createCodingClass',
            'CodeableConcept' => 'createCodeableConceptClass',
            'Period' => 'createPeriodClass',
            'Quantity' => 'createQuantityClass',
            'Identifier' => 'createIdentifierClass',
            'Ratio' => 'createRatioClass',
            'ContactPoint' => 'createContactPointClass',
            'Attachment' => 'createAttachmentClass',
            'Reference' => 'createReferenceClass',
            'Annotation' => 'createAnnotationClass',
            'Range' => 'createRangeClass',
        ];

        foreach ($baseClasses as $className => $methodName) {
            try {
                $io->text("Création de $className...");
                $classDefinitions->$methodName($io);
            } catch (\Exception $e) {
                $io->error("Erreur lors de la création de $className : " . $e->getMessage());
            }
        }

        $io->success('Classes de base FHIR installées avec succès !');
        $io->note('Exécutez maintenant : bin/console pimcore:deployment:classes-rebuild');

        return Command::SUCCESS;
    }
}