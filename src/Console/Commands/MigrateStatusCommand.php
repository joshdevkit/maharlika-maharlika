<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class MigrateStatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Show the status of each migration');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $migrator = $this->app->getContainer()->get('migrator');
        
        try {
            $status = $migrator->status();
            
            if (empty($status)) {
                $this->io->info('No migrations found.');
                return Command::SUCCESS;
            }

            // Get terminal width
            $terminal = new Terminal();
            $terminalWidth = $terminal->getWidth();
            
            // Calculate widths
            $batchWidth = 5;  // [1] or [12]
            $statusWidth = 10; // [PENDING] or [DONE]
            $dotsPadding = 3; // Space for dots
            $nameWidth = $terminalWidth - $batchWidth - $statusWidth - $dotsPadding;
            
            $this->io->newLine();
            
            foreach ($status as $migration) {
                $name = $migration['name'];
                $ran = $migration['ran'];
                $batch = $migration['batch'] ?? null;
                
                // Prepare batch display
                $batchStr = $batch !== null ? "[$batch]" : '[-]';
                
                // Prepare status
                if ($ran) {
                    $statusStr = '<info>[DONE]</info>';
                } else {
                    $statusStr = '<comment>[PENDING]</comment>';
                }
                
                // Calculate how many dots we need
                $nameLength = strlen($name);
                $dotsLength = $nameWidth - $nameLength - strlen($batchStr);
                
                // Ensure we have at least some dots
                $dotsLength = max(3, $dotsLength);
                
                $dots = str_repeat('.', $dotsLength);
                
                // Build the line
                $line = sprintf(
                    '%s%s%s %s',
                    $name,
                    $dots,
                    $batchStr,
                    $statusStr
                );
                
                $output->writeln($line);
            }
            
            $this->io->newLine();
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error("Failed to get migration status: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}