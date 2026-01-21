<?php

namespace App\Console\Commands;

use App\Models\Garage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class BackfillGarageEnglishAndArabic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'garage:backfill-en-ar
                            {--limit= : Limit the number of garages to process}
                            {--chunk=100 : Chunk size for processing}
                            {--only-missing : Only fill title_ar/description_ar when they are empty (do not overwrite existing Arabic)}
                            {--dry-run : Show what would be updated without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate garages title/description into Kuwaiti Arabic and store in title_ar/description_ar. Does not modify title/description.';

    public function handle(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $chunk = $this->option('chunk') ? (int) $this->option('chunk') : 100;
        $onlyMissing = (bool) $this->option('only-missing');
        $dryRun = (bool) $this->option('dry-run');
        $verbose = $this->getOutput()->isVerbose();

        if ($chunk < 1) {
            $this->error('--chunk must be >= 1');
            return Command::FAILURE;
        }

        $this->info('Starting garages backfill (Kuwaiti Arabic translation)...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be saved.');
        }
        if ($onlyMissing) {
            $this->line('Mode: only fill missing Arabic fields (no overwrites).');
        } else {
            $this->line('Mode: overwrite Arabic fields from title/description (recommended for full refresh).');
        }

        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            $this->error('OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return Command::FAILURE;
        }

        $query = Garage::query()
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('title')->where('title', '!=', '')
                  ->orWhereNotNull('description')->where('description', '!=', '');
            });

        if ($onlyMissing) {
            $query->where(function ($q) {
                $q->whereNull('title_ar')
                    ->orWhereNull('description_ar')
                    ->orWhere('title_ar', '')
                    ->orWhere('description_ar', '');
            });
        }

        $totalQuery = clone $query;
        $total = $totalQuery->count();
        if ($total === 0) {
            $this->info('No garages to process.');
            return Command::SUCCESS;
        }

        $targetTotal = $limit ? min($limit, $total) : $total;
        $this->info("Garages to process: {$targetTotal}");

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($targetTotal);
        $bar->start();

        $stop = false;

        $query->orderBy('id')
            ->chunkById($chunk, function ($garages) use (&$processed, &$updated, &$skipped, &$failed, $dryRun, $verbose, $bar, $limit, $onlyMissing, &$stop) {
                foreach ($garages as $garage) {
                    if ($limit && $processed >= $limit) {
                        $stop = true;
                        break;
                    }
                    $processed++;

                    try {
                        $sourceTitle = trim((string) ($garage->title ?? ''));
                        $sourceDesc = trim((string) ($garage->description ?? ''));

                        if ($sourceTitle === '' && $sourceDesc === '') {
                            $skipped++;
                            $bar->advance();
                            continue;
                        }

                        // Keep English fields as-is; generate Arabic translations into *_ar.
                        // Default behavior: overwrite Arabic fields from source text.
                        // With --only-missing: only fill Arabic fields if currently empty.
                        $shouldTranslateTitle = $sourceTitle !== '' && (!$onlyMissing || empty($garage->title_ar));
                        $shouldTranslateDesc = $sourceDesc !== '' && (!$onlyMissing || empty($garage->description_ar));

                        $arabicTitle = $shouldTranslateTitle
                            ? $this->translateEnglishToKuwaitiArabic($sourceTitle, $garage->id, 'title', $verbose)
                            : null;
                        $arabicDesc = $shouldTranslateDesc
                            ? $this->translateEnglishToKuwaitiArabic($sourceDesc, $garage->id, 'description', $verbose)
                            : null;

                        // If translation fails, keep existing title_ar/description_ar as-is (don't overwrite with null)
                        $updateData = [];
                        if ($arabicTitle) {
                            $updateData['title_ar'] = $arabicTitle;
                        }
                        if ($arabicDesc) {
                            $updateData['description_ar'] = $arabicDesc;
                        }

                        if (empty($updateData)) {
                            if ($verbose) {
                                $this->newLine();
                                $this->warn("Skipped garage #{$garage->id}: nothing to update (source empty, --only-missing and Arabic exists, or translation failed).");
                            }
                            $skipped++;
                            $bar->advance();
                            continue;
                        }

                        if ($dryRun) {
                            if ($verbose) {
                                $this->newLine();
                                $this->line("Garage #{$garage->id}");
                                $this->line("  title      : {$sourceTitle}");
                                $this->line("  title_ar   : " . ($updateData['title_ar'] ?? $garage->title_ar ?? '(unchanged)'));
                                $this->line("  description: {$sourceDesc}");
                                $this->line("  desc_ar    : " . ($updateData['description_ar'] ?? $garage->description_ar ?? '(unchanged)'));
                            }
                        } else {
                            $garage->update($updateData);
                        }

                        $updated++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error("garage:backfill-en-ar failed for garage_id={$garage->id}: {$e->getMessage()}");
                        if ($verbose) {
                            $this->newLine();
                            $this->error("Failed garage #{$garage->id}: {$e->getMessage()}");
                        }
                    } finally {
                        $bar->advance();
                    }
                }

                if ($stop) {
                    return false; // stops chunkById
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("Done. processed={$processed}, updated={$updated}, skipped={$skipped}, failed={$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function translateEnglishToKuwaitiArabic(string $englishText, ?int $garageId = null, string $fieldName = 'text', bool $verbose = false): ?string
    {
        if (trim($englishText) === '') {
            return null;
        }

        try {
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');

            $fieldContext = match ($fieldName) {
                'title' => 'garage/business title in Kuwait',
                'description' => 'garage/business description in Kuwait',
                default => 'text',
            };

            $system = "You are a professional translator into Kuwaiti Arabic (Gulf dialect, Kuwait).
- Output ONLY the Arabic translation.
- Keep brand names, phone numbers, addresses, and URLs unchanged.
- Use common Kuwaiti terms where natural, but keep it understandable.";

            $prompt = "Translate the following {$fieldContext} into Kuwaiti Arabic. Return ONLY the Arabic translation:\n\n{$englishText}";

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            $content = trim($content, "\"'\n\r\t ");

            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            Log::error("OpenAI translateEnglishToKuwaitiArabic error for garage_id={$garageId}, field={$fieldName}: {$e->getMessage()} | text={$englishText}");
            if ($verbose) {
                $this->newLine();
                $this->error("OpenAI error (Arabic translate) garage #{$garageId}, {$fieldName}: {$e->getMessage()}");
            }
            return null;
        }
    }
}

