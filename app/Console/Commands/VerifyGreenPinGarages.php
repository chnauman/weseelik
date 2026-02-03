<?php

namespace App\Console\Commands;

use App\Models\Garage;
use App\Services\MapGarageTitleMatchService;
use Illuminate\Console\Command;

class VerifyGreenPinGarages extends Command
{
    protected $signature = 'garages:verify-green-pins
                            {--dry-run : Only show what would be updated}
                            {--limit= : Max number of garages to check}';

    protected $description = 'Set location_verified to "verified" for all green-pinned garages (title matches map)';

    public function handle(MapGarageTitleMatchService $titleMatchService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = Garage::query()
            ->whereNull('deleted_at')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('maps_share_link');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $garages = $query->orderBy('id')->get();
        $total = $garages->count();

        if ($total === 0) {
            $this->info('No garages with location data found.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run: no changes will be saved.');
        }

        $this->info("Checking {$total} garage(s) for green-pin (title match)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $verified = 0;
        foreach ($garages as $garage) {
            if ($titleMatchService->titleMatches($garage)) {
                if (!$dryRun) {
                    $garage->update(['location_verified' => 'verified']);
                }
                $verified++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info($dryRun
            ? "Would set {$verified} garage(s) as verified."
            : "Set {$verified} garage(s) as verified.");

        return self::SUCCESS;
    }
}
