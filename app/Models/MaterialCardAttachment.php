<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialCardAttachment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_FILE = 'file';

    public const TYPE_LINK = 'link';

    protected $fillable = [
        'material_card_id',
        'attachment_type',
        'name',
        'url',
        'source_url',
        'file_path',
        'mime_type',
        'size_bytes',
        'downloaded_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function materialCard(): BelongsTo
    {
        return $this->belongsTo(MaterialCard::class);
    }
}
