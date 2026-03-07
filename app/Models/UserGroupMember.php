<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGroupMember extends Model
{
    use HasFactory;

    public const PROVIDER_USER = 'user';
    public const PROVIDER_IMPORT116_STUDENT = 'import116.student';
    public const PROVIDER_IMPORT116_PARENT_CONTACT = 'import116.parent_contact';
    public const PROVIDER_TEACHER_LIST_TEACHER = 'teacher_list.teacher';

    public const SOURCE_STATUS_ACTIVE = 'active';
    public const SOURCE_STATUS_MISSING = 'missing';
    public const SOURCE_STATUS_OUT_OF_SCOPE = 'out_of_scope';

    public const LINKED_USER_STATUS_LINKED = 'linked';
    public const LINKED_USER_STATUS_MISSING = 'missing';
    public const LINKED_USER_STATUS_NOT_APPLICABLE = 'not_applicable';

    protected $fillable = [
        'user_group_id',
        'school_id',
        'member_provider',
        'member_ref',
        'linked_user_id',
        'source_schoolyear_id',
        'display_name',
        'display_email',
        'display_phone',
        'display_schoolclass',
        'display_children_label',
        'member_type_label',
        'source_status',
        'linked_user_status',
        'meta',
        'added_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}
