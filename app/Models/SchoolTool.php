<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_id
 * @property int $tutoring_student_must_be_confirmed
 * @property string|null $tutoring_confirmer_email
 * @property int $tutoring_max_offers_per_student
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
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
 * @method static \Database\Factories\SchoolToolFactory factory($count = null, $state = [])
 *
 * @property Carbon|null $health_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereHealthAt($value)
 *
 * @mixin \Eloquent
 */
class SchoolTool extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'active_schoolyear_id',
        'tutoring_student_must_be_confirmed',
        'tutoring_confirmer_email',
        'tutoring_max_offers_per_student',
        'may_visible_for_other_schools',
        'material_max_file_upload_size',
        'health_at',
        'import_166_at',
        'restaurant_menu_order_start_mode',
        'restaurant_menu_order_start_week_offset',
        'restaurant_menu_order_start_day_of_week',
        'restaurant_menu_order_start_time',
        'restaurant_menu_order_end_week_offset',
        'restaurant_menu_order_end_day_of_week',
        'restaurant_menu_order_end_time',
    ];

    protected $casts = [
        'health_at' => 'datetime',
        'import_166_at' => 'datetime',
        'tutoring_student_must_be_confirmed' => 'boolean',
        'may_visible_for_other_schools' => 'boolean',
        'material_max_file_upload_size' => 'integer',
        'restaurant_menu_order_start_week_offset' => 'integer',
        'restaurant_menu_order_start_day_of_week' => 'integer',
        'restaurant_menu_order_end_week_offset' => 'integer',
        'restaurant_menu_order_end_day_of_week' => 'integer',
    ];
}
