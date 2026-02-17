<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialCardAttachment extends Model
{
    use HasFactory;

    public const TYPE_FILE = 'file';
    public const TYPE_LINK = 'link';

    protected $fillable = [
        'material_card_id',
        'attachment_type',
        'name',
        'url',
        'file_path',
        'mime_type',
        'size_bytes',
    ];

    public function materialCard(): BelongsTo
    {
        return $this->belongsTo(MaterialCard::class);
    }
}
