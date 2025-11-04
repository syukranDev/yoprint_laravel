<?php

use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/sample', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'This is a sample API endpoint',
        'data' => [
            'id' => 1,
            'name' => 'Sample Item',
            'timestamp' => now()->toDateTimeString()
        ]
    ]);
});

Route::post('/files/upload', [FileUploadController::class, 'upload']);

Route::get('/files', [FileUploadController::class, 'listFiles']);

Route::get('/files/details', [FileUploadController::class, 'getByUniqueKey']);

