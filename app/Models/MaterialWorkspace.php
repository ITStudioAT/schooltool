<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialWorkspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(MaterialCard::class, 'workspace_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(MaterialSubject::class, 'workspace_id');
    }

    public function shareRules(): HasMany
    {
        return $this->hasMany(MaterialShareRule::class, 'workspace_id');
    }
}
