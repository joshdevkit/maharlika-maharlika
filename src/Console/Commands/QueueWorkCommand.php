<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueWorkCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('queue:work')
            ->setDescription('Process jobs from the queue')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'The queue to work', 'default')
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Seconds to sleep when no job is available', 3)
            ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'Maximum number of jobs to process', 0);
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $queue = $input->getOption('queue');
        $sleep = (int) $input->getOption('sleep');
        $maxJobs = (int) $input->getOption('max-jobs');

        $this->io->info("Processing jobs from '{$queue}' queue...");
        $this->io->info("Press Ctrl+C to stop gracefully.");
        
        $queueManager = $this->app->getContainer()->get('queue');
        
        try {
            $queueManager->work($queue, $sleep, $maxJobs);
            $this->io->success("Queue worker stopped gracefully.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error("Queue processing failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}