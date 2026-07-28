<?php

namespace App\Models;

use App\Support\SafeExternalUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function url(): Attribute
    {
        return Attribute::get(
            fn (?string $value): ?string => SafeExternalUrl::sanitize($value)
        );
    }

    protected function sourceUrl(): Attribute
    {
        return Attribute::get(
            fn (?string $value): ?string => SafeExternalUrl::sanitize($value)
        );
    }

    public function materialCard(): BelongsTo
    {
        return $this->belongsTo(MaterialCard::class);
    }
}
