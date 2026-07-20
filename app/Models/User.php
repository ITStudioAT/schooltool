<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\StandardEmail;
use App\Services\AccessScopeService;
use App\Services\EmailAliasResolver;
use App\Traits\UserTrait;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate as ImpersonateTrait;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int $school_id
 * @property int|null $schoolyear_id
 * @property int|null $register_id
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $last_name
 * @property string|null $first_name
 * @property string|null $phone
 * @property Carbon|null $sepa_at
 * @property string|null $login_at
 * @property string|null $login_ip
 * @property int|null $is_2fa
 * @property string|null $token_2fa
 * @property Carbon|null $token_2fa_expires_at
 * @property string|null $token_2fa_2
 * @property string|null $token_2fa_2_expires_at
 * @property string|null $email_2fa
 * @property string|null $email_2fa_verified_at
 * @property int|null $is_active
 * @property string|null $register_started_at
 * @property string|null $register_as
 * @property string|null $confirmed_at
 * @property Carbon|null $restaurant_confirmed_at
 * @property string|null $uuid
 * @property string|null $uuid_at
 * @property-read string $full_name
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, RegisterDateBooking> $registerDateBookings
 * @property-read int|null $register_date_bookings_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Register|null $selectedRegister
 * @property-read School|null $selectedSchool
 * @property-read Schoolyear|null $selectedSchoolyear
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail2fa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail2faVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIs2fa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisterAs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisterStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRestaurantConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSchoolyearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereToken2fa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereToken2fa2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereToken2fa2ExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereToken2faExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method bool hasRole(string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles, string|null $guard = null)
 * @method bool hasAnyRole(string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles, string|null $guard = null)
 * @method bool hasAllRoles(string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles, string|null $guard = null)
 * @method \Illuminate\Database\Eloquent\Collection|\Spatie\Permission\Models\Role[] getRoleNames()
 *
 * @mixin HasRoles
 * @mixin IdeHelperUser
 *
 * @property string|null $sex
 * @property string $schoolclass
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User bySchoolAndRole($schoolId, $roleName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSchoolclass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSex($value)
 *
 * @property string|null $short
 * @property array<array-key, mixed>|null $tutoring_filter
 * @property array<array-key, mixed>|null $restaurant_booking_defaults
 * @property array<array-key, int>|null $hopper_account_ids
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User teachers($school_id = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTutoringFilter($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use ImpersonateTrait;
    use Notifiable;
    use UserTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'register_id',
        'email',
        'password',
        'last_name',
        'first_name',
        'short',
        'phone',
        'sex',
        'schoolclass',
        'register_as',
        'import116_id',
        'teaching_active_semester',
        'teaching_count_for_semester_2_date',
        'teaching_behaviour',
        'teaching_behaviour_by_schoolyear',
        'teaching_notifications',
        'teaching_notifications_by_schoolyear',
        'teaching_show_behaviour',
        'teaching_grade_columns_by_schoolyear',
        'teaching_student_grade_columns_by_schoolyear',
        'materials_pagination_number',
        'restaurant_foods_pagination_number',
    ];

    // Protected fields that should NOT be mass assignable:
    // email_verified_at, confirmed_at, is_2fa, token_2fa, token_2fa_expires_at,
    // token_2fa_2, token_2fa_2_expires_at, email_2fa, email_2fa_verified_at,
    // is_active, uuid, uuid_at, login_at, login_ip, remember_token, register_started_at
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',

    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'restaurant_confirmed_at' => 'datetime',
            'token_2fa_expires_at' => 'datetime',
            'sepa_at' => 'datetime',
            'tutoring_filter' => 'array',
            'teaching_behaviour' => 'array',
            'teaching_behaviour_by_schoolyear' => 'array',
            'teaching_notifications' => 'array',
            'teaching_notifications_by_schoolyear' => 'array',
            'teaching_show_behaviour' => 'boolean',
            'teaching_grade_columns_by_schoolyear' => 'array',
            'teaching_student_grade_columns_by_schoolyear' => 'array',
            'restaurant_booking_defaults' => 'array',
            'hopper_account_ids' => 'array',
        ];
    }

    protected $attributes = [
        'tutoring_filter' => '{"only_boys":false,"only_girls":false,"only_in_my_school":true,"schools":[]}',
    ];

    public function scopeTeachers($query, $school_id = null)
    {
        $query->role('teacher');

        if ($school_id) {
            $query->where('school_id', $school_id);
        }

        return $query;
    }

    public function scopeBySchoolAndRole($query, $schoolId, $roleName)
    {
        return $query->where('school_id', $schoolId)
            ->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            });
    }

    public function selectedSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function selectedSchoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class, 'schoolyear_id');
    }

    public function selectedRegister(): BelongsTo
    {
        return $this->belongsTo(Register::class, 'register_id');
    }

    public function teachingCourseStudents(): HasMany
    {
        return $this->hasMany(TeachingCourseStudent::class);
    }

    public function registerDateBookings(): HasMany
    {
        return $this->hasMany(RegisterDateBooking::class);
    }

    public function getFullNameAttribute(): string
    {
        $firstName = trim((string) $this->first_name);
        $lastName = trim((string) $this->last_name);
        $fullName = trim(implode(' ', array_filter([$firstName, $lastName])));

        if ($fullName !== '') {
            return $fullName;
        }

        return trim((string) $this->email);
    }

    public function import116(): BelongsTo
    {
        return $this->belongsTo(Import116::class);
    }

    public function teachingSchemas(): HasMany
    {
        return $this->hasMany(TeachingSchema::class);
    }

    public function schoolUserLicences(): HasMany
    {
        return $this->hasMany(SchoolUserLicence::class);
    }

    public function restaurantSepaMandates(): HasMany
    {
        return $this->hasMany(RestaurantSepaMandate::class);
    }

    public function materialCards(): HasMany
    {
        return $this->hasMany(MaterialCard::class);
    }

    public function materialWorkspaces(): HasMany
    {
        return $this->hasMany(MaterialWorkspace::class);
    }

    public function tutoringOffers(): HasMany
    {
        return $this->hasMany(TutoringOffer::class, 'user_id');
    }

    public function shouldDelete(): bool
    {

        if (count($this->roles) > 0) {
            abort(403, 'Benutzer kann nicht gelöscht werden, da er noch Rollen inne hat.');
        }
        $this->delete();

        return true;
    }

    public function sendVerificationEmail()
    {
        $uuid = $this->generateUuid();

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'E-Mail-Verifikation',
            'markdown' => 'spa::mails.admin.sendEmailVerification',
            'url' => $data['url'] = config('app.url').'/admin/email_verification?email='.$this->email.'&uuid='.$uuid,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($this->email))->notify(new StandardEmail($data));
    }

    public function sendConfirmEmail()
    {
        // XXXXXXXXXXXXXXXXXXXXX
        $uuid = $this->generateUuid();

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'E-Mail-Verifikation',
            'markdown' => 'spa::mails.admin.sendEmailVerification',
            'url' => $data['url'] = config('app.url').'/admin/email_verification?email='.$this->email.'&uuid='.$uuid,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($this->email))->notify(new StandardEmail($data));
    }

    public function generateUuid(): string
    {
        $this->uuid = (string) Str::uuid();
        $this->uuid_at = now();
        $this->save();

        return $this->uuid;
    }

    public function checkUuid($uuid): bool
    {
        if ($this->uuid !== $uuid || ! $this->uuid_at) {
            return false;
        }

        return $this->uuid_at > now()->subMinutes(config('spa.token_expire_time', 120));
    }

    public function emailVerified(): bool
    {
        $this->email_verified_at = now();
        $this->uuid = null;
        $this->uuid_at = null;
        $this->save();

        return true;
    }

    public function hasDependencies(): bool
    {
        if (RegisterDateBooking::where('user_id', $this->id)->count() > 0) {
            return true;
        }
        if (TutoringOffer::where('user_id', $this->id)->count() > 0) {
            return true;
        }

        return false;
    }

    public function canImpersonate(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function canBeImpersonated(): bool
    {
        return true;
    }

    public function hasAdminShellAccess(): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $hasAdminRole = $this->relationLoaded('roles')
            ? $this->roles->contains(fn ($role): bool => (bool) ($role->is_admin ?? false))
            : $this->roles()->where('is_admin', true)->exists();

        return $hasAdminRole
            || $this->hasAnyRole(app(AccessScopeService::class)->roleNamesForScope('admin_shell_access'));
    }
}
