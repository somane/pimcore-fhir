<?php
// src/Command/FhirIdmpCompleteInstallCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FhirIdmpCompleteInstallCommand extends Command
{
    protected static $defaultName = 'app:fhir-idmp:install';

    protected function configure(): void
    {
        $this->setDescription('Installation complète FHIR-IDMP 6.0.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installation complète FHIR-IDMP 6.0.0');

        $steps = [
            // 1. Classes de base FHIR
            [
                'command' => 'app:fhir:install-base-classes',
                'description' => 'Installation des classes de base FHIR'
            ],
            // 2. Rebuild après les classes de base
            [
                'command' => 'pimcore:deployment:classes-rebuild',
                'description' => 'Reconstruction des classes PHP (1/2)'
            ],
            // 3. Classes de support IDMP
            [
                'command' => 'app:fhir:install-support-classes',
                'description' => 'Installation des classes de support IDMP'
            ],
            // 4. Migration des classes IDMP
            [
                'command' => 'app:idmp:migrate-to-fhir',
                'description' => 'Migration des classes IDMP vers FHIR 6.0.0'
            ],
            // 5. Rebuild final
            [
                'command' => 'pimcore:deployment:classes-rebuild',
                'description' => 'Reconstruction des classes PHP (2/2)'
            ],
            // 6. Cache
            [
                'command' => 'cache:clear',
                'description' => 'Nettoyage du cache'
            ]
        ];

        $application = $this->getApplication();
        
        foreach ($steps as $index => $step) {
            $io->section(sprintf('[%d/%d] %s', $index + 1, count($steps), $step['description']));
            
            try {
                $command = $application->find($step['command']);
                $commandInput = new ArrayInput([]);
                $returnCode = $command->run($commandInput, $output);
                
                if ($returnCode !== Command::SUCCESS) {
                    $io->error('Échec : ' . $step['description']);
                    return Command::FAILURE;
                }
                
                $io->success('✓ ' . $step['description']);
                
            } catch (\Exception $e) {
                $io->error('Erreur : ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->success('Installation FHIR-IDMP terminée avec succès !');
        $io->note([
            'Prochaines étapes :',
            '1. Migrez vos données : bin/console app:idmp:migrate-data',
            '2. Validez l\'installation : bin/console app:idmp:validate --fhir',
            '3. Testez l\'API FHIR : GET /api/fhir/MedicinalProduct'
        ]);

        return Command::SUCCESS;
    }
}