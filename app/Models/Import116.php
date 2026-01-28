<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $school_id
 * @property string $class
 * @property string $student_code
 * @property string $last_name
 * @property string $first_name
 * @property string|null $email
 * @property string|null $phone_1
 * @property string|null $phone_2
 * @property string|null $sex
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $mother_name
 * @property string|null $mother_email
 * @property string|null $mother_phone_1
 * @property string|null $mother_phone_2
 * @property string|null $father_name
 * @property string|null $father_email
 * @property string|null $father_phone_1
 * @property string|null $father_phone_2
 * @property \Illuminate\Support\Carbon $import_date
 * @property \Illuminate\Support\Carbon|null $exists_date
 * @property int $import_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin \Eloquent
 */
class Import116 extends Model
{
    use HasFactory;

    protected $table = 'import116';

    protected $fillable = [
        'school_id',
        'class',
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
        'birth_date' => 'date',
        'import_date' => 'datetime',
        'exists_date' => 'datetime',
    ];
}
