<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_header_id',
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
     * Get the file header that owns the file detail.
     */
    public function fileHeader(): BelongsTo
    {
        return $this->belongsTo(FileHeader::class);
    }
}

