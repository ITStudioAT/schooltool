<?php

namespace App\Models;

use App\Models\Licence;
use App\Models\Schoolyear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function licences(): BelongsToMany
    {
        return $this->belongsToMany(Licence::class, 'school_licences');
    }

    public function schoolyears(): HasMany
    {
        return $this->hasMany(Schoolyear::class);
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }


    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // The one active year (or null)
    public function activeSchoolyear(): HasOne
    {
        return $this->hasOne(Schoolyear::class)->where('is_active', true);
    }

    public function scopeSelectables($query)
    {
        return $query->where('is_selectable', true)->orderBy('long_name');
    }

    public function selectableValidLicences()
    {
        return $this->licences()
            ->where('licences.is_selectable', true)
            ->where(function ($q) {
                $q->whereNull('school_licences.valid_until')
                    ->orWhereDate('school_licences.valid_until', '>=', now()->toDateString());
            })
            ->orderBy('licences.long_name');
    }
}
