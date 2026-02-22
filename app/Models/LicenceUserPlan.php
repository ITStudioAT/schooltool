<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenceUserPlan extends Model
{
    protected $fillable = [
        'licence_id',
        'role_name',
        'text',
        'price_per_year',
        'sort_order',
    ];

    public function licence(): BelongsTo
    {
        return $this->belongsTo(Licence::class);
    }
}
