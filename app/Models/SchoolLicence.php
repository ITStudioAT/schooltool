<?php

namespace App\Models;

use App\Models\Licence;
use App\Models\School;
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
 * @mixin IdeHelperSchoolLicence
 * @property-read Licence|null $licence
 * @property-read School|null $school
 * @mixin \Eloquent
 */
class SchoolLicence extends Model
{
    protected $fillable = [
        'school_id',
        'licence_id',
        'valid_until',
        'licence_model',
    ];

    protected $casts = [
        'licence_model' => 'array',
    ];

    public function licence()
    {
        return $this->belongsTo(Licence::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
