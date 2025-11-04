<?php

namespace App\Jobs;

use App\Models\FileDetail;
use App\Models\FileHeader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessCsvFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $fileHeaderId,
        public string $filePath,
        public string $fileName
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $fileHeader = FileHeader::findOrFail($this->fileHeaderId);

        // Update status to processing
        $fileHeader->update([
            'status' => 'processing',
        ]);

        try {
            $this->processCsvFile($fileHeader);
        } catch (\Exception $e) {
            $fileHeader->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Process the CSV file
     */
    private function processCsvFile(FileHeader $fileHeader): void
    {
        $handle = fopen($this->filePath, 'r');

        if ($handle === false) {
            throw new \Exception("Unable to open file: {$this->fileName}");
        }

        // Read header row
        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            throw new \Exception("File is empty or invalid: {$this->fileName}");
        }

        // Clean UTF-8 from headers
        $headers = $this->cleanUtf8($headers);

        // Count total rows (for progress tracking)
        $totalRows = 0;
        $tempHandle = fopen($this->filePath, 'r');
        fgetcsv($tempHandle); // Skip header
        while (fgetcsv($tempHandle) !== false) {
            $totalRows++;
        }
        fclose($tempHandle);

        // Update total rows
        $fileHeader->update(['total_rows' => $totalRows]);

        $processedRows = 0;
        $successfulRows = 0;
        $failedRows = 0;
        $errors = [];

        // Process data rows
        while (($row = fgetcsv($handle)) !== false) {
            $processedRows++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Clean UTF-8 from row data
            $row = $this->cleanUtf8($row);

            // Map row data to columns
            $rowData = array_combine($headers, $row);

            if ($rowData === false) {
                $errors[] = "Row {$processedRows}: Column count mismatch";
                $failedRows++;
                $this->updateProgress($fileHeader, $processedRows, $successfulRows, $failedRows);
                continue;
            }

            // Clean UTF-8 from row values
            $rowData = $this->cleanUtf8Array($rowData);

            // Validate required fields
            if (!isset($rowData['UNIQUE_KEY']) || empty($rowData['UNIQUE_KEY']) ||
                !isset($rowData['PRODUCT_TITLE']) || empty($rowData['PRODUCT_TITLE']) ||
                !isset($rowData['PRODUCT_DESCRIPTION']) || empty($rowData['PRODUCT_DESCRIPTION'])) {
                $errors[] = "Row {$processedRows}: Missing required fields (UNIQUE_KEY, PRODUCT_TITLE, or PRODUCT_DESCRIPTION)";
                $failedRows++;
                $this->updateProgress($fileHeader, $processedRows, $successfulRows, $failedRows);
                continue;
            }

            // Process the row
            try {
                $this->upsertRow($fileHeader, $rowData);
                $successfulRows++;
            } catch (\Exception $e) {
                $errors[] = "Row {$processedRows}: " . $e->getMessage();
                $failedRows++;
            }

            // Update progress every 100 rows
            if ($processedRows % 100 === 0) {
                $this->updateProgress($fileHeader, $processedRows, $successfulRows, $failedRows);
            }
        }

        fclose($handle);

        // Final progress update
        $this->updateProgress($fileHeader, $processedRows, $successfulRows, $failedRows);

        // Update final status
        $status = $failedRows > 0 ? 'completed_with_errors' : 'completed';
        $fileHeader->update([
            'status' => $status,
            'completed_at' => now(),
        ]);

        // Clean up file
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }

    /**
     * Upsert a single row
     */
    private function upsertRow(FileHeader $fileHeader, array $rowData): void
    {
        $upsertData = [
            'file_header_id' => $fileHeader->id,
            'PRODUCT_TITLE' => $rowData['PRODUCT_TITLE'] ?? '',
            'PRODUCT_DESCRIPTION' => $rowData['PRODUCT_DESCRIPTION'] ?? '',
        ];

        // Add optional columns
        $validColumns = ['STYLE#', 'SANMAR_MAINFRAME_COLOR', 'SIZE', 'COLOR_NAME', 'PIECE_PRICE'];
        foreach ($validColumns as $column) {
            if (isset($rowData[$column])) {
                if ($column === 'PIECE_PRICE') {
                    $upsertData[$column] = !empty($rowData[$column]) ? (float) $rowData[$column] : null;
                } else {
                    $upsertData[$column] = $rowData[$column] ?? null;
                }
            }
        }

        FileDetail::updateOrCreate(
            ['UNIQUE_KEY' => $rowData['UNIQUE_KEY']],
            $upsertData
        );
    }

    /**
     * Update progress in database
     */
    private function updateProgress(FileHeader $fileHeader, int $processed, int $successful, int $failed): void
    {
        $fileHeader->update([
            'processed_rows' => $processed,
            'successful_rows' => $successful,
            'failed_rows' => $failed,
        ]);
    }

    /**
     * Clean non-UTF-8 characters from string or array
     */
    private function cleanUtf8($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'cleanUtf8'], $data);
        }

        if (!is_string($data)) {
            return $data;
        }

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $data);

        if ($cleaned === false) {
            $cleaned = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);

        if (!mb_check_encoding($cleaned, 'UTF-8')) {
            $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
            $cleaned = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $cleaned);
        }

        return trim($cleaned);
    }

    /**
     * Clean UTF-8 from array values recursively
     */
    private function cleanUtf8Array(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $cleaned[$key] = $this->cleanUtf8($value);
        }
        return $cleaned;
    }
}

