<?php

namespace App\Console\Commands;

use App\Models\Garage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class FillGarageLocationData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'garage:fill-location-data 
                            {--limit= : Limit the number of garages to process}
                            {--dry-run : Show what would be updated without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill area, city, block, and governate columns for garages using AI to extract from address column';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;
        $dryRun = $this->option('dry-run');
        $verbose = $this->getOutput()->isVerbose();

        $this->info('Starting garage location data fill process...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be saved');
        }

        // Get approved garages where area, city, block, or governate is NULL/empty
        $query = Garage::where('status', 'approved')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('area')
                  ->orWhere('area', '')
                  ->orWhereNull('city')
                  ->orWhere('city', '')
                  ->orWhereNull('block')
                  ->orWhere('block', '')
                  ->orWhereNull('governate')
                  ->orWhere('governate', '');
            })
            ->whereNotNull('address')
            ->where('address', '!=', '');

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No garages found that need location data filling.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalCount} garage(s) to process.");

        if ($limit) {
            $query->limit($limit);
            $this->info("Processing limit: {$limit}");
        }

        $garages = $query->get();
        $processed = 0;
        $successful = 0;
        $failed = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($garages->count());
        $bar->start();

        foreach ($garages as $garage) {
            try {
                // Skip if all location fields are already filled
                if (!empty($garage->area) && !empty($garage->city) && !empty($garage->block) && !empty($garage->governate)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Extract location data from address using OpenAI
                $locationData = $this->extractLocationFromAddress($garage->address, $garage->id, $verbose);

                if ($locationData) {
                    $updateData = [];

                    // Only update if the field is empty/null
                    if (empty($garage->area) && !empty($locationData['area'])) {
                        $updateData['area'] = $locationData['area'];
                    }

                    if (empty($garage->city) && !empty($locationData['city'])) {
                        $updateData['city'] = $locationData['city'];
                    }

                    if (empty($garage->block) && !empty($locationData['block'])) {
                        $updateData['block'] = $locationData['block'];
                    }

                    if (empty($garage->governate) && !empty($locationData['governate'])) {
                        $updateData['governate'] = $locationData['governate'];
                    }

                    if (!empty($updateData) && !$dryRun) {
                        $garage->update($updateData);
                        $successful++;
                        if ($verbose) {
                            $this->newLine();
                            $this->info("✓ Updated Garage ID {$garage->id}: " . json_encode($updateData));
                        }
                    } elseif (!empty($updateData)) {
                        $this->newLine();
                        $this->line("Would update Garage ID {$garage->id}: " . json_encode($updateData));
                        $successful++;
                    } else {
                        $skipped++;
                        if ($verbose) {
                            $this->newLine();
                            $this->comment("⊘ Skipped Garage ID {$garage->id} - No fields to update. Extracted: " . json_encode($locationData));
                        }
                    }
                } else {
                    $failed++;
                    if ($verbose) {
                        $this->newLine();
                        $this->error("✗ Failed Garage ID {$garage->id}, Address: {$garage->address}");
                    }
                    Log::warning("Failed to extract location data for garage ID: {$garage->id}, Address: {$garage->address}");
                }

                $processed++;

                // Delay 1 second between API calls
                if ($processed < $garages->count()) {
                    sleep(1);
                }

            } catch (\Exception $e) {
                $failed++;
                Log::error("Error processing garage ID {$garage->id}: " . $e->getMessage());
                $this->newLine();
                $this->error("Error processing garage ID {$garage->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Processing completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Total Processed', $processed],
                ['Successfully Updated', $successful],
                ['Failed', $failed],
                ['Skipped', $skipped],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Extract area, city, block, and governate from address using OpenAI
     *
     * @param string $address
     * @param int|null $garageId For logging purposes
     * @param bool $verbose Show verbose output
     * @return array|null Returns ['area' => string, 'city' => string, 'block' => string, 'governate' => string] or null on failure
     */
    private function extractLocationFromAddress(string $address, ?int $garageId = null, bool $verbose = false): ?array
    {
        try {
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');
            
            if (empty($address)) {
                Log::warning("Empty address provided for garage ID: {$garageId}");
                return null;
            }
            
            $prompt = "Extract the area, city, block, and governate from this Kuwait address. IMPORTANT: Return all values in ENGLISH, even if the address is in Arabic. Translate Arabic location names to their English equivalents.

Address: {$address}

Example response format:
{
  \"area\": \"Salmiya\",
  \"city\": \"Kuwait City\",
  \"block\": \"12\",
  \"governate\": \"Hawalli\"
}

Return ONLY a valid JSON object with 'area', 'city', 'block', and 'governate' keys. If you cannot determine a value, use null for that key. Only return valid JSON. Do not include any markdown formatting or additional text.";

            // Check if API key is configured
            $apiKey = env('OPENAI_API_KEY');
            if (empty($apiKey)) {
                Log::error("OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.");
                if ($verbose) {
                    $this->error("OpenAI API key is not configured!");
                }
                return null;
            }
            
            // Validate API key format (should start with 'sk-')
            if (!str_starts_with($apiKey, 'sk-')) {
                Log::error("Invalid OpenAI API key format. API keys should start with 'sk-'. Current key starts with: " . substr($apiKey, 0, 10) . "...");
                if ($verbose) {
                    $this->error("Invalid API key format! OpenAI API keys must start with 'sk-'. Please check your OPENAI_API_KEY in .env file.");
                    $this->info("Get a valid API key from: https://platform.openai.com/account/api-keys");
                }
                return null;
            }

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that extracts location information (area, city, block, and governate) from Kuwait addresses. Always return values in ENGLISH, translating from Arabic if necessary. Always respond with valid JSON only containing area, city, block, and governate keys.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            
            if (empty($content)) {
                Log::warning("Empty response from OpenAI for garage ID: {$garageId}, Address: {$address}");
                return null;
            }

            // Parse JSON response
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("JSON parse error for garage ID {$garageId}: " . json_last_error_msg() . " | Response: {$content}");
                return null;
            }

            if (!is_array($data)) {
                Log::warning("Invalid response format for garage ID: {$garageId}, Response: {$content}");
                return null;
            }

            // Check if we got at least one valid field
            $hasValidData = false;
            $result = [
                'area' => null,
                'city' => null,
                'block' => null,
                'governate' => null,
            ];

            if (isset($data['area']) && $data['area'] !== null && $data['area'] !== '') {
                $result['area'] = $data['area'];
                $hasValidData = true;
            }

            if (isset($data['city']) && $data['city'] !== null && $data['city'] !== '') {
                $result['city'] = $data['city'];
                $hasValidData = true;
            }

            if (isset($data['block']) && $data['block'] !== null && $data['block'] !== '') {
                $result['block'] = $data['block'];
                $hasValidData = true;
            }

            if (isset($data['governate']) && $data['governate'] !== null && $data['governate'] !== '') {
                $result['governate'] = $data['governate'];
                $hasValidData = true;
            }

            if ($hasValidData) {
                return $result;
            }

            Log::warning("No valid location data extracted for garage ID: {$garageId}, Address: {$address}, Response: " . json_encode($data));
            return null;

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error("OpenAI extraction error for garage ID {$garageId}: {$errorMessage} | Address: {$address}");
            
            if ($verbose) {
                $this->newLine();
                $this->error("API Error for Garage ID {$garageId}: {$errorMessage}");
            }
            
            // Check for common API errors
            if (strpos($errorMessage, 'API key') !== false || strpos($errorMessage, 'authentication') !== false) {
                if ($verbose) {
                    $this->error("Please check your OPENAI_API_KEY in .env file");
                }
            }
            
            return null;
        }
    }
}

