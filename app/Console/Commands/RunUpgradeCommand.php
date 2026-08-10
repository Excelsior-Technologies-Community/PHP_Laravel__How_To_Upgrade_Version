<?php

namespace App\Console\Commands;

use App\Models\LaravelUpgrade;
use App\Services\LaravelUpgradeService;
use Illuminate\Console\Command;

class RunUpgradeCommand extends Command
{
    protected $signature = 'upgrade:run {id}';
    protected $description = 'Run a Laravel upgrade in the background';

    public function handle(LaravelUpgradeService $service): void
    {
        $upgrade = LaravelUpgrade::findOrFail($this->argument('id'));
        $service->runUpgrade($upgrade);
    }
}
