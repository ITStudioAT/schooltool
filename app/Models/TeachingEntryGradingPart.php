<?php

namespace App\Models;

use Database\Factories\TeachingEntryGradingPartFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingEntryGradingPart extends Model
{
    /** @use HasFactory<TeachingEntryGradingPartFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'teaching_entry_area_id',
        'grading_group_id',
        'name',
        'weight',
        'is_required',
        'fixed_percentage',
        'allowed_entry_types',
        'points_assessment_mode',
        'individual_points_weighting_mode',
        'overall_points_grade_thresholds',
        'sign_grade_thresholds',
        'sign_adjustment',
    ];

    protected $attributes = ['weight' => 1, 'is_required' => false, 'allowed_entry_types' => 'all', 'points_assessment_mode' => 'individual', 'individual_points_weighting_mode' => 'weighted'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:3', 'is_required' => 'boolean', 'fixed_percentage' => 'decimal:3', 'overall_points_grade_thresholds' => 'array', 'sign_grade_thresholds' => 'array', 'sign_adjustment' => 'array'];
    }

    /** @return list<string> */
    public static function allowedEntryTypeOptions(): array
    {
        return ['all', 'points', 'non_points', 'signs', 'grades', 'signs_pts', 'signs_note', 'pts_notes'];
    }

    public static function entryTypeGroup(TeachingEntryDefinition $entry): string
    {
        if ($entry->properties_mode === 'points') {
            return 'points';
        }
        if (in_array($entry->properties_mode, ['plus', 'plus_minus'], true)) {
            return 'signs';
        }
        $properties = $entry->fixed_properties ?? [];
        if (collect($properties)->contains(fn (mixed $value): bool => is_string($value) && preg_match('/^(?:[+\-−]+|~)$/u', trim($value)) === 1)
            && collect($properties)->every(fn (mixed $value): bool => is_string($value) && (trim($value) === '0' || preg_match('/^(?:[+\-−]+|~)$/u', trim($value)) === 1))) {
            return 'signs';
        }

        return 'grades';
    }

    public function allowsEntry(TeachingEntryDefinition $entry, ?string $allowedTypes = null): bool
    {
        $groups = match ($allowedTypes ?? $this->allowed_entry_types) {
            'all' => ['signs', 'points', 'grades'],
            'non_points', 'signs_note' => ['signs', 'grades'],
            'signs_pts' => ['signs', 'points'],
            'pts_notes' => ['points', 'grades'],
            default => [$allowedTypes ?? $this->allowed_entry_types],
        };

        return in_array(self::entryTypeGroup($entry), $groups, true);
    }

    public static function isStandardGradeType(TeachingEntryDefinition $entry): bool
    {
        return $entry->calculation_mode === 'grades';
    }

    public function usesSignBalance(): bool
    {
        return in_array($this->points_assessment_mode, ['plus_minus', 'sign_grade', 'sign_adjust'], true);
    }

    public function overallMaximumPoints(): float
    {
        return (float) (array_key_exists('overall_maximum_points', $this->getAttributes())
            ? $this->getAttributes()['overall_maximum_points']
            : $this->entryDefinitions()->where('properties_mode', 'points')->sum('maximum_points'));
    }

    public function hasValidSignGradeThresholds(): bool
    {
        $thresholds = $this->sign_grade_thresholds;
        if (! is_array($thresholds) || count($thresholds) !== 4) {
            return false;
        }
        foreach ([4, 3, 2, 1] as $grade) {
            $value = $thresholds[$grade] ?? null;
            if (! is_int($value) || abs($value) > 9007199254740991 || ($grade < 4 && $value <= $thresholds[$grade + 1])) {
                return false;
            }
        }

        return true;
    }

    public function invalidateOverallPointsThresholds(): void
    {
        $maximum = $this->overallMaximumPoints();
        if ($this->overall_points_grade_thresholds !== null
            && collect($this->overall_points_grade_thresholds)->contains(fn (mixed $value): bool => $value > $maximum)) {
            $this->update(['overall_points_grade_thresholds' => null]);
        }
    }

    protected function pointsAssessmentMode(): Attribute
    {
        return Attribute::make(get: fn (?string $value): string => in_array($value, ['sum_percent', 'plus_minus', 'sign_grade', 'sign_adjust', 'grade_each', 'grade_mean'], true) ? $value : ($this->allowed_entry_types === 'points' ? ($value ?? 'individual') : 'individual'));
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(TeachingEntryArea::class, 'teaching_entry_area_id');
    }

    /** @return HasMany<TeachingEntryDefinition, $this> */
    public function entryDefinitions(): HasMany
    {
        return $this->hasMany(TeachingEntryDefinition::class, 'teaching_entry_grading_part_id');
    }
}
