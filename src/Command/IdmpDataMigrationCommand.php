<?php
// src/Command/IdmpDataMigrationCommand.php (version corrigée)
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

        // Créer les dossiers nécessaires
        $this->createRequiredFolders($io);

        // Migration des MedicinalProduct
        $this->migrateMedicinalProducts($io);

        // Migration des Substances
        $this->migrateSubstances($io);

        $io->success('Migration des données terminée !');
        return Command::SUCCESS;
    }

    private function createRequiredFolders(SymfonyStyle $io): void
    {
        $folders = [
            '/IDMP/MedicinalProductNames',
            '/IDMP/Identifiers',
            '/IDMP/CodeableConcepts',
            '/IDMP/SubstanceNames',
        ];

        foreach ($folders as $path) {
            $folder = DataObject::getByPath($path);
            if (!$folder) {
                $parts = explode('/', trim($path, '/'));
                $parentPath = '/' . $parts[0];
                $parent = DataObject::getByPath($parentPath);
                
                if ($parent) {
                    $folder = new DataObject\Folder();
                    $folder->setKey($parts[1]);
                    $folder->setParent($parent);
                    $folder->save();
                    $io->text("Dossier créé : $path");
                }
            }
        }
    }

    private function migrateMedicinalProducts(SymfonyStyle $io): void
    {
        $io->section('Migration des MedicinalProduct');

        // Utiliser l'ancienne classe si elle existe
        $oldProducts = DataObject\MedicinalProduct::getList();
        $oldProducts->setUnpublished(true); // Inclure les non publiés
        
        foreach ($oldProducts as $oldProduct) {
            try {
                // Si c'est déjà un produit FHIR, skip
                if (strpos($oldProduct->getKey(), '-fhir') !== false) {
                    continue;
                }

                $io->text("Migration de : " . $oldProduct->getName());

                // Créer le nouveau MedicinalProduct
                $newProduct = new DataObject\MedicinalProduct();
                $newProduct->setParent($oldProduct->getParent());
                $newProduct->setKey($oldProduct->getKey() . '-fhir');
                $newProduct->setPublished(true);

                // 1. Migrer l'identifiant MPID
                if ($oldProduct->getMpid()) {
                    $identifier = $this->createIdentifier(
                        $oldProduct->getMpid(),
                        'official',
                        'urn:oid:2.16.840.1.113883.3.1937'
                    );
                    $newProduct->setIdentifier([$identifier]);
                }

                // 2. Migrer le nom commercial
                if ($oldProduct->getName()) {
                    $productName = $this->createMedicinalProductName(
                        $oldProduct->getName(),
                        'BAN' // Brand Name
                    );
                    $newProduct->setName([$productName]);
                }

                // 3. Migrer le type de produit
                if ($oldProduct->getProductType()) {
                    $type = $this->createCodeableConcept(
                        $oldProduct->getProductType(),
                        $this->getProductTypeDisplay($oldProduct->getProductType())
                    );
                    $newProduct->setMedicinalProductType($type);
                }

                // 4. Migrer le domaine
                if ($oldProduct->getDomain()) {
                    $domain = $this->createCodeableConcept(
                        $oldProduct->getDomain(),
                        $this->getDomainDisplay($oldProduct->getDomain())
                    );
                    $newProduct->setDomain($domain);
                }

                // 5. Migrer le code ATC
                if ($oldProduct->getAtcCode()) {
                    $atc = $this->createCodeableConcept(
                        $oldProduct->getAtcCode(),
                        'Code ATC'
                    );
                    $newProduct->setClassification([$atc]);
                }

                // 6. Migrer le statut légal
                if ($oldProduct->getLegalStatusOfSupply()) {
                    $status = $this->createCodeableConcept(
                        $oldProduct->getLegalStatusOfSupply(),
                        $this->getLegalStatusDisplay($oldProduct->getLegalStatusOfSupply())
                    );
                    $newProduct->setLegalStatusOfSupply($status);
                }

                // 7. Migrer la description
                if ($oldProduct->getDescription()) {
                    $newProduct->setDescription($oldProduct->getDescription());
                }

                $newProduct->save();
                $io->success("✓ Migré : " . $newProduct->getKey());

            } catch (\Exception $e) {
                $io->error("Erreur migration produit {$oldProduct->getId()}: " . $e->getMessage());
            }
        }
    }

    private function migrateSubstances(SymfonyStyle $io): void
    {
        $io->section('Migration des Substances');

        $oldSubstances = DataObject\Substance::getList();
        $oldSubstances->setUnpublished(true);

        foreach ($oldSubstances as $oldSubstance) {
            try {
                if (strpos($oldSubstance->getKey(), '-fhir') !== false) {
                    continue;
                }

                $io->text("Migration de : " . $oldSubstance->getSubstanceName());

                $newSubstance = new DataObject\Substance();
                $newSubstance->setParent($oldSubstance->getParent());
                $newSubstance->setKey($oldSubstance->getKey() . '-fhir');
                $newSubstance->setPublished(true);

                // 1. Migrer l'identifiant
                if ($oldSubstance->getIdentifier()) {
                    $identifier = $this->createIdentifier(
                        $oldSubstance->getIdentifier(),
                        'official',
                        'urn:oid:2.16.840.1.113883.4.9'
                    );
                    $newSubstance->setIdentifier([$identifier]);
                }

                // 2. Migrer le nom
                if ($oldSubstance->getSubstanceName()) {
                    $substanceName = $this->createSubstanceName(
                        $oldSubstance->getSubstanceName()
                    );
                    $newSubstance->setName([$substanceName]);
                }

                // 3. Description
                if (method_exists($oldSubstance, 'getDescription') && $oldSubstance->getDescription()) {
                    $newSubstance->setDescription($oldSubstance->getDescription());
                }

                $newSubstance->save();
                $io->success("✓ Migré : " . $newSubstance->getKey());

            } catch (\Exception $e) {
                $io->error("Erreur migration substance {$oldSubstance->getId()}: " . $e->getMessage());
            }
        }
    }

    private function createIdentifier(string $value, string $use, string $system): DataObject\Identifier
    {
        $identifier = new DataObject\Identifier();
        $identifier->setKey('id-' . md5($value . time()));
        $identifier->setParent(DataObject::getByPath('/IDMP/Identifiers'));
        $identifier->setIdentifierValue($value);
        $identifier->setUse($use);
        $identifier->setSystem($system);
        $identifier->setPublished(true);
        $identifier->save();
        
        return $identifier;
    }

    private function createMedicinalProductName(string $name, string $type = 'BAN'): DataObject\MedicinalProductName
    {
        $productName = new DataObject\MedicinalProductName();
        $productName->setKey('name-' . md5($name . time()));
        $productName->setParent(DataObject::getByPath('/IDMP/MedicinalProductNames'));
        $productName->setProductName($name);
        
        // Créer le type de nom
        $nameType = $this->createCodeableConcept($type, $this->getNameTypeDisplay($type));
        $productName->setNameType($nameType);
        
        $productName->setPublished(true);
        $productName->save();
        
        return $productName;
    }

    private function createSubstanceName(string $name): DataObject\SubstanceName
    {
        $substanceName = new DataObject\SubstanceName();
        $substanceName->setKey('sname-' . md5($name . time()));
        $substanceName->setParent(DataObject::getByPath('/IDMP/SubstanceNames'));
        $substanceName->setName($name);
        $substanceName->setPreferred(true);
        $substanceName->setPublished(true);
        $substanceName->save();
        
        return $substanceName;
    }

    private function createCodeableConcept(string $code, string $display): DataObject\CodeableConcept
    {
        $concept = new DataObject\CodeableConcept();
        $concept->setKey('cc-' . md5($code . time()));
        $concept->setParent(DataObject::getByPath('/IDMP/CodeableConcepts'));
        $concept->setText($display);
        
        // Créer un Coding
        $coding = new DataObject\Coding();
        $coding->setKey('coding-' . md5($code . time()));
        $coding->setParent(DataObject::getByPath('/IDMP/Codings'));
        $coding->setCode($code);
        $coding->setDisplay($display);
        $coding->setPublished(true);
        $coding->save();
        
        $concept->setCodings([$coding]);
        $concept->setPublished(true);
        $concept->save();
        
        return $concept;
    }

    private function getProductTypeDisplay(string $type): string
    {
        $types = [
            'chemical' => 'Médicament chimique',
            'biological' => 'Médicament biologique',
            'vaccine' => 'Vaccin',
            'blood' => 'Produit sanguin',
            'radiopharmaceutical' => 'Radiopharmaceutique'
        ];
        return $types[$type] ?? $type;
    }

    private function getDomainDisplay(string $domain): string
    {
        $domains = [
            'human' => 'Usage humain',
            'veterinary' => 'Usage vétérinaire',
            'both' => 'Usage humain et vétérinaire'
        ];
        return $domains[$domain] ?? $domain;
    }

    private function getLegalStatusDisplay(string $status): string
    {
        $statuses = [
            'prescription' => 'Sur ordonnance',
            'otc' => 'Sans ordonnance',
            'hospital' => 'Usage hospitalier',
            'narcotic' => 'Stupéfiant'
        ];
        return $statuses[$status] ?? $status;
    }

    private function getNameTypeDisplay(string $type): string
    {
        $types = [
            'BAN' => 'Brand Name',
            'INN' => 'International Nonproprietary Name',
            'SCI' => 'Scientific Name'
        ];
        return $types[$type] ?? $type;
    }
}