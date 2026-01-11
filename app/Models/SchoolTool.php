<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $school_id
 * @property int $tutoring_student_must_be_confirmed
 * @property string|null $tutoring_confirmer_email
 * @property int $tutoring_max_offers_per_student
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereTutoringConfirmerEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereTutoringMaxOffersPerStudent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereTutoringStudentMustBeConfirmed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SchoolTool extends Model
{
    protected $fillable = [
        'school_id',
        'tutoring_student_must_be_confirmed',
        'tutoring_confirmer_email',
        'tutoring_max_offers_per_student',
    ];
}
