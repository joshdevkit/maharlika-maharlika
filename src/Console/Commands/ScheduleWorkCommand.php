<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Scheduling\ScheduleRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScheduleWorkCommand extends Command
{
    protected bool $shouldQuit = false;

    protected function configure(): void
    {
        $this
            ->setName('schedule:work')
            ->setDescription('Run the scheduler as a daemon (checks every minute)')
            ->addOption(
                'no-log',
                null,
                InputOption::VALUE_NONE,
                'Disable logging of task execution'
            );
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Schedule Worker Started');
        $this->io->text('The scheduler is now running. Press Ctrl+C to stop.');
        $this->io->newLine();

        $runner = $this->app->getContainer()->make(ScheduleRunner::class);

        if ($input->getOption('no-log')) {
            $runner->setLogging(false);
        }

        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        $lastRun = null;

        while (!$this->shouldQuit) {
            $currentMinute = date('Y-m-d H:i');

            // Only run once per minute
            if ($lastRun !== $currentMinute) {
                $this->runScheduledTasks($runner);
                $lastRun = $currentMinute;
            }

            // Check for signals
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            // Sleep for a short time to avoid high CPU usage
            sleep(1);
        }

        $this->io->newLine();
        $this->io->success('Schedule worker stopped gracefully.');

        return Command::SUCCESS;
    }

    protected function runScheduledTasks(ScheduleRunner $runner): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $this->io->writeln("<comment>[{$timestamp}]</comment> Running scheduled tasks...");

        $results = $runner->run();

        if (empty($results)) {
            $this->io->writeln('  <fg=gray>No tasks due</>');
            return;
        }

        foreach ($results as $result) {
            $status = $result['success'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $description = $result['description'] ?? $this->truncateCommand($result['command']);
            
            $this->io->writeln("  {$status} {$description} ({$result['duration']})");
            
            if (!$result['success'] && $result['error']) {
                $this->io->writeln("    <fg=red>Error: {$result['error']}</>");
            }
        }
    }

    protected function truncateCommand(string $command, int $length = 50): string
    {
        if (strlen($command) <= $length) {
            return $command;
        }

        return substr($command, 0, $length - 3) . '...';
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->io->newLine();
        $this->io->comment('Received shutdown signal, stopping...');
        return $this->shouldQuit = true;
    }
}
