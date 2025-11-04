<?php

namespace App\Http\Controllers;

use App\Models\FileDetail;
use App\Models\FileHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    /**
     * Expected CSV columns
     */
    private const REQUIRED_COLUMNS = [
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
            'files.*' => 'required|file|mimes:csv,txt|max:10240', // max 10MB per file
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

        DB::beginTransaction();

        try {
            foreach ($files as $file) {
                $result = $this->processCsvFile($file);
                $results[] = $result;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Files processed successfully',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process a single CSV file
     */
    private function processCsvFile($file)
    {
        $fileName = $file->getClientOriginalName();
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new \Exception("Unable to open file: {$fileName}");
        }

        $headers = fgetcsv($handle);
        
        if ($headers === false) {
            fclose($handle);
            throw new \Exception("File is empty or invalid: {$fileName}");
        }

        $this->validateHeaders($headers, $fileName);

        $fileHeader = FileHeader::create([
            'file_name' => $fileName,
            'status' => 'uploaded',
        ]);

        $rowCount = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            
            if (empty(array_filter($row))) {
                continue;
            }

            $rowData = array_combine($headers, $row);
            
            if ($rowData === false) {
                $errors[] = "Row {$rowCount}: Column count mismatch";
                continue;
            }

            if (empty($rowData['UNIQUE_KEY']) || empty($rowData['PRODUCT_TITLE']) || empty($rowData['PRODUCT_DESCRIPTION'])) {
                $errors[] = "Row {$rowCount}: Missing required fields (UNIQUE_KEY, PRODUCT_TITLE, or PRODUCT_DESCRIPTION)";
                continue;
            }

            try {
                FileDetail::create([
                    'file_header_id' => $fileHeader->id,
                    'UNIQUE_KEY' => $rowData['UNIQUE_KEY'],
                    'PRODUCT_TITLE' => $rowData['PRODUCT_TITLE'],
                    'PRODUCT_DESCRIPTION' => $rowData['PRODUCT_DESCRIPTION'],
                    'STYLE#' => $rowData['STYLE#'] ?? null,
                    'SANMAR_MAINFRAME_COLOR' => $rowData['SANMAR_MAINFRAME_COLOR'] ?? null,
                    'SIZE' => $rowData['SIZE'] ?? null,
                    'COLOR_NAME' => $rowData['COLOR_NAME'] ?? null,
                    'PIECE_PRICE' => !empty($rowData['PIECE_PRICE']) ? (float) $rowData['PIECE_PRICE'] : null,
                ]);
            } catch (\Exception $e) {
                $errors[] = "Row {$rowCount}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $status = empty($errors) ? 'processed' : 'processed_with_errors';
        $fileHeader->update(['status' => $status]);

        return [
            'file_name' => $fileName,
            'file_header_id' => $fileHeader->id,
            'rows_processed' => $rowCount,
            'status' => $status,
            'errors' => $errors,
        ];
    }

    /**
     * Validate CSV headers
     */
    private function validateHeaders(array $headers, string $fileName)
    {
        $headers = array_map('trim', $headers);
        
        $missingColumns = [];
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (!in_array($column, $headers, true)) {
                $missingColumns[] = $column;
            }
        }

        if (!empty($missingColumns)) {
            throw new \Exception("File '{$fileName}' has missing columns: " . implode(', ', $missingColumns));
        }

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

