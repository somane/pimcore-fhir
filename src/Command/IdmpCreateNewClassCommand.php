<?php
// src/Command/IdmpCreateNewClassCommand.php
namespace App\Command;

use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpCreateNewClassCommand extends Command
{
    protected static $defaultName = 'app:idmp:create-new-class';

    protected function configure(): void
    {
        $this->setDescription('Crée une nouvelle classe MedicinalProductV2 sans les problèmes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Création de MedicinalProductV2');

        // Supprimer l'ancienne classe si elle existe
        $oldClass = ClassDefinition::getByName('MedicinalProduct');
        if ($oldClass) {
            $oldClass->delete();
            $io->text('Ancienne classe MedicinalProduct supprimée');
        }

        // Créer la nouvelle classe
        $class = new ClassDefinition();
        $class->setName('MedicinalProductV2');
        $class->setGroup('IDMP-V2');
        
        // Layout principal
        $layout = new ClassDefinition\Layout\Panel();
        $layout->setName('Layout');
        $layout->setTitle('Layout');

        // Panel d'information de base
        $basicPanel = new ClassDefinition\Layout\Panel();
        $basicPanel->setName('BasicInfo');
        $basicPanel->setTitle('Informations de base');

        // Champs simples pour commencer
        $fields = [];

        // Identifiant MPID (string simple)
        $mpidField = new ClassDefinition\Data\Input();
        $mpidField->setName('mpid');
        $mpidField->setTitle('MPID');
        $mpidField->setColumnLength(100);
        $fields[] = $mpidField;

        // Nom commercial (string simple pour l'instant)
        $simpleNameField = new ClassDefinition\Data\Input();
        $simpleNameField->setName('simpleName');
        $simpleNameField->setTitle('Nom commercial');
        $simpleNameField->setColumnLength(255);
        $fields[] = $simpleNameField;

        // Description
        $descField = new ClassDefinition\Data\Textarea();
        $descField->setName('description');
        $descField->setTitle('Description');
        $fields[] = $descField;

        // Code ATC
        $atcField = new ClassDefinition\Data\Input();
        $atcField->setName('atcCode');
        $atcField->setTitle('Code ATC');
        $atcField->setColumnLength(10);
        $fields[] = $atcField;

        // Ajouter les champs au panel
        foreach ($fields as $field) {
            $basicPanel->addChild($field);
        }

        $layout->addChild($basicPanel);
        $class->setLayoutDefinitions($layout);
        
        try {
            $class->save();
            $io->success('Classe MedicinalProductV2 créée avec succès');
            
            // Rebuild
            exec('php bin/console pimcore:deployment:classes-rebuild');
            $io->success('Classes reconstruites');
            
        } catch (\Exception $e) {
            $io->error('Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}