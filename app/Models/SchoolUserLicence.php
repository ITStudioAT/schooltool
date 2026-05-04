<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolUserLicence extends Model
{
    protected $fillable = [
        'school_id',
        'licence_id',
        'user_id',
        'assignment_type',
        'role_name',
        'valid_from',
        'valid_until',
        'base_price_per_year',
        'charged_price',
        'is_active',
        'plan_id',
        'extra_storage_units',
        'extra_storage_unit_price',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'base_price_per_year' => 'decimal:2',
            'charged_price' => 'decimal:2',
            'is_active' => 'boolean',
            'extra_storage_unit_price' => 'decimal:2',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function licence(): BelongsTo
    {
        return $this->belongsTo(Licence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
