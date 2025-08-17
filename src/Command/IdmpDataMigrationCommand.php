<?php
// src/Command/IdmpDataMigrationCommand.php
namespace App\Command;

use Pimcore\Model\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpDataMigrationCommand extends Command
{
    protected static $defaultName = 'app:idmp:migrate-data';

    protected function configure(): void
    {
        $this->setDescription('Migre les données IDMP existantes vers le nouveau format FHIR 6.0.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration des données IDMP vers FHIR 6.0.0');

        // Mapping des champs
        $mappings = [
            'MedicinalProduct' => [
                'mpid' => 'identifier', // Devient un objet Identifier
                'name' => 'name', // Devient un objet MedicinalProductName
                'productType' => 'medicinalProductType', // Devient CodeableConcept
                'atcCode' => 'classification', // Devient CodeableConcept
                'legalStatusOfSupply' => 'legalStatusOfSupply', // Devient CodeableConcept
            ],
            'Substance' => [
                'identifier' => 'identifier', // Devient un objet Identifier
                'substanceName' => 'name', // Devient SubstanceName
                'substanceType' => 'classification', // Devient CodeableConcept
                'molecularFormula' => 'structure.molecularFormula',
                'molecularWeight' => 'molecularWeight', // Devient SubstanceMolecularWeight
            ]
        ];

        foreach ($mappings as $className => $fieldMap) {
            $io->section("Migration de $className");
            $this->migrateClass($io, $className, $fieldMap);
        }

        $io->success('Migration des données terminée !');
        return Command::SUCCESS;
    }

    private function migrateClass(SymfonyStyle $io, string $className, array $fieldMap): void
    {
        $oldClassName = $className . '_OLD';
        $oldClass = "\\Pimcore\\Model\\DataObject\\" . $oldClassName;
        $newClass = "\\Pimcore\\Model\\DataObject\\" . $className;

        if (!class_exists($oldClass)) {
            $io->warning("Classe $oldClass non trouvée, skip");
            return;
        }

        $listing = $oldClass::getList();
        $objects = $listing->load();
        
        $io->progressStart(count($objects));

        foreach ($objects as $oldObject) {
            try {
                $newObject = new $newClass();
                $newObject->setParent($oldObject->getParent());
                $newObject->setKey($oldObject->getKey() . '-fhir');
                $newObject->setPublished(true);

                // Migration des champs simples
                foreach ($fieldMap as $oldField => $newField) {
                    $method = 'get' . ucfirst($oldField);
                    if (method_exists($oldObject, $method)) {
                        $value = $oldObject->$method();
                        
                        // Conversion vers les types FHIR
                        if ($newField === 'identifier' && $value) {
                            // Créer un objet Identifier
                            $identifier = new DataObject\Identifier();
                            $identifier->setIdentifierValue($value);
                            $identifier->setUse('official');
                            $identifier->setKey('id-' . uniqid());
                            $identifier->setParent(DataObject::getByPath('/IDMP/Identifiers'));
                            $identifier->save();
                            
                            $newObject->setIdentifier([$identifier]);
                        } elseif (strpos($newField, 'Type') !== false && $value) {
                            // Créer un CodeableConcept
                            $concept = $this->createCodeableConcept($value);
                            $setter = 'set' . ucfirst($newField);
                            if (method_exists($newObject, $setter)) {
                                $newObject->$setter($concept);
                            }
                        } else {
                            // Copie directe
                            $setter = 'set' . ucfirst($newField);
                            if (method_exists($newObject, $setter)) {
                                $newObject->$setter($value);
                            }
                        }
                    }
                }

                $newObject->save();
                $io->progressAdvance();
                
            } catch (\Exception $e) {
                $io->error("Erreur migration objet {$oldObject->getId()}: " . $e->getMessage());
            }
        }

        $io->progressFinish();
    }

    private function createCodeableConcept(string $value): DataObject\CodeableConcept
    {
        $concept = new DataObject\CodeableConcept();
        $concept->setText($value);
        $concept->setKey('concept-' . uniqid());
        $concept->setParent(DataObject::getByPath('/IDMP/CodeableConcepts'));
        $concept->save();
        
        return $concept;
    }
}