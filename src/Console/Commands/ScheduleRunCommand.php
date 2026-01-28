<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Scheduling\ScheduleRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScheduleRunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('schedule:run')
            ->setDescription('Run all scheduled tasks that are due')
            ->addOption(
                'no-log',
                null,
                InputOption::VALUE_NONE,
                'Disable logging of task execution'
            );
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Running Scheduled Tasks');

        $runner = $this->app->getContainer()->make(ScheduleRunner::class);

        if ($input->getOption('no-log')) {
            $runner->setLogging(false);
        }

        $startTime = microtime(true);
        $results = $runner->run();
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if (empty($results)) {
            $this->io->info('No scheduled tasks are due to run.');
            return Command::SUCCESS;
        }

        $this->displayResults($results);

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        $this->io->newLine();
        $this->io->success(sprintf(
            'Executed %d task(s) in %sms (%d succeeded, %d failed)',
            count($results),
            $duration,
            $successCount,
            $failCount
        ));

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    protected function displayResults(array $results): void
    {
        $rows = [];

        foreach ($results as $result) {
            $status = $result['success'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            
            $rows[] = [
                $status,
                $result['description'] ?? $this->truncateCommand($result['command']),
                $result['duration'],
                $result['error'] ?? 'Success',
            ];
        }

        $this->io->table(
            ['Status', 'Task', 'Duration', 'Result'],
            $rows
        );
    }

    protected function truncateCommand(string $command, int $length = 50): string
    {
        if (strlen($command) <= $length) {
            return $command;
        }

        return substr($command, 0, $length - 3) . '...';
    }
}
