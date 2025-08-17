<?php
// src/Command/IdmpCreateSimpleDataCommand.php
namespace App\Command;

use Pimcore\Model\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpCreateSimpleDataCommand extends Command
{
    protected static $defaultName = 'app:idmp:create-simple-data';

    protected function configure(): void
    {
        $this->setDescription('Crée des données IDMP simples pour tester');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Création de données IDMP simples');

        // Créer le dossier IDMP
        $idmpFolder = DataObject::getByPath('/IDMP');
        if (!$idmpFolder) {
            $idmpFolder = new DataObject\Folder();
            $idmpFolder->setKey('IDMP');
            $idmpFolder->setParent(DataObject::getById(1));
            $idmpFolder->save();
            $io->text('✓ Dossier /IDMP créé');
        }

        // Créer le sous-dossier MedicinalProducts
        $productsFolder = DataObject::getByPath('/IDMP/MedicinalProducts');
        if (!$productsFolder) {
            $productsFolder = new DataObject\Folder();
            $productsFolder->setKey('MedicinalProducts');
            $productsFolder->setParent($idmpFolder);
            $productsFolder->save();
            $io->text('✓ Dossier /IDMP/MedicinalProducts créé');
        }

        // Vérifier que la classe existe
        if (!class_exists('\\Pimcore\\Model\\DataObject\\MedicinalProduct')) {
            $io->error('La classe MedicinalProduct n\'existe pas. Exécutez d\'abord : bin/console app:idmp:regenerate-classes');
            return Command::FAILURE;
        }

        // Créer quelques produits simples
        $products = [
            ['key' => 'doliprane-500', 'mpid' => 'FR-001', 'name' => 'Doliprane 500mg', 'atc' => 'N02BE01'],
            ['key' => 'advil-400', 'mpid' => 'FR-002', 'name' => 'Advil 400mg', 'atc' => 'M01AE01'],
            ['key' => 'aspirine-500', 'mpid' => 'FR-003', 'name' => 'Aspirine 500mg', 'atc' => 'N02BA01'],
        ];

        foreach ($products as $data) {
            try {
                $product = new DataObject\MedicinalProduct();
                $product->setKey($data['key']);
                $product->setParent($productsFolder);
                $product->setMpid($data['mpid']);
                $product->setProductName($data['name']);
                $product->setAtcCode($data['atc']);
                $product->setProductType('chemical');
                $product->setDescription('Médicament de test');
                $product->setPublished(true);
                $product->save();
                
                $io->text('✓ Créé : ' . $data['name']);
            } catch (\Exception $e) {
                $io->error('Erreur création ' . $data['name'] . ' : ' . $e->getMessage());
            }
        }

        $io->success('Données créées avec succès !');
        
        return Command::SUCCESS;
    }
}