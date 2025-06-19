<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;

class AdDetection
{
    protected Main $main;
    protected string $api_key;
    protected float $last_transcription_cost = 0.0;
    protected float $last_detection_cost = 0.0;
    protected ?array $model_info_cache = null;
    
    // Whisper API file size limit (25MB with some buffer)
    const WHISPER_FILE_SIZE_LIMIT = 24 * 1024 * 1024; // 24MB to be safe
    const CHUNK_DURATION_SECONDS = 600; // 10 minutes per chunk
    const MAX_CHUNK_DURATION_SECONDS = 300; // 5 minutes max for better reliability
    
    // Default model for ad detection
    const AD_DETECTION_MODEL = 'gpt-4o-mini';
    
    public function __construct(Main $main)
    {
        $this->main = $main;
        $this->api_key = strval($this->main->getConf('podsumer', 'openai_api_key'));
        
        if (empty($this->api_key)) {
            throw new Exception('OpenAI API key not configured');
        }
    }
    
    /**
     * Get the ad detection model from configuration
     */
    protected function getAdDetectionModel(): string
    {
        return strval($this->main->getConf('podsumer', 'openai_ad_detection_model') ?? self::AD_DETECTION_MODEL);
    }
    
    /**
     * Get model information from OpenAI API
     */
    protected function getModelInfo(?string $model = null): array
    {
        if ($model === null) {
            $model = $this->getAdDetectionModel();
        }
        
        // Return cached info if available
        if ($this->model_info_cache !== null && isset($this->model_info_cache['id']) && $this->model_info_cache['id'] === $model) {
            return $this->model_info_cache;
        }
        
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/models/{$model}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("cURL error fetching model info: $curl_error");
            }
            
            if ($httpCode !== 200) {
                throw new Exception("OpenAI API error fetching model info (HTTP $httpCode): $response");
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from OpenAI API: ' . json_last_error_msg());
            }
            
            // Cache the result
            $this->model_info_cache = $result;
            
