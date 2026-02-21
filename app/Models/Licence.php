<?php

namespace App\Models;

use App\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


/**
 * @property int $id
 * @property string|null $name
 * @property string|null $long_name
 * @property int|null $price_per_year
 * @property int $is_selectable
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereIsSelectable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereLongName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence wherePricePerYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereUpdatedAt($value)
 * @mixin IdeHelperLicence
 * @property-read \Illuminate\Database\Eloquent\Collection<int, School> $schools
 * @property-read int|null $schools_count
 * @mixin \Eloquent
 */
class Licence extends Model
{
    protected $fillable = [
        'name',
        'long_name',
        'price_per_year',
        'is_selectable',
        'licence_model',
    ];

    protected $casts = [
        'licence_model' => 'array',
    ];

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_licences');
    }
}
