<?php

namespace App\Support;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

class TeachingSignAdjustment
{
    public const FIELDS = ['improvement_factor', 'max_improvement', 'deterioration_factor', 'max_deterioration'];

    public static function isValid(?array $configuration): bool
    {
        if ($configuration === null || count($configuration) !== 4) {
            return false;
        }
        foreach (self::FIELDS as $field) {
            $value = $configuration[$field] ?? null;
            if (! is_scalar($value) || ! preg_match('/^\d+(?:\.\d+)?$/D', (string) $value)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{raw: BigRational, delta: BigRational, grade: int}|null */
    public static function calculate(BigRational $base, BigRational $balance, ?array $configuration): ?array
    {
        if (! self::isValid($configuration) || $base->compareTo(1) < 0 || $base->compareTo(5) > 0) {
            return null;
        }
        $positive = $balance->compareTo(0) >= 0;
        $factor = BigRational::of($configuration[$positive ? 'improvement_factor' : 'deterioration_factor']);
        $cap = BigRational::of($configuration[$positive ? 'max_improvement' : 'max_deterioration']);
        $amount = $balance->abs()->multipliedBy($factor);
        if ($amount->compareTo($cap) > 0) {
            $amount = $cap;
        }
        $delta = $positive ? $amount->negated() : $amount;
        $raw = $base->plus($delta);
        $bounded = $raw->compareTo(1) < 0 ? BigRational::of(1) : ($raw->compareTo(5) > 0 ? BigRational::of(5) : $raw);

        return ['raw' => $raw, 'delta' => $delta, 'grade' => $bounded->toScale(0, RoundingMode::HalfUp)->toInt()];
    }
}