            return $result;
            
        } catch (Exception $e) {
            $this->main->log("Failed to fetch model info for {$model}: " . $e->getMessage());
            
            // Fall back to known defaults for common models
            $defaults = [
                'gpt-4o-mini' => ['context_window' => 128000],
                'gpt-4o' => ['context_window' => 128000],
                'gpt-4-turbo' => ['context_window' => 128000],
                'gpt-4' => ['context_window' => 8192],
                'gpt-3.5-turbo' => ['context_window' => 16384],
            ];
            
            if (isset($defaults[$model])) {
                $this->main->log("Using fallback context window for {$model}: " . $defaults[$model]['context_window']);
                $this->model_info_cache = [
                    'id' => $model,
                    'context_window' => $defaults[$model]['context_window']
                ];
                return $this->model_info_cache;
            }
            
            // Ultimate fallback
            $this->main->log("Using ultimate fallback context window: 128000");
            $this->model_info_cache = [
                'id' => $model,
                'context_window' => 128000
            ];
            return $this->model_info_cache;
        }
    }
    
    /**
     * Get the maximum context window for the model
     */
    protected function getModelContextLimit(?string $model = null): int
    {
        $modelInfo = $this->getModelInfo($model);
        
        // Try different possible keys for context window
        if (isset($modelInfo['context_window'])) {
            return intval($modelInfo['context_window']);
        } elseif (isset($modelInfo['max_tokens'])) {
            return intval($modelInfo['max_tokens']);
        } elseif (isset($modelInfo['context_length'])) {
            return intval($modelInfo['context_length']);
        }
        
        // Fallback to a reasonable default
        $this->main->log("Could not determine context limit for {$model}, using fallback: 128000");
        return 128000;
    }
    
    /**
     * Calculate optimal chunk size based on model context limit
     */
    protected function calculateOptimalChunkSize(int $contextLimit): int
    {
        // Reserve tokens for:
        // - System prompt (~100 tokens)
        // - User prompt template (~500 tokens - increased from 200 due to longer prompt)  
        // - Response (~1000 tokens for ad detection JSON)
        // - Safety buffer (~1000 tokens for overlap and safety)
        $reservedTokens = 2600;
        
        $availableTokens = $contextLimit - $reservedTokens;
        
        // Use 25% of model's maximum context as requested by user (reduced from 70% of available)
        // This is more conservative and allows for better processing of smaller chunks
        $maxChunkTokens = intval($contextLimit * 0.25);
        
        // Ensure we don't exceed available tokens after reservations
        $maxChunkTokens = min($maxChunkTokens, $availableTokens);
        
        $this->main->log("Model context limit: {$contextLimit}, Using 25% for chunks: {$maxChunkTokens} tokens");
        
        return max(4000, $maxChunkTokens); // Minimum 4k tokens to ensure useful chunks
    }
    
    /**
     * Get audio file duration using ffmpeg
     */
    protected function getAudioDuration(string $file_path): float
    {
        // Check if ffprobe is available
        $checkCmd = 'which ffprobe 2>/dev/null';
        $ffprobePath = shell_exec($checkCmd);
        if ($ffprobePath === null || trim($ffprobePath) === '') {
            throw new Exception('ffprobe not found. Please ensure ffmpeg is installed.');
        }
        
        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellarg($file_path)
        );
        
        $output = shell_exec($cmd);
        if ($output === null || !is_numeric(trim($output))) {
            throw new Exception('Could not determine audio duration');
        }
        return floatval(trim($output));
    }
    
    /**
     * Split audio file into chunks
     */
    protected function splitAudioFile(string $input_file): array
    {
        $chunks = [];
        $duration = $this->getAudioDuration($input_file);
        
        // Use smaller chunks for better reliability (5 minutes max)
        $chunkDuration = min(self::CHUNK_DURATION_SECONDS, self::MAX_CHUNK_DURATION_SECONDS);
        $num_chunks = ceil($duration / $chunkDuration);
        
        $this->main->log("Splitting audio file: duration={$duration}s, chunk_duration={$chunkDuration}s, num_chunks={$num_chunks}");
        
        // Detect input file format
        $pathInfo = pathinfo($input_file);
        $extension = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : 'mp3';
        
        // Map common extensions to codec settings
        $codecSettings = match($extension) {
            'mp3' => '-c:a libmp3lame -b:a 128k',
            'mp4', 'm4a' => '-c:a aac -b:a 128k',
            'ogg' => '-c:a libvorbis -b:a 128k',
            'flac' => '-c:a flac',
            'wav' => '-c:a pcm_s16le',
            default => '-c:a libmp3lame -b:a 128k' // Default to mp3
        };
        
        for ($i = 0; $i < $num_chunks; $i++) {
            $start_time = $i * $chunkDuration;
            $chunk_file = tempnam(sys_get_temp_dir(), 'podsumer_chunk_') . '.' . $extension;
            
            $cmd = sprintf(
                'ffmpeg -i %s -ss %d -t %d %s -y %s 2>&1',
                escapeshellarg($input_file),
                $start_time,
                $chunkDuration,
                $codecSettings,
                escapeshellarg($chunk_file)
            );
            
            exec($cmd, $output, $returnVar);
            
            if ($returnVar !== 0) {
                // Clean up any created chunks on error
                foreach ($chunks as $chunk) {
                    if (isset($chunk['file']) && file_exists($chunk['file'])) {
                        @unlink($chunk['file']);
                    }
                }
                throw new Exception('Failed to split audio file: ' . implode("\n", $output));
            }
            
            $chunks[] = [
                'file' => $chunk_file,
                'start_offset' => $start_time
            ];
        }
        
        return $chunks;
    }
    
    /**
     * Transcribe a single audio file chunk
     */
    protected function transcribeChunk(string $audio_file_path): array
    {
        try {
            if (!file_exists($audio_file_path)) {
                throw new Exception("Audio file not found: $audio_file_path");
            }
            
            if (!is_readable($audio_file_path)) {
                throw new Exception("Audio file not readable: $audio_file_path");
            }

            // Check file size and log warning if it's approaching limits
            $fileSize = filesize($audio_file_path);
            $this->main->log("Transcribing chunk: " . basename($audio_file_path) . " (size: " . number_format($fileSize / 1024 / 1024, 2) . " MB)");
            
            if ($fileSize > self::WHISPER_FILE_SIZE_LIMIT) {
                $this->main->log("WARNING: Chunk file size (" . number_format($fileSize / 1024 / 1024, 2) . " MB) exceeds recommended limit");
            }
            
            $ch = curl_init();
            
            $postData = [
                'file' => new \CURLFile($audio_file_path),
                'model' => 'whisper-1',
                'response_format' => 'verbose_json',
                'timestamp_granularities' => ['segment']
            ];
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->api_key
            ]);
            // Increase timeout to 10 minutes for large files, with longer connection timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 minute timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // 60 second connection timeout
            // Add progress callback to prevent timeout on slow uploads
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $download_size, $downloaded, $upload_size, $uploaded) {
                // This prevents timeout during long uploads by keeping the connection active
                return 0;
            });
            
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("cURL error during transcription: $curl_error");
            }
            
            if ($httpCode !== 200) {
                throw new Exception("Whisper API error (HTTP $httpCode): $response");
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from Whisper API: ' . json_last_error_msg());
            }
            
            if (!isset($result['segments'])) {
                throw new Exception('Whisper API response missing segments data');
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->main->log("Transcription error for file $audio_file_path: " . $e->getMessage());
            throw new Exception("Audio transcription failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Merge transcripts from multiple chunks
     */
    protected function mergeTranscripts(array $chunk_transcripts): array
    {
        $merged = [
            'text' => '',
            'segments' => [],
            'language' => $chunk_transcripts[0]['language'] ?? 'en'
        ];
        
        foreach ($chunk_transcripts as $index => $transcript) {
            $offset = $transcript['offset'];
            
            // Add text with space separator
            if (!empty($merged['text'])) {
                $merged['text'] .= ' ';
            }
            $transcriptText = isset($transcript['text']) ? strval($transcript['text']) : '';
            $merged['text'] .= $transcriptText;
            
            // Adjust segment timestamps and merge
            if (isset($transcript['segments']) && is_array($transcript['segments'])) {
                foreach ($transcript['segments'] as $segment) {
                    if (isset($segment['start']) && isset($segment['end'])) {
                        $adjustedSegment = $segment;
                        $adjustedSegment['start'] = floatval($segment['start']) + $offset;
                        $adjustedSegment['end'] = floatval($segment['end']) + $offset;
                        $merged['segments'][] = $adjustedSegment;
                    }
                }
            }
        }
        
        return $merged;
    }
    
    /**
     * Transcribe audio file using OpenAI Whisper API
     * 
     * @param string $audio_file_path Path to the audio file
     * @return array Transcript with timestamps
     */
    public function transcribeAudio(string $audio_file_path): array
    {
        $this->last_transcription_cost = $this->calculateTranscriptionCost($audio_file_path);
        
        $fileSize = filesize($audio_file_path);
        
        // If file is small enough, transcribe directly
        if ($fileSize <= self::WHISPER_FILE_SIZE_LIMIT) {
            return $this->transcribeChunk($audio_file_path);
        }
        
        // File is too large, split into chunks
        $this->main->log("Audio file too large ({$fileSize} bytes), splitting into chunks...");
        
        $chunks = $this->splitAudioFile($audio_file_path);
        $chunk_transcripts = [];
        
        try {
            foreach ($chunks as $index => $chunk) {
                $this->main->log("Transcribing chunk " . ($index + 1) . " of " . count($chunks));
                
                // Add retry logic for individual chunks
                $maxRetries = 2;
                $transcript = null;
                $lastError = null;
                
                for ($retry = 0; $retry <= $maxRetries; $retry++) {
                    try {
                        $transcript = $this->transcribeChunk($chunk['file']);
                        $transcript['offset'] = $chunk['start_offset'];
                        break; // Success, exit retry loop
                    } catch (Exception $e) {
                        $lastError = $e;
                        if ($retry < $maxRetries) {
                            $this->main->log("Transcription failed for chunk " . ($index + 1) . ", retrying... (attempt " . ($retry + 2) . "/" . ($maxRetries + 1) . ")");
                            sleep(2); // Wait 2 seconds before retry
                        } else {
                            $this->main->log("Transcription failed for chunk " . ($index + 1) . " after " . ($maxRetries + 1) . " attempts");
                        }
                    }
                }
                
                if ($transcript === null) {
                    throw new Exception("Failed to transcribe chunk after " . ($maxRetries + 1) . " attempts: " . $lastError->getMessage());
                }
                
                $chunk_transcripts[] = $transcript;
                
                // Clean up chunk file
                if (file_exists($chunk['file'])) {
                    @unlink($chunk['file']);
                    $this->main->log("Cleaned up chunk file: " . basename($chunk['file']));
                }
            }
            
            // Merge all transcripts
            return $this->mergeTranscripts($chunk_transcripts);
            
        } catch (Exception $e) {
            // Clean up any remaining chunk files on error
            foreach ($chunks as $chunk) {
                if (isset($chunk['file']) && file_exists($chunk['file'])) {
                    @unlink($chunk['file']);
                    $this->main->log("Cleaned up chunk file on error: " . basename($chunk['file']));
                }
            }
            throw $e;
        }
    }
    
    /**
     * Detect ad sections in transcript using GPT-4o-mini
     * 
     * @param array $transcript Transcript data from Whisper
     * @param string $show Show/feed title
     * @param string $episode Episode/item title
     * @return array Ad sections with start/end timestamps
     */
    public function detectAds(array $transcript, string $show, string $episode): array
    {
        try {
            $segments = $transcript['segments'] ?? [];
            if (empty($segments)) {
                return [];
            }
            
            // Check if transcript is too large and needs to be chunked
            $transcriptText = $this->formatTranscriptText($segments);
            
            // Get the model's actual context limit and calculate optimal chunk size
            $contextLimit = $this->getModelContextLimit();
            $maxTokensPerChunk = $this->calculateOptimalChunkSize($contextLimit);
            
            // More conservative token estimation: ~3 characters per token for formatted text
            // (reduced from 4 to be more conservative due to transcript formatting)
            $estimatedTokens = intval(strlen($transcriptText) / 3);
            
            $this->main->log("Transcript length: " . strlen($transcriptText) . " characters, estimated tokens: {$estimatedTokens}, max per chunk: {$maxTokensPerChunk} (25% of context)");
            
            $adSections = [];
            
            if ($estimatedTokens > $maxTokensPerChunk) {
                $this->main->log("Large transcript detected ({$estimatedTokens} estimated tokens), processing with overlapping chunks (max per chunk: {$maxTokensPerChunk}, 5% overlap)");
                $adSections = $this->detectAdsInChunks($segments, $maxTokensPerChunk, $show, $episode);
            } else {
                $this->main->log("Processing transcript in single request ({$estimatedTokens} estimated tokens, within chunk limit: {$maxTokensPerChunk})");
                $adSections = $this->detectAdsInSingleRequest($transcriptText, $show, $episode);
            }
            
            // Refine ad boundaries if sections were found
            if (!empty($adSections)) {
                $this->main->log("Refining ad boundaries for " . count($adSections) . " detected sections");
                
                // Before refinement, merge sections that are within 33 seconds of each other
                // This helps when there are multiple ads in the 6-minute refinement window
                $adSectionsForRefinement = $this->mergeOverlappingAdSections($adSections, 33.0);
                $this->main->log("Merged close sections for refinement: " . count($adSections) . " => " . count($adSectionsForRefinement) . " sections (33s merge buffer)");
                
                $adSections = $this->refineAdBoundaries($adSectionsForRefinement, $transcript);
            }
            
            return $adSections;
            
        } catch (Exception $e) {
            $this->main->log("Ad detection error: " . $e->getMessage());
            throw new Exception("Ad detection failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Format transcript segments into text with timestamps
     */
    protected function formatTranscriptText(array $segments): string
    {
        $transcriptText = '';
        foreach ($segments as $segment) {
            $start = floatval($segment['start']);
            $end = floatval($segment['end']);
            $startFormatted = gmdate('H:i:s', intval($start));
            $endFormatted = gmdate('H:i:s', intval($end));
            $text = isset($segment['text']) && is_string($segment['text']) ? trim($segment['text']) : '';
            if (!empty($text)) {
                $transcriptText .= "[{$startFormatted} - {$endFormatted}] (start: {$start}s, end: {$end}s) {$text}\n";
            }
        }
        return $transcriptText;
    }
    
    /**
     * Process large transcripts by splitting them into chunks
     */
    protected function detectAdsInChunks(array $segments, int $maxTokensPerChunk, string $show, string $episode): array
    {
        $allAdSections = [];
        $totalCost = 0.0;
        
        // Find the total duration
        $totalDuration = 0;
        foreach ($segments as $segment) {
            $end = floatval($segment['end']);
            if ($end > $totalDuration) {
                $totalDuration = $end;
            }
        }
        
        // Create chunks based on token count with 5% overlap on each side
        $chunks = $this->createOptimalChunks($segments, $maxTokensPerChunk);
        $numChunks = count($chunks);
        
        $this->main->log("Splitting transcript into {$numChunks} overlapping chunks (max {$maxTokensPerChunk} tokens per chunk, 5% overlap each side)");
        
        foreach ($chunks as $i => $chunk) {
            $chunkSegments = $chunk['segments'];
            $chunkStart = $chunk['start_time'];
            $chunkEnd = $chunk['end_time'];
            $estimatedTokens = $chunk['estimated_tokens'];
            
            $chunkText = $this->formatTranscriptText($chunkSegments);
            $this->main->log("Processing chunk " . ($i + 1) . "/{$numChunks} (duration: " . gmdate('H:i:s', intval($chunkStart)) . " - " . gmdate('H:i:s', intval($chunkEnd)) . ", ~{$estimatedTokens} tokens, " . count($chunkSegments) . " segments)");
            
            $chunkAdSections = $this->detectAdsInSingleRequest($chunkText, $show, $episode);
            
            // Add the chunk's ad sections to the overall results
            $allAdSections = array_merge($allAdSections, $chunkAdSections);
            
            $totalCost += $this->last_detection_cost;
        }
        
        // Update the total cost
        $this->last_detection_cost = $totalCost;
        
        // Sort and merge overlapping ad sections
        $mergedAdSections = $this->mergeOverlappingAdSections($allAdSections);
        
        $this->main->log("Found " . count($mergedAdSections) . " ad sections after processing {$numChunks} overlapping chunks");
        
        return $mergedAdSections;
    }
    
    /**
     * Create optimal chunks based on token limits with 5% overlap on each side
     */
    protected function createOptimalChunks(array $segments, int $maxTokensPerChunk): array
    {
        if (empty($segments)) {
            return [];
        }
        
        $chunks = [];
        $totalSegments = count($segments);
        
        // Calculate 5% overlap in terms of tokens
        $overlapTokens = intval($maxTokensPerChunk * 0.05);
        
        // Use 90% of max chunk size as the target to leave room for overlap
        $targetChunkTokens = intval($maxTokensPerChunk * 0.90);
        
        $currentSegmentIndex = 0;
        
        while ($currentSegmentIndex < $totalSegments) {
            $chunk = [
                'segments' => [],
                'start_time' => 0,
                'end_time' => 0,
                'estimated_tokens' => 0
            ];
            
            // Add segments starting from current index
            $chunkTokens = 0;
            $segmentIndex = $currentSegmentIndex;
            
            // Build the core chunk
            while ($segmentIndex < $totalSegments) {
                $segment = $segments[$segmentIndex];
                $segmentText = isset($segment['text']) && is_string($segment['text']) ? trim($segment['text']) : '';
                
                if (empty($segmentText)) {
                    $segmentIndex++;
                    continue;
                }
                
                // Estimate tokens for this segment (including timestamp formatting)
                $start = floatval($segment['start']);
                $end = floatval($segment['end']);
                $startFormatted = gmdate('H:i:s', intval($start));
                $endFormatted = gmdate('H:i:s', intval($end));
                $formattedSegment = "[{$startFormatted} - {$endFormatted}] (start: {$start}s, end: {$end}s) {$segmentText}\n";
                
                // Conservative token estimation: ~3 chars per token for formatted text
                $segmentTokens = intval(strlen($formattedSegment) / 3);
                
                // Check if adding this segment would exceed our target
                if ($chunkTokens + $segmentTokens > $targetChunkTokens && !empty($chunk['segments'])) {
                    break;
                }
                
                // Add segment to chunk
                $chunk['segments'][] = $segment;
                $chunkTokens += $segmentTokens;
                
                // Update chunk time boundaries
                if ($chunk['start_time'] == 0) {
                    $chunk['start_time'] = $start;
                }
                $chunk['end_time'] = $end;
                
                $segmentIndex++;
            }
            
            // If this is not the first chunk, add overlap from previous segments (5% overlap on left side)
            if ($currentSegmentIndex > 0) {
                $leftOverlapSegments = [];
                $leftOverlapTokens = 0;
                $leftIndex = $currentSegmentIndex - 1;
                
                // Go backwards to collect segments for left overlap
                while ($leftIndex >= 0 && $leftOverlapTokens < $overlapTokens) {
                    $segment = $segments[$leftIndex];
                    $segmentText = isset($segment['text']) && is_string($segment['text']) ? trim($segment['text']) : '';
                    
                    if (!empty($segmentText)) {
                        $start = floatval($segment['start']);
                        $end = floatval($segment['end']);
                        $startFormatted = gmdate('H:i:s', intval($start));
                        $endFormatted = gmdate('H:i:s', intval($end));
                        $formattedSegment = "[{$startFormatted} - {$endFormatted}] (start: {$start}s, end: {$end}s) {$segmentText}\n";
                        $segmentTokens = intval(strlen($formattedSegment) / 3);
                        
                        // Check if we can fit this segment in the overlap
                        if ($leftOverlapTokens + $segmentTokens <= $overlapTokens) {
                            array_unshift($leftOverlapSegments, $segment);
                            $leftOverlapTokens += $segmentTokens;
                            $chunk['start_time'] = $start; // Update start time for overlap
                        } else {
                            break;
                        }
                    }
                    
                    $leftIndex--;
                }
                
                // Prepend overlap segments to the chunk
                $chunk['segments'] = array_merge($leftOverlapSegments, $chunk['segments']);
                $chunkTokens += $leftOverlapTokens;
            }
            
            // If this is not the last chunk, add overlap from next segments (5% overlap on right side)
            if ($segmentIndex < $totalSegments) {
                $rightOverlapSegments = [];
                $rightOverlapTokens = 0;
                $rightIndex = $segmentIndex;
                
                // Go forwards to collect segments for right overlap
                while ($rightIndex < $totalSegments && $rightOverlapTokens < $overlapTokens) {
                    $segment = $segments[$rightIndex];
                    $segmentText = isset($segment['text']) && is_string($segment['text']) ? trim($segment['text']) : '';
                    
                    if (!empty($segmentText)) {
                        $start = floatval($segment['start']);
                        $end = floatval($segment['end']);
                        $startFormatted = gmdate('H:i:s', intval($start));
                        $endFormatted = gmdate('H:i:s', intval($end));
                        $formattedSegment = "[{$startFormatted} - {$endFormatted}] (start: {$start}s, end: {$end}s) {$segmentText}\n";
                        $segmentTokens = intval(strlen($formattedSegment) / 3);
                        
                        // Check if we can fit this segment in the overlap
                        if ($rightOverlapTokens + $segmentTokens <= $overlapTokens) {
                            $rightOverlapSegments[] = $segment;
                            $rightOverlapTokens += $segmentTokens;
                            $chunk['end_time'] = $end; // Update end time for overlap
                        } else {
                            break;
                        }
                    }
                    
                    $rightIndex++;
                }
                
                // Append overlap segments to the chunk
                $chunk['segments'] = array_merge($chunk['segments'], $rightOverlapSegments);
                $chunkTokens += $rightOverlapTokens;
            }
            
            $chunk['estimated_tokens'] = $chunkTokens;
            
            if (!empty($chunk['segments'])) {
                $chunks[] = $chunk;
            }
            
            // Move to the next chunk starting point
            // The next chunk starts where the core content of this chunk ended (not including right overlap)
            $currentSegmentIndex = $segmentIndex;
        }
        
        return $chunks;
    }
    
    /**
     * Process transcript in a single API request
     */
    protected function detectAdsInSingleRequest(string $transcriptText, string $show, string $episode): array
    {
        $prompt = "Your job is to read each and every line in the following podcast transcript excerpt and judge, considering the lines before and after each line, if that line is a part of an advertisement, a sponsorsed segment, or native advertising.: An advertisement is when a paid product, recurring subscription, corporation, company, or non-profit is being explicitly promoted. Many hosts and guests talk about themselves and their output, this is not to be considered a promotion. If the host or guest is talking about call to actions for paid events, merch sales, donations, monetary contributions, recurring subscriptions, patreon, super chats, etc. those are categorically advertisements. Mentions by name of corporations at the very beginning or end are ads. When you find an entry in the transcript you suspect might be a match search surrounding entries for at least 30-45 seconds to see where the real content ends and when it starts again. PINPOINT THE EXACT RANGE OF THE MATCHED CONTENT. \n\nReturn a JSON object with a key 'segments' containing an array of objects with 'start' and 'end' timestamps (in seconds) for each matched section. When you've found the entries that match, use the end timestamp from the entry immediately preceding the first entry in the matched section as the START value. For the END value, use the start timestamp from the transcript entry immediately following the last transcript entry in the matched section. Also include a reason key with a brief description of why this was matched. Example format: {\"segments\": [{\"start\": 0, \"end\": 30, \"reason\": \"\"}, {\"start\": 600, \"end\": 660, \"reason\": \"\"}]}. Show name: {$show}\n\n Episode Title: {$episode}\n\n \n\nTranscript:\n```{$transcriptText}```";
        
        $ch = curl_init();
        
        $model = $this->getAdDetectionModel();
        
        $postData = json_encode([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an anti advertising activist. Your movement has won unanimous favor in society. You are now the national czar for removing ads from podcasts. The highest honor of your dreams. You can spot the beginning and end of an ad like a hawk.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'ad_detection_response',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'segments' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'start' => ['type' => 'number'],
                                        'end' => ['type' => 'number'],
                                        'reason' => ['type' => 'string']
                                    ],
                                    'required' => ['start', 'end', 'reason'],
                                    'additionalProperties' => false
                                ]
                            ]
                        ],
                        'required' => ['segments'],
                        'additionalProperties' => false
                    ]
                ]
            ]
        ]);
        
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minute timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 second connection timeout
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("cURL error during ad detection: $curl_error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("GPT API error (HTTP $httpCode): $response");
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from GPT API: ' . json_last_error_msg());
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception('GPT API response missing content data');
        }
        
        $content = $result['choices'][0]['message']['content'] ?? '{}';
        
        // Calculate cost using actual token usage from API response
        $this->last_detection_cost = $this->calculateGptCostFromUsage($result['usage'] ?? [], $model);
        
        $adData = json_decode($content, true);
        
        // Log the raw response for debugging
        $this->main->log("GPT Ad Detection Response: " . $content);
        
        // Ensure we have a proper array structure
        if (!is_array($adData)) {
            $this->main->log("Invalid ad data structure: " . print_r($adData, true));
            return [];
        }
        
        // Handle different possible response formats
        $adSections = [];
        
        // If it's a direct array of ad objects
        if (isset($adData[0]) && isset($adData[0]['start'])) {
            $adSections = $adData;
        }
        // If it's wrapped in a 'segments' key (preferred format)
        elseif (isset($adData['segments']) && is_array($adData['segments'])) {
            $adSections = $adData['segments'];
        }
        // If it's wrapped in an 'ads' key
        elseif (isset($adData['ads']) && is_array($adData['ads'])) {
            $adSections = $adData['ads'];
        }
        // If it's wrapped in some other key, try to find an array with start/end
        else {
            foreach ($adData as $key => $value) {
                if (is_array($value) && !empty($value)) {
                    if (isset($value[0]['start']) || (isset($value[0]) && is_array($value[0]))) {
                        $adSections = $value;
                        break;
                    }
                }
            }
        }
        
        // Validate and sanitize ad sections
        $validatedSections = [];
        foreach ($adSections as $section) {
            if (isset($section['start']) && isset($section['end'])) {
                $validatedSections[] = [
                    'start' => floatval($section['start']),
                    'end' => floatval($section['end']),
                    'reason' => isset($section['reason']) ? strval($section['reason']) : ''
                ];
            }
        }
        
        // Always merge overlapping sections before returning
        return $this->mergeOverlappingAdSections($validatedSections);
    }
    
    /**
     * Merge overlapping ad sections with configurable buffer
     * 
     * @param array $adSections Ad sections to merge
     * @param float|null $customBuffer Optional custom buffer in seconds, defaults to config value
     * @return array Merged ad sections
     */
    protected function mergeOverlappingAdSections(array $adSections, ?float $customBuffer = null): array
    {
        if (empty($adSections)) {
            return [];
        }
        
        // Use custom buffer if provided, otherwise get from config (default 8 seconds)
        $mergeBuffer = $customBuffer ?? floatval($this->main->getConf('podsumer', 'ad_merge_buffer_seconds') ?? 8.0);
        
        // Sort by start time
        usort($adSections, function($a, $b) {
            return $a['start'] <=> $b['start'];
        });
        
        $merged = [];
        $current = $adSections[0];
        
        $this->main->log("Merging ad sections with {$mergeBuffer}s buffer. Input sections: " . count($adSections));
        
        for ($i = 1; $i < count($adSections); $i++) {
            $next = $adSections[$i];
            
            // Calculate the gap between current section end and next section start
            $gap = $next['start'] - $current['end'];
            
            // If sections overlap (negative gap) or are within the merge buffer, merge them
            if ($gap <= $mergeBuffer) {
                $this->main->log("Merging sections: [{$current['start']}-{$current['end']}] and [{$next['start']}-{$next['end']}] (gap: {$gap}s)");
                
                // Update the end time to the maximum of both sections
                $current['end'] = max($current['end'], $next['end']);
                
                // If the next section starts before current ends (true overlap), 
                // also make sure we capture the earliest start time
                if ($next['start'] < $current['start']) {
                    $current['start'] = $next['start'];
                }
                
                // Combine reasons if both exist
                $currentReason = $current['reason'] ?? '';
                $nextReason = $next['reason'] ?? '';
                if (!empty($currentReason) && !empty($nextReason) && $currentReason !== $nextReason) {
                    $current['reason'] = $currentReason . "\n</end ad><start-ad>\n" . $nextReason;
                } elseif (empty($currentReason) && !empty($nextReason)) {
                    $current['reason'] = $nextReason;
                }
            } else {
                // Gap is too large, don't merge - save current and move to next
                $merged[] = $current;
                $current = $next;
            }
        }
        
        // Add the last section
        $merged[] = $current;
        
        $this->main->log("Ad section merging complete. Output sections: " . count($merged));
        
        // Log the final merged sections for debugging
        foreach ($merged as $i => $section) {
            $duration = $section['end'] - $section['start'];
            $this->main->log("Merged section " . ($i + 1) . ": {$section['start']}s - {$section['end']}s (duration: {$duration}s)");
        }
        
        return $merged;
    }
    
    /**
     * Refine ad boundaries by analyzing surrounding transcript context
     * 
     * @param array $adSections Initial ad sections with rough boundaries
     * @param array $transcript Full transcript with segments
     * @return array Refined ad sections with precise boundaries
     */
    protected function refineAdBoundaries(array $adSections, array $transcript): array
    {
        if (empty($adSections) || empty($transcript['segments'])) {
            return $adSections;
        }
        
        $refinedSections = [];
        $totalRefinementCost = 0.0;
        
        $this->main->log("Starting ad boundary refinement for " . count($adSections) . " sections");
        
        foreach ($adSections as $index => $section) {
            try {
                // Calculate the midpoint of the ad section
                $midpoint = ($section['start'] + $section['end']) / 2;
                
                // Extract 3 minutes (180 seconds) before and after the midpoint
                $contextStart = max(0, $midpoint - 180);
                $contextEnd = $midpoint + 180;
                
                // Find all transcript segments within this time range
                $contextSegments = [];
                foreach ($transcript['segments'] as $segment) {
                    $segmentStart = floatval($segment['start']);
                    $segmentEnd = floatval($segment['end']);
                    
                    // Include segments that overlap with our context window
                    if ($segmentEnd >= $contextStart && $segmentStart <= $contextEnd) {
                        $contextSegments[] = $segment;
                    }
                }
                
                if (empty($contextSegments)) {
                    $this->main->log("No transcript segments found for refinement of section " . ($index + 1));
                    $refinedSections[] = $section;
                    continue;
                }
                
                // Format the context transcript with timestamps
                $contextTranscript = '';
                foreach ($contextSegments as $segment) {
                    $start = floatval($segment['start']);
                    $end = floatval($segment['end']);
                    $text = isset($segment['text']) && is_string($segment['text']) ? trim($segment['text']) : '';
                    if (!empty($text)) {
                        $contextTranscript .= "[{$start} - {$end}] {$text}\n";
                    }
                }
                
                $this->main->log("Refining section " . ($index + 1) . " (original: {$section['start']}s - {$section['end']}s, context: {$contextStart}s - {$contextEnd}s)");
                
                // Call LLM to refine boundaries
                $refinedBoundaries = $this->callRefinementLLM($contextTranscript, $section['reason'] ?? 'Advertisement detected');
                $totalRefinementCost += $this->last_detection_cost;
                
                if ($refinedBoundaries !== null) {
                    // Search for transcript segments that match the refined boundaries
                    $refinedStart = $refinedBoundaries['start'];
                    $refinedEnd = $refinedBoundaries['end'];
                    
                    // Find segment that contains or matches the start boundary
                    foreach ($contextSegments as $segment) {
                        $segmentStart = floatval($segment['start']);
                        $segmentEnd = floatval($segment['end']);
                        
                        // Check if this segment's start or end matches the refined start boundary
                        if (abs($segmentStart - $refinedStart) < 0.1 || abs($segmentEnd - $refinedStart) < 0.1) {
                            // Use the end timestamp of this segment as the new start boundary
                            $refinedStart = $segmentEnd;
                            $this->main->log("Adjusted start boundary to segment end: {$refinedBoundaries['start']}s => {$refinedStart}s");
                            break;
                        }
                    }
                    
                    // Find segment that contains or matches the end boundary
                    foreach ($contextSegments as $segment) {
                        $segmentStart = floatval($segment['start']);
                        $segmentEnd = floatval($segment['end']);
                        
                        // Check if this segment's start or end matches the refined end boundary
                        if (abs($segmentStart - $refinedEnd) < 0.1 || abs($segmentEnd - $refinedEnd) < 0.1) {
                            // Use the start timestamp of this segment as the new end boundary
                            $refinedEnd = $segmentStart;
                            $this->main->log("Adjusted end boundary to segment start: {$refinedBoundaries['end']}s => {$refinedEnd}s");
                            break;
                        }
                    }
                    
                    // Successfully refined
                    $refinedSection = [
                        'start' => $refinedStart,
                        'end' => $refinedEnd,
                        'reason' => $section['reason'] ?? 'Advertisement detected'
                    ];
                    
                    $this->main->log("Refined section " . ($index + 1) . ": {$section['start']}s - {$section['end']}s => {$refinedSection['start']}s - {$refinedSection['end']}s");
                    $refinedSections[] = $refinedSection;
                } else {
                    // Refinement failed, keep original
                    $this->main->log("Refinement failed for section " . ($index + 1) . ", keeping original boundaries");
                    $refinedSections[] = $section;
                }
                
            } catch (Exception $e) {
                $this->main->log("Error refining section " . ($index + 1) . ": " . $e->getMessage());
                $refinedSections[] = $section; // Keep original on error
            }
        }
        
        // Add refinement cost to total detection cost
        $this->last_detection_cost += $totalRefinementCost;
        
        $this->main->log("Ad boundary refinement complete");
        
        // Final adjustment: subtract 0.5s from start and add 0.5s to end for each section
        foreach ($refinedSections as &$section) {
            $originalStart = $section['start'];
            $originalEnd = $section['end'];
            
            // Ensure start doesn't go below 0
            $section['start'] = max(0, $section['start'] - 0.5);
            $section['end'] = $section['end'] + 0.5;
            
            $this->main->log("Final adjustment for section: start {$originalStart}s => {$section['start']}s, end {$originalEnd}s => {$section['end']}s");
        }
        
        // Find the total duration from transcript segments
        $totalDuration = 0;
        foreach ($transcript['segments'] as $segment) {
            $end = floatval($segment['end']);
            if ($end > $totalDuration) {
                $totalDuration = $end;
            }
        }
        
        // Extend segments that are within 25 seconds of beginning or end
        $boundaryExtensionThreshold = 25.0; // 25 seconds
        
        foreach ($refinedSections as &$section) {
            $originalStart = $section['start'];
            $originalEnd = $section['end'];
            $extended = false;
            
            // Check if start is within 25 seconds of beginning
            if ($section['start'] <= $boundaryExtensionThreshold) {
                $section['start'] = 0;
                $extended = true;
                $this->main->log("Extended section to beginning: start {$originalStart}s => 0s (was within {$boundaryExtensionThreshold}s of start)");
            }
            
            // Check if end is within 25 seconds of end
            if ($totalDuration > 0 && ($totalDuration - $section['end']) <= $boundaryExtensionThreshold) {
                $section['end'] = $totalDuration;
                $extended = true;
                $this->main->log("Extended section to end: end {$originalEnd}s => {$totalDuration}s (was within {$boundaryExtensionThreshold}s of end)");
            }
            
            if ($extended) {
                $this->main->log("Boundary extension applied for section: {$originalStart}s-{$originalEnd}s => {$section['start']}s-{$section['end']}s");
            }
        }
        
        return $refinedSections;
    }
    
    /**
     * Call LLM to refine ad boundaries
     * 
     * @param string $contextTranscript Transcript excerpt with timestamps
     * @param string $reason Original reason for ad detection
     * @return array|null Refined boundaries or null on failure
     */
    protected function callRefinementLLM(string $contextTranscript, string $reason): ?array
    {
        $prompt = "The following podcast transcript excerpt has been identified as containing advertisement(s) or promotion(s). The topic(s) of the advertisement(s):\n\n{$reason}\n\nPlease complete the following two tasks:\n\n1. Find the exact timestamp where the topic switches FROM the regular episode content TO the FIRST advertisement.\n2. Find the exact timestamp where the topic switches FROM the LAST advertisement BACK to the regular episode content.\n\nProvide the start timestamp (when the first ad begins) and end timestamp (when the last ad ends) based on the transcript entries below.\n\nTranscript:\n{$contextTranscript}";
        
        try {
            $ch = curl_init();
            
            $model = $this->getAdDetectionModel();
            
            $postData = json_encode([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an anti advertising activist. Your movement has won unanimous favor in society. You are now the national czar for removing ads from podcasts. The highest honor of your dreams. You can spot the beginning and end of an ad like a hawk.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1, // Lower temperature for more precise boundary detection
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'boundary_refinement_response',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'start' => [
                                    'type' => 'number',
                                    'description' => 'The timestamp in seconds where the advertisement begins'
                                ],
                                'end' => [
                                    'type' => 'number',
                                    'description' => 'The timestamp in seconds where the advertisement ends'
                                ],
                                'start_line' => [
                                    'type' => 'string',
                                    'description' => 'The transcript line where the ad starts'
                                ],
                                'end_line' => [
                                    'type' => 'string',
                                    'description' => 'The transcript line where the ad ends'
                                ]
                            ],
                            'required' => ['start', 'end', 'start_line', 'end_line'],
                            'additionalProperties' => false
                        ]
                    ]
                ]
            ]);
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 1 minute timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 second connection timeout
            
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("cURL error during boundary refinement: $curl_error");
            }
            
            if ($httpCode !== 200) {
                throw new Exception("GPT API error (HTTP $httpCode): $response");
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from GPT API: ' . json_last_error_msg());
            }
            
            if (!isset($result['choices'][0]['message']['content'])) {
                throw new Exception('GPT API response missing content data');
            }
            
            $content = $result['choices'][0]['message']['content'] ?? '{}';
            
            // Calculate cost using actual token usage from API response
            $this->last_detection_cost = $this->calculateGptCostFromUsage($result['usage'] ?? [], $model);
            
            $boundaries = json_decode($content, true);
            
            if (!is_array($boundaries) || !isset($boundaries['start']) || !isset($boundaries['end'])) {
                throw new Exception('Invalid boundary data returned from LLM');
            }
            
            return [
                'start' => floatval($boundaries['start']),
                'end' => floatval($boundaries['end'])
            ];
            
        } catch (Exception $e) {
            $this->main->log("Boundary refinement LLM error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Remove ad sections from audio file using ffmpeg
     * 
     * @param string $input_file Path to input audio file
     * @param string $output_file Path to output audio file
     * @param array $ad_sections Array of ad sections with start/end times
     * @return bool Success status
     */
    public function removeAdsFromAudio(string $input_file, string $output_file, array $ad_sections): bool
    {
        if (empty($ad_sections)) {
            // No ads to remove, just copy the file
            return copy($input_file, $output_file);
        }
        
        // Sort ad sections by start time
        usort($ad_sections, function($a, $b) {
            return $a['start'] - $b['start'];
        });
        
        // Build ffmpeg filter to remove ad sections
        $filters = [];
        $lastEnd = 0;
        $partIndex = 0;
        $parts = [];
        
        foreach ($ad_sections as $ad) {
            $start = floatval($ad['start']);
            $end = floatval($ad['end']);
            
            if ($start > $lastEnd) {
                // Add the content between ads
                $filters[] = "[0:a]atrim=start={$lastEnd}:end={$start},asetpts=PTS-STARTPTS[a{$partIndex}]";
                $parts[] = "[a{$partIndex}]";
                $partIndex++;
            }
            
            $lastEnd = $end;
        }
        
        // Add the final segment after the last ad
        $filters[] = "[0:a]atrim=start={$lastEnd},asetpts=PTS-STARTPTS[a{$partIndex}]";
        $parts[] = "[a{$partIndex}]";
        
        // Concatenate all parts
        $concatFilter = implode('', $parts) . "concat=n=" . count($parts) . ":v=0:a=1[out]";
        
        $filterComplex = implode(';', $filters) . ';' . $concatFilter;
        
        // Build ffmpeg command
        $cmd = sprintf(
            'ffmpeg -i %s -filter_complex %s -map "[out]" -c:a libmp3lame -b:a 128k -y %s 2>&1',
            escapeshellarg($input_file),
            escapeshellarg($filterComplex),
            escapeshellarg($output_file)
        );
        
        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            $this->main->log('ffmpeg error: ' . implode("\n", $output));
            return false;
        }
        
        return true;
    }
    
    /**
     * Calculate cost for Whisper transcription based on audio duration
     */
    protected function calculateTranscriptionCost(string $audio_file_path): float
    {
        $duration = $this->getAudioDuration($audio_file_path);
        $minutes = $duration / 60.0;
        $whisperCostPerMinute = floatval($this->main->getConf('podsumer', 'openai_whisper_cost_per_minute') ?? 0.006);
        return $minutes * $whisperCostPerMinute;
    }
    
    /**
     * Calculate cost using actual token usage from OpenAI API response
     */
    protected function calculateGptCostFromUsage(array $usage, ?string $model = null): float
    {
        if ($model === null) {
            $model = $this->getAdDetectionModel();
        }
        
        if (empty($usage)) {
            $this->main->log("Warning: No usage data available for cost calculation");
            return 0.0;
        }
        
        // Extract token counts from usage data
        $inputTokens = intval($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $outputTokens = intval($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
        
        if ($inputTokens === 0 && $outputTokens === 0) {
            $this->main->log("Warning: Zero tokens reported in usage data");
            return 0.0;
        }
        
        // Get pricing from configuration based on model
        $inputCostPer1k = $this->getInputCostPer1kTokens($model);
        $outputCostPer1k = $this->getOutputCostPer1kTokens($model);
        
        $inputCost = ($inputTokens / 1000.0) * $inputCostPer1k;
        $outputCost = ($outputTokens / 1000.0) * $outputCostPer1k;
        
        $totalCost = $inputCost + $outputCost;
        
        $this->main->log("Token usage - Model: {$model}, Input: {$inputTokens}, Output: {$outputTokens}, Cost: $" . number_format($totalCost, 6));
        
        return $totalCost;
    }
    
    /**
     * Get input token pricing per 1000 tokens for a model
     */
    protected function getInputCostPer1kTokens(string $model): float
    {
        $configKey = match($model) {
            'gpt-4o-mini' => 'openai_gpt4o_mini_input_cost_per_1k_tokens',
            'gpt-4o' => 'openai_gpt4o_input_cost_per_1k_tokens',
            default => 'openai_gpt4o_mini_input_cost_per_1k_tokens' // Default to gpt-4o-mini
        };
        
        return floatval($this->main->getConf('podsumer', $configKey) ?? 0.00015);
    }
    
    /**
     * Get output token pricing per 1000 tokens for a model
     */
    protected function getOutputCostPer1kTokens(string $model): float
    {
        $configKey = match($model) {
            'gpt-4o-mini' => 'openai_gpt4o_mini_output_cost_per_1k_tokens',
            'gpt-4o' => 'openai_gpt4o_output_cost_per_1k_tokens',
            default => 'openai_gpt4o_mini_output_cost_per_1k_tokens' // Default to gpt-4o-mini
        };
        
        return floatval($this->main->getConf('podsumer', $configKey) ?? 0.0006);
    }
    
    /**
     * Legacy method for backward compatibility - now uses estimation
     * @deprecated Use calculateGptCostFromUsage() instead when usage data is available
     */
    protected function estimateGptCost(string $input_text, string $output_text = '', ?string $model = null): float
    {
        if ($model === null) {
            $model = $this->getAdDetectionModel();
        }
        
        // Conservative token estimation: ~3 characters per token for formatted text
        $input_tokens = strlen($input_text) / 3;
        $output_tokens = strlen($output_text) / 3;
        
        $inputCostPer1k = $this->getInputCostPer1kTokens($model);
        $outputCostPer1k = $this->getOutputCostPer1kTokens($model);
        
        $input_cost = ($input_tokens / 1000.0) * $inputCostPer1k;
        $output_cost = ($output_tokens / 1000.0) * $outputCostPer1k;
        
        return $input_cost + $output_cost;
    }
    
    /**
     * Get the cost of the last transcription operation
     */
    public function getLastTranscriptionCost(): float
    {
        return $this->last_transcription_cost;
    }
    
    /**
     * Get the cost of the last ad detection operation
     */
    public function getLastDetectionCost(): float
    {
        return $this->last_detection_cost;
    }
    
    /**
     * Reset cost tracking
     */
    public function resetCosts(): void
    {
        $this->last_transcription_cost = 0.0;
        $this->last_detection_cost = 0.0;
    }
    
    /**
     * Process ad detection for a complete item
     * 
     * @param int $item_id The item ID to process
     * @param string $audio_file_path Path to the audio file
     * @return float Total cost of processing
     */
    public function processItem(int $item_id, string $audio_file_path): float
    {
        try {
            $this->resetCosts();
            
            // Check if item already has transcript and ad_sections (including ad-free episodes)
            $existing_transcript = $this->main->getState()->getItemTranscript($item_id);
            $existing_ad_sections = $this->main->getState()->getItemAdSections($item_id);
            
            // Check if the item has been fully processed (has transcript and ad_sections is not null)
            // Note: ad_sections could be an empty array for ad-free episodes, which is still "processed"
            $has_transcript = !empty($existing_transcript);
            $has_ad_sections_processed = false;
            
            // Get raw ad_sections data to check if it's been processed (not null/empty string)
            $item_data = $this->main->getState()->getFeedItem($item_id);
            if (isset($item_data['ad_sections']) && $item_data['ad_sections'] !== null && $item_data['ad_sections'] !== '') {
                $has_ad_sections_processed = true;
            }
            
            if ($has_transcript && $has_ad_sections_processed) {
                $this->main->log("Item $item_id already has transcript and has been processed for ad_sections, skipping processing");
                return 0.0; // No cost since we're not processing
            }
            
            // Transcribe the audio (if transcript doesn't exist)
            $transcript = null;
            $transcription_cost = 0.0;
            
            if (empty($existing_transcript)) {
                $transcript = $this->transcribeAudio($audio_file_path);
                $transcription_cost = $this->getLastTranscriptionCost();
                
                // Validate transcript before storing
                if ($transcript === null || !is_array($transcript) || !isset($transcript['segments'])) {
                    throw new Exception("Transcription failed for item $item_id - invalid transcript data returned");
                }
                
                // Store transcript
                $this->main->getState()->setItemTranscript($item_id, json_encode($transcript));
            } else {
                // Use existing transcript
                $transcript = json_decode($existing_transcript, true);
                $this->main->log("Using existing transcript for item $item_id");
            }
            
            // Validate transcript before proceeding
            if ($transcript === null || !is_array($transcript)) {
                throw new Exception("Invalid transcript data for item $item_id. Transcript may be corrupted or failed to decode.");
            }
            
            if (!isset($transcript['segments']) || !is_array($transcript['segments'])) {
                throw new Exception("Transcript missing segments data for item $item_id");
            }
            
            // Detect ads (if ad_sections haven't been processed yet)
            $ad_sections = [];
            $detection_cost = 0.0;
            
            // Check if ad_sections have been processed - they should not be null/empty string in database
            $item_data = $this->main->getState()->getFeedItem($item_id);
            $ad_sections_raw = $item_data['ad_sections'] ?? null;
            
            if ($ad_sections_raw === null || $ad_sections_raw === '') {
                // No ad detection has been performed yet
                $this->main->log("No ad_sections found for item $item_id, performing ad detection");
                
                // Get feed and item information for show and episode titles
                $item = $this->main->getState()->getFeedItem($item_id);
                $feed = $this->main->getState()->getFeed($item['feed_id']);
                $show = $feed['name'] ?? 'Unknown Show';
                $episode = $item['name'] ?? 'Unknown Episode';
                
                $ad_sections = $this->detectAds($transcript, $show, $episode);
                $detection_cost = $this->getLastDetectionCost();
                
                // Store ad sections (even if empty array, this marks it as processed)
                $this->main->getState()->setItemAdSections($item_id, $ad_sections);
            } else {
                // Ad detection has already been performed, use existing results
                $ad_sections = $existing_ad_sections;
                $this->main->log("Using existing ad_sections for item $item_id (" . count($ad_sections) . " sections found)");
            }
            
            // If ffmpeg ad removal is enabled, process the audio
            if ($this->main->getConf('podsumer', 'use_ffmpeg_ad_removal') && !empty($ad_sections)) {
                $output_file = tempnam(sys_get_temp_dir(), 'podsumer_clean_');
                
                if ($this->removeAdsFromAudio($audio_file_path, $output_file, $ad_sections)) {
                    // Read the cleaned audio and update the file
                    $cleaned_audio = file_get_contents($output_file);
                    $item = $this->main->getState()->getFeedItem($item_id);
                    $feed = $this->main->getState()->getFeed($item['feed_id']);
                    $file_id = $this->main->getState()->addFile($item['audio_url'] . '_cleaned', $cleaned_audio, $feed);
                    $this->main->getState()->setItemAudioFile($item_id, $file_id);
                    unlink($output_file);
                }
            }
            
            return $transcription_cost + $detection_cost;
            
        } catch (Exception $e) {
            // Log the error details
            $this->main->log("AdDetection error for item $item_id: " . $e->getMessage());
            $this->main->log("Stack trace: " . $e->getTraceAsString());
            
            // Re-throw the exception so it can be caught by the job script
            throw new Exception("Ad detection failed: " . $e->getMessage(), 0, $e);
        }
    }
} 