<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallApiCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install:api')
            ->setDescription('Install API token authentication system');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('API Token Authentication Installation');

        try {
            // Step 1: Check if migration already exists
            $migrationPath = $this->getMigrationsPath();
            $existingMigration = $this->findExistingMigration($migrationPath, 'create_personal_access_token_table');

            if ($existingMigration) {
                $this->io->warning("Migration already exists: {$existingMigration}");

                if (!$this->io->confirm('Do you want to recreate it?', false)) {
                    $this->io->info('Skipping migration creation.');
                } else {
                    unlink($migrationPath . '/' . $existingMigration);
                    $this->createMigration($migrationPath);
                }
            } else {
                $this->createMigration($migrationPath);
            }

            $this->io->success('API Token Authentication installed successfully!');
            
            // Display next steps
            $this->displayNextSteps();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error("Installation failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display next steps to the user
     */
    protected function displayNextSteps(): void
    {
        $this->io->section('Next Steps');
        
        $this->io->writeln([
            '',
            '<info>Step 1:</info> Run the migration',
            '  <comment>php maharlika migrate</comment>',
            '',
            '<info>Step 2:</info> Add the TokenProvider trait to your User model',
            '  Open <comment>app/Models/User.php</comment> and add:',
            '',
            '  <comment>use Maharlika\Auth\TokenProvider;</comment>',
            '',
            '  <comment>class User extends Model</comment>',
            '  <comment>{</comment>',
            '      <comment>use TokenProvider;  // Add this line</comment>',
            '',
            '      <comment>// ... rest of your User model</comment>',
            '  <comment>}</comment>',
            '',
            '<info>Step 3:</info> You\'re ready! Create tokens like this:',
            '  <comment>$user = User::find(1);</comment>',
            '  <comment>$token = $user->createToken(\'my-app-token\');</comment>',
            '  <comment>echo $token->plainTextToken;</comment>',
            '',
        ]);
    }

    /**
     * Find existing migration file
     */
    protected function findExistingMigration(string $path, string $name): ?string
    {
        if (!is_dir($path)) {
            return null;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if (str_contains($file, $name)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Create migration file
     */
    protected function createMigration(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_personal_access_token_table.php";
        $filepath = $path . '/' . $filename;

        $stub = $this->getStub();
        file_put_contents($filepath, $stub);

        $this->io->writeln("  <info>✓</info> Created migration: {$filename}");
    }

    protected function getStub(): string
    {
        $stubDir = Framework::stub('api_tokens.stub');

        if (file_exists($stubDir)) {
            return file_get_contents($stubDir);
        }

        return "";
    }
}