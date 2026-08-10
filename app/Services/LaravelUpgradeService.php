<?php

namespace App\Services;

use App\Models\LaravelUpgrade;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class LaravelUpgradeService
{
    private array $versionConstraints = [
        '12' => '^12.0',
        '11' => '^11.0',
        '10' => '^10.0',
        '9'  => '^9.0',
    ];

    public function getCurrentVersion(): string
    {
        $installed = base_path('vendor/composer/installed.json');

        if (File::exists($installed)) {
            $data     = json_decode(File::get($installed), true);
            $packages = $data['packages'] ?? $data;
            foreach ($packages as $pkg) {
                if (($pkg['name'] ?? '') === 'laravel/framework') {
                    return ltrim($pkg['version'], 'v');
                }
            }
        }

        $composer = json_decode(File::get(base_path('composer.json')), true);
        return $composer['require']['laravel/framework'] ?? 'unknown';
    }

    public function getCurrentMajor(): int
    {
        return (int) explode('.', $this->getCurrentVersion())[0];
    }

    public function getAvailableVersions(): array
    {
        return [
            '12' => 'Laravel 12.x',
            '11' => 'Laravel 11.x',
            '10' => 'Laravel 10.x',
            '9'  => 'Laravel 9.x',
        ];
    }

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

    public function runUpgrade(LaravelUpgrade $upgrade): void
    {
        $composerPath       = base_path('composer.json');
        $lockPath           = base_path('composer.lock');
        $composer           = json_decode(File::get($composerPath), true);
        $originalConstraint = $composer['require']['laravel/framework'];
        $targetMajor        = $upgrade->target_version;
        $newConstraint      = $this->versionConstraints[$targetMajor] ?? ('^' . $targetMajor . '.0');

        // Backup current lock file
        $lockBackup = $lockPath . '.bak';
        if (File::exists($lockPath)) {
            File::copy($lockPath, $lockBackup);
        }

        // Update composer.json
        $composer['require']['laravel/framework'] = $newConstraint;
        File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $output = "$ Upgrading Laravel {$this->getCurrentMajor()}.x → {$targetMajor}.x\n";
        $output .= "  Constraint: {$originalConstraint} → {$newConstraint}\n\n";
        $upgrade->update(['output' => $output]);

        // Check if we have a cached lock file for this target version
        $cachedLock = storage_path("app/locks/laravel-{$targetMajor}.lock");

        if (File::exists($cachedLock)) {
            $output .= "⚡ Found cached lock file for Laravel {$targetMajor}.x — using fast install\n\n";
            $upgrade->update(['output' => $output]);

            File::copy($cachedLock, $lockPath);
            $success = $this->runComposerInstall($upgrade, $output);
        } else {
            $output .= "📦 No cache found — running composer update (this takes a few minutes)\n\n";
            $upgrade->update(['output' => $output]);

            $success = $this->runComposerUpdate($upgrade, $output);

            // Cache the lock file for future use
            if ($success) {
                File::ensureDirectoryExists(storage_path('app/locks'));
                File::copy($lockPath, $cachedLock);
                $output .= "\n💾 Lock file cached for future fast upgrades to Laravel {$targetMajor}.x\n";
            }
        }

        $upgrade->completed_at = now();

        if ($success) {
            $newVersion              = $this->getCurrentVersion();
            $output                 .= "\n✔ Done! Installed: v{$newVersion}\n";
            $upgrade->target_version = $newVersion;
            $upgrade->status         = 'completed';
            // Remove backup
            File::delete($lockBackup);
        } else {
            $output .= "\n✘ Failed. Rolling back...\n";
            $upgrade->status = 'failed';
            // Restore backup
            $composer['require']['laravel/framework'] = $originalConstraint;
            File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            if (File::exists($lockBackup)) {
                File::copy($lockBackup, $lockPath);
                File::delete($lockBackup);
            }
        }

        $upgrade->output = $output;
        $upgrade->save();
    }

    private function runComposerInstall(LaravelUpgrade $upgrade, string &$output): bool
    {
        $process = new Process([
            'composer', 'install',
            '--no-interaction',
            '--no-scripts',
            '--no-audit',
            '--prefer-dist',
        ], base_path(), ['COMPOSER_NO_INTERACTION' => '1']);

        $process->setTimeout(600);
        $process->run(function ($type, $buffer) use (&$output, $upgrade) {
            $output .= $buffer;
            if (str_contains($buffer, "\n")) {
                $upgrade->update(['output' => $output]);
            }
        });

        $upgrade->update(['output' => $output]);
        return $process->isSuccessful();
    }

    private function runComposerUpdate(LaravelUpgrade $upgrade, string &$output): bool
    {
        $process = new Process([
            'composer', 'update', 'laravel/framework',
            '--with-all-dependencies',
            '--no-interaction',
            '--no-scripts',
            '--no-audit',
            '--prefer-dist',
        ], base_path(), ['COMPOSER_NO_INTERACTION' => '1']);

        $process->setTimeout(900);
        $process->run(function ($type, $buffer) use (&$output, $upgrade) {
            $output .= $buffer;
            if (str_contains($buffer, "\n")) {
                $upgrade->update(['output' => $output]);
            }
        });

        $upgrade->update(['output' => $output]);
        return $process->isSuccessful();
    }
}
