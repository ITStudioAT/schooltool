<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_id
 * @property int $licence_id
 * @property string|null $valid_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereLicenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereValidUntil($value)
 *
 * @mixin IdeHelperSchoolLicence
 *
 * @property-read Licence|null $licence
 * @property-read School|null $school
 *
 * @mixin \Eloquent
 */
class SchoolLicence extends Model
{
    protected $fillable = [
        'school_id',
        'licence_id',
        'valid_until',
        'charged_school_price',
        'extra_storage_units',
        'extra_storage_unit_price',
        'charged_admin_price',
        'admin_extra_storage_units',
        'admin_extra_storage_unit_price',
        'charged_user_price',
        'user_extra_storage_units',
        'user_extra_storage_unit_price',
        'licence_model',
        'user_licence_assignments',
    ];

    protected $casts = [
        'charged_school_price' => 'decimal:2',
        'extra_storage_unit_price' => 'decimal:2',
        'charged_admin_price' => 'decimal:2',
        'admin_extra_storage_unit_price' => 'decimal:2',
        'charged_user_price' => 'decimal:2',
        'user_extra_storage_unit_price' => 'decimal:2',
        'licence_model' => 'array',
        'user_licence_assignments' => 'array',
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
