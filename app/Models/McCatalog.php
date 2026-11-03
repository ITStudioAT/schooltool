<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McCatalog extends Model
{
    protected $fillable = ['school_id', 'owner_id', 'title', 'questions', 'module_sources'];

    protected $casts = ['questions' => 'array', 'module_sources' => 'array'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(McTest::class, 'catalog_id');
    }
}
