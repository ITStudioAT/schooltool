<?php

namespace App\Models;

use Database\Factories\TeachingEntryDefinitionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingEntryDefinition extends Model
{
    /** @use HasFactory<TeachingEntryDefinitionFactory> */
    use HasFactory;

    public const TableMarkingColors = ['blue', 'green', 'orange', 'purple', 'red'];

    public const SpecialProperties = ['NA', 'VL', 'F'];

    public static function allowedOtherAssessmentModes(string $propertiesMode): array
    {
        return match ($propertiesMode) {
            'points' => ['points', 'weighted'],
            'plus_minus' => ['balance_rounding', 'balance_adjustment'],
            default => [],
        };
    }

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'teaching_entry_area_id',
        'teaching_entry_grading_part_id',
        'grading_part_assessment_mode',
        'grading_part_other_assessment_mode',
        'grading_part_plus_adjustment',
        'grading_part_minus_adjustment',
        'grading_part_weight',
        'short_name',
        'name',
        'description',
        'category',
        'has_properties',
        'properties_mode',
        'maximum_points',
        'points_grade_thresholds',
        'fixed_properties',
        'property_evaluations',
        'calculation_mode',
        'allows_maximum_plus',
        'sum_plus_evaluations',
        'maximum_plus_grading_mode',
        'maximum_plus_grade_thresholds',
        'free_grading_mode',
        'free_deficit_grade_thresholds',
        'free_points_grade_thresholds',
        'enabled_special_properties',
        'has_notifications',
        'notification_recipients',
        'has_table_marking',
        'table_marking_color',
    ];

    protected $attributes = [
        'grading_part_assessment_mode' => 'weighted',
        'grading_part_other_assessment_mode' => null,
        'grading_part_plus_adjustment' => null,
        'grading_part_minus_adjustment' => null,
        'grading_part_weight' => 1,
        'has_properties' => false,
        'properties_mode' => 'free',
        'maximum_points' => null,
        'points_grade_thresholds' => null,
        'calculation_mode' => 'individual',
        'allows_maximum_plus' => false,
        'sum_plus_evaluations' => false,
        'maximum_plus_grading_mode' => null,
        'maximum_plus_grade_thresholds' => null,
        'free_grading_mode' => null,
        'free_deficit_grade_thresholds' => null,
        'free_points_grade_thresholds' => null,
        'enabled_special_properties' => null,
        'has_notifications' => false,
        'has_table_marking' => false,
    ];

    protected $casts = [
        'grading_part_plus_adjustment' => 'float',
        'grading_part_minus_adjustment' => 'float',
        'grading_part_weight' => 'decimal:3',
        'maximum_points' => 'float',
        'points_grade_thresholds' => 'array',
        'allows_maximum_plus' => 'boolean',
        'sum_plus_evaluations' => 'boolean',
        'maximum_plus_grade_thresholds' => 'array',
        'free_deficit_grade_thresholds' => 'array',
        'free_points_grade_thresholds' => 'array',
        'enabled_special_properties' => 'array',
        'has_properties' => 'boolean',
        'fixed_properties' => 'array',
        'property_evaluations' => 'array',
        'has_notifications' => 'boolean',
        'notification_recipients' => 'array',
        'has_table_marking' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $entry): void {
            if ($entry->wasChanged(['maximum_points', 'properties_mode', 'teaching_entry_grading_part_id'])) {
                $entry->invalidateGradingPartThresholds();
            }
        });
        static::deleted(fn (self $entry) => $entry->invalidateGradingPartThresholds());
    }

    private function invalidateGradingPartThresholds(): void
    {
        $partIds = array_filter([$this->teaching_entry_grading_part_id, $this->getOriginal('teaching_entry_grading_part_id')]);
        TeachingEntryGradingPart::query()->whereKey($partIds)->get()
            ->each(fn (TeachingEntryGradingPart $part) => $part->invalidateOverallPointsThresholds());
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function supportsFreeGrading(): bool
    {
        return $this->category === 'Benotung' && $this->has_properties
            && in_array($this->properties_mode, ['free', 'fixed'], true) && $this->calculation_mode === 'individual';
    }

    protected function freeGradingMode(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?string => $this->supportsFreeGrading() ? ($value ?? 'deficit_points') : null);
    }

    public function gradeForFreePointDeficit(int|float $count): ?int
    {
        return ! is_finite((float) $count) || $count < 0 || $this->free_grading_mode !== 'deficit_points' ? null : $this->gradeFromFreeThresholds($count, true);
    }

    public function gradeFromPointTotal(int|float $points): ?int
    {
        return ! is_finite((float) $points) || $this->free_grading_mode !== 'points' ? null : $this->gradeFromFreeThresholds($points, false);
    }

    private function gradeFromFreeThresholds(int|float $value, bool $missing): ?int
    {
        $thresholds = $missing ? $this->free_deficit_grade_thresholds : $this->free_points_grade_thresholds;
        if (! is_array($thresholds)) {
            return null;
        }

        foreach ([1, 2, 3, 4] as $grade) {
            $threshold = $thresholds[$grade] ?? null;
            if ((! is_int($threshold) && ! is_float($threshold)) || ! is_finite((float) $threshold)
                || ($missing && $threshold < 0)
                || ($grade > 1 && ($missing ? $thresholds[$grade - 1] >= $threshold : $thresholds[$grade - 1] <= $threshold))) {
                return null;
            }
        }

        foreach ([1, 2, 3, 4] as $grade) {
            if ($missing ? $value <= $thresholds[$grade] : $value >= $thresholds[$grade]) {
                return $grade;
            }
        }

        return 5;
    }

    protected function maximumPlusGradingMode(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?string => $this->category === 'Benotung' && $this->properties_mode === 'plus'
            ? ($this->allows_maximum_plus ? ($value ?? 'standard_percentage') : 'other')
            : null);
    }

    public function gradeForPlusCount(int $count): ?int
    {
        if ($count < 0 || $this->maximum_plus_grading_mode !== 'other' || ! is_array($this->maximum_plus_grade_thresholds)) {
            return null;
        }

        $thresholds = $this->maximum_plus_grade_thresholds;
        foreach ([1, 2, 3, 4] as $grade) {
            if (! isset($thresholds[$grade]) || ! is_int($thresholds[$grade]) || $thresholds[$grade] < 0
                || ($grade > 1 && $thresholds[$grade - 1] <= $thresholds[$grade])) {
                return null;
            }
        }

        foreach ([1, 2, 3, 4] as $grade) {
            if ($count >= $thresholds[$grade]) {
                return $grade;
            }
        }

        return 5;
    }

    protected function enabledSpecialProperties(): Attribute
    {
        return Attribute::make(get: fn (?string $value): array => $value === null ? self::SpecialProperties : json_decode($value, true));
    }

    protected function calculationMode(): Attribute
    {
        return Attribute::make(get: function (): string {
            if ($this->category !== 'Benotung' || ! $this->has_properties) {
                return 'individual';
            }

            if (in_array($this->properties_mode, ['plus', 'plus_minus'], true)) {
                return 'plus_minus';
            }

            if ($this->properties_mode === 'points') {
                return 'points';
            }

            $properties = $this->fixed_properties ?? [];
            sort($properties);

            return $this->properties_mode === 'fixed' && $properties === ['1', '2', '3', '4', '5']
                ? 'grades'
                : 'individual';
        });
    }

    public function resolvePropertyEvaluation(string $property): int|float|string|null
    {
        if ($this->category !== 'Benotung' || ! $this->has_properties) {
            return null;
        }

        if (in_array($property, self::SpecialProperties, true)) {
            return in_array($property, $this->enabled_special_properties, true) ? $this->individualPropertyEvaluation($property) : null;
        }

        if ($this->properties_mode === 'points') {
            return $this->acceptsPoints($property) ? (float) $property : null;
        }

        if ($this->calculation_mode === 'plus_minus') {
            $signs = str_replace('−', '-', trim($property));

            $pattern = $this->properties_mode === 'plus' ? '/\A\++\z/' : '/\A(?:\++|-+)\z/';
            if (preg_match($pattern, $signs)) {
                return substr_count($signs, '+') - substr_count($signs, '-');
            }

            return null;
        }

        if ($this->calculation_mode === 'grades') {
            return in_array($property, ['1', '2', '3', '4', '5'], true) ? (int) $property : null;
        }

        return $this->individualPropertyEvaluation($property);
    }

    public function gradeForPoints(int|float $points): ?int
    {
        if ($this->properties_mode !== 'points' || ! $this->acceptsPoints($points) || ! is_array($this->points_grade_thresholds)) {
            return null;
        }
        $thresholds = $this->points_grade_thresholds;
        foreach ([1, 2, 3, 4] as $grade) {
            if (! isset($thresholds[$grade]) || ! $this->acceptsPoints($thresholds[$grade])
                || ($grade > 1 && $thresholds[$grade - 1] <= $thresholds[$grade])) {
                return null;
            }
        }
        foreach ([1, 2, 3, 4] as $grade) {
            if ($points >= $thresholds[$grade]) {
                return $grade;
            }
        }

        return 5;
    }

    public function acceptsPoints(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0
            && $this->maximum_points !== null && is_finite($this->maximum_points)
            && $this->maximum_points > 0 && (float) $value <= $this->maximum_points;
    }

    private function individualPropertyEvaluation(string $property): int|float|string|null
    {
        $evaluation = collect($this->property_evaluations ?? [])
            ->first(fn (array $evaluation): bool => $evaluation['property'] === $property);
        $value = $evaluation['evaluation'] ?? null;

        return match ($value) {
            'positive' => 1,
            'negative' => -1,
            'neutral' => 0,
            'ignored' => 'ignored',
            default => (is_int($value) || is_float($value)) && is_finite((float) $value) ? $value : null,
        };
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

    public function gradingPart(): BelongsTo
    {
        return $this->belongsTo(TeachingEntryGradingPart::class, 'teaching_entry_grading_part_id');
    }
}
