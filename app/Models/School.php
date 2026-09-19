<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $long_name
 * @property string|null $short_name
 * @property string|null $email
 * @property string|null $logo
 * @property string $color
 * @property int $is_selectable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Schoolyear|null $activeSchoolyear
 * @property-read Collection<int, Licence> $licences
 * @property-read int|null $licences_count
 * @property-read Collection<int, Register> $registers
 * @property-read int|null $registers_count
 * @property-read Collection<int, Schoolyear> $schoolyears
 * @property-read int|null $schoolyears_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\SchoolFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School selectables()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereIsSelectable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereLongName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereShortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereUpdatedAt($value)
 */
class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'long_name',
        'short_name',
        'email',
        'logo',
        'color',
        'is_selectable',
    ];

    public function licences(): BelongsToMany
    {
        return $this->belongsToMany(Licence::class, 'school_licences')->withPivot('id', 'valid_until', 'licence_model');
    }

    public function schoolyears(): HasMany
    {
        return $this->hasMany(Schoolyear::class);
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }

    /** @return HasOne<SchoolTool, $this> */
    public function schoolTool(): HasOne
    {
        return $this->hasOne(SchoolTool::class, 'school_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function schoolUserLicences(): HasMany
    {
        return $this->hasMany(SchoolUserLicence::class);
    }

    public function schoolLicences(): HasMany
    {
        return $this->hasMany(SchoolLicence::class);
    }

    // The one active year (or null)
    public function activeSchoolyear(): HasOne
    {
        return $this->hasOne(Schoolyear::class)->where('is_active', true);
    }

    public function scopeSelectables($query)
    {
        return $query->where('is_selectable', true)->orderBy('long_name');
    }

    public function selectableValidLicences()
    {
        $today = now()->toDateString();

        return $this->licences()
            ->where('licences.is_selectable', true)
            ->where(function ($q) use ($today) {
                $q->where(function ($requiredQuery) use ($today) {
                    $requiredQuery
                        ->where(function ($requiresSchoolLicence) {
                            $requiresSchoolLicence
                                ->whereNull('school_licences.licence_model->school_licence_required')
                                ->orWhere('school_licences.licence_model->school_licence_required', true);
                        })
                        ->where(function ($validDateQuery) use ($today) {
                            $validDateQuery
                                ->whereNull('school_licences.valid_until')
                                ->orWhereDate('school_licences.valid_until', '>=', $today);
                        });
                })->orWhere(function ($notRequiredQuery) {
                    $notRequiredQuery
                        ->where('school_licences.licence_model->school_licence_required', false)
                        ->orWhere(function ($fallbackTemplateQuery) {
                            $fallbackTemplateQuery
                                ->whereNull('school_licences.licence_model->school_licence_required')
                                ->where('licences.licence_model->school_licence_required', false);
                        });
                });
            })
            ->orderBy('licences.long_name');
    }
}
