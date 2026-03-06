<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class MaterialSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'name',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (MaterialSubject $subject): void {
            if ((int) ($subject->workspace_id ?? 0) > 0 || (int) ($subject->user_id ?? 0) <= 0) {
                return;
            }

            $workspace = MaterialWorkspace::query()->firstOrCreate(
                [
                    'user_id' => (int) $subject->user_id,
                    'name' => 'Workspace',
                ],
                [
                    'is_default' => true,
                ]
            );

            $subject->workspace_id = (int) $workspace->id;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(MaterialWorkspace::class, 'workspace_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(MaterialTopic::class, 'subject_id')
            ->when(
                Schema::hasColumn('material_topics', 'sort_order'),
                fn ($query) => $query->orderBy('sort_order')
            )
            ->orderBy('name')
            ->orderBy('id');
    }
}
