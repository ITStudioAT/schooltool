<?php

namespace App\Models;

use App\Support\SafeHtml;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const STUDENTS_TIMETABLES_ADMIN_VERSION_V2 = 'v2';

    public const STUDENTS_TIMETABLES_ADMIN_VERSION_V3 = 'v3';

    public const STUDENTS_TIMETABLES_ADMIN_VERSIONS = [
        self::STUDENTS_TIMETABLES_ADMIN_VERSION_V2,
        self::STUDENTS_TIMETABLES_ADMIN_VERSION_V3,
    ];

    protected $fillable = [
        'school_id',
        'active_schoolyear_id',
        'register_visible_admin',
        'register_visible_user',
        'register_user_test_mode',
        'register_user_comming_soon',
        'tutoring_visible_admin',
        'tutoring_visible_user',
        'tutoring_user_test_mode',
        'tutoring_user_comming_soon',
        'teaching_visible_admin',
        'teaching_visible_user',
        'teaching_user_test_mode',
        'teaching_user_comming_soon',
        'materials_visible_admin',
        'materials_visible_user',
        'materials_user_test_mode',
        'materials_user_comming_soon',
        'restaurant_visible_admin',
        'restaurant_visible_user',
        'restaurant_user_test_mode',
        'restaurant_user_comming_soon',
        'aba_visible_admin',
        'aba_visible_user',
        'aba_user_test_mode',
        'aba_user_comming_soon',
        'students_timetables_visible_admin',
        'students_timetables_visible_user',
        'students_timetables_user_test_mode',
        'students_timetables_user_comming_soon',
        'students_timetables_admin_version',
        'register_status',
        'tutoring_status',
        'teaching_status',
        'materials_status',
        'restaurant_status',
        'tutoring_student_must_be_confirmed',
        'tutoring_confirmer_email',
        'tutoring_max_offers_per_student',
        'may_visible_for_other_schools',
        'material_max_file_upload_size',
        'health_at',
        'import_166_at',
        'restaurant_menu_visibility_start_mode',
        'restaurant_menu_visibility_start_week_offset',
        'restaurant_menu_visibility_start_day_of_week',
        'restaurant_menu_visibility_start_time',
        'restaurant_menu_order_start_mode',
        'restaurant_menu_order_start_week_offset',
        'restaurant_menu_order_start_day_of_week',
        'restaurant_menu_order_start_time',
        'restaurant_menu_order_end_week_offset',
        'restaurant_menu_order_end_day_of_week',
        'restaurant_menu_order_end_time',
        'restaurant_menu_visibility_end_mode',
        'restaurant_service_email',
        'restaurant_new_users_must_confirm_email',
        'restaurant_new_users_confirmer_email',
        'restaurant_user_information_intro_html',
        'restaurant_sepa_online_enabled',
        'restaurant_sepa_payee',
        'restaurant_sepa_mandate_text',
    ];

    protected $casts = [
        'health_at' => 'datetime',
        'import_166_at' => 'datetime',
        'register_visible_admin' => 'boolean',
        'register_visible_user' => 'boolean',
        'register_user_test_mode' => 'boolean',
        'register_user_comming_soon' => 'boolean',
        'tutoring_visible_admin' => 'boolean',
        'tutoring_visible_user' => 'boolean',
        'tutoring_user_test_mode' => 'boolean',
        'tutoring_user_comming_soon' => 'boolean',
        'teaching_visible_admin' => 'boolean',
        'teaching_visible_user' => 'boolean',
        'teaching_user_test_mode' => 'boolean',
        'teaching_user_comming_soon' => 'boolean',
        'materials_visible_admin' => 'boolean',
        'materials_visible_user' => 'boolean',
        'materials_user_test_mode' => 'boolean',
        'materials_user_comming_soon' => 'boolean',
        'restaurant_visible_admin' => 'boolean',
        'restaurant_visible_user' => 'boolean',
        'restaurant_user_test_mode' => 'boolean',
        'restaurant_user_comming_soon' => 'boolean',
        'aba_visible_admin' => 'boolean',
        'aba_visible_user' => 'boolean',
        'aba_user_test_mode' => 'boolean',
        'aba_user_comming_soon' => 'boolean',
        'students_timetables_visible_admin' => 'boolean',
        'students_timetables_visible_user' => 'boolean',
        'students_timetables_user_test_mode' => 'boolean',
        'students_timetables_user_comming_soon' => 'boolean',
        'tutoring_student_must_be_confirmed' => 'boolean',
        'may_visible_for_other_schools' => 'boolean',
        'material_max_file_upload_size' => 'integer',
        'restaurant_menu_visibility_start_week_offset' => 'integer',
        'restaurant_menu_visibility_start_day_of_week' => 'integer',
        'restaurant_menu_order_start_week_offset' => 'integer',
        'restaurant_menu_order_start_day_of_week' => 'integer',
        'restaurant_menu_order_end_week_offset' => 'integer',
        'restaurant_menu_order_end_day_of_week' => 'integer',
        'restaurant_new_users_must_confirm_email' => 'boolean',
        'restaurant_sepa_online_enabled' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public static function normalizeStudentsTimetablesAdminVersion(mixed $version): string
    {
        return in_array($version, self::STUDENTS_TIMETABLES_ADMIN_VERSIONS, true)
            ? $version
            : self::STUDENTS_TIMETABLES_ADMIN_VERSION_V3;
    }

    protected function restaurantUserInformationIntroHtml(): Attribute
    {
        return $this->safeHtmlAttribute();
    }

    protected function restaurantSepaPayee(): Attribute
    {
        return $this->safeHtmlAttribute();
    }

    protected function restaurantSepaMandateText(): Attribute
    {
        return $this->safeHtmlAttribute();
    }

    private function safeHtmlAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
            set: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
        );
    }
}
