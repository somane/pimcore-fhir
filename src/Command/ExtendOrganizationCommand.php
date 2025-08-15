<?php
// src/Command/ExtendOrganizationCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;

class ExtendOrganizationCommand extends Command
{
    protected static $defaultName = 'app:idmp:extend-organization';

    protected function configure(): void
    {
        $this->setDescription('Étend la classe Organization pour IDMP');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Extension de Organization pour IDMP');

        $class = ClassDefinition::getByName('Organization');
        if (!$class) {
            $io->error('La classe Organization n\'existe pas');
            return Command::FAILURE;
        }

        // Créer un nouveau panel pour les informations IDMP
        $idmpPanel = new ClassDefinition\Layout\Panel();
        $idmpPanel->setName('idmpInfo');
        $idmpPanel->setTitle('Informations IDMP');

        // Ajouter les champs IDMP
        $fields = [
            // Identifiant fabricant
            [
                'type' => 'input',
                'name' => 'manufacturerId',
                'title' => 'Identifiant Fabricant',
                'columnLength' => 100
            ],
            // Type d'organisation IDMP
            [
                'type' => 'select',
                'name' => 'idmpOrganizationType',
                'title' => 'Type d\'organisation IDMP',
                'options' => [
                    ['key' => 'Fabricant', 'value' => 'manufacturer'],
                    ['key' => 'Titulaire AMM', 'value' => 'marketing_authorization_holder'],
                    ['key' => 'Distributeur', 'value' => 'distributor'],
                    ['key' => 'Autorité régulatrice', 'value' => 'regulatory_authority']
                ]
            ],
            // Numéro d'établissement pharmaceutique
            [
                'type' => 'input',
                'name' => 'pharmaceuticalEstablishmentNumber',
                'title' => 'Numéro d\'établissement pharmaceutique',
                'columnLength' => 50
            ],
            // Certifications
            [
                'type' => 'multiselect',
                'name' => 'certifications',
                'title' => 'Certifications',
                'options' => [
                    ['key' => 'BPF (Bonnes Pratiques de Fabrication)', 'value' => 'gmp'],
                    ['key' => 'ISO 9001', 'value' => 'iso9001'],
                    ['key' => 'ISO 13485', 'value' => 'iso13485'],
                    ['key' => 'GDP (Good Distribution Practice)', 'value' => 'gdp']
                ]
            ],
            // Relations avec les médicaments
            [
                'type' => 'manyToManyRelation',
                'name' => 'manufacturedProducts',
                'title' => 'Produits fabriqués',
                'classes' => ['MedicinalProduct']
            ],
            [
                'type' => 'manyToManyRelation',
                'name' => 'authorizedProducts',
                'title' => 'Produits autorisés (AMM)',
                'classes' => ['MedicinalProduct']
            ]
        ];

        foreach ($fields as $fieldConfig) {
            $field = $this->createField($fieldConfig);
            if ($field) {
                $idmpPanel->addChild($field);
            }
        }

        // Ajouter le panel IDMP à la définition de classe existante
        $rootLayout = $class->getLayoutDefinitions();
        if ($rootLayout instanceof ClassDefinition\Layout\Panel) {
            $rootLayout->addChild($idmpPanel);
        }

        $class->save();
        $io->success('Classe Organization étendue avec succès pour IDMP');
        
        return Command::SUCCESS;
    }

    private function createField(array $config)
    {
        $type = $config['type'];
        $field = null;

        switch ($type) {
            case 'input':
                $field = new Data\Input();
                if (isset($config['columnLength'])) {
                    $field->setColumnLength($config['columnLength']);
                }
                break;
            case 'select':
                $field = new Data\Select();
                if (isset($config['options'])) {
                    $field->setOptions($config['options']);
                }
                break;
            case 'multiselect':
                $field = new Data\Multiselect();
                if (isset($config['options'])) {
                    $field->setOptions($config['options']);
                }
                break;
            case 'manyToManyRelation':
                $field = new Data\ManyToManyRelation();
                if (isset($config['classes'])) {
                    $field->setClasses($config['classes']);
                }
                break;
        }

        if ($field) {
            $field->setName($config['name']);
            if (isset($config['title'])) {
                $field->setTitle($config['title']);
            }
        }

        return $field;
    }
}