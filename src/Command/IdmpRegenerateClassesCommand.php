<?php
// src/Command/IdmpRegenerateClassesCommand.php
namespace App\Command;

use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpRegenerateClassesCommand extends Command
{
    protected static $defaultName = 'app:idmp:regenerate-classes';

    protected function configure(): void
    {
        $this->setDescription('Régénère les classes IDMP avec une structure simple');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Régénération des classes IDMP');

        // Créer une version simplifiée de MedicinalProduct pour commencer
        $this->createSimpleMedicinalProduct($io);

        // Rebuild
        exec('php bin/console pimcore:deployment:classes-rebuild', $output, $returnCode);
        
        $io->success('Classes régénérées !');
        
        return Command::SUCCESS;
    }

    private function createSimpleMedicinalProduct(SymfonyStyle $io): void
    {
        $class = new ClassDefinition();
        $class->setName('MedicinalProduct');
        $class->setGroup('IDMP');
        
        $layout = new ClassDefinition\Layout\Panel();
        $layout->setName('Layout');
        
        // Créer des champs simples sans relations complexes
        $fields = [];
        
        // MPID
        $mpid = new ClassDefinition\Data\Input();
        $mpid->setName('mpid');
        $mpid->setTitle('MPID');
        $mpid->setColumnLength(100);
        $fields[] = $mpid;
        
        // Nom (simple string pour l'instant)
        $name = new ClassDefinition\Data\Input();
        $name->setName('productName');
        $name->setTitle('Nom du produit');
        $name->setColumnLength(255);
        $fields[] = $name;
        
        // Description
        $desc = new ClassDefinition\Data\Textarea();
        $desc->setName('description');
        $desc->setTitle('Description');
        $fields[] = $desc;
        
        // Code ATC
        $atc = new ClassDefinition\Data\Input();
        $atc->setName('atcCode');
        $atc->setTitle('Code ATC');
        $atc->setColumnLength(20);
        $fields[] = $atc;
        
        // Type de produit (select simple)
        $type = new ClassDefinition\Data\Select();
        $type->setName('productType');
        $type->setTitle('Type de produit');
        $type->setOptions([
            ['key' => 'Médicament chimique', 'value' => 'chemical'],
            ['key' => 'Médicament biologique', 'value' => 'biological'],
            ['key' => 'Vaccin', 'value' => 'vaccine'],
        ]);
        $fields[] = $type;
        
        // Ajouter les champs au layout
        foreach ($fields as $field) {
            $layout->addChild($field);
        }
        
        $class->setLayoutDefinitions($layout);
        
        try {
            $class->save();
            $io->success('Classe MedicinalProduct créée (version simple)');
        } catch (\Exception $e) {
            $io->error('Erreur : ' . $e->getMessage());
        }
    }
}