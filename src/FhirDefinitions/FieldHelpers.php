<?php
// src/FhirDefinitions/FieldHelpers.php
namespace App\FhirDefinitions;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\Console\Style\SymfonyStyle;

class FieldHelpers
{
    /**
     * Crée un champ Input
     */
    public function createInputField(
        string $name,
        string $title,
        string $tooltip = '',
        bool $mandatory = false,
        int $width = 400
    ): Data\Input {
        $field = new Data\Input();
        $field->setName($name);
        $field->setTitle($title);
        $field->setTooltip($tooltip);
        $field->setMandatory($mandatory);
        $field->setColumnLength($width);
        return $field;
    }

    /**
     * Crée un champ Textarea
     */
    public function createTextareaField(
        string $name,
        string $title,
        string $tooltip = ''
    ): Data\Textarea {
        $field = new Data\Textarea();
        $field->setName($name);
        $field->setTitle($title);
        $field->setTooltip($tooltip);
        $field->setHeight(200);
        return $field;
    }

    /**
     * Crée un champ Checkbox
     */
    public function createCheckboxField(
        string $name,
        string $title,
        string $tooltip = ''
    ): Data\Checkbox {
        $field = new Data\Checkbox();
        $field->setName($name);
        $field->setTitle($title);
        $field->setTooltip($tooltip);
        return $field;
    }

    /**
     * Crée un champ Datetime
     */
    public function createDatetimeField(
        string $name,
        string $title,
        string $tooltip = ''
    ): Data\Datetime {
        $field = new Data\Datetime();
        $field->setName($name);
        $field->setTitle($title);
        $field->setTooltip($tooltip);
        return $field;
    }

    /**
     * Crée un champ Numeric
     */
    public function createNumericField(
        string $name,
        string $title,
        string $tooltip = ''
    ): Data\Numeric {
        $field = new Data\Numeric();
        $field->setName($name);
        $field->setTitle($title);
        $field->setTooltip($tooltip);
        $field->setDecimalPrecision(2);
        return $field;
    }

    /**
     * Crée un champ de relation objet
     */
    public function createObjectRelationField(
        string $name,
        string $title,
        array $classes,
        string $tooltip = '',
        bool $mandatory = false,
        bool $multiple = false
    ): Data\ManyToOneRelation|Data\ManyToManyRelation {
        if ($multiple) {
            $field = new Data\ManyToManyRelation();
        } else {
            $field = new Data\ManyToOneRelation();
        }
        
        $field->setName($name);
        $field->setTitle($title);
        $field->setTooltip($tooltip);
        $field->setMandatory($mandatory);
        
        // Format correct pour les classes
        $classesConfig = [];
        foreach ($classes as $className) {
            $classesConfig[] = [
                'classes' => $className,
                'pathFormatterClass' => ''
            ];
        }
        $field->setClasses($classesConfig);
        $field->setWidth(600);
        
        if ($multiple) {
            $field->setHeight(200);
        }
        
        return $field;
    }

    /**
     * Crée un layout panel par défaut
     */
    public function createDefaultPanelLayout(array $fields): ClassDefinition\Layout\Panel
    {
        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('Layout');
        $panel->setTitle('Layout');
        
        foreach ($fields as $field) {
            $panel->addChild($field);
        }
        
        return $panel;
    }

    /**
     * Sauvegarde une définition de classe
     */
    public function saveDefinition(ClassDefinition $class, string $name, SymfonyStyle $io): void
    {
        try {
            $class->save();
            $io->success(sprintf('Classe "%s" sauvegardée avec succès.', $name));
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la sauvegarde de "%s": %s', $name, $e->getMessage()));
        }
    }

    /**
     * Upsert générique pour une classe
     */
    public function upsertClass(
        SymfonyStyle $io,
        string $name,
        string $description,
        array $classFields
    ): void {
        $class = ClassDefinition::getByName($name);
        if ($class) {
            $io->warning(sprintf('Classe "%s" existe déjà. Mise à jour...', $name));
        } else {
            $class = new ClassDefinition();
            $class->setName($name);
            $io->writeln(sprintf('Création de la classe "%s"...', $name));
        }

        $class->setDescription($description);
        $class->setGroup('FHIR-IDMP');
        
        $layout = $this->createDefaultPanelLayout($classFields);
        $class->setLayoutDefinitions($layout);
        
        $this->saveDefinition($class, $name, $io);
    }
}