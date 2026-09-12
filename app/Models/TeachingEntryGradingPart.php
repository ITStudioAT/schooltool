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
        'name',
        'weight',
        'is_required',
        'fixed_percentage',
        'allowed_entry_types',
        'points_assessment_mode',
        'individual_points_weighting_mode',
        'overall_points_grade_thresholds',
    ];

    protected $attributes = ['weight' => 1, 'is_required' => false, 'allowed_entry_types' => 'all', 'points_assessment_mode' => 'individual', 'individual_points_weighting_mode' => 'weighted'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:3', 'is_required' => 'boolean', 'fixed_percentage' => 'decimal:3', 'overall_points_grade_thresholds' => 'array'];
    }

    public function overallMaximumPoints(): float
    {
        return (float) (array_key_exists('overall_maximum_points', $this->getAttributes())
            ? $this->getAttributes()['overall_maximum_points']
            : $this->entryDefinitions()->where('properties_mode', 'points')->sum('maximum_points'));
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
        return Attribute::make(get: fn (?string $value): string => $this->allowed_entry_types === 'points' ? ($value ?? 'individual') : 'individual');
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
