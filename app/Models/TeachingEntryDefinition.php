<?php

namespace App\Models;

use Database\Factories\TeachingEntryDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingEntryDefinition extends Model
{
    /** @use HasFactory<TeachingEntryDefinitionFactory> */
    use HasFactory;

    public const TableMarkingColors = ['blue', 'green', 'orange', 'purple', 'red'];

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'teaching_entry_area_id',
        'teaching_entry_grading_part_id',
        'short_name',
        'name',
        'description',
        'category',
        'has_properties',
        'properties_mode',
        'fixed_properties',
        'property_evaluations',
        'calculation_mode',
        'has_notifications',
        'notification_recipients',
        'has_table_marking',
        'table_marking_color',
    ];

    protected $attributes = [
        'has_properties' => false,
        'properties_mode' => 'free',
        'calculation_mode' => 'individual',
        'has_notifications' => false,
        'has_table_marking' => false,
    ];

    protected $casts = [
        'has_properties' => 'boolean',
        'fixed_properties' => 'array',
        'property_evaluations' => 'array',
        'has_notifications' => 'boolean',
        'notification_recipients' => 'array',
        'has_table_marking' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function resolvePropertyEvaluation(string $property): int|float|string|null
    {
        if ($this->category !== 'Benotung' || ! $this->has_properties) {
            return null;
        }

        if ($this->calculation_mode === 'plus_minus') {
            $signs = str_replace('−', '-', trim($property));

            if ($signs === '0') {
                return 0;
            }

            if (preg_match('/^[+-]+$/D', $signs)) {
                return substr_count($signs, '+') - substr_count($signs, '-');
            }
        }

        if ($this->calculation_mode === 'grades' && in_array($property, ['1', '2', '3', '4', '5'], true)) {
            return (int) $property;
        }

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
