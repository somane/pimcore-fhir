<?php
// src/Command/IdmpTestMigrationCommand.php
namespace App\Command;

use Pimcore\Model\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpTestMigrationCommand extends Command
{
    protected static $defaultName = 'app:idmp:test-migration';

    protected function configure(): void
    {
        $this->setDescription('Teste la migration IDMP vers FHIR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test de migration IDMP-FHIR');

        // Créer un produit de test avec l'ancien format
        $io->section('Test avec l\'ancien format');
        
        try {
            $oldProduct = new DataObject\MedicinalProduct();
            $oldProduct->setParent(DataObject::getByPath('/IDMP/MedicinalProducts'));
            $oldProduct->setKey('test-old-format');
            
            // Utiliser la méthode helper pour définir le nom
            if (method_exists($oldProduct, 'setSimpleName')) {
                $oldProduct->setSimpleName('Test Product Old Format');
            } else {
                $io->warning('Méthode setSimpleName non disponible');
            }
            
            $oldProduct->save();
            $io->success('Produit créé avec succès');
            
            // Tester la récupération
            $name = method_exists($oldProduct, 'getMainName') 
                ? $oldProduct->getMainName() 
                : 'Méthode getMainName non disponible';
            
            $io->text('Nom récupéré : ' . $name);
            
            // Supprimer le test
            $oldProduct->delete();
            
        } catch (\Exception $e) {
            $io->error('Erreur : ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}