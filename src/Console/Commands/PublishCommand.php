<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PublishCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('vendor:publish')
            ->setDescription('allows service providers to publish their configuration files, views, assets, and other resources 
            to your application. This is particularly useful for packages that need to export files for customization');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $provider = $input->getOption('provider');
        $tag = $input->getOption('tag');
        $force = $input->getOption('force');
        $all = $input->getOption('all');

        // Get the publisher service
        if (!$this->app->getContainer()->has('publisher')) {
            $this->io->error('Publisher service is not registered.');
            return Command::FAILURE;
        }

        $publisher = $this->app->getContainer()->get('publisher');

        // If no options provided, show available providers and tags
        if (!$provider && !$tag && !$all) {
            $this->showAvailableProviders($publisher);
            return Command::SUCCESS;
        }

        try {
            $published = [];

            if ($all) {
                $published = $publisher->publishAll($force);
            } elseif ($provider) {
                $published = $publisher->publishProvider($provider, $force);
            } elseif ($tag) {
                $published = $publisher->publishTag($tag, $force);
            }

            if (empty($published)) {
                $this->io->warning('No publishable assets found.');
                return Command::SUCCESS;
            }

            $this->io->success(sprintf('Published %d file(s) successfully.', count($published)));
            
            if ($output->isVerbose()) {
                $this->io->listing($published);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Publishing failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function showAvailableProviders($publisher): void
    {
        $providers = $publisher->getPublishableProviders();
        $tags = $publisher->getPublishableTags();

        if (empty($providers) && empty($tags)) {
            $this->io->info('No publishable assets available.');
            return;
        }

        $this->io->title('Available Assets to Publish');

        if (!empty($providers)) {
            $this->io->section('Providers');
            $rows = [];
            foreach ($providers as $providerClass => $paths) {
                $rows[] = [
                    $providerClass,
                    count($paths) . ' file(s)'
                ];
            }
            $this->io->table(['Provider', 'Assets'], $rows);
        }

        if (!empty($tags)) {
            $this->io->section('Tags');
            $rows = [];
            foreach ($tags as $tag => $paths) {
                $rows[] = [
                    $tag,
                    count($paths) . ' file(s)'
                ];
            }
            $this->io->table(['Tag', 'Assets'], $rows);
        }

    }
}