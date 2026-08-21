<?php

namespace App\Models;

use App\Enums\StudentTimetableStudyProgram;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_id
 * @property int|null $schoolyear_id
 * @property string $class
 * @property string|null $school_level
 * @property string|null $attendance_year
 * @property string|null $original_school_level
 * @property string|null $original_attendance_year
 * @property StudentTimetableStudyProgram|null $study_program
 * @property array<string, string|null>|null $study_selection
 * @property array{completed: list<array{code: string, grade: string, status: string}>, negative: list<array{code: string, grade: string, status: string}>}|null $course_results
 * @property string|null $religion
 * @property string $student_code
 * @property string $last_name
 * @property string $first_name
 * @property string|null $email
 * @property string|null $phone_1
 * @property string|null $phone_2
 * @property string|null $sex
 * @property Carbon|null $birth_date
 * @property string|null $mother_name
 * @property string|null $mother_email
 * @property string|null $mother_phone_1
 * @property string|null $mother_phone_2
 * @property string|null $father_name
 * @property string|null $father_email
 * @property string|null $father_phone_1
 * @property string|null $father_phone_2
 * @property Carbon $import_date
 * @property Carbon|null $exists_date
 * @property int $import_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class Import116 extends Model
{
    use HasFactory;

    protected $table = 'import116';

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'class',
        'school_level',
        'attendance_year',
        'original_school_level',
        'original_attendance_year',
        'study_program',
        'study_selection',
        'course_results',
        'religion',
        'student_code',
        'last_name',
        'first_name',
        'email',
        'phone_1',
        'phone_2',
        'sex',
        'birth_date',
        'mother_name',
        'mother_email',
        'mother_phone_1',
        'mother_phone_2',
        'father_name',
        'father_email',
        'father_phone_1',
        'father_phone_2',
        'import_date',
        'exists_date',
        'import_user_id',
        'user_id',
    ];

    protected $casts = [
        'study_program' => StudentTimetableStudyProgram::class,
        'study_selection' => 'array',
        'course_results' => 'array',
        'birth_date' => 'date',
        'import_date' => 'datetime',
        'exists_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function linkedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'import116_id');
    }

    public function teachingCourseStudents(): HasMany
    {
        return $this->hasMany(TeachingCourseStudent::class, 'import116_id');
    }
}
