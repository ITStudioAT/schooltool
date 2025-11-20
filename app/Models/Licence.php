<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
 * @mixin \Eloquent
 * @mixin IdeHelperLicence
 */
class Licence extends Model
{
    protected $guarded = [];
}
