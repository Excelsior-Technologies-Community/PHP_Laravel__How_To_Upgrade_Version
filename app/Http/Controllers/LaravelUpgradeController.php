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

    public function index()
    {
        $currentVersion    = $this->upgradeService->getCurrentVersion();
        $availableVersions = $this->upgradeService->getAvailableVersions();
        $recentUpgrades    = LaravelUpgrade::latest()->take(10)->get();

        return view('laravel-upgrade.index', compact(
            'currentVersion',
            'availableVersions',
            'recentUpgrades'
        ));
    }

    public function upgrade(Request $request)
    {
        $request->validate(['target_version' => 'required|string|in:9,10,11,12']);

        $currentMajor = $this->upgradeService->getCurrentMajor();
        $targetMajor  = (int) $request->target_version;

        if ($currentMajor === $targetMajor) {
            return response()->json([
                'success' => false,
                'message' => "Already on Laravel {$targetMajor}.x (v" . $this->upgradeService->getCurrentVersion() . ")",
            ], 422);
        }

        // Clear any stale running records older than 15 minutes
        LaravelUpgrade::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(15))
            ->update(['status' => 'failed', 'output' => 'Timed out', 'completed_at' => now()]);

        if (LaravelUpgrade::where('status', 'running')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Another upgrade is already in progress.',
            ], 422);
        }

        $upgrade = $this->upgradeService->startUpgrade($request->target_version);

        // Windows-compatible background process
        $phpBinary   = PHP_BINARY;
        $artisanPath = base_path('artisan');
        $logPath     = storage_path('logs/upgrade-' . $upgrade->id . '.log');

        // Use start /B on Windows to detach process
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen(
                "start /B \"\" \"{$phpBinary}\" \"{$artisanPath}\" upgrade:run {$upgrade->id} > \"{$logPath}\" 2>&1",
                'r'
            ));
        } else {
            $process = new Process([$phpBinary, $artisanPath, 'upgrade:run', $upgrade->id]);
            $process->start();
        }

        return response()->json([
            'success'    => true,
            'upgrade_id' => $upgrade->id,
            'message'    => 'Upgrade started.',
        ]);
    }

    public function status(LaravelUpgrade $upgrade)
    {
        $fresh = $upgrade->fresh();
        return response()->json([
            'status'       => $fresh->status,
            'output'       => $fresh->output,
            'completed_at' => $fresh->completed_at?->diffForHumans(),
        ]);
    }

    public function stream(LaravelUpgrade $upgrade)
    {
        return response()->stream(function () use ($upgrade) {
            $lastLength = 0;
            $maxWait    = 900; // 15 min timeout
            $waited     = 0;

            while ($waited < $maxWait) {
                $upgrade->refresh();
                $output   = $upgrade->output ?? '';
                $newChunk = substr($output, $lastLength);

                if ($newChunk !== '') {
                    $lastLength = strlen($output);
                    echo 'data: ' . json_encode(['chunk' => $newChunk, 'status' => $upgrade->status]) . "\n\n";
                    ob_flush();
                    flush();
                }

                if (in_array($upgrade->status, ['completed', 'failed'])) {
                    echo 'data: ' . json_encode(['done' => true, 'status' => $upgrade->status]) . "\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                usleep(300000); // 300ms
                $waited += 0.3;
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
