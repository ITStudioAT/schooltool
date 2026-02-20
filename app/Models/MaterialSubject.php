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
        'name',
        'sort_order',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
