<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCsvFile;
use App\Models\FileDetail;
use App\Models\FileHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    /**
     * Valid database columns (only these will be processed, extra columns ignored)
     */
    private const VALID_DB_COLUMNS = [
        'UNIQUE_KEY',
        'PRODUCT_TITLE',
        'PRODUCT_DESCRIPTION',
        'STYLE#',
        'SANMAR_MAINFRAME_COLOR',
        'SIZE',
        'COLOR_NAME',
        'PIECE_PRICE',
    ];

    /**
     * Upload multiple CSV files
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files.*' => 'required|file|mimes:csv,txt|max:51200', // max 50MB per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $files = $request->file('files');
        $results = [];

        try {
            foreach ($files as $file) {
                $result = $this->prepareFileForProcessing($file);
                $results[] = $result;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Files uploaded successfully. Processing in background.',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Prepare file for background processing
     */
    private function prepareFileForProcessing($file)
    {
        $fileName = $file->getClientOriginalName();
        $filePath = $file->getRealPath();
        
        // Calculate file hash for idempotency
        $fileHash = hash_file('sha256', $filePath);
        
        // Check if file with same hash already exists (idempotency check)
        $existingFileHeader = FileHeader::where('file_hash', $fileHash)->first();
        
        if ($existingFileHeader) {
            return [
                'file_name' => $fileName,
                'file_header_id' => $existingFileHeader->id,
                'status' => 'skipped',
                'message' => 'File already uploaded (idempotent)',
            ];
        }
        
        // Store file temporarily
        $tempPath = storage_path('app/temp/' . uniqid() . '_' . $fileName);
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        copy($filePath, $tempPath);
        
        // Create file header record with processing status
        $fileHeader = FileHeader::create([
            'file_name' => $fileName,
            'file_hash' => $fileHash,
            'status' => 'processing',
        ]);
        
        // Dispatch job to process CSV in background
        ProcessCsvFile::dispatch($fileHeader->id, $tempPath, $fileName);
        
        return [
            'file_name' => $fileName,
            'file_header_id' => $fileHeader->id,
            'status' => 'processing',
            'message' => 'File uploaded. Processing in background.',
        ];
    }

    /**
     * Get file processing status
     */
    public function getStatus($id)
    {
        $fileHeader = FileHeader::find($id);

        if (!$fileHeader) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found',
            ], 404);
        }

        // Calculate progress percentage
        $progress = 0;
        if ($fileHeader->total_rows > 0) {
            $progress = round(($fileHeader->processed_rows / $fileHeader->total_rows) * 100, 2);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $fileHeader->id,
                'file_name' => $fileHeader->file_name,
                'status' => $fileHeader->status,
                'total_rows' => $fileHeader->total_rows,
                'processed_rows' => $fileHeader->processed_rows,
                'successful_rows' => $fileHeader->successful_rows,
                'failed_rows' => $fileHeader->failed_rows,
                'progress_percentage' => $progress,
                'error_message' => $fileHeader->error_message,
                'created_at' => $fileHeader->created_at,
                'completed_at' => $fileHeader->completed_at,
            ],
        ], 200);
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
        
        // Use iconv to remove invalid UTF-8 sequences
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
        
        // If iconv fails, try mb_convert_encoding
        if ($cleaned === false) {
            $cleaned = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }
        
        // Remove any control characters except newlines (\n), tabs (\t), and carriage returns (\r)
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);
        
        // Final validation - ensure it's valid UTF-8
        if (!mb_check_encoding($cleaned, 'UTF-8')) {
            // Last resort: convert again and remove any remaining invalid sequences
            $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
            // Remove any bytes that aren't valid UTF-8
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

    /**
     * Fetch all file headers
     */
    public function listFiles()
    {
        $fileHeaders = FileHeader::orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $fileHeaders,
        ], 200);
    }

    /**
     * Fetch file details by unique key
     */
    public function getByUniqueKey(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unique_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $uniqueKey = $request->input('unique_key');
        
        $fileDetails = FileDetail::where('UNIQUE_KEY', $uniqueKey)
            ->with('fileHeader')
            ->get();

        if ($fileDetails->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No records found for the given unique key',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $fileDetails,
        ], 200);
    }
}

