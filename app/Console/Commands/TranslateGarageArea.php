<?php

namespace App\Console\Commands;

use App\Models\Garage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class TranslateGarageArea extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'garage:translate-area 
                            {--limit= : Limit the number of garages to process}
                            {--dry-run : Show what would be updated without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate non-English area, title, and description to English and add Arabic translations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;
        $dryRun = $this->option('dry-run');
        $verbose = $this->getOutput()->isVerbose();

        $this->info('Starting garage translation process (area, title, description)...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be saved');
        }

        // Get all garages (we'll check each field individually)
        $query = Garage::whereNull('deleted_at');

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No garages found.');
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
                $updateData = [];
                $hasUpdates = false;
                $garageUpdated = false;

                // Process Area
                if (!empty($garage->area)) {
                    $areaResult = $this->processField($garage->area, 'area', $garage->id, $verbose);
                    if ($areaResult) {
                        $updateData['area'] = $areaResult['en'];
                        if (!empty($areaResult['ar'])) {
                            // Note: area doesn't have _ar column, so we skip it
                        }
                        $hasUpdates = true;
                    }
                }

                // Process Title
                if (!empty($garage->title)) {
                    $titleResult = $this->processField($garage->title, 'title', $garage->id, $verbose);
                    if ($titleResult) {
                        // Update title if it's non-English (needs translation to English)
                        if (!$this->isEnglish($garage->title)) {
                            $updateData['title'] = $titleResult['en'];
                        }
                        // Always add/update Arabic translation
                        if (!empty($titleResult['ar'])) {
                            $updateData['title_ar'] = $titleResult['ar'];
                        }
                        $hasUpdates = true;
                    }
                }

                // Process Description
                if (!empty($garage->description)) {
                    $descriptionResult = $this->processField($garage->description, 'description', $garage->id, $verbose);
                    if ($descriptionResult) {
                        // Update description if it's non-English (needs translation to English)
                        if (!$this->isEnglish($garage->description)) {
                            $updateData['description'] = $descriptionResult['en'];
                        }
                        // Always add/update Arabic translation
                        if (!empty($descriptionResult['ar'])) {
                            $updateData['description_ar'] = $descriptionResult['ar'];
                        }
                        $hasUpdates = true;
                    }
                }

                if ($hasUpdates && !empty($updateData)) {
                    if (!$dryRun) {
                        $garage->update($updateData);
                        $successful++;
                        if ($verbose) {
                            $this->newLine();
                            $this->info("✓ Updated Garage ID {$garage->id}: " . json_encode($updateData));
                        }
                    } else {
                        $this->newLine();
                        $this->line("Would update Garage ID {$garage->id}: " . json_encode($updateData));
                        $successful++;
                    }
                    $garageUpdated = true;
                } else {
                    $skipped++;
                    if ($verbose) {
                        $this->newLine();
                        $this->comment("⊘ Skipped Garage ID {$garage->id} - No updates needed");
                    }
                }

                $processed++;

                // Delay 1 second between API calls (only if we made updates)
                if ($garageUpdated && $processed < $garages->count()) {
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
                ['Successfully Translated', $successful],
                ['Failed', $failed],
                ['Skipped (Already English)', $skipped],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Check if text is already in English
     * 
     * @param string $text
     * @return bool
     */
    private function isEnglish(string $text): bool
    {
        // Remove whitespace for checking
        $text = trim($text);
        
        if (empty($text)) {
            return true;
        }

        // Check for Arabic characters (Unicode range 0600-06FF) - most common non-English in Kuwait
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return false;
        }

        // Check for other common non-Latin scripts
        // Cyrillic: 0400-04FF
        // Chinese/Japanese/Korean: 4E00-9FFF, 3040-309F, 30A0-30FF, AC00-D7AF
        if (preg_match('/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $text)) {
            return false;
        }

        // Check if text contains only ASCII characters (basic Latin alphabet, numbers, and common punctuation)
        // If it's pure ASCII with at least one letter, consider it English
        if (mb_check_encoding($text, 'ASCII')) {
            // Must contain at least one letter (not just numbers or punctuation)
            if (preg_match('/[a-zA-Z]/', $text)) {
                return true;
            }
        }

        // If text is not pure ASCII or doesn't contain letters, translate it
        return false;
    }

    /**
     * Process a field: detect language, translate to English if needed, and translate to Arabic
     *
     * @param string $text
     * @param string $fieldName
     * @param int|null $garageId
     * @param bool $verbose
     * @return array|null Returns ['en' => string, 'ar' => string] or null on failure
     */
    private function processField(string $text, string $fieldName, ?int $garageId = null, bool $verbose = false): ?array
    {
        if (empty(trim($text))) {
            return null;
        }

        $isEnglish = $this->isEnglish($text);
        $result = ['en' => '', 'ar' => ''];

        if ($isEnglish) {
            // Text is English, keep it and translate to Arabic
            $result['en'] = $text;
            $result['ar'] = $this->translateToArabic($text, $garageId, $fieldName, $verbose) ?? $text;
        } else {
            // Text is non-English, translate to English and keep original as Arabic
            $english = $this->translateToEnglish($text, $garageId, $fieldName, $verbose);
            if ($english) {
                $result['en'] = $english;
                $result['ar'] = $text; // Keep original non-English text as Arabic
            } else {
                // If translation fails, keep original
                $result['en'] = $text;
                $result['ar'] = $text;
            }
        }

        return $result;
    }

    /**
     * Translate text to English using OpenAI
     *
     * @param string $text
     * @param int|null $garageId For logging purposes
     * @param string $fieldName Field name for logging
     * @param bool $verbose Show verbose output
     * @return string|null
     */
    private function translateToEnglish(string $text, ?int $garageId = null, string $fieldName = 'text', bool $verbose = false): ?string
    {
        try {
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');
            
            if (empty($text)) {
                Log::warning("Empty text provided for garage ID: {$garageId}");
                return null;
            }
            
            $fieldContext = match($fieldName) {
                'area' => 'location/area name from Kuwait',
                'title' => 'garage/business title',
                'description' => 'garage/business description',
                default => 'text'
            };

            $prompt = "Translate the following {$fieldContext} to English. Return ONLY the English translation, nothing else. Do not include any explanations, notes, or additional text.

Text to translate: {$text}

English translation:";

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
                        'content' => 'You are a professional translator. Translate the given text to English. Return only the English translation, no explanations or additional text.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            
            if (empty($content)) {
                Log::warning("Empty response from OpenAI for garage ID: {$garageId}, Area: {$text}");
                return null;
            }

            // Clean up the response (remove any quotes or extra formatting)
            $content = trim($content, '"\'');
            $content = trim($content);

            return $content;

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error("OpenAI translation error for garage ID {$garageId}, field {$fieldName}: {$errorMessage} | Text: {$text}");
            
            if ($verbose) {
                $this->newLine();
                $this->error("API Error for Garage ID {$garageId}, field {$fieldName}: {$errorMessage}");
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

    /**
     * Translate text to Arabic using OpenAI
     *
     * @param string $text
     * @param int|null $garageId For logging purposes
     * @param string $fieldName Field name for logging
     * @param bool $verbose Show verbose output
     * @return string|null
     */
    private function translateToArabic(string $text, ?int $garageId = null, string $fieldName = 'text', bool $verbose = false): ?string
    {
        try {
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');
            
            if (empty($text)) {
                Log::warning("Empty text provided for garage ID: {$garageId}, field: {$fieldName}");
                return null;
            }
            
            $fieldContext = match($fieldName) {
                'area' => 'location/area name',
                'title' => 'garage/business title',
                'description' => 'garage/business description',
                default => 'text'
            };

            $prompt = "Translate the following {$fieldContext} to Arabic. Return ONLY the Arabic translation, nothing else. Do not include any explanations, notes, or additional text.

Text to translate: {$text}

Arabic translation:";

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
                        'content' => 'You are a professional translator. Translate the given text to Arabic. Return only the Arabic translation, no explanations or additional text.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            
            if (empty($content)) {
                Log::warning("Empty response from OpenAI for garage ID: {$garageId}, field: {$fieldName}, Text: {$text}");
                return null;
            }

            // Clean up the response (remove any quotes or extra formatting)
            $content = trim($content, '"\'');
            $content = trim($content);

            return $content;

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error("OpenAI translation to Arabic error for garage ID {$garageId}, field {$fieldName}: {$errorMessage} | Text: {$text}");
            
            if ($verbose) {
                $this->newLine();
                $this->error("API Error for Garage ID {$garageId}, field {$fieldName}: {$errorMessage}");
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

