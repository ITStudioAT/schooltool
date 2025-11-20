<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $school_id
 * @property int $licence_id
 * @property string|null $valid_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereLicenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereValidUntil($value)
 * @mixin \Eloquent
 * @mixin IdeHelperSchoolLicence
 */
class SchoolLicence extends Model
{
    protected $guarded = [];
}
