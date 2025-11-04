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
        'status',
    ];

    /**
     * Get the file details for the file header.
     */
    public function fileDetails(): HasMany
    {
        return $this->hasMany(FileDetail::class);
    }
}

