<?php
// src/Command/IdmpResetAndCreateCommand.php
namespace App\Command;

use Pimcore\Model\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class IdmpResetAndCreateCommand extends Command
{
    protected static $defaultName = 'app:idmp:reset-and-create';

    protected function configure(): void
    {
        $this->setDescription('Supprime toutes les données IDMP et crée de nouvelles données conformes FHIR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Réinitialisation complète IDMP');

        // Confirmation
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Cette commande va SUPPRIMER toutes les données IDMP existantes. Êtes-vous sûr ? [y/N] ',
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $io->warning('Opération annulée');
            return Command::SUCCESS;
        }

        // 1. Supprimer toutes les données
        $this->deleteAllIdmpData($io);

        // 2. Créer la structure de dossiers
        $this->createFolderStructure($io);

        // 3. Créer les données de base FHIR
        $this->createBaseFhirData($io);

        // 4. Créer les données IDMP
        $this->createIdmpData($io);

        $io->success('Réinitialisation terminée avec succès !');
        
        return Command::SUCCESS;
    }

    private function deleteAllIdmpData(SymfonyStyle $io): void
    {
        $io->section('Suppression des données existantes');

        $classesToDelete = [
            'MedicinalProduct',
            'Substance',
            'ManufacturedItem',
            'PackagedProduct',
            'RegulatedAuthorization',
            'ClinicalUseDefinition',
            'ClinicalUse',
            'AdministrableProduct',
            'Ingredient',
            // Classes de support FHIR
            'MedicinalProductName',
            'SubstanceName',
            'Identifier',
            'CodeableConcept',
            'Coding',
            'Period',
            'Quantity',
            'Reference',
            'Attachment',
            'ContactPoint',
        ];

        foreach ($classesToDelete as $className) {
            try {
                $class = "\\Pimcore\\Model\\DataObject\\$className";
                if (class_exists($class)) {
                    $listing = $class::getList();
                    $listing->setUnpublished(true);
                    $objects = $listing->load();
                    
                    foreach ($objects as $object) {
                        $object->delete();
                    }
                    
                    $io->text("✓ Supprimé tous les objets $className");
                }
            } catch (\Exception $e) {
                $io->text("⚠ Classe $className non trouvée ou erreur : " . $e->getMessage());
            }
        }

        // Supprimer les dossiers IDMP
        $mainFolder = DataObject::getByPath('/IDMP');
        if ($mainFolder) {
            $mainFolder->delete();
            $io->text('✓ Dossier /IDMP supprimé');
        }
    }

    private function createFolderStructure(SymfonyStyle $io): void
    {
        $io->section('Création de la structure de dossiers');

        // Structure complète IDMP + FHIR
        $folders = [
            '/IDMP',
            '/IDMP/MedicinalProducts',
            '/IDMP/Substances',
            '/IDMP/ManufacturedItems',
            '/IDMP/PackagedProducts',
            '/IDMP/RegulatedAuthorizations',
            '/IDMP/ClinicalUseDefinitions',
            '/IDMP/AdministrableProducts',
            '/IDMP/Ingredients',
            // Dossiers pour les types FHIR
            '/IDMP/MedicinalProductNames',
            '/IDMP/SubstanceNames',
            '/IDMP/Identifiers',
            '/IDMP/CodeableConcepts',
            '/IDMP/Codings',
            '/IDMP/Periods',
            '/IDMP/Quantities',
            '/IDMP/References',
            '/IDMP/Attachments',
            '/IDMP/ContactPoints',
        ];

        foreach ($folders as $path) {
            $parts = explode('/', trim($path, '/'));
            $parentPath = count($parts) > 1 ? '/' . implode('/', array_slice($parts, 0, -1)) : '/';
            $parent = DataObject::getByPath($parentPath);
            
            if (!DataObject::getByPath($path)) {
                $folder = new DataObject\Folder();
                $folder->setKey(end($parts));
                $folder->setParent($parent ?: DataObject::getById(1));
                $folder->save();
                $io->text("✓ Créé : $path");
            }
        }
    }

    private function createBaseFhirData(SymfonyStyle $io): void
    {
        $io->section('Création des données de base FHIR');

        // Créer des Coding de base
        $codings = [
            ['code' => 'BAN', 'display' => 'Brand Name', 'system' => 'http://hl7.org/fhir/medicinal-product-name-type'],
            ['code' => 'INN', 'display' => 'International Nonproprietary Name', 'system' => 'http://hl7.org/fhir/medicinal-product-name-type'],
            ['code' => 'chemical', 'display' => 'Médicament chimique', 'system' => 'http://hl7.org/fhir/medicinal-product-type'],
            ['code' => 'biological', 'display' => 'Médicament biologique', 'system' => 'http://hl7.org/fhir/medicinal-product-type'],
            ['code' => 'human', 'display' => 'Usage humain', 'system' => 'http://hl7.org/fhir/medicinal-product-domain'],
            ['code' => 'prescription', 'display' => 'Sur ordonnance', 'system' => 'http://hl7.org/fhir/legal-status-of-supply'],
            ['code' => 'otc', 'display' => 'Sans ordonnance', 'system' => 'http://hl7.org/fhir/legal-status-of-supply'],
        ];

        $codingObjects = [];
        foreach ($codings as $data) {
            $coding = new DataObject\Coding();
            $coding->setKey('coding-' . $data['code']);
            $coding->setParent(DataObject::getByPath('/IDMP/Codings'));
            $coding->setCode($data['code']);
            $coding->setDisplay($data['display']);
            $coding->setSystem($data['system']);
            $coding->setPublished(true);
            $coding->save();
            $codingObjects[$data['code']] = $coding;
            $io->text("✓ Créé Coding : " . $data['code']);
        }

        // Créer des CodeableConcept
        $concepts = [
            'BAN' => ['text' => 'Brand Name', 'coding' => 'BAN'],
            'INN' => ['text' => 'International Nonproprietary Name', 'coding' => 'INN'],
            'chemical' => ['text' => 'Médicament chimique', 'coding' => 'chemical'],
            'biological' => ['text' => 'Médicament biologique', 'coding' => 'biological'],
            'human' => ['text' => 'Usage humain', 'coding' => 'human'],
            'prescription' => ['text' => 'Sur ordonnance', 'coding' => 'prescription'],
            'otc' => ['text' => 'Sans ordonnance', 'coding' => 'otc'],
        ];

        $this->codeableConcepts = [];
        foreach ($concepts as $key => $data) {
            $concept = new DataObject\CodeableConcept();
            $concept->setKey('concept-' . $key);
            $concept->setParent(DataObject::getByPath('/IDMP/CodeableConcepts'));
            $concept->setText($data['text']);
            if (isset($codingObjects[$data['coding']])) {
                $concept->setCodings([$codingObjects[$data['coding']]]);
            }
            $concept->setPublished(true);
            $concept->save();
            $this->codeableConcepts[$key] = $concept;
            $io->text("✓ Créé CodeableConcept : " . $key);
        }
    }

    private array $codeableConcepts = [];

    private function createIdmpData(SymfonyStyle $io): void
    {
        $io->section('Création des données IDMP');

        // 1. Créer des substances
        $substances = $this->createSubstances($io);

        // 2. Créer des médicaments
        $this->createMedicinalProducts($io, $substances);
    }

    private function createSubstances(SymfonyStyle $io): array
    {
        $substancesData = [
            [
                'key' => 'paracetamol',
                'identifier' => '362O9ITL9D',
                'name' => 'Paracétamol',
                'cas' => '103-90-2',
                'formula' => 'C8H9NO2',
                'weight' => 151.163
            ],
            [
                'key' => 'ibuprofen',
                'identifier' => 'WK2XYI10QM',
                'name' => 'Ibuprofène',
                'cas' => '15687-27-1',
                'formula' => 'C13H18O2',
                'weight' => 206.28
            ],
            [
                'key' => 'aspirin',
                'identifier' => 'R16CO5Y76E',
                'name' => 'Aspirine',
                'cas' => '50-78-2',
                'formula' => 'C9H8O4',
                'weight' => 180.16
            ]
        ];

        $substances = [];
        foreach ($substancesData as $data) {
            // Créer l'identifiant
            $identifier = new DataObject\Identifier();
            $identifier->setKey('id-' . $data['key']);
            $identifier->setParent(DataObject::getByPath('/IDMP/Identifiers'));
            $identifier->setIdentifierValue($data['identifier']);
            $identifier->setSystem('urn:oid:2.16.840.1.113883.4.9');
            $identifier->setUse('official');
            $identifier->setPublished(true);
            $identifier->save();

            // Créer le nom de substance
            $substanceName = new DataObject\SubstanceName();
            $substanceName->setKey('sname-' . $data['key']);
            $substanceName->setParent(DataObject::getByPath('/IDMP/SubstanceNames'));
            $substanceName->setName($data['name']);
            $substanceName->setPreferred(true);
            $substanceName->setPublished(true);
            $substanceName->save();

            // Créer la substance
            $substance = new DataObject\Substance();
            $substance->setKey($data['key']);
            $substance->setParent(DataObject::getByPath('/IDMP/Substances'));
            $substance->setIdentifier([$identifier]);
            $substance->setName([$substanceName]);
            $substance->setDescription($data['name'] . ' - CAS: ' . $data['cas']);
            $substance->setPublished(true);
            $substance->save();

            $substances[$data['key']] = $substance;
            $io->text("✓ Créé Substance : " . $data['name']);
        }

        return $substances;
    }

    private function createMedicinalProducts(SymfonyStyle $io, array $substances): void
    {
        $productsData = [
            [
                'key' => 'doliprane-500mg',
                'mpid' => 'FR-MP-001',
                'name' => 'Doliprane 500mg',
                'type' => 'chemical',
                'domain' => 'human',
                'status' => 'otc',
                'atc' => 'N02BE01',
                'substance' => 'paracetamol'
            ],
            [
                'key' => 'advil-400mg',
                'mpid' => 'FR-MP-002',
                'name' => 'Advil 400mg',
                'type' => 'chemical',
                'domain' => 'human',
                'status' => 'otc',
                'atc' => 'M01AE01',
                'substance' => 'ibuprofen'
            ],
            [
                'key' => 'aspirine-500mg',
                'mpid' => 'FR-MP-003',
                'name' => 'Aspirine UPSA 500mg',
                'type' => 'chemical',
                'domain' => 'human',
                'status' => 'otc',
                'atc' => 'N02BA01',
                'substance' => 'aspirin'
            ]
        ];

        foreach ($productsData as $data) {
            // Créer l'identifiant MPID
            $identifier = new DataObject\Identifier();
            $identifier->setKey('mpid-' . $data['key']);
            $identifier->setParent(DataObject::getByPath('/IDMP/Identifiers'));
            $identifier->setIdentifierValue($data['mpid']);
            $identifier->setSystem('urn:oid:2.16.840.1.113883.3.1937');
            $identifier->setUse('official');
            $identifier->setPublished(true);
            $identifier->save();

            // Créer le nom du produit
            $productName = new DataObject\MedicinalProductName();
            $productName->setKey('mpname-' . $data['key']);
            $productName->setParent(DataObject::getByPath('/IDMP/MedicinalProductNames'));
            $productName->setProductName($data['name']);
            if (isset($this->codeableConcepts['BAN'])) {
                $productName->setNameType($this->codeableConcepts['BAN']);
            }
            $productName->setPublished(true);
            $productName->save();

            // Créer le code ATC
            $atcCoding = new DataObject\Coding();
            $atcCoding->setKey('atc-' . $data['atc']);
            $atcCoding->setParent(DataObject::getByPath('/IDMP/Codings'));
            $atcCoding->setCode($data['atc']);
            $atcCoding->setSystem('http://www.whocc.no/atc');
            $atcCoding->setPublished(true);
            $atcCoding->save();

            $atcConcept = new DataObject\CodeableConcept();
            $atcConcept->setKey('atc-concept-' . $data['atc']);
            $atcConcept->setParent(DataObject::getByPath('/IDMP/CodeableConcepts'));
            $atcConcept->setText('Code ATC ' . $data['atc']);
            $atcConcept->setCodings([$atcCoding]);
            $atcConcept->setPublished(true);
            $atcConcept->save();

            // Créer le médicament
            $product = new DataObject\MedicinalProduct();
            $product->setKey($data['key']);
            $product->setParent(DataObject::getByPath('/IDMP/MedicinalProducts'));
            $product->setIdentifier([$identifier]);
            $product->setName([$productName]);
            $product->setMedicinalProductType($this->codeableConcepts[$data['type']] ?? null);
            $product->setDomain($this->codeableConcepts[$data['domain']] ?? null);
            $product->setLegalStatusOfSupply($this->codeableConcepts[$data['status']] ?? null);
            $product->setClassification([$atcConcept]);
            $product->setDescription('Médicament de test FHIR - ' . $data['name']);
            
            // Ajouter l'ingrédient si disponible
            if (isset($substances[$data['substance']])) {
                // Créer un CodeableConcept pour l'ingrédient
                $ingredientConcept = new DataObject\CodeableConcept();
                $ingredientConcept->setKey('ingredient-' . $data['key']);
                $ingredientConcept->setParent(DataObject::getByPath('/IDMP/CodeableConcepts'));
                $ingredientConcept->setText($substances[$data['substance']]->getName()[0]->getName());
                $ingredientConcept->setPublished(true);
                $ingredientConcept->save();
                
                $product->setIngredient([$ingredientConcept]);
            }
            
            $product->setPublished(true);
            $product->save();

            $io->text("✓ Créé MedicinalProduct : " . $data['name']);
        }
    }
}