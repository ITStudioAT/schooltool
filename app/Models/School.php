<?php

namespace App\Models;

use App\Models\Licence;
use App\Models\Schoolyear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    protected $guarded = [];

    public function licences(): BelongsToMany
    {
        return $this->belongsToMany(Licence::class, 'school_licences');
    }

    public function schoolyears(): HasMany
    {
        return $this->hasMany(Schoolyear::class);
    }

    // The one active year (or null)
    public function activeSchoolyear(): HasOne
    {
        return $this->hasOne(Schoolyear::class)->where('is_active', true);
    }
}
