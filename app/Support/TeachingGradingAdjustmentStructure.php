<?php

namespace App\Support;

use App\Models\TeachingEntryArea;
use Illuminate\Validation\ValidationException;

class TeachingGradingAdjustmentStructure
{
    /** @param list<array{points_assessment_mode?: ?string, grading_group_id?: ?string}> $parts
     * @return list<string>
     */
    public static function contexts(array $parts): array
    {
        return array_values(array_unique(array_map(fn (array $part): string => $part['grading_group_id'] ?? 'root',
            array_filter($parts, fn (array $part): bool => ($part['points_assessment_mode'] ?? null) === 'sign_adjust'))));
    }

    public static function hasAdjustment(TeachingEntryArea $area, ?string $context = null): bool
    {
        return $area->gradingParts()->where('user_id', $area->user_id)->where('school_id', $area->school_id)
            ->where('schoolyear_id', $area->schoolyear_id)->where('grading_group_id', $context)
            ->where('points_assessment_mode', 'sign_adjust')->exists();
    }

    /** @param list<string> $contexts */
    public static function clearContextWeights(TeachingEntryArea $area, array $contexts): bool
    {
        $changes = [];
        $groups = $area->grading_part_groups ?? [];
        foreach (array_unique($contexts) as $context) {
            if (! self::hasAdjustment($area, $context === 'root' ? null : $context)) {
                continue;
            }
            if ($context === 'root' && $area->grading_level_weights !== null) {
                $changes['grading_level_weights'] = null;
            }
            foreach ($groups as $index => $group) {
                if ($group['id'] === $context && isset($group['weights'])) {
                    unset($groups[$index]['weights']);
                    $changes['grading_part_groups'] = $groups;
                }
            }
        }
        if ($changes !== []) {
            $area->update($changes);
        }

        return $changes !== [];
    }

    /**
     * @param  list<array{id: string, parent_group_id?: ?string}>  $groups
     * @param  list<array{id: int, grading_group_id?: ?string, points_assessment_mode?: ?string}>  $parts
     * @return array<int, array{message: string, context: string, siblings: list<string>, adjustments: list<int>}>
     */
    public static function issues(array $groups, array $parts): array
    {
        $contexts = [];
        $adjustments = [];
        foreach ($groups as $group) {
            $contexts[$group['parent_group_id'] ?? 'root'][] = 'group:'.$group['id'];
        }
        foreach ($parts as $part) {
            $context = $part['grading_group_id'] ?? 'root';
            $contexts[$context][] = 'part:'.$part['id'];
            if (($part['points_assessment_mode'] ?? null) === 'sign_adjust') {
                $adjustments[$context][] = $part['id'];
            }
        }
        $issues = [];
        foreach ($adjustments as $context => $ids) {
            $siblings = $contexts[$context];
            sort($siblings);
            sort($ids);
            if (count($siblings) === 2 && count($ids) === 1) {
                continue;
            }
            $message = count($ids) > 1
                ? 'Zwei Anpassungs-Benotungsteile können einander nicht als Ziel verwenden. Auf dieser Ebene braucht die Anpassung genau einen anderen Baustein ohne den Zweck „Bestehende Note anpassen“.'
                : '„Bestehende Note anpassen“ braucht auf derselben Ebene genau einen weiteren Baustein als Ziel: insgesamt genau zwei direkte Bausteine. Eine Untergruppe zählt als ein Baustein.';
            foreach ($ids as $id) {
                $issues[$id] = ['message' => $message, 'context' => (string) $context, 'siblings' => $siblings, 'adjustments' => $ids];
            }
        }

        return $issues;
    }

    /**
     * @param  list<array{id: string, parent_group_id?: ?string, part_ids: list<int>}>|null  $groups
     * @param  array{id: int, points_assessment_mode: string}|null  $partChange
     */
    public static function assertChange(TeachingEntryArea $area, string $field, ?array $groups = null, ?array $partChange = null): void
    {
        $parts = $area->gradingParts()->where('user_id', $area->user_id)
            ->where('school_id', $area->school_id)->where('schoolyear_id', $area->schoolyear_id)
            ->lockForUpdate()->get(['id', 'grading_group_id', 'points_assessment_mode'])->toArray();
        $before = self::issues($area->grading_part_groups ?? [], $parts);
        if ($groups !== null) {
            foreach ($parts as &$part) {
                $part['grading_group_id'] = null;
                foreach ($groups as $group) {
                    if (in_array($part['id'], array_map('intval', $group['part_ids']), true)) {
                        $part['grading_group_id'] = $group['id'];
                        break;
                    }
                }
            }
            unset($part);
        }
        if ($partChange !== null) {
            $found = false;
            foreach ($parts as &$part) {
                if ($part['id'] === $partChange['id']) {
                    $part['points_assessment_mode'] = $partChange['points_assessment_mode'];
                    $found = true;
                }
            }
            unset($part);
            if (! $found) {
                $parts[] = $partChange;
            }
        }
        foreach (self::issues($groups ?? $area->grading_part_groups ?? [], $parts) as $id => $issue) {
            $previous = $before[$id] ?? null;
            $improves = $previous !== null && $previous['context'] === $issue['context']
                && count($issue['siblings']) <= count($previous['siblings'])
                && count($issue['adjustments']) <= count($previous['adjustments'])
                && (count($issue['adjustments']) < count($previous['adjustments'])
                    || abs(count($issue['siblings']) - 2) < abs(count($previous['siblings']) - 2));
            if ($previous !== $issue && ! $improves) {
                throw ValidationException::withMessages([$field => $issue['message']]);
            }
        }
    }
}
