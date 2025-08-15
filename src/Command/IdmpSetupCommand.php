<?php
// src/Command/IdmpSetupCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IdmpSetupCommand extends Command
{
    protected static $defaultName = 'app:idmp:setup';

    protected function configure(): void
    {
        $this->setDescription('Configure complètement l\'environnement IDMP dans Pimcore');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Configuration complète IDMP pour Pimcore');

        $steps = [
            [
                'title' => 'Installation des classes IDMP',
                'command' => 'app:idmp:install',
                'description' => 'Création des classes MedicinalProduct, Substance, etc.'
            ],
            /*[
                'title' => 'Extension de la classe Organization',
                'command' => 'app:idmp:extend-organization',
                'description' => 'Ajout des champs IDMP à Organization'
            ],*/
            [
                'title' => 'Extension de la classe Practitioner',
                'command' => 'app:idmp:extend-practitioner',
                'description' => 'Ajout des champs de prescription à Practitioner'
            ]
        ];

        $application = $this->getApplication();
        $totalSteps = count($steps);
        $currentStep = 0;

        foreach ($steps as $step) {
            $currentStep++;
            $io->section(sprintf('[%d/%d] %s', $currentStep, $totalSteps, $step['title']));
            $io->text($step['description']);

            try {
                $command = $application->find($step['command']);
                $commandInput = new ArrayInput([]);
                $returnCode = $command->run($commandInput, $output);

                if ($returnCode === Command::SUCCESS) {
                    $io->success('✓ ' . $step['title'] . ' - Terminé');
                } else {
                    $io->error('✗ ' . $step['title'] . ' - Échec');
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $io->error('Erreur lors de l\'exécution de ' . $step['command'] . ': ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->section('Configuration post-installation');

        // Créer des exemples de données
        if ($io->confirm('Voulez-vous créer des données d\'exemple IDMP ?')) {
            $this->createSampleData($io);
        }

        // Afficher les informations d'utilisation
        $io->section('Configuration terminée !');
        $io->success('L\'environnement IDMP est maintenant configuré dans Pimcore.');
        
        $io->text([
            'Prochaines étapes :',
            '1. Vérifiez les classes dans l\'admin Pimcore : /admin',
            '2. Testez l\'API FHIR :',
            '   - GET /api/fhir/MedicinalProduct',
            '   - GET /api/fhir/Substance',
            '   - GET /api/fhir/MedicinalProduct/$lookup?code=<ATC_CODE>',
            '3. Consultez la documentation IDMP : https://www.ema.europa.eu/en/human-regulatory/research-development/data-medicines-iso-idmp-standards'
        ]);

        return Command::SUCCESS;
    }

    private function createSampleData(SymfonyStyle $io): void
    {
        $io->text('Création de données d\'exemple...');

        try {
            // Créer une substance exemple
            $paracetamol = new \App\Model\DataObject\Substance();
            $paracetamol->setParent(\Pimcore\Model\DataObject::getByPath('/IDMP/Substances'));
            $paracetamol->setKey('paracetamol');
            $paracetamol->setIdentifier('362O9ITL9D');
            $paracetamol->setSubstanceName('Paracétamol');
            $paracetamol->setInn('Paracetamol');
            $paracetamol->setCasNumber('103-90-2');
            $paracetamol->setSubstanceType('chemical');
            $paracetamol->setMolecularFormula('C8H9NO2');
            $paracetamol->setMolecularWeight(151.163);
            $paracetamol->save();

            // Créer un médicament exemple
            $doliprane = new \App\Model\DataObject\MedicinalProduct();
            $doliprane->setParent(\Pimcore\Model\DataObject::getByPath('/IDMP/MedicinalProducts'));
            $doliprane->setKey('doliprane-500mg');
            $doliprane->setMpid('FR-MP-001');
            $doliprane->setName('Doliprane 500mg');
            $doliprane->setNonproprietaryName('Paracétamol');
            $doliprane->setProductType('chemical');
            $doliprane->setDomain('human');
            $doliprane->setAtcCode('N02BE01');
            $doliprane->setLegalStatusOfSupply('otc');
            $doliprane->setIngredient([$paracetamol]);
            $doliprane->save();

            $io->success('Données d\'exemple créées avec succès');
        } catch (\Exception $e) {
            $io->warning('Impossible de créer les données d\'exemple : ' . $e->getMessage());
        }
    }
}