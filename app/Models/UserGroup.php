<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGroup extends Model
{
    public const TYPE_SCHOOL = 'school';

    public const TYPE_MATERIALS = 'materials';

    public const TYPE_OWN = 'own';

    public const TYPES = [
        self::TYPE_SCHOOL,
        self::TYPE_MATERIALS,
        self::TYPE_OWN,
    ];

    public const TEACHING_COURSE_GROUP_TYPE_STUDENTS = 'students';

    public const TEACHING_COURSE_GROUP_TYPE_PARENTS = 'parents';

    protected $fillable = [
        'school_id',
        'type',
        'name',
        'description',
        'created_by_user_id',
        'teaching_course_id',
        'teaching_course_group_type',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }

    public function groupMembers(): HasMany
    {
        return $this->hasMany(UserGroupMember::class, 'user_group_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_members', 'user_group_id', 'linked_user_id')
            ->withPivot([
                'added_by_user_id',
                'member_provider',
                'member_ref',
                'source_status',
                'linked_user_status',
            ])
            ->withTimestamps();
    }
}
