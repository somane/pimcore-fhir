<?php
// src/Command/ExtendPractitionerCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;

class ExtendPractitionerCommand extends Command
{
    protected static $defaultName = 'app:idmp:extend-practitioner';

    protected function configure(): void
    {
        $this->setDescription('Étend la classe Practitioner pour la prescription de médicaments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Extension de Practitioner pour IDMP');

        $class = ClassDefinition::getByName('Practitioner');
        if (!$class) {
            $io->error('La classe Practitioner n\'existe pas');
            return Command::FAILURE;
        }

        // Créer un panel pour les informations de prescription
        $prescriptionPanel = new ClassDefinition\Layout\Panel();
        $prescriptionPanel->setName('prescriptionInfo');
        $prescriptionPanel->setTitle('Informations de prescription');

        // Ajouter les champs pour la prescription
        $fields = [
            // Numéro RPPS (Répertoire Partagé des Professionnels de Santé)
            [
                'type' => 'input',
                'name' => 'rppsNumber',
                'title' => 'Numéro RPPS',
                'unique' => true,
                'columnLength' => 20
            ],
            // Droits de prescription
            [
                'type' => 'multiselect',
                'name' => 'prescriptionRights',
                'title' => 'Droits de prescription',
                'options' => [
                    ['key' => 'Médicaments standards', 'value' => 'standard_drugs'],
                    ['key' => 'Stupéfiants', 'value' => 'narcotics'],
                    ['key' => 'Médicaments d\'exception', 'value' => 'exceptional_drugs'],
                    ['key' => 'Médicaments hospitaliers', 'value' => 'hospital_drugs']
                ]
            ],
            // Spécialités autorisant des prescriptions spécifiques
            [
                'type' => 'select',
                'name' => 'prescriptionSpecialty',
                'title' => 'Spécialité pour prescription',
                'options' => [
                    ['key' => 'Médecine générale', 'value' => 'general_practice'],
                    ['key' => 'Cardiologie', 'value' => 'cardiology'],
                    ['key' => 'Psychiatrie', 'value' => 'psychiatry'],
                    ['key' => 'Oncologie', 'value' => 'oncology'],
                    ['key' => 'Pédiatrie', 'value' => 'pediatrics'],
                    ['key' => 'Anesthésie', 'value' => 'anesthesiology']
                ]
            ],
            // Habilitation à prescrire certains médicaments
            [
                'type' => 'manyToManyRelation',
                'name' => 'authorizedMedicinalProducts',
                'title' => 'Médicaments autorisés à prescrire',
                'classes' => ['MedicinalProduct']
            ],
            // Historique des prescriptions
            [
                'type' => 'manyToManyRelation',
                'name' => 'prescriptionHistory',
                'title' => 'Historique des prescriptions',
                'classes' => ['MedicationRequest']
            ],
            // Restrictions de prescription
            [
                'type' => 'textarea',
                'name' => 'prescriptionRestrictions',
                'title' => 'Restrictions de prescription',
                'height' => 100
            ]
        ];

        foreach ($fields as $fieldConfig) {
            $field = $this->createField($fieldConfig);
            if ($field) {
                $prescriptionPanel->addChild($field);
            }
        }

        // Ajouter le panel à la définition de classe existante
        $rootLayout = $class->getLayoutDefinitions();
        if ($rootLayout instanceof ClassDefinition\Layout\Panel) {
            $rootLayout->addChild($prescriptionPanel);
        }

        $class->save();
        $io->success('Classe Practitioner étendue avec succès pour la prescription de médicaments');
        
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
                if (isset($config['unique'])) {
                    $field->setUnique($config['unique']);
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
            case 'textarea':
                $field = new Data\Textarea();
                if (isset($config['height'])) {
                    $field->setHeight($config['height']);
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