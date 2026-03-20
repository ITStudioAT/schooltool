<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbaAttachment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const DOCUMENT_KIND_MAIN = 'main_document';

    public const DOCUMENT_KIND_ADDITIONAL = 'additional_document';

    protected $fillable = [
        'aba_id',
        'document_kind',
        'original_name',
        'path',
        'stored_name',
        'disk',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function aba(): BelongsTo
    {
        return $this->belongsTo(Aba::class);
    }

    public function isMainDocument(): bool
    {
        return $this->document_kind === self::DOCUMENT_KIND_MAIN;
    }
}
