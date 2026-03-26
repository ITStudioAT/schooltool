<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $long_name
 * @property int|null $price_per_year
 * @property string|null $start_day_month
 * @property string|null $end_day_month
 * @property int $is_selectable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
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
 *
 * @mixin IdeHelperLicence
 *
 * @property-read Collection<int, School> $schools
 * @property-read int|null $schools_count
 *
 * @mixin \Eloquent
 */
class Licence extends Model
{
    protected $fillable = [
        'name',
        'long_name',
        'price_per_year',
        'start_day_month',
        'end_day_month',
        'is_selectable',
        'licence_model',
        'licence_schema_version',
        'school_licence_enabled',
        'school_price_per_year',
        'admin_licence_enabled',
        'admin_price_per_year',
        'admin_role_names',
        'user_licence_enabled',
        'user_price_per_year',
        'user_role_names',
    ];

    protected $casts = [
        'licence_model' => 'array',
        'admin_role_names' => 'array',
        'user_role_names' => 'array',
        'school_licence_enabled' => 'boolean',
        'admin_licence_enabled' => 'boolean',
        'user_licence_enabled' => 'boolean',
    ];

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_licences');
    }

    public function userPlans(): HasMany
    {
        return $this->hasMany(LicenceUserPlan::class)->orderBy('role_name')->orderBy('sort_order')->orderBy('id');
    }

    public function schoolUserLicences(): HasMany
    {
        return $this->hasMany(SchoolUserLicence::class);
    }
}
