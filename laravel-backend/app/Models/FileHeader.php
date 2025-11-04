<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FileHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_hash',
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * Get the file details for the file header.
     */
    public function fileDetails(): HasMany
    {
        return $this->hasMany(FileDetail::class);
    }
}

