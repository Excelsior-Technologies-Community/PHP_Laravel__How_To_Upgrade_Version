<?php

namespace App\Http\Controllers;

use App\Models\LaravelUpgrade;
use App\Services\LaravelUpgradeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class LaravelUpgradeController extends Controller
{
    public function __construct(
        protected LaravelUpgradeService $upgradeService
    ) {}

    /**
     * =========================================================
     * DASHBOARD
     * =========================================================
     */
    public function index(Request $request)
    {
        $currentVersion =
            $this->upgradeService
            ->getCurrentVersion();

        $availableVersions =
            $this->upgradeService
            ->getAvailableVersions();

        /*
         * Feature #1:
         * Upgrade statistics.
         */
        $statistics = [
            'total' =>
            LaravelUpgrade::count(),

            'completed' =>
            LaravelUpgrade::where(
                'status',
                'completed'
            )->count(),

            'failed' =>
            LaravelUpgrade::where(
                'status',
                'failed'
            )->count(),

            'running' =>
            LaravelUpgrade::where(
                'status',
                'running'
            )->count(),
        ];

        /*
         * Feature #2:
         * Search + status + date filter.
         */
        $historyQuery =
            LaravelUpgrade::query();

        if ($request->filled('search')) {
            $search =
                trim(
                    $request->search
                );

            $historyQuery->where(
                function ($query) use ($search) {
                    $query
                        ->where(
                            'current_version',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'target_version',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'status',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('status')) {
            $historyQuery->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('date_from')) {
            $historyQuery->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $historyQuery->whereDate(
                'created_at',
                '<=',
                $request->date_to
            );
        }

        $recentUpgrades =
            $historyQuery
            ->oldest()
            ->paginate(5)
            ->withQueryString();

        return view(
            'laravel-upgrade.index',
            compact(
                'currentVersion',
                'availableVersions',
                'recentUpgrades',
                'statistics'
            )
        );
    }

    /**
     * Compatibility check.
     */
    public function compatibility(
        Request $request
    ) {
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
            $this->upgradeService
            ->getCurrentMajor();

        if (
            $currentMajor ===
            (int) $targetVersion
        ) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                "Already on Laravel {$targetVersion}.x.",
            ], 422);
        }

        $environment =
            $this->upgradeService
            ->checkEnvironment(
                $targetVersion
            );

        $dependencies =
            $this->upgradeService
            ->getDependencyReport(
                $targetVersion
            );

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
     * Composer dry run.
     */
    public function dryRun(
        Request $request
    ) {
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
            $this->upgradeService
            ->getCurrentMajor();

        if (
            $currentMajor ===
            (int) $targetVersion
        ) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                "Already on Laravel {$targetVersion}.x.",
            ], 422);
        }

        $environment =
            $this->upgradeService
            ->checkEnvironment(
                $targetVersion
            );

        if (!$environment['success']) {
            return response()->json([
                'success' =>
                false,

                'type' =>
                'environment',

                'message' =>
                'Environment compatibility check failed.',

                'environment' =>
                $environment,
            ], 422);
        }

        $dryRun =
            $this->upgradeService
            ->runComposerDryRun(
                $targetVersion
            );

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
     * Start real upgrade.
     */
    public function upgrade(
        Request $request
    ) {
        $request->validate([
            'target_version' => [
                'required',
                'string',
                'in:9,10,11,12',
            ],
        ]);

        $currentMajor =
            $this->upgradeService
            ->getCurrentMajor();

        $targetMajor =
            (int) $request->target_version;

        if (
            $currentMajor ===
            $targetMajor
        ) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                "Already on Laravel {$targetMajor}.x " .
                    "(v{$this->upgradeService->getCurrentVersion()})",
            ], 422);
        }

        /*
         * Clear stale running upgrades.
         */
        LaravelUpgrade::where(
            'status',
            'running'
        )
            ->where(
                'started_at',
                '<',
                now()->subMinutes(15)
            )
            ->update([
                'status' =>
                'failed',

                'output' =>
                'Timed out',

                'completed_at' =>
                now(),
            ]);

        /*
         * Prevent concurrent upgrades.
         */
        if (
            LaravelUpgrade::where(
                'status',
                'running'
            )->exists()
        ) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                'Another upgrade is already in progress.',
            ], 422);
        }

        /*
         * Environment check.
         */
        $environment =
            $this->upgradeService
            ->checkEnvironment(
                $request->target_version
            );

        if (!$environment['success']) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                'Environment compatibility check failed.',

                'environment' =>
                $environment,
            ], 422);
        }

        /*
         * Create record.
         */
        $upgrade =
            $this->upgradeService
            ->startUpgrade(
                $request->target_version
            );

        $this->startBackgroundUpgrade(
            $upgrade
        );

        return response()->json([
            'success' =>
            true,

            'upgrade_id' =>
            $upgrade->id,

            'message' =>
            'Upgrade started.',
        ]);
    }

    /**
     * =========================================================
     * FEATURE #5
     * RETRY FAILED UPGRADE
     * =========================================================
     */
    public function retry(
        LaravelUpgrade $upgrade
    ) {
        if (
            $upgrade->status !==
            'failed'
        ) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                'Only failed upgrades can be retried.',
            ], 422);
        }

        if (
            LaravelUpgrade::where(
                'status',
                'running'
            )->exists()
        ) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                'Another upgrade is already running.',
            ], 422);
        }

        $targetVersion =
            preg_replace(
                '/^v?(\d+)(\..*)?$/',
                '$1',
                (string) $upgrade->target_version
            );

        if (
            !in_array(
                $targetVersion,
                ['9', '10', '11', '12'],
                true
            )
        ) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                'Invalid target Laravel version.',
            ], 422);
        }

        /*
         * Create a new upgrade record.
         */
        $newUpgrade =
            $this->upgradeService
            ->startUpgrade(
                $targetVersion
            );

        $this->startBackgroundUpgrade(
            $newUpgrade
        );

        return response()->json([
            'success' =>
            true,

            'upgrade_id' =>
            $newUpgrade->id,

            'message' =>
            'Retry upgrade started.',
        ]);
    }

    /**
     * Start background upgrade.
     */
    private function startBackgroundUpgrade(
        LaravelUpgrade $upgrade
    ): void {
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

        if (
            PHP_OS_FAMILY ===
            'Windows'
        ) {
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
            $process =
                new Process([
                    $phpBinary,
                    $artisanPath,
                    'upgrade:run',
                    $upgrade->id,
                ]);

            $process->start();
        }
    }

    /**
     * Upgrade status.
     */
    public function status(
        LaravelUpgrade $upgrade
    ) {
        $fresh =
            $upgrade->fresh();

        return response()->json([
            'status' =>
            $fresh->status,

            'output' =>
            $fresh->output,

            'completed_at' =>
            $fresh->completed_at
                ?->diffForHumans(),
        ]);
    }

    /**
     * SSE stream.
     */
    public function stream(
        LaravelUpgrade $upgrade
    ) {
        return response()->stream(
            function () use ($upgrade) {

                $lastLength = 0;

                $maxWait = 900;

                $waited = 0;

                while (
                    $waited < $maxWait
                ) {
                    $upgrade->refresh();

                    $output =
                        $upgrade->output
                        ?? '';

                    $newChunk =
                        substr(
                            $output,
                            $lastLength
                        );

                    if (
                        $newChunk !== ''
                    ) {
                        $lastLength =
                            strlen(
                                $output
                            );

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
                                'done' =>
                                true,

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

    /**
     * =========================================================
     * FEATURE #3
     * EXPORT CSV
     * =========================================================
     */
    public function export(
        Request $request
    ): StreamedResponse {
        $query =
            LaravelUpgrade::query();

        if ($request->filled('search')) {
            $search =
                trim(
                    $request->search
                );

            $query->where(
                function ($q) use ($search) {
                    $q
                        ->where(
                            'current_version',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'target_version',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'status',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->date_to
            );
        }

        $filename =
            'laravel-upgrade-history-' .
            now()->format('Y-m-d-H-i-s') .
            '.csv';

        return response()->streamDownload(
            function () use ($query) {
                $handle =
                    fopen(
                        'php://output',
                        'w'
                    );

                fputcsv(
                    $handle,
                    [
                        'ID',
                        'Current Version',
                        'Target Version',
                        'Status',
                        'Started At',
                        'Completed At',
                        'Duration',
                        'Created At',
                    ]
                );

                $query
                    ->latest()
                    ->chunk(
                        500,
                        function ($upgrades) use (
                            $handle
                        ) {
                            foreach (
                                $upgrades
                                as $upgrade
                            ) {
                                fputcsv(
                                    $handle,
                                    [
                                        $upgrade->id,
                                        $upgrade->current_version,
                                        $upgrade->target_version,
                                        $upgrade->status,
                                        optional(
                                            $upgrade->started_at
                                        )->format(
                                            'Y-m-d H:i:s'
                                        ),
                                        optional(
                                            $upgrade->completed_at
                                        )->format(
                                            'Y-m-d H:i:s'
                                        ),
                                        $upgrade->duration_in_seconds
                                            ? $upgrade->duration_in_seconds . ' seconds'
                                            : '',
                                        optional(
                                            $upgrade->created_at
                                        )->format(
                                            'Y-m-d H:i:s'
                                        ),
                                    ]
                                );
                            }
                        }
                    );

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                'text/csv',
            ]
        );
    }

    /**
     * =========================================================
     * FEATURE #4
     * DELETE HISTORY
     * =========================================================
     */
    public function destroy(
        LaravelUpgrade $upgrade
    ) {
        if (
            $upgrade->status ===
            'running'
        ) {
            return response()->json([
                'success' =>
                false,

                'message' =>
                'A running upgrade cannot be deleted.',
            ], 422);
        }

        $upgrade->delete();

        return response()->json([
            'success' =>
            true,

            'message' =>
            'Upgrade history deleted successfully.',
        ]);
    }
}
