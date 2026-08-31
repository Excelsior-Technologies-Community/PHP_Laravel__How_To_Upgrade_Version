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
     * Minimum PHP version.
     */
    private array $phpRequirements = [
        '12' => '8.2.0',
        '11' => '8.2.0',
        '10' => '8.1.0',
        '9'  => '8.0.2',
    ];

    /**
     * Required PHP extensions.
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
     * =========================================================
     * CURRENT LARAVEL VERSION
     * =========================================================
     */
    public function getCurrentVersion(): string
    {
        $installed = base_path('vendor/composer/installed.json');

        if (File::exists($installed)) {
            $data = json_decode(
                File::get($installed),
                true
            );

            if (is_array($data)) {
                $packages = $data['packages'] ?? $data;

                foreach ($packages as $pkg) {
                    if (
                        ($pkg['name'] ?? '') ===
                        'laravel/framework'
                    ) {
                        return ltrim(
                            $pkg['version'] ?? 'unknown',
                            'v'
                        );
                    }
                }
            }
        }

        $composerPath = base_path('composer.json');

        if (File::exists($composerPath)) {
            $composer = json_decode(
                File::get($composerPath),
                true
            );

            if (is_array($composer)) {
                return $composer['require']['laravel/framework']
                    ?? 'unknown';
            }
        }

        return 'unknown';
    }

    /**
     * =========================================================
     * CURRENT MAJOR VERSION
     * =========================================================
     */
    public function getCurrentMajor(): int
    {
        $version = $this->getCurrentVersion();

        if (
            !preg_match(
                '/^(\d+)/',
                $version,
                $matches
            )
        ) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * =========================================================
     * AVAILABLE VERSIONS
     * =========================================================
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
     * =========================================================
     * VERSION CONSTRAINT
     * =========================================================
     */
    public function getVersionConstraint(
        string $targetVersion
    ): string {
        return $this->versionConstraints[$targetVersion]
            ?? '^' . $targetVersion . '.0';
    }

    /**
     * =========================================================
     * MINIMUM PHP VERSION
     * =========================================================
     */
    public function getMinimumPhpVersion(
        string $targetVersion
    ): string {
        return $this->phpRequirements[$targetVersion]
            ?? '8.0.0';
    }

    /**
     * =========================================================
     * START UPGRADE
     * =========================================================
     */
    public function startUpgrade(
        string $targetVersion
    ): LaravelUpgrade {
        return LaravelUpgrade::create([
            'current_version' => $this->getCurrentVersion(),
            'target_version'  => $targetVersion,
            'status'          => 'running',
            'output'          => '',
            'started_at'      => now(),
        ]);
    }

    /**
     * =========================================================
     * ENVIRONMENT COMPATIBILITY CHECK
     * =========================================================
     */
    public function checkEnvironment(
        string $targetVersion
    ): array {
        $results = [];

        /**
         * PHP VERSION
         */
        $currentPhp = PHP_VERSION;

        $minimumPhp = $this->getMinimumPhpVersion(
            $targetVersion
        );

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
            'status' => $phpCompatible
                ? 'pass'
                : 'error',
            'message' => $phpCompatible
                ? "PHP {$currentPhp} is compatible with Laravel {$targetVersion}.x."
                : "PHP {$currentPhp} is too old for Laravel {$targetVersion}.x. PHP {$minimumPhp}+ is required.",
        ];

        /**
         * PHP EXTENSIONS
         */
        foreach ($this->requiredExtensions as $extension) {
            $loaded = extension_loaded($extension);

            $results[] = [
                'type' => 'extension',
                'name' => "PHP Extension: {$extension}",
                'current' => $loaded
                    ? 'Installed'
                    : 'Missing',
                'required' => 'Required',
                'status' => $loaded
                    ? 'pass'
                    : 'error',
                'message' => $loaded
                    ? "{$extension} extension is installed."
                    : "{$extension} extension is missing.",
            ];
        }

        /**
         * COMPOSER
         */
        $composerPath = $this->findComposerBinary();

        $composerExists = $composerPath !== null;

        $results[] = [
            'type' => 'composer',
            'name' => 'Composer',
            'current' => $composerExists
                ? 'Available'
                : 'Not Found',
            'required' => 'Required',
            'status' => $composerExists
                ? 'pass'
                : 'error',
            'message' => $composerExists
                ? "Composer executable was found at: {$composerPath}"
                : 'Composer executable could not be found.',
        ];

        /**
         * COMPOSER.JSON
         */
        $composerJsonExists = File::exists(
            base_path('composer.json')
        );

        $results[] = [
            'type' => 'file',
            'name' => 'composer.json',
            'current' => $composerJsonExists
                ? 'Found'
                : 'Missing',
            'required' => 'Required',
            'status' => $composerJsonExists
                ? 'pass'
                : 'error',
            'message' => $composerJsonExists
                ? 'composer.json was found.'
                : 'composer.json is missing.',
        ];

        return [
            'success' => !collect($results)
                ->contains(
                    fn ($item) =>
                    $item['status'] === 'error'
                ),

            'target_version' => $targetVersion,
            'current_php' => $currentPhp,
            'minimum_php' => $minimumPhp,
            'checks' => $results,
        ];
    }

    /**
     * =========================================================
     * DEPENDENCY REPORT
     * =========================================================
     */
    public function getDependencyReport(
        string $targetVersion
    ): array {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            return [
                'success' => false,
                'message' => 'composer.json was not found.',
                'dependencies' => [],
            ];
        }

        $composer = json_decode(
            File::get($composerPath),
            true
        );

        if (!is_array($composer)) {
            return [
                'success' => false,
                'message' => 'composer.json contains invalid JSON.',
                'dependencies' => [],
            ];
        }

        $dependencies = [];

        foreach (
            ($composer['require'] ?? []) as $name => $constraint
        ) {
            if ($name === 'laravel/framework') {
                $dependencies[] = [
                    'name' => $name,
                    'constraint' => $constraint,
                    'type' => 'runtime',
                    'status' => 'target',
                    'message' =>
                        "Will be changed to {$this->getVersionConstraint($targetVersion)}.",
                ];

                continue;
            }

            $dependencies[] = [
                'name' => $name,
                'constraint' => $constraint,
                'type' => 'runtime',
                'status' => 'review',
                'message' =>
                    'Dependency will be evaluated by Composer during dry-run.',
            ];
        }

        foreach (
            ($composer['require-dev'] ?? []) as $name => $constraint
        ) {
            $dependencies[] = [
                'name' => $name,
                'constraint' => $constraint,
                'type' => 'development',
                'status' => 'review',
                'message' =>
                    'Development dependency will be evaluated by Composer.',
            ];
        }

        return [
            'success' => true,
            'target_version' => $targetVersion,
            'dependencies' => $dependencies,
            'runtime_count' => count(
                $composer['require'] ?? []
            ),
            'dev_count' => count(
                $composer['require-dev'] ?? []
            ),
        ];
    }

    /**
     * =========================================================
     * COMPOSER DRY RUN
     * =========================================================
     */
    public function runComposerDryRun(
        string $targetVersion
    ): array {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            return [
                'success' => false,
                'output' => 'composer.json was not found.',
                'exit_code' => 1,
            ];
        }

        $composer = json_decode(
            File::get($composerPath),
            true
        );

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

        /**
         * Temporarily change Laravel constraint.
         */
        $composer['require']['laravel/framework'] =
            $targetConstraint;

        File::put(
            $composerPath,
            json_encode(
                $composer,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES
            ) . PHP_EOL
        );

        try {
            /**
             * Find Composer.
             */
            $composerBinary =
                $this->findComposerBinary();

            if (!$composerBinary) {
                return [
                    'success' => false,
                    'output' =>
                        "Composer executable could not be found.\n\n" .
                        "Please make sure Composer is installed and available.",
                    'exit_code' => 1,
                ];
            }

            /**
             * Build Composer command.
             */
            $command = $this->buildComposerCommand(
                $composerBinary,
                [
                    'update',
                    'laravel/framework',
                    '--with-all-dependencies',
                    '--dry-run',
                    '--no-interaction',
                    '--no-scripts',
                    '--no-audit',
                    '--prefer-dist',
                ]
            );

            $process = new Process(
                $command,
                base_path()
            );

            $process->setTimeout(900);

            $output = '';

            $process->run(
                function ($type, $buffer) use (&$output) {
                    $output .= $buffer;
                }
            );

            return [
                'success' => $process->isSuccessful(),
                'output' => $output,
                'exit_code' => $process->getExitCode(),
                'original_constraint' => $originalConstraint,
                'target_constraint' => $targetConstraint,
                'composer' => $composerBinary,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'output' =>
                    "Composer execution failed:\n\n" .
                    $e->getMessage(),
                'exit_code' => 1,
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
     * =========================================================
     * FIND COMPOSER
     * =========================================================
     *
     * IMPORTANT:
     *
     * On Windows Composer is normally composer.bat.
     *
     * Apache/PHP may have a different PATH from CMD.
     *
     * Therefore we:
     *
     * 1. Check COMPOSER environment variable.
     * 2. Check common Composer locations.
     * 3. Use where.exe composer.bat.
     * 4. Use where.exe composer.
     */
    private function findComposerBinary(): ?string
    {
        /**
         * -----------------------------------------------------
         * 1. COMPOSER environment variable
         * -----------------------------------------------------
         */
        $composerEnv = getenv('COMPOSER');

        if (
            $composerEnv &&
            File::exists($composerEnv)
        ) {
            return $composerEnv;
        }

        /**
         * -----------------------------------------------------
         * 2. Windows common locations
         * -----------------------------------------------------
         */
        if (PHP_OS_FAMILY === 'Windows') {
            $possiblePaths = [
                'C:\\ProgramData\\ComposerSetup\\bin\\composer.bat',

                'C:\\ProgramData\\ComposerSetup\\bin\\composer.exe',

                getenv('APPDATA')
                    ? getenv('APPDATA') . '\\Composer\\composer.bat'
                    : null,

                getenv('APPDATA')
                    ? getenv('APPDATA') . '\\Composer\\composer.phar'
                    : null,

                'C:\\composer\\composer.bat',

                'C:\\composer\\composer.phar',
            ];

            foreach ($possiblePaths as $path) {
                if (
                    $path &&
                    File::exists($path)
                ) {
                    return $path;
                }
            }
        }

        /**
         * -----------------------------------------------------
         * 3. Use WHERE command
         * -----------------------------------------------------
         */
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $process = new Process([
                    'where.exe',
                    'composer.bat',
                ]);

                $process->setTimeout(10);
                $process->run();

                if ($process->isSuccessful()) {
                    $lines = preg_split(
                        '/\r\n|\r|\n/',
                        trim($process->getOutput())
                    );

                    foreach ($lines as $line) {
                        $line = trim($line);

                        if (
                            $line !== '' &&
                            File::exists($line)
                        ) {
                            return $line;
                        }
                    }
                }

                /**
                 * Try composer.exe too.
                 */
                $process = new Process([
                    'where.exe',
                    'composer.exe',
                ]);

                $process->setTimeout(10);
                $process->run();

                if ($process->isSuccessful()) {
                    $lines = preg_split(
                        '/\r\n|\r|\n/',
                        trim($process->getOutput())
                    );

                    foreach ($lines as $line) {
                        $line = trim($line);

                        if (
                            $line !== '' &&
                            File::exists($line)
                        ) {
                            return $line;
                        }
                    }
                }

                /**
                 * Try composer.phar.
                 */
                $process = new Process([
                    'where.exe',
                    'composer.phar',
                ]);

                $process->setTimeout(10);
                $process->run();

                if ($process->isSuccessful()) {
                    $lines = preg_split(
                        '/\r\n|\r|\n/',
                        trim($process->getOutput())
                    );

                    foreach ($lines as $line) {
                        $line = trim($line);

                        if (
                            $line !== '' &&
                            File::exists($line)
                        ) {
                            return $line;
                        }
                    }
                }
            } else {
                /**
                 * Linux / macOS.
                 */
                $process = new Process([
                    'which',
                    'composer',
                ]);

                $process->setTimeout(10);
                $process->run();

                if ($process->isSuccessful()) {
                    $path = trim(
                        $process->getOutput()
                    );

                    if (
                        $path !== '' &&
                        File::exists($path)
                    ) {
                        return $path;
                    }
                }

                $unixCandidates = [
                    '/usr/local/bin/composer',
                    '/usr/bin/composer',
                ];

                foreach ($unixCandidates as $path) {
                    if (File::exists($path)) {
                        return $path;
                    }
                }
            }
        } catch (\Throwable $e) {
            /**
             * Continue to final fallback.
             */
        }

        /**
         * -----------------------------------------------------
         * 4. Final fallback
         * -----------------------------------------------------
         */
        return null;
    }

    /**
     * =========================================================
     * BUILD COMPOSER COMMAND
     * =========================================================
     *
     * Symfony Process cannot reliably execute composer.bat
     * directly on Windows from every PHP/Apache environment.
     *
     * Therefore:
     *
     * Windows composer.bat:
     *
     *     cmd.exe /D /S /C composer.bat ...
     *
     * Linux/macOS:
     *
     *     composer ...
     */
private function buildComposerCommand(
    string $composerBinary,
    array $arguments
): array {
    if (PHP_OS_FAMILY === 'Windows') {

        $extension = strtolower(
            pathinfo(
                $composerBinary,
                PATHINFO_EXTENSION
            )
        );

        /**
         * composer.bat / composer.cmd
         *
         * Execute through cmd.exe.
         */
        if (
            in_array(
                $extension,
                ['bat', 'cmd'],
                true
            )
        ) {
            $command = '"' . $composerBinary . '"';

            foreach ($arguments as $argument) {
                $command .= ' "' . $argument . '"';
            }

            return [
                'cmd.exe',
                '/D',
                '/C',
                $command,
            ];
        }

        /**
         * composer.phar
         */
        if ($extension === 'phar') {
            return array_merge(
                [
                    PHP_BINARY,
                    $composerBinary,
                ],
                $arguments
            );
        }
    }

    /**
     * Linux / macOS.
     */
    return array_merge(
        [$composerBinary],
        $arguments
    );
}

    /**
     * =========================================================
     * AUTOMATIC BACKUP
     * =========================================================
     */
    public function createBackup(
        LaravelUpgrade $upgrade
    ): array {
        $backupDirectory = storage_path(
            'app/upgrade-backups/' .
            $upgrade->id
        );

        File::ensureDirectoryExists(
            $backupDirectory
        );

        $backupFiles = [];

        /**
         * composer.json
         */
        $composerPath = base_path(
            'composer.json'
        );

        if (File::exists($composerPath)) {
            $destination =
                $backupDirectory .
                DIRECTORY_SEPARATOR .
                'composer.json';

            File::copy(
                $composerPath,
                $destination
            );

            $backupFiles[] = 'composer.json';
        }

        /**
         * composer.lock
         */
        $lockPath = base_path(
            'composer.lock'
        );

        if (File::exists($lockPath)) {
            $destination =
                $backupDirectory .
                DIRECTORY_SEPARATOR .
                'composer.lock';

            File::copy(
                $lockPath,
                $destination
            );

            $backupFiles[] = 'composer.lock';
        }

        return [
            'success' => count($backupFiles) > 0,
            'path' => $backupDirectory,
            'files' => $backupFiles,
        ];
    }

    /**
     * =========================================================
     * REAL UPGRADE
     * =========================================================
     */
    public function runUpgrade(
        LaravelUpgrade $upgrade
    ): void {
        $composerPath = base_path(
            'composer.json'
        );

        $lockPath = base_path(
            'composer.lock'
        );

        if (!File::exists($composerPath)) {
            $upgrade->update([
                'status' => 'failed',
                'output' =>
                    'composer.json was not found.',
                'completed_at' => now(),
            ]);

            return;
        }

        $composer = json_decode(
            File::get($composerPath),
            true
        );

        if (!is_array($composer)) {
            $upgrade->update([
                'status' => 'failed',
                'output' =>
                    'Invalid composer.json.',
                'completed_at' => now(),
            ]);

            return;
        }

        $originalComposer = $composer;

        $originalConstraint =
            $composer['require']['laravel/framework']
            ?? null;

        $targetMajor =
            (string) $upgrade->target_version;

        /**
         * -----------------------------------------------------
         * Environment check
         * -----------------------------------------------------
         */
        $environment = $this->checkEnvironment(
            $targetMajor
        );

        if (!$environment['success']) {
            $messages = collect(
                $environment['checks']
            )
                ->where('status', 'error')
                ->pluck('message')
                ->implode("\n");

            $upgrade->update([
                'status' => 'failed',
                'output' =>
                    "Environment compatibility check failed.\n\n" .
                    $messages,
                'completed_at' => now(),
            ]);

            return;
        }

        /**
         * -----------------------------------------------------
         * AUTOMATIC BACKUP
         * -----------------------------------------------------
         */
        $backup = $this->createBackup(
            $upgrade
        );

        $output =
            "=================================================\n";

        $output .=
            "Laravel Upgrade Started\n";

        $output .=
            "=================================================\n\n";

        $output .=
            "Current Laravel: " .
            $this->getCurrentVersion() .
            "\n";

        $output .=
            "Target Laravel: {$targetMajor}.x\n";

        $output .=
            "Started: " .
            now()->format('Y-m-d H:i:s') .
            "\n\n";

        if ($backup['success']) {
            $output .=
                "Backup created successfully.\n";

            $output .=
                "Backup location: {$backup['path']}\n";

            $output .=
                "Backup files: " .
                implode(
                    ', ',
                    $backup['files']
                ) .
                "\n\n";
        } else {
            $output .=
                "WARNING: No Composer files were available for backup.\n\n";
        }

        /**
         * -----------------------------------------------------
         * Update composer.json
         * -----------------------------------------------------
         */
        $newConstraint =
            $this->getVersionConstraint(
                $targetMajor
            );

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

        $output .=
            "Upgrading Laravel " .
            $this->getCurrentMajor() .
            ".x -> {$targetMajor}.x\n";

        $output .=
            "Constraint: {$originalConstraint} -> {$newConstraint}\n\n";

        $upgrade->update([
            'output' => $output,
        ]);

        /**
         * -----------------------------------------------------
         * Backup current composer.lock
         * -----------------------------------------------------
         */
        $lockBackup = $lockPath . '.bak';

        if (File::exists($lockPath)) {
            File::copy(
                $lockPath,
                $lockBackup
            );
        }

        /**
         * -----------------------------------------------------
         * Cached lock
         * -----------------------------------------------------
         */
        $cachedLock = storage_path(
            "app/locks/laravel-{$targetMajor}.lock"
        );

        if (File::exists($cachedLock)) {
            $output .=
                "Found cached lock file for Laravel {$targetMajor}.x\n";

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
                "No cached lock file found.\n";

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

            /**
             * Cache successful lock.
             */
            if (
                $success &&
                File::exists($lockPath)
            ) {
                File::ensureDirectoryExists(
                    storage_path('app/locks')
                );

                File::copy(
                    $lockPath,
                    $cachedLock
                );

                $output .=
                    "\nLock file cached for future upgrades.\n";
            }
        }

        /**
         * -----------------------------------------------------
         * FINISH
         * -----------------------------------------------------
         */
        $upgrade->completed_at = now();

        if ($success) {
            $newVersion =
                $this->getCurrentVersion();

            $output .=
                "\nUpgrade completed successfully!\n";

            $output .=
                "Installed Laravel version: v{$newVersion}\n";

            $output .=
                "Backup retained at: {$backup['path']}\n";

            $upgrade->target_version =
                $newVersion;

            $upgrade->status =
                'completed';

            /**
             * Delete temporary lock backup.
             */
            if (File::exists($lockBackup)) {
                File::delete($lockBackup);
            }
        } else {
            $output .=
                "\nUpgrade failed.\n";

            $output .=
                "Rolling back composer.json and composer.lock...\n";

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

                File::delete(
                    $lockBackup
                );
            }

            $output .=
                "Rollback completed.\n";

            $output .=
                "Original backup retained at: {$backup['path']}\n";
        }

        $upgrade->output =
            $output;

        $upgrade->save();
    }

    /**
     * =========================================================
     * COMPOSER INSTALL
     * =========================================================
     */
    private function runComposerInstall(
        LaravelUpgrade $upgrade,
        string &$output
    ): bool {
        $composer =
            $this->findComposerBinary();

        if (!$composer) {
            $output .=
                "\nComposer executable not found.\n";

            $upgrade->update([
                'output' => $output,
            ]);

            return false;
        }

        try {
            $command =
                $this->buildComposerCommand(
                    $composer,
                    [
                        'install',
                        '--no-interaction',
                        '--no-scripts',
                        '--no-audit',
                        '--prefer-dist',
                    ]
                );

            $process = new Process(
                $command,
                base_path(),
                [
                    'COMPOSER_NO_INTERACTION' => '1',
                ]
            );

            $process->setTimeout(900);

            $process->run(
                function (
                    $type,
                    $buffer
                ) use (
                    &$output,
                    $upgrade
                ) {
                    $output .= $buffer;

                    $upgrade->update([
                        'output' => $output,
                    ]);
                }
            );

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            $output .=
                "\nComposer error:\n" .
                $e->getMessage() .
                "\n";

            $upgrade->update([
                'output' => $output,
            ]);

            return false;
        }
    }

    /**
     * =========================================================
     * COMPOSER UPDATE
     * =========================================================
     */
    private function runComposerUpdate(
        LaravelUpgrade $upgrade,
        string &$output
    ): bool {
        $composer =
            $this->findComposerBinary();

        if (!$composer) {
            $output .=
                "\nComposer executable not found.\n";

            $upgrade->update([
                'output' => $output,
            ]);

            return false;
        }

        try {
            $command =
                $this->buildComposerCommand(
                    $composer,
                    [
                        'update',
                        'laravel/framework',
                        '--with-all-dependencies',
                        '--no-interaction',
                        '--no-scripts',
                        '--no-audit',
                        '--prefer-dist',
                    ]
                );

            $process = new Process(
                $command,
                base_path(),
                [
                    'COMPOSER_NO_INTERACTION' => '1',
                ]
            );

            $process->setTimeout(900);

            $process->run(
                function (
                    $type,
                    $buffer
                ) use (
                    &$output,
                    $upgrade
                ) {
                    $output .= $buffer;

                    $upgrade->update([
                        'output' => $output,
                    ]);
                }
            );

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            $output .=
                "\nComposer error:\n" .
                $e->getMessage() .
                "\n";

            $upgrade->update([
                'output' => $output,
            ]);

            return false;
        }
    }
}
