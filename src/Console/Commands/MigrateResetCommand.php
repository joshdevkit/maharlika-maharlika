<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class MigrateResetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:reset')
            ->setDescription('Rollback all database migrations');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $migrator = $this->app->getContainer()->get('migrator');
        
        // Get terminal width
        $terminal = new Terminal();
        $terminalWidth = $terminal->getWidth();
        
        $this->io->warning("This will rollback all migrations!");
        $this->io->newLine();
        
        // Output starting line
        $this->outputLine($output, 'Reset started', $terminalWidth);
        $this->io->newLine();
        
        try {
            $rolledBack = $migrator->reset();
            
            if (empty($rolledBack)) {
                $this->io->info("Nothing to reset.");
                return Command::SUCCESS;
            }
            
            $failed = [];
            
            foreach ($rolledBack as $migration) {
                $name = $migration['name'];
                $status = $migration['status'];
                
                if ($status === 'failed') {
                    $this->outputMigrationLine($output, $name, 'FAILED', $terminalWidth, true);
                    $failed[] = $migration;
                } else {
                    $this->outputMigrationLine($output, $name, 'ROLLED BACK', $terminalWidth);
                }
            }
            
            $this->io->newLine();
            
            // Show warnings for failed rollbacks
            if (!empty($failed)) {
                foreach ($failed as $failedMigration) {
                    $this->io->warning("Reset failed: {$failedMigration['name']}");
                    $this->io->writeln("  Error: {$failedMigration['error']}");
                    $this->io->newLine();
                }
                return Command::FAILURE;
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->newLine();
            $this->io->error("Reset failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Output a line with dots filling to terminal width
     */
    private function outputLine(OutputInterface $output, string $text, int $terminalWidth): void
    {
        $textLength = strlen($text);
        $dotsLength = $terminalWidth - $textLength - 3;
        $dotsLength = max(3, $dotsLength);
        
        $dots = str_repeat('.', $dotsLength);
        $output->writeln($text . $dots);
    }
    
    /**
     * Output a migration line with status
     */
    private function outputMigrationLine(
        OutputInterface $output, 
        string $name, 
        string $status, 
        int $terminalWidth,
        bool $isFailed = false
    ): void {
        $statusStr = $isFailed 
            ? '<error>[' . $status . ']</error>' 
            : '<info>[' . $status . ']</info>';
        
        // Calculate dots
        $statusLength = strlen('[' . $status . ']');
        $nameLength = strlen($name);
        $dotsLength = $terminalWidth - $nameLength - $statusLength - 2;
        $dotsLength = max(3, $dotsLength);
        
        $dots = str_repeat('.', $dotsLength);
        
        $line = sprintf('%s%s %s', $name, $dots, $statusStr);
        $output->writeln($line);
    }
}