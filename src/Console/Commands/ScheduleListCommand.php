<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Scheduling\Schedule;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScheduleListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('schedule:list')
            ->setDescription('List all scheduled tasks');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Scheduled Tasks');

        $schedule = $this->app->getContainer()->get('schedule');
        $events = $schedule->events();

        if (empty($events)) {
            $this->io->warning('No scheduled tasks have been defined.');
            $this->io->text('Define your scheduled tasks in routes/schedule.php');
            return Command::SUCCESS;
        }

        $this->displayTasks($events);

        $this->io->newLine();
        $this->io->info(sprintf('Total: %d scheduled task(s)', count($events)));

        return Command::SUCCESS;
    }

    protected function displayTasks(array $events): void
    {
        $rows = [];
        $now = new \DateTime();

        foreach ($events as $event) {
            $summary = $event->getSummary();
            
            $isDue = $event->isDue() ? '<fg=green>Yes</>' : '<fg=gray>No</>';
            
            $rows[] = [
                $summary['description'] ?? $this->truncateCommand($summary['command']),
                $summary['expression'],
                $summary['next_run'],
                $isDue,
                $summary['timezone'] ?? date_default_timezone_get(),
            ];
        }

        $this->io->table(
            ['Task', 'Expression', 'Next Run', 'Due Now', 'Timezone'],
            $rows
        );
    }

    protected function truncateCommand(string $command, int $length = 40): string
    {
        if (strlen($command) <= $length) {
            return $command;
        }

        return substr($command, 0, $length - 3) . '...';
    }
}
