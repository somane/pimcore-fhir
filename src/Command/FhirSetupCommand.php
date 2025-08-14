<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;

class FhirSetupCommand extends Command
{
    protected static $defaultName = 'fhir:setup';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installation FHIR pour Pimcore');

        // Création des classes
        $this->createPatientClass($io);
        $this->createPractitionerClass($io);
        $this->createObservationClass($io);
        $this->createOrganizationClass($io);
        
        $io->success('Installation terminée !');
        return Command::SUCCESS;
    }

    private function createPatientClass(SymfonyStyle $io): void
    {
        $class = new ClassDefinition();
        $class->setName('Patient');
        $class->setGroup('FHIR');

        $layout = new ClassDefinition\Layout\Panel();
        $layout->setName('root');
        
        // Panel Identité
        $identityPanel = new ClassDefinition\Layout\Panel();
        $identityPanel->setTitle('Identité');
        
        $identifier = new Data\Input();
        $identifier->setName('identifier');
        $identifier->setTitle('Identifiant');
        $identifier->setMandatory(true);
        $identifier->setUnique(true);
        
        $familyName = new Data\Input();
        $familyName->setName('familyName');
        $familyName->setTitle('Nom');
        $familyName->setMandatory(true);
        
        $givenName = new Data\Input();
        $givenName->setName('givenName');
        $givenName->setTitle('Prénom');
        $givenName->setMandatory(true);
        
        $identityPanel->addChild($identifier);
        $identityPanel->addChild($familyName);
        $identityPanel->addChild($givenName);
        
        // Panel Démographie
        $demoPanel = new ClassDefinition\Layout\Panel();
        $demoPanel->setTitle('Démographie');
        
        $birthDate = new Data\Date();
        $birthDate->setName('birthDate');
        $birthDate->setTitle('Date de naissance');
        
        $gender = new Data\Select();
        $gender->setName('gender');
        $gender->setTitle('Genre');
        $gender->setOptions([
            ['key' => 'Masculin', 'value' => 'male'],
            ['key' => 'Féminin', 'value' => 'female'],
            ['key' => 'Autre', 'value' => 'other'],
            ['key' => 'Inconnu', 'value' => 'unknown']
        ]);
        
        $demoPanel->addChild($birthDate);
        $demoPanel->addChild($gender);
        
        // Ajout des panels au layout
        $layout->addChild($identityPanel);
        $layout->addChild($demoPanel);
        
        $class->setLayoutDefinitions($layout);
        $class->save();
        
        $io->success('Classe Patient créée');
    }
    
    // Méthodes similaires pour les autres classes...
}