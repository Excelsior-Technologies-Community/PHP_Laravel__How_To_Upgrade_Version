<?php

namespace App\Http\Controllers;

use App\Models\LaravelUpgrade;
use App\Services\LaravelUpgradeService;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class LaravelUpgradeController extends Controller
{
    public function __construct(
        protected LaravelUpgradeService $upgradeService
    ) {}

    /**
     * Upgrade dashboard.
     */
    public function index()
    {
        $currentVersion =
            $this->upgradeService->getCurrentVersion();

        $availableVersions =
            $this->upgradeService->getAvailableVersions();

        $recentUpgrades =
            LaravelUpgrade::latest()
                ->take(10)
                ->get();

        return view(
            'laravel-upgrade.index',
            compact(
                'currentVersion',
                'availableVersions',
                'recentUpgrades'
            )
        );
    }

    /**
     * ---------------------------------------------------------
     * ENVIRONMENT CHECK
     * ---------------------------------------------------------
     */
    public function compatibility(Request $request)
    {
        $request->validate([
            'target_version' => [
                'required',
                'string',
                'in:9,10,11,12',
            ],
        ]);

        $targetVersion =
            $request->target_version;

        $currentMajor =
            $this->upgradeService->getCurrentMajor();

        if ($currentMajor === (int) $targetVersion) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Already on Laravel {$targetVersion}.x.",
            ], 422);
        }

        $environment =
            $this->upgradeService
                ->checkEnvironment($targetVersion);

        $dependencies =
            $this->upgradeService
                ->getDependencyReport($targetVersion);

        return response()->json([
            'success' =>
                $environment['success'],

            'environment' =>
                $environment,

            'dependencies' =>
                $dependencies,
        ]);
    }

    /**
     * ---------------------------------------------------------
     * COMPOSER DRY RUN
     * ---------------------------------------------------------
     */
    public function dryRun(Request $request)
    {
        $request->validate([
            'target_version' => [
                'required',
                'string',
                'in:9,10,11,12',
            ],
        ]);

        $targetVersion =
            $request->target_version;

        $currentMajor =
            $this->upgradeService->getCurrentMajor();

        if ($currentMajor === (int) $targetVersion) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Already on Laravel {$targetVersion}.x.",
            ], 422);
        }

        /**
         * First environment check.
         */
        $environment =
            $this->upgradeService
                ->checkEnvironment($targetVersion);

        if (!$environment['success']) {
            return response()->json([
                'success' => false,
                'type' => 'environment',
                'message' =>
                    'Environment compatibility check failed.',
                'environment' =>
                    $environment,
            ], 422);
        }

        /**
         * Then Composer dry-run.
         */
        $dryRun =
            $this->upgradeService
                ->runComposerDryRun($targetVersion);

        return response()->json([
            'success' =>
                $dryRun['success'],

            'message' =>
                $dryRun['success']
                    ? 'Composer dry-run completed successfully.'
                    : 'Composer found dependency conflicts.',

            'output' =>
                $dryRun['output'],

            'exit_code' =>
                $dryRun['exit_code'] ?? null,
        ], $dryRun['success'] ? 200 : 422);
    }

    /**
     * ---------------------------------------------------------
     * START REAL UPGRADE
     * ---------------------------------------------------------
     */
    public function upgrade(Request $request)
    {
        $request->validate([
            'target_version' => [
                'required',
                'string',
                'in:9,10,11,12',
            ],
        ]);

        $currentMajor =
            $this->upgradeService->getCurrentMajor();

        $targetMajor =
            (int) $request->target_version;

        if ($currentMajor === $targetMajor) {
            return response()->json([
                'success' => false,
                'message' =>
                    "Already on Laravel {$targetMajor}.x " .
                    "(v{$this->upgradeService->getCurrentVersion()})",
            ], 422);
        }

        /**
         * Clear stale running upgrades.
         */
        LaravelUpgrade::where('status', 'running')
            ->where(
                'started_at',
                '<',
                now()->subMinutes(15)
            )
            ->update([
                'status' => 'failed',
                'output' => 'Timed out',
                'completed_at' => now(),
            ]);

        /**
         * Prevent concurrent upgrades.
         */
        if (
            LaravelUpgrade::where(
                'status',
                'running'
            )->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Another upgrade is already in progress.',
            ], 422);
        }

        /**
         * Environment check before creating
         * the real upgrade.
         */
        $environment =
            $this->upgradeService
                ->checkEnvironment(
                    $request->target_version
                );

        if (!$environment['success']) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Environment compatibility check failed.',
                'environment' =>
                    $environment,
            ], 422);
        }

        /**
         * Create upgrade record.
         */
        $upgrade =
            $this->upgradeService
                ->startUpgrade(
                    $request->target_version
                );

        /**
         * Windows-compatible background process.
         */
        $phpBinary =
            PHP_BINARY;

        $artisanPath =
            base_path('artisan');

        $logPath =
            storage_path(
                'logs/upgrade-' .
                $upgrade->id .
                '.log'
            );

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(
                popen(
                    "start /B \"\" " .
                    "\"{$phpBinary}\" " .
                    "\"{$artisanPath}\" " .
                    "upgrade:run {$upgrade->id} " .
                    "> \"{$logPath}\" 2>&1",
                    'r'
                )
            );
        } else {
            $process = new Process([
                $phpBinary,
                $artisanPath,
                'upgrade:run',
                $upgrade->id,
            ]);

            $process->start();
        }

        return response()->json([
            'success' => true,
            'upgrade_id' => $upgrade->id,
            'message' => 'Upgrade started.',
        ]);
    }

    /**
     * Upgrade status.
     */
    public function status(LaravelUpgrade $upgrade)
    {
        $fresh =
            $upgrade->fresh();

        return response()->json([
            'status' =>
                $fresh->status,

            'output' =>
                $fresh->output,

            'completed_at' =>
                $fresh->completed_at?->diffForHumans(),
        ]);
    }

    /**
     * Real-time SSE stream.
     */
    public function stream(LaravelUpgrade $upgrade)
    {
        return response()->stream(
            function () use ($upgrade) {

                $lastLength = 0;

                $maxWait = 900;

                $waited = 0;

                while ($waited < $maxWait) {

                    $upgrade->refresh();

                    $output =
                        $upgrade->output ?? '';

                    $newChunk =
                        substr(
                            $output,
                            $lastLength
                        );

                    if ($newChunk !== '') {

                        $lastLength =
                            strlen($output);

                        echo 'data: ' .
                            json_encode([
                                'chunk' =>
                                    $newChunk,

                                'status' =>
                                    $upgrade->status,
                            ]) .
                            "\n\n";

                        @ob_flush();
                        flush();
                    }

                    if (
                        in_array(
                            $upgrade->status,
                            [
                                'completed',
                                'failed',
                            ]
                        )
                    ) {

                        echo 'data: ' .
                            json_encode([
                                'done' => true,
                                'status' =>
                                    $upgrade->status,
                            ]) .
                            "\n\n";

                        @ob_flush();
                        flush();

                        break;
                    }

                    usleep(300000);

                    $waited += 0.3;
                }
            },
            200,
            [
                'Content-Type' =>
                    'text/event-stream',

                'Cache-Control' =>
                    'no-cache',

                'X-Accel-Buffering' =>
                    'no',
            ]
        );
    }
}