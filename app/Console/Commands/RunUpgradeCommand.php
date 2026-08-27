<?php

namespace App\Console\Commands;

use App\Models\LaravelUpgrade;
use App\Services\LaravelUpgradeService;
use Illuminate\Console\Command;

class RunUpgradeCommand extends Command
{
    protected $signature = 'upgrade:run {id}';

    protected $description =
        'Run a Laravel upgrade in the background';

    public function handle(
        LaravelUpgradeService $service
    ): int {
        $upgrade =
            LaravelUpgrade::find($this->argument('id'));

        if (!$upgrade) {
            $this->error(
                'Upgrade record not found.'
            );

            return self::FAILURE;
        }

        $service->runUpgrade($upgrade);

        return self::SUCCESS;
    }
}