<?php
// src/Command/IdmpTestDataCommand.php
namespace App\Command;

use Pimcore\Model\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpTestDataCommand extends Command
{
    protected static $defaultName = 'app:idmp:test-data';

    protected function configure(): void
    {
        $this->setDescription('Teste les données IDMP créées');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test des données IDMP');

        // Tester les MedicinalProduct
        $products = DataObject\MedicinalProduct::getList();
        $io->text('Nombre de MedicinalProduct : ' . $products->getTotalCount());

        foreach ($products as $product) {
            $io->section('Produit : ' . $product->getKey());
            
            // Identifiants
            $identifiers = $product->getIdentifier();
            if ($identifiers) {
                foreach ($identifiers as $id) {
                    $io->text('- Identifiant : ' . $id->getIdentifierValue());
                }
            }

            // Noms
            $names = $product->getName();
            if ($names) {
                foreach ($names as $name) {
                    $io->text('- Nom : ' . $name->getProductName());
                }
            }

            // Type
            if ($product->getMedicinalProductType()) {
                $io->text('- Type : ' . $product->getMedicinalProductType()->getText());
            }

            // Domaine
            if ($product->getDomain()) {
                $io->text('- Domaine : ' . $product->getDomain()->getText());
            }
        }

        return Command::SUCCESS;
    }
}