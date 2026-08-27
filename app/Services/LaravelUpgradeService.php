<?php

namespace App\Services;

use App\Models\LaravelUpgrade;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class LaravelUpgradeService
{
    /**
     * Laravel major version constraints.
     */
    private array $versionConstraints = [
        '12' => '^12.0',
        '11' => '^11.0',
        '10' => '^10.0',
        '9'  => '^9.0',
    ];

    /**
     * Minimum PHP version required by each Laravel major version.
     *
     * These are used as a quick environment check.
     * Composer remains the final authority for dependency compatibility.
     */
    private array $phpRequirements = [
        '12' => '8.2.0',
        '11' => '8.2.0',
        '10' => '8.1.0',
        '9'  => '8.0.2',
    ];

    /**
     * Required PHP extensions commonly required by Laravel.
     */
    private array $requiredExtensions = [
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'filter',
        'hash',
        'mbstring',
        'openssl',
        'pcre',
        'session',
        'tokenizer',
        'xml',
    ];

    /**
     * Get currently installed Laravel version.
     */
    public function getCurrentVersion(): string
    {
        $installed = base_path('vendor/composer/installed.json');

        if (File::exists($installed)) {
            $data = json_decode(File::get($installed), true);

            if (is_array($data)) {
                $packages = $data['packages'] ?? $data;

                foreach ($packages as $pkg) {
                    if (($pkg['name'] ?? '') === 'laravel/framework') {
                        return ltrim($pkg['version'] ?? 'unknown', 'v');
                    }
                }
            }
        }

        $composerPath = base_path('composer.json');

        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);

            return $composer['require']['laravel/framework'] ?? 'unknown';
        }

        return 'unknown';
    }

    /**
     * Get current Laravel major version.
     */
    public function getCurrentMajor(): int
    {
        $version = $this->getCurrentVersion();

        if (!preg_match('/^(\d+)/', $version, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * Get available Laravel versions.
     */
    public function getAvailableVersions(): array
    {
        return [
            '12' => 'Laravel 12.x',
            '11' => 'Laravel 11.x',
            '10' => 'Laravel 10.x',
            '9'  => 'Laravel 9.x',
        ];
    }

    /**
     * Get Laravel version constraint.
     */
    public function getVersionConstraint(string $targetVersion): string
    {
        return $this->versionConstraints[$targetVersion]
            ?? '^' . $targetVersion . '.0';
    }

    /**
     * Get minimum PHP requirement.
     */
    public function getMinimumPhpVersion(string $targetVersion): string
    {
        return $this->phpRequirements[$targetVersion]
            ?? '8.0.0';
    }

    /**
     * Start upgrade record.
     */
    public function startUpgrade(string $targetVersion): LaravelUpgrade
    {
        return LaravelUpgrade::create([
            'current_version' => $this->getCurrentVersion(),
            'target_version'  => $targetVersion,
            'status'          => 'running',
            'output'          => '',
            'started_at'      => now(),
        ]);
    }

    /**
     * ---------------------------------------------------------
     * 1. ENVIRONMENT COMPATIBILITY CHECK
     * ---------------------------------------------------------
     */
    public function checkEnvironment(string $targetVersion): array
    {
        $results = [];

        $currentPhp = PHP_VERSION;
        $minimumPhp = $this->getMinimumPhpVersion($targetVersion);

        $phpCompatible = version_compare(
            $currentPhp,
            $minimumPhp,
            '>='
        );

        $results[] = [
            'type' => 'php',
            'name' => 'PHP Version',
            'current' => $currentPhp,
            'required' => ">={$minimumPhp}",
            'status' => $phpCompatible ? 'pass' : 'error',
            'message' => $phpCompatible
                ? "PHP {$currentPhp} is compatible with Laravel {$targetVersion}.x."
                : "PHP {$currentPhp} is too old for Laravel {$targetVersion}.x. PHP {$minimumPhp}+ is required.",
        ];

        foreach ($this->requiredExtensions as $extension) {
            $loaded = extension_loaded($extension);

            $results[] = [
                'type' => 'extension',
                'name' => "PHP Extension: {$extension}",
                'current' => $loaded ? 'Installed' : 'Missing',
                'required' => 'Required',
                'status' => $loaded ? 'pass' : 'error',
                'message' => $loaded
                    ? "{$extension} extension is installed."
                    : "{$extension} extension is missing.",
            ];
        }

        $composerExists = $this->findComposerBinary() !== null;

        $results[] = [
            'type' => 'composer',
            'name' => 'Composer',
            'current' => $composerExists ? 'Available' : 'Not Found',
            'required' => 'Required',
            'status' => $composerExists ? 'pass' : 'error',
            'message' => $composerExists
                ? 'Composer executable was found.'
                : 'Composer executable could not be found.',
        ];

        $composerJsonExists = File::exists(base_path('composer.json'));

        $results[] = [
            'type' => 'file',
            'name' => 'composer.json',
            'current' => $composerJsonExists ? 'Found' : 'Missing',
            'required' => 'Required',
            'status' => $composerJsonExists ? 'pass' : 'error',
            'message' => $composerJsonExists
                ? 'composer.json was found.'
                : 'composer.json is missing.',
        ];

        return [
            'success' => !collect($results)
                ->contains(fn ($item) => $item['status'] === 'error'),

            'target_version' => $targetVersion,
            'current_php' => $currentPhp,
            'minimum_php' => $minimumPhp,
            'checks' => $results,
        ];
    }

    /**
     * ---------------------------------------------------------
     * 2. DEPENDENCY COMPATIBILITY REPORT
     * ---------------------------------------------------------
     */
    public function getDependencyReport(string $targetVersion): array
    {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            return [
                'success' => false,
                'message' => 'composer.json was not found.',
                'dependencies' => [],
            ];
        }

        $composer = json_decode(File::get($composerPath), true);

        if (!is_array($composer)) {
            return [
                'success' => false,
                'message' => 'composer.json contains invalid JSON.',
                'dependencies' => [],
            ];
        }

        $dependencies = [];

        foreach (($composer['require'] ?? []) as $name => $constraint) {
            if ($name === 'laravel/framework') {
                $dependencies[] = [
                    'name' => $name,
                    'constraint' => $constraint,
                    'type' => 'runtime',
                    'status' => 'target',
                    'message' => "Will be changed to {$this->getVersionConstraint($targetVersion)}.",
                ];

                continue;
            }

            $dependencies[] = [
                'name' => $name,
                'constraint' => $constraint,
                'type' => 'runtime',
                'status' => 'review',
                'message' => 'Dependency will be evaluated by Composer during dry-run.',
            ];
        }

        foreach (($composer['require-dev'] ?? []) as $name => $constraint) {
            $dependencies[] = [
                'name' => $name,
                'constraint' => $constraint,
                'type' => 'development',
                'status' => 'review',
                'message' => 'Development dependency will be evaluated by Composer.',
            ];
        }

        return [
            'success' => true,
            'target_version' => $targetVersion,
            'dependencies' => $dependencies,
            'runtime_count' => count($composer['require'] ?? []),
            'dev_count' => count($composer['require-dev'] ?? []),
        ];
    }

    /**
     * ---------------------------------------------------------
     * 3. COMPOSER DRY RUN
     * ---------------------------------------------------------
     *
     * This method temporarily changes Laravel's framework
     * constraint, runs Composer with --dry-run, and restores
     * composer.json immediately afterward.
     */
    public function runComposerDryRun(string $targetVersion): array
    {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            return [
                'success' => false,
                'output' => 'composer.json was not found.',
                'exit_code' => 1,
            ];
        }

        $composer = json_decode(File::get($composerPath), true);

        if (!is_array($composer)) {
            return [
                'success' => false,
                'output' => 'composer.json contains invalid JSON.',
                'exit_code' => 1,
            ];
        }

        $originalComposer = $composer;

        $originalConstraint =
            $composer['require']['laravel/framework'] ?? null;

        $targetConstraint =
            $this->getVersionConstraint($targetVersion);

        $composer['require']['laravel/framework'] = $targetConstraint;

        File::put(
            $composerPath,
            json_encode(
                $composer,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES
            ) . PHP_EOL
        );

        try {
            $composerBinary = $this->findComposerBinary();

            if (!$composerBinary) {
                return [
                    'success' => false,
                    'output' => 'Composer executable could not be found.',
                    'exit_code' => 1,
                ];
            }

            $process = new Process([
                $composerBinary,
                'update',
                'laravel/framework',
                '--with-all-dependencies',
                '--dry-run',
                '--no-interaction',
                '--no-scripts',
                '--no-audit',
                '--prefer-dist',
            ], base_path());

            $process->setTimeout(900);

            $output = '';

            $process->run(function ($type, $buffer) use (&$output) {
                $output .= $buffer;
            });

            return [
                'success' => $process->isSuccessful(),
                'output' => $output,
                'exit_code' => $process->getExitCode(),
                'original_constraint' => $originalConstraint,
                'target_constraint' => $targetConstraint,
            ];
        } finally {
            /**
             * ALWAYS restore composer.json.
             */
            File::put(
                $composerPath,
                json_encode(
                    $originalComposer,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES
                ) . PHP_EOL
            );
        }
    }

    /**
     * Find Composer executable.
     *
     * Supports Windows, macOS and Linux.
     */
    private function findComposerBinary(): ?string
    {
        $candidates = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                'composer.bat',
                'composer',
            ];
        } else {
            $candidates = [
                'composer',
                '/usr/local/bin/composer',
                '/usr/bin/composer',
            ];
        }

        foreach ($candidates as $candidate) {
            $process = new Process([
                $candidate,
                '--version',
            ], base_path());

            try {
                $process->setTimeout(10);
                $process->run();

                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * ---------------------------------------------------------
     * REAL UPGRADE
     * ---------------------------------------------------------
     */
    public function runUpgrade(LaravelUpgrade $upgrade): void
    {
        $composerPath = base_path('composer.json');
        $lockPath = base_path('composer.lock');

        $composer = json_decode(
            File::get($composerPath),
            true
        );

        if (!is_array($composer)) {
            $upgrade->update([
                'status' => 'failed',
                'output' => 'Invalid composer.json.',
                'completed_at' => now(),
            ]);

            return;
        }

        $originalComposer = $composer;

        $originalConstraint =
            $composer['require']['laravel/framework'] ?? null;

        $targetMajor = $upgrade->target_version;

        /**
         * Environment check.
         */
        $environment = $this->checkEnvironment($targetMajor);

        if (!$environment['success']) {
            $messages = collect($environment['checks'])
                ->where('status', 'error')
                ->pluck('message')
                ->implode("\n");

            $upgrade->update([
                'status' => 'failed',
                'output' => "Environment compatibility check failed.\n\n{$messages}",
                'completed_at' => now(),
            ]);

            return;
        }

        $newConstraint =
            $this->getVersionConstraint($targetMajor);

        /**
         * Backup composer.lock.
         */
        $lockBackup = $lockPath . '.bak';

        if (File::exists($lockPath)) {
            File::copy($lockPath, $lockBackup);
        }

        /**
         * Update composer.json.
         */
        $composer['require']['laravel/framework'] =
            $newConstraint;

        File::put(
            $composerPath,
            json_encode(
                $composer,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES
            ) . PHP_EOL
        );

        $output =
            "$ Upgrading Laravel {$this->getCurrentMajor()}.x → {$targetMajor}.x\n";

        $output .=
            "  Constraint: {$originalConstraint} → {$newConstraint}\n\n";

        $upgrade->update([
            'output' => $output,
        ]);

        /**
         * Look for cached lock file.
         */
        $cachedLock =
            storage_path(
                "app/locks/laravel-{$targetMajor}.lock"
            );

        if (File::exists($cachedLock)) {
            $output .=
                "⚡ Found cached lock file for Laravel {$targetMajor}.x\n";

            $output .=
                "Using cached dependency tree...\n\n";

            $upgrade->update([
                'output' => $output,
            ]);

            File::copy(
                $cachedLock,
                $lockPath
            );

            $success =
                $this->runComposerInstall(
                    $upgrade,
                    $output
                );
        } else {
            $output .=
                "📦 No cached lock file found.\n";

            $output .=
                "Running Composer update...\n\n";

            $upgrade->update([
                'output' => $output,
            ]);

            $success =
                $this->runComposerUpdate(
                    $upgrade,
                    $output
                );

            if ($success && File::exists($lockPath)) {
                File::ensureDirectoryExists(
                    storage_path('app/locks')
                );

                File::copy(
                    $lockPath,
                    $cachedLock
                );

                $output .=
                    "\n💾 Lock file cached for future upgrades.\n";
            }
        }

        $upgrade->completed_at = now();

        if ($success) {
            $newVersion =
                $this->getCurrentVersion();

            $output .=
                "\n✔ Upgrade completed successfully!\n";

            $output .=
                "✔ Installed Laravel version: v{$newVersion}\n";

            $upgrade->target_version =
                $newVersion;

            $upgrade->status =
                'completed';

            if (File::exists($lockBackup)) {
                File::delete($lockBackup);
            }
        } else {
            $output .=
                "\n✘ Upgrade failed.\n";

            $output .=
                "↩ Rolling back composer.json and composer.lock...\n";

            $upgrade->status =
                'failed';

            /**
             * Restore composer.json.
             */
            File::put(
                $composerPath,
                json_encode(
                    $originalComposer,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES
                ) . PHP_EOL
            );

            /**
             * Restore composer.lock.
             */
            if (File::exists($lockBackup)) {
                File::copy(
                    $lockBackup,
                    $lockPath
                );

                File::delete($lockBackup);
            }

            $output .=
                "↩ Rollback completed.\n";
        }

        $upgrade->output =
            $output;

        $upgrade->save();
    }

    /**
     * Composer install.
     */
    private function runComposerInstall(
        LaravelUpgrade $upgrade,
        string &$output
    ): bool {
        $composer = $this->findComposerBinary();

        if (!$composer) {
            $output .= "\n✘ Composer executable not found.\n";

            $upgrade->update([
                'output' => $output,
            ]);

            return false;
        }

        $process = new Process([
            $composer,
            'install',
            '--no-interaction',
            '--no-scripts',
            '--no-audit',
            '--prefer-dist',
        ], base_path(), [
            'COMPOSER_NO_INTERACTION' => '1',
        ]);

        $process->setTimeout(900);

        $process->run(
            function ($type, $buffer) use (&$output, $upgrade) {
                $output .= $buffer;

                $upgrade->update([
                    'output' => $output,
                ]);
            }
        );

        return $process->isSuccessful();
    }

    /**
     * Composer update.
     */
    private function runComposerUpdate(
        LaravelUpgrade $upgrade,
        string &$output
    ): bool {
        $composer = $this->findComposerBinary();

        if (!$composer) {
            $output .= "\n✘ Composer executable not found.\n";

            $upgrade->update([
                'output' => $output,
            ]);

            return false;
        }

        $process = new Process([
            $composer,
            'update',
            'laravel/framework',
            '--with-all-dependencies',
            '--no-interaction',
            '--no-scripts',
            '--no-audit',
            '--prefer-dist',
        ], base_path(), [
            'COMPOSER_NO_INTERACTION' => '1',
        ]);

        $process->setTimeout(900);

        $process->run(
            function ($type, $buffer) use (&$output, $upgrade) {
                $output .= $buffer;

                $upgrade->update([
                    'output' => $output,
                ]);
            }
        );

        return $process->isSuccessful();
    }
}