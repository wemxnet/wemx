<?php

namespace App\Extensions\Commands;

use App\Extensions\Traits\CommandHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateExtensions extends Command
{
    use CommandHelper;

    protected $signature = 'extension:migrate {name?}';

    protected $description = 'Perform migrations for extensions';

    public function handle(): void
    {
        $name = Str::lower($this->argument('name'));

        if ($name) {
            $extension = $this->getExtensions($name);
            if ($extension && $extension->status === 'enabled') {
                $this->info("Performing migrations for the {$extension->name}");
                $this->migrateExtension($extension);
                $this->logAction('Performed migrations', $extension);
            } else {
                $this->error("The {$name} extension is either not installed or not enabled.");
            }
        } else {
            $this->migrateAllExtensions();
        }
    }

    protected function migrateExtension($extension): void
    {
        $migrationPath = $extension->extension()->getMigrationsPath();
        if (is_dir($migrationPath)) {
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $migrationPath);
            $this->info("Performing migrations for the {$extension->name} at {$relativePath}");
            $this->call('migrate', [
                '--path' => $relativePath,
                '--force' => true,
            ]);

            $this->logAction('Performed migrations', $extension);
        } else {
            $this->info("Noting to migrate for the {$extension->name} extension.");
        }
    }

    protected function migrateAllExtensions(): void
    {
        $extensions = $this->extensionClass()->where('status', 'enabled')->get();
        if ($extensions->isEmpty()) {
            $this->info('No enabled extensions found for migrations.');

            return;
        }

        foreach ($extensions as $extension) {
            $this->migrateExtension($extension);
        }
    }
}
