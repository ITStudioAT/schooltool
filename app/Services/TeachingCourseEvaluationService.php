<?php

namespace App\Services;

use App\Http\Resources\Admin\Teaching\TeachingEntryDefinitionResource;
use App\Http\Resources\Admin\Teaching\TeachingEntryGradingPartResource;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Support\TeachingSignAdjustment;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

class TeachingCourseEvaluationService
{
    public function report(TeachingCourse $course, int $semester): array
    {
        $course->loadMissing(['user', 'teachingCourseStudents.user', 'teachingCourseStudents.import116']);
        $year = Schoolyear::findOrFail($course->schoolyear_id);
        $definitions = TeachingEntryDefinition::query()->where('user_id', $course->user_id)
            ->where('school_id', $course->school_id)->where('schoolyear_id', $course->schoolyear_id)
            ->where('teaching_entry_area_id', $course->teaching_entry_area_id)->where('category', 'Benotung')->get();
        $parts = TeachingEntryGradingPart::query()->where('user_id', $course->user_id)
            ->where('school_id', $course->school_id)->where('schoolyear_id', $course->schoolyear_id)
            ->where('teaching_entry_area_id', $course->teaching_entry_area_id)->get();
        $entries = TeachingCourseStudentEntry::query()->with('teachingCourseWork')->where('teaching_course_id', $course->id)->orderBy('date')->orderBy('id')->get();
        $area = TeachingEntryArea::query()->whereKey($course->teaching_entry_area_id)->where('user_id', $course->user_id)
            ->where('school_id', $course->school_id)->where('schoolyear_id', $course->schoolyear_id)->first();
        $grading = $area?->only(['semester_count', 'semester_1_weight', 'semester_2_weight']) ?? [];
        $semesterCount = (int) ($grading['semester_count'] ?? 1);
        $year->sem_2_start = $year->sem_2_start ?: $course->user?->teaching_count_for_semester_2_date;
        $semesters = $semesterCount === 1 ? [1] : ($semester === 3 ? [1, 2] : [$semester]);
        $students = [];
        foreach ($course->teachingCourseStudents as $student) {
            $aliases = array_filter([$student->user_id, $student->import116?->user_id]);
            $studentEntries = $entries->filter(fn ($entry): bool => in_array($entry->user_id, $aliases, true));
            $reports = array_map(fn (int $value): array => $this->semester($value, $semesterCount, $year, $definitions, $parts, $studentEntries, $area), $semesters);
            $students[] = ['course_student_id' => $student->id, 'user_id' => $student->user_id, 'import116_id' => $student->import116_id,
                'semesters' => $reports, 'year' => $this->year($reports, $grading, $semesterCount)];
        }

        return ['course_id' => $course->id, 'schoolyear_id' => $year->id, 'semester' => $semester, 'semester_count' => $semesterCount,
            'rounding' => ($area?->grading_part_groups ?? []) !== [] || $area?->grading_level_weights !== null || $parts->contains('points_assessment_mode', 'sign_adjust') ? 'half_up' : false, 'issues' => $area ? [] : [$this->issue('missing_area', 'Dem Kurs ist kein gültiger eigener Eintragsbereich zugeordnet.')], 'students' => $students];
    }

    private function semester(int $semester, int $semesterCount, Schoolyear $year, Collection $definitions, Collection $parts, Collection $entries, ?TeachingEntryArea $area): array
    {
        $included = collect();
        $excluded = [];
        $seenWorks = [];
        $issues = [];
        if (! $this->validDate($year->from) || ! $this->validDate($year->until) || $year->from > $year->until
            || ($semesterCount === 2 && (! $this->validDate($year->sem_2_start) || $year->sem_2_start < $year->from || $year->sem_2_start > $year->until))) {
            $issues[] = $this->issue('invalid_period', 'Schuljahreszeitraum oder Semestergrenze ist nicht gültig festgelegt.');
        }
        foreach ($entries as $entry) {
            if (! $definitions->contains('short_name', $entry->type)) {
                continue;
            }
            $date = $this->entryDate($entry);
            $reason = null;
            if (! $date || ! $year->from || ! $year->until || ($semesterCount === 2 && ! $year->sem_2_start)) {
                $reason = $this->issue('unassigned_date', 'Datum oder Semestergrenze fehlt; der Eintrag wird nicht einem Semester zugerechnet.');
            } elseif ($date < $year->from || $date > $year->until || ($semesterCount === 2 && ($semester === 1 ? $date >= $year->sem_2_start : $date < $year->sem_2_start))) {
                $reason = $this->issue('outside_period', 'Der Eintrag liegt außerhalb dieses Auswertungszeitraums.', 'info');
            } elseif ($entry->source === 'course_work' && $entry->teaching_course_work_id && isset($seenWorks[$entry->teaching_course_work_id])) {
                $reason = $this->issue('duplicate_work', 'Die Arbeit besitzt mehrere gespiegelte Einträge; sie wird nicht doppelt berechnet.');
            }
            if ($reason) {
                $excluded[] = ['id' => $entry->id, 'date' => $date, 'raw_value' => $entry->grade, 'type' => $entry->type, 'issues' => [$reason]];
                if ($reason['severity'] === 'error') {
                    $issues[] = $reason;
                }

                continue;
            }
            if ($entry->source === 'course_work' && $entry->teaching_course_work_id) {
                $seenWorks[$entry->teaching_course_work_id] = true;
            }
            $included->push($entry);
        }
        $types = $definitions->mapWithKeys(fn ($definition): array => [$definition->id => $this->type($definition, $included->where('type', $definition->short_name), $parts->firstWhere('id', $definition->teaching_entry_grading_part_id))]);
        $partReports = $parts->map(fn ($part): array => $this->part($part, $definitions->where('teaching_entry_grading_part_id', $part->id)->map(fn ($definition) => $types[$definition->id])->values()->all(), $this->hasErrors($issues), $semesterCount === 2))->all();
        $unassigned = $definitions->filter(fn ($definition): bool => ! $parts->contains('id', $definition->teaching_entry_grading_part_id))->map(fn ($definition) => $types[$definition->id])->values()->all();
        if (collect($unassigned)->contains(fn ($type): bool => count($type['entries']) > 0)) {
            $issues[] = $this->issue('unassigned_type', 'Bewertete Eintragstypen sind keinem Benotungsteil zugeordnet.');
        }
        $usesStructure = ($area?->grading_part_groups ?? []) !== [] || $area?->grading_level_weights !== null || $parts->contains('points_assessment_mode', 'sign_adjust');
        $total = $usesStructure ? $this->structuredTotal($partReports, $parts, $area) : $this->total($partReports);
        if ($this->hasErrors($issues)) {
            $total['result'] = null;
            $total['status'] = 'incomplete';
        }

        return [...$total, 'semester' => $semester, 'parts' => $partReports, 'unassigned_types' => $unassigned, 'excluded_entries' => $excluded, 'issues' => [...$issues, ...$total['issues']]];
    }

    private function structuredTotal(array &$reports, Collection $parts, ?TeachingEntryArea $area): array
    {
        $groups = $area?->grading_part_groups ?? [];
        $groupReports = [];
        $emptyOptionalGroups = [];
        $byId = [];
        foreach ($reports as $index => $report) {
            $byId[$report['id']] = $index;
        }
        $resolve = function (string $context, array $visited = []) use (&$resolve, &$reports, &$groupReports, &$emptyOptionalGroups, $groups, $parts, $byId, $area): ?BigRational {
            if (in_array($context, $visited, true)) {
                return null;
            }
            $visited[] = $context;
            $children = [];
            $omitted = [];
            foreach ($groups as $group) {
                if (($group['parent_group_id'] ?? 'root') === $context) {
                    $children['group:'.$group['id']] = $resolve($group['id'], $visited);
                    if (isset($emptyOptionalGroups[$group['id']])) {
                        $omitted['group:'.$group['id']] = true;
                    }
                }
            }
            $direct = $parts->filter(fn ($part): bool => ($part->grading_group_id ?? 'root') === $context);
            $adjustments = $direct->where('points_assessment_mode', 'sign_adjust');
            foreach ($direct as $part) {
                $report = $reports[$byId[$part->id]];
                $children['part:'.$part->id] = $report['result_exact'] !== null ? BigRational::of($report['result_exact']) : null;
                if (! $part->is_required && in_array($part->points_assessment_mode, ['grade_each', 'grade_mean'], true)
                    && $report['types'] !== [] && collect($report['types'])->every(fn (array $type): bool => $type['config']['calculation_mode'] === 'grades' && $type['entries'] === [])) {
                    $omitted['part:'.$part->id] = true;
                }
            }
            $result = null;
            $method = 'weighted_children';
            if ($adjustments->isNotEmpty()) {
                $method = 'sign_adjust';
                if ($adjustments->count() === 1 && count($children) === 2) {
                    $part = $adjustments->first();
                    $index = $byId[$part->id];
                    unset($children['part:'.$part->id]);
                    $base = reset($children);
                    $balance = $reports[$index]['trace']['balance_exact'] ?? null;
                    if ($balance === null && ! $part->is_required && ($reports[$index]['trace']['balance_status'] ?? null) === 'empty') {
                        $balance = '0';
                    }
                    $calculation = $base !== null && $balance !== null ? TeachingSignAdjustment::calculate($base, BigRational::of($balance), $part->sign_adjustment) : null;
                    if ($calculation !== null) {
                        $raw = $calculation['raw'];
                        $result = $raw->compareTo(1) < 0 ? BigRational::of(1) : ($raw->compareTo(5) > 0 ? BigRational::of(5) : $raw);
                        $reports[$index]['result'] = $result->toFloat();
                        $reports[$index]['result_exact'] = (string) $result;
                        $reports[$index]['rounded_result'] = $calculation['grade'];
                        $reports[$index]['status'] = 'complete';
                        $reports[$index]['issues'] = [];
                        $reports[$index]['trace'] += ['base_exact' => (string) $base, 'delta_exact' => (string) $calculation['delta'], 'adjusted_exact' => (string) $raw, 'configuration' => $part->sign_adjustment, 'rounding' => 'half_up'];
                        $reports[$index]['trace']['rule_label'] = 'Netto-Saldo × Faktor, begrenzt durch die maximale Notenwertänderung; kaufmännische Rundung erst am Endergebnis.';
                    }
                }
            } else {
                $weights = $context === 'root' ? $area?->grading_level_weights : collect($groups)->firstWhere('id', $context)['weights'] ?? null;
                if ($context === 'root' && $weights === null && count($children) === 1) {
                    $result = reset($children);
                } elseif (is_array($weights) && count($weights) === count($children) && $children !== []) {
                    $weighted = BigRational::of(0);
                    $weightSum = BigRational::of(0);
                    $seen = [];
                    $validWeights = true;
                    foreach ($weights as $row) {
                        $groupId = $row['grading_group_id'] ?? $row['group_id'] ?? null;
                        $key = $groupId !== null ? 'group:'.$groupId : 'part:'.($row['teaching_entry_grading_part_id'] ?? $row['part_id'] ?? '');
                        $weight = $row['weight'] ?? null;
                        if (isset($seen[$key]) || ! array_key_exists($key, $children) || ! is_numeric($weight) || ! is_finite((float) $weight) || $weight <= 0) {
                            $validWeights = false;
                            $weightSum = BigRational::of(0);
                            break;
                        }
                        $seen[$key] = true;
                        if (isset($omitted[$key])) {
                            continue;
                        }
                        if ($children[$key] === null) {
                            $validWeights = false;
                            $weightSum = BigRational::of(0);
                            break;
                        }
                        $weight = BigRational::of((string) $weight);
                        $weighted = $weighted->plus($children[$key]->multipliedBy($weight));
                        $weightSum = $weightSum->plus($weight);
                    }
                    if ($weightSum->compareTo(0) > 0) {
                        $result = $weighted->dividedBy($weightSum);
                    } elseif ($validWeights && count($omitted) === count($children)) {
                        $emptyOptionalGroups[$context] = true;
                    }
                }
            }
            if ($context !== 'root') {
                $groupReports[$context] = ['id' => $context, 'result' => $result?->toFloat(), 'result_exact' => $result ? (string) $result : null, 'status' => $result ? 'complete' : (isset($emptyOptionalGroups[$context]) ? 'empty' : 'incomplete'), 'trace' => ['method' => $method]];
            }

            return $result;
        };
        $result = $resolve('root');

        return ['result' => $result?->toScale(0, RoundingMode::HalfUp)->toInt(), 'result_exact' => $result ? (string) $result : null, 'status' => $result ? 'complete' : 'incomplete', 'trace' => ['method' => 'grading_structure', 'rounding' => 'half_up'], 'groups' => array_values($groupReports), 'issues' => $result ? [] : [$this->issue('grading_structure_incomplete', 'Eine Kindnote, Anpassungsregel oder vollständige Gewichtung fehlt; die Gesamtnote ist nicht berechenbar.')]];
    }

    private function type(TeachingEntryDefinition $definition, Collection $entries, ?TeachingEntryGradingPart $part): array
    {
        $rows = [];
        $values = [];
        $issues = [];
        $signBalance = $part?->usesSignBalance() ?? false;
        $gradeMethodPending = in_array($part?->points_assessment_mode, ['grade_each', 'grade_mean'], true)
            || (TeachingEntryGradingPart::isStandardGradeType($definition) && $definition->standard_grade_occurrences !== null);
        $supportsSigns = TeachingEntryGradingPart::entryTypeGroup($definition) === 'signs';
        $overall = $part?->points_assessment_mode === 'sum_percent'
            || ($part?->allowed_entry_types === 'points' && $part->points_assessment_mode === 'overall');
        foreach ($entries as $entry) {
            $value = $signBalance
                ? ($supportsSigns ? $this->signedValue($entry->grade) : null)
                : $definition->resolvePropertyEvaluation((string) $entry->grade);
            $rowIssues = [];
            $status = $value === 'ignored' ? 'ignored' : 'included';
            if ($value === null) {
                $status = 'incomplete';
                $rowIssues[] = $signBalance
                    ? $this->issue('invalid_sign_value', 'Der Plus-/Minuswert fehlt oder ist nicht eindeutig auswertbar: „'.($entry->grade ?? '').'“.')
                    : $this->issue('unmapped_value', 'Für den Wert „'.($entry->grade ?? '').'“ ist keine gültige numerische Bewertung festgelegt.');
            } elseif ($value !== 'ignored') {
                $values[] = $value;
            }
            $rows[] = ['id' => $entry->id, 'date' => $this->entryDate($entry), 'source' => $entry->source,
                'work_id' => $entry->teaching_course_work_id, 'raw_value' => $entry->grade, 'numeric_value' => is_numeric($value) ? $value : null,
                'grade' => ! $signBalance && $definition->properties_mode === 'points' && is_numeric($value) && ! $overall ? $definition->gradeForPoints($value) : null,
                'status' => $status, 'issues' => $rowIssues];
            array_push($issues, ...$rowIssues);
        }
        $result = null;
        $sum = $this->sum($values);
        $trace = ['method' => $definition->calculation_mode, 'rule_label' => 'Bewertung nach Eintragseinstellungen', 'values' => $values, 'sum' => $sum->toFloat(), 'sum_exact' => (string) $sum, 'count' => count($values)];
        if ($signBalance) {
            $trace = ['method' => 'sign_balance', 'rule_label' => 'Jedes Plus zählt +1, jedes Minus −1; 0 zählt neutral, ~ zählt +0,5.',
                'values' => $values, 'sum' => $sum->toFloat(), 'sum_exact' => (string) $sum, 'count' => count($values),
                'balance' => $issues === [] && $values !== [] ? $sum->toFloat() : null];
        } elseif ($gradeMethodPending) {
            $trace = ['method' => 'grade_entries', 'rule_label' => 'Erfasste Standardnoten; die Berechnungsregel des Benotungsteils ist noch offen.', 'values' => $values, 'count' => count($values)];
        } elseif ($values !== []) {
            if ($definition->calculation_mode === 'grades') {
                $result = $sum->dividedBy(count($values));
                $trace['rule_label'] = 'Arithmetischer Mittelwert der Standardnoten';
            } elseif ($definition->properties_mode === 'points') {
                $trace['maximum'] = $definition->maximum_points;
                if ($overall) {
                    $trace['rule_label'] = 'Rohpunkte für die Gesamtbeurteilung des Benotungsteils';
                } else {
                    $grades = array_map(fn ($value) => $definition->gradeForPoints($value), $values);
                    $trace['grades'] = $grades;
                    $result = in_array(null, $grades, true) ? null : $this->sum($grades)->dividedBy(count($grades));
                    $trace['rule_label'] = 'Einzelnoten aus Punkteschwellen, anschließend Mittelwert';
                }
            } elseif ($definition->properties_mode === 'plus') {
                if ($definition->maximum_plus_grading_mode === 'standard_percentage') {
                    $maxima = $entries->filter(fn ($entry): bool => is_numeric($definition->resolvePropertyEvaluation((string) $entry->grade)))
                        ->map(fn ($entry) => $entry->teachingCourseWork?->maximum_plus)->values()->all();
                    if (count($maxima) !== count($values) || collect($maxima)->contains(fn ($maximum): bool => ! is_numeric($maximum) || $maximum <= 0)) {
                        $issues[] = $this->issue('missing_plus_maximum', 'Die mögliche Plusanzahl je Bewertung ist nicht erfasst; Standardprozente sind nicht berechenbar.');
                    } elseif (collect($values)->contains(fn ($value, $index): bool => $value < 0 || $value > $maxima[$index])) {
                        $issues[] = $this->issue('invalid_plus_count', 'Die Plusanzahl liegt außerhalb der möglichen Plusanzahl.');
                    } else {
                        $trace['maxima'] = $maxima;
                        $trace['maximum'] = $this->sum($maxima)->toFloat();
                        $grades = $definition->sum_plus_evaluations
                            ? [$this->percentageGrade($sum->dividedBy($this->sum($maxima)))]
                            : array_map(fn ($value, $maximum) => $this->percentageGrade($this->number($value)->dividedBy($this->number($maximum))), $values, $maxima);
                        $trace['grades'] = $grades;
                        $trace['rule_label'] = $definition->sum_plus_evaluations ? 'Erreichte und mögliche Plusanzahlen summieren, dann Standardprozente' : 'Jede Bewertung anhand ihrer möglichen Plusanzahl benoten, anschließend Mittelwert';
                        $result = $this->sum($grades)->dividedBy(count($grades));
                    }
                } else {
                    $grades = $definition->sum_plus_evaluations ? [$definition->gradeForPlusCount((int) $sum->toFloat())] : array_map(fn ($value) => $definition->gradeForPlusCount((int) $value), $values);
                    $trace['grades'] = $grades;
                    $result = in_array(null, $grades, true) ? null : $this->sum($grades)->dividedBy(count($grades));
                    $trace['rule_label'] = $definition->sum_plus_evaluations ? 'Plusanzahlen summieren und mit den Notenschwellen vergleichen' : 'Jede Plusanzahl benoten, anschließend Mittelwert';
                }
            } elseif ($definition->supportsFreeGrading()) {
                if ($definition->free_grading_mode === 'points') {
                    $grade = $this->thresholdGrade($sum, $definition->free_points_grade_thresholds, false);
                    $trace['rule_label'] = 'Zugeordnete Zahlenwerte summieren und mit Punkteschwellen vergleichen';
                } else {
                    $mapped = collect($definition->property_evaluations ?? [])->filter(fn ($mapping): bool => ! in_array($mapping['property'], TeachingEntryDefinition::SpecialProperties, true) || in_array($mapping['property'], $definition->enabled_special_properties, true))
                        ->map(fn ($mapping) => $definition->resolvePropertyEvaluation($mapping['property']))->filter(fn ($value): bool => is_int($value) || is_float($value));
                    $maximum = $this->number($mapped->max() ?? 0)->multipliedBy(count($values));
                    $deficit = $maximum->minus($sum);
                    $trace['maximum'] = $maximum->toFloat();
                    $trace['deficit'] = $deficit->toFloat();
                    $trace['deficit_exact'] = (string) $deficit;
                    $grade = $mapped->isNotEmpty() ? $this->thresholdGrade($deficit, $definition->free_deficit_grade_thresholds, true) : null;
                    $trace['rule_label'] = 'Höchster zugeordneter Wert × bewertete Einträge minus tatsächliche Punktesumme';
                }
                $result = $grade === null ? null : $this->number($grade);
            } elseif ($definition->properties_mode === 'plus_minus') {
                $trace['method'] = 'sign_balance';
                $trace['rule_label'] = 'Pluszeichen minus Minuszeichen';
                if ($definition->grading_part_assessment_mode !== 'other' || ! in_array($definition->grading_part_other_assessment_mode, ['balance_rounding', 'balance_adjustment'], true)) {
                    $issues[] = $this->issue('sign_grade_undefined', 'Die Umrechnung der Plus-/Minusbilanz in eine gewichtete Note ist nicht festgelegt.');
                }
            }
            if ($result === null && ! $overall && $definition->properties_mode !== 'plus_minus' && $issues === []) {
                $issues[] = $this->issue('missing_thresholds', 'Gültige Notenschwellen fehlen.');
            }
        }
        if ($this->hasErrors($issues)) {
            $result = null;
        }

        return ['id' => $definition->id, 'short_name' => $definition->short_name, 'name' => $definition->name,
            'config' => (new TeachingEntryDefinitionResource($definition))->resolve(), 'result' => $result?->toFloat(), 'result_exact' => $result ? (string) $result : null,
            'status' => $this->hasErrors($issues) ? 'incomplete' : ($values === [] ? 'empty' : 'complete'), 'trace' => $trace, 'issues' => $issues, 'entries' => $rows];
    }

    private function part(TeachingEntryGradingPart $part, array $types, bool $periodIncomplete = false, bool $separateSemesters = true): array
    {
        $issues = [];
        $result = null;
        $trace = ['method' => 'weighted_mean', 'rule_label' => 'Gewichteter Mittelwert der Eintragstypen', 'values' => [], 'weights' => []];
        $active = array_filter($types, fn ($type): bool => $type['status'] !== 'empty');
        $standardTypes = $types !== [] && collect($types)->every(fn (array $type): bool => $type['config']['calculation_mode'] === 'grades');
        $configuredStandardTypes = $standardTypes && collect($types)->contains(fn (array $type): bool => $type['config']['standard_grade_occurrences'] !== null);
        if ($standardTypes && count($types) === 1 && ($types[0]['config']['standard_grade_occurrences']['mode'] ?? null) === 'single') {
            $values = $types[0]['trace']['values'];
            $trace = ['method' => 'single_grade', 'rule_label' => 'Eine erfasste Standardnote wird direkt übernommen.', 'values' => $values, 'count' => count($values)];
            if (count($values) === 1 && $types[0]['status'] === 'complete' && ! $periodIncomplete) {
                $result = $this->number($values[0]);
            } elseif (count($values) > 1) {
                $issues[] = $this->issue('multiple_single_grades', 'Für diesen Eintragstyp ist eine Note vorgesehen, aber mehrere Bewertungen sind erfasst. Es wird keine davon verworfen oder gemittelt.');
            }
        } elseif ($standardTypes && count($types) === 1 && $part->points_assessment_mode === 'grade_mean'
            && ($types[0]['config']['standard_grade_occurrences']['mode'] ?? null) === 'fixed'
            && isset($types[0]['config']['standard_grade_occurrences']['mean'])) {
            [$result, $trace, $meanIssues] = $this->standardGradeMean($types[0], $periodIncomplete, $separateSemesters);
            array_push($issues, ...$meanIssues);
        } elseif (in_array($part->points_assessment_mode, ['grade_each', 'grade_mean'], true) || $configuredStandardTypes) {
            $trace = ['method' => $part->points_assessment_mode === 'grade_mean' ? 'grade_mean' : 'grade_each', 'rule_label' => 'Die genaue Berechnungsregel für '.($part->points_assessment_mode === 'grade_mean' ? 'Notendurchschnitt' : 'Jede Note extra rechnen').' ist noch nicht festgelegt.'];
            $issues[] = $this->issue('part_rule_undefined', $trace['rule_label']);
        } elseif ($part->usesSignBalance()) {
            $values = array_merge([], ...array_map(fn (array $type): array => $type['trace']['values'], $types));
            $balanceStatus = $periodIncomplete || collect($types)->contains('status', 'incomplete') ? 'incomplete' : ($values === [] ? 'empty' : 'complete');
            $balance = $balanceStatus === 'complete' ? $this->sum($values) : null;
            $trace = ['method' => 'plus_minus', 'rule_label' => 'Pluszeichen minus Minuszeichen; 0 zählt neutral. Die Umrechnung des Saldos in eine Note ist noch nicht festgelegt.',
                'values' => $values, 'balance' => $balance?->toFloat(), 'balance_exact' => $balance ? (string) $balance : null,
                'balance_status' => $balanceStatus, 'count' => count($values)];
            $trace['purpose'] = match ($part->points_assessment_mode) {
                'sign_grade' => 'own_grade',
                'sign_adjust' => 'adjust_grade',
                default => null,
            };
            $pendingRule = $trace['purpose'] === 'adjust_grade'
                ? 'Die Regel zum Anpassen einer bestehenden Note durch den Plus-/Minus-Saldo ist noch nicht festgelegt.'
                : 'Die Umrechnung des Plus-/Minus-Saldos in eine Note ist noch nicht festgelegt.';
            $trace['rule_label'] = 'Pluszeichen minus Minuszeichen; 0 zählt neutral, ~ zählt +0,5. '.$pendingRule;
            if ($part->points_assessment_mode === 'sign_grade' && $part->hasValidSignGradeThresholds()) {
                $trace['thresholds'] = $part->sign_grade_thresholds;
                $trace['rule_label'] = 'Pluszeichen minus Minuszeichen; 0 zählt neutral, ~ zählt +0,5. Die höchste erreichte Saldo-Grenze bestimmt die Note; darunter gilt Nicht genügend (5).';
                if ($balance !== null) {
                    $result = $this->number(5);
                    foreach ([1, 2, 3, 4] as $grade) {
                        if ($balance->compareTo($part->sign_grade_thresholds[$grade]) >= 0) {
                            $result = $this->number($grade);
                            break;
                        }
                    }
                }
            } else {
                $issues[] = $this->issue('part_rule_undefined', $pendingRule);
            }
        } elseif ($part->points_assessment_mode === 'sum_percent') {
            $active = array_filter($active, fn (array $type): bool => $type['config']['properties_mode'] === 'points');
            $sum = $this->sum(array_column(array_column($active, 'trace'), 'sum_exact'));
            $maximum = $this->number(0);
            foreach ($active as $type) {
                $typeMaximum = $type['config']['maximum_points'];
                if (! is_numeric($typeMaximum) || $typeMaximum <= 0) {
                    $issues[] = $this->issue('missing_points_maximum', 'Für einen Punktetyp fehlt eine gültige Höchstpunktzahl.');

                    continue;
                }
                $maximum = $maximum->plus($this->number($typeMaximum)->multipliedBy($type['trace']['count']));
            }
            $trace = ['method' => 'sum_percent', 'rule_label' => 'Alle Punkte addieren; feste Notengrenzen ab 50 %',
                'sum' => $sum->toFloat(), 'sum_exact' => (string) $sum, 'maximum' => $maximum->toFloat()];
            if ($active !== [] && $maximum->compareTo(0) > 0) {
                $ratio = $sum->dividedBy($maximum);
                $trace['percentage'] = $ratio->multipliedBy(100)->toFloat();
                $result = $this->number($this->percentageGrade($ratio));
            }
        } elseif ($part->allowed_entry_types === 'points' && $part->points_assessment_mode === 'overall') {
            $sum = $this->sum(array_column(array_column($types, 'trace'), 'sum_exact'));
            $maximum = $this->sum(array_column(array_column($types, 'config'), 'maximum_points'));
            $trace = ['method' => 'overall_points', 'rule_label' => 'Punktesumme anhand der Gesamtpunkteschwellen', 'sum' => $sum->toFloat(), 'sum_exact' => (string) $sum, 'maximum' => $maximum->toFloat()];
            foreach ($types as $type) {
                if ($type['trace']['count'] > 1) {
                    $issues[] = $this->issue('repeated_overall_type', 'Mehrere Bewertungen desselben Punktetyps: Die Gesamtmaximalpunkte gelten bislang einmal pro Eintragstyp.');
                }
                if ($type['status'] === 'empty' && $active !== []) {
                    $issues[] = $this->issue('missing_overall_type', 'Für einen Punktetyp der Gesamtbeurteilung fehlt eine Bewertung.');
                }
            }
            $thresholds = $part->overall_points_grade_thresholds;
            if ($active !== [] && is_array($thresholds) && count($thresholds) === 4) {
                $result = $this->number(5);
                foreach ([1, 2, 3, 4] as $grade) {
                    $threshold = $thresholds[$grade] ?? null;
                    if (! is_numeric($threshold) || ! is_finite((float) $threshold) || $threshold < 0 || $this->number($threshold)->compareTo($maximum) > 0 || ($grade > 1 && $thresholds[$grade - 1] <= $threshold)) {
                        $issues[] = $this->issue('missing_thresholds', 'Gültige Gesamtpunkteschwellen fehlen.');
                        break;
                    }
                    if ($result->compareTo(5) === 0 && $sum->compareTo($this->number($threshold)) >= 0) {
                        $result = $this->number($grade);
                    }
                }
            } elseif ($active !== []) {
                $issues[] = $this->issue('missing_thresholds', 'Gültige Gesamtpunkteschwellen fehlen.');
            }
        } else {
            foreach ($active as $type) {
                $config = $type['config'];
                if ($config['properties_mode'] === 'plus_minus' && $config['grading_part_assessment_mode'] === 'other') {
                    if ($config['grading_part_other_assessment_mode'] === 'balance_rounding') {
                        $issues[] = $this->issue('rounding_disabled', 'Die Rundungsregel wird nicht angewendet: Die Auswertung erfolgt ohne Rundung.', 'info');
                    } elseif ($config['grading_part_other_assessment_mode'] === 'balance_adjustment') {
                        $issues[] = $this->issue('adjustment_target_unresolved', 'Der Anpassungsbetrag ist festgelegt; das Ziel (Benotungsteil oder Gesamtbeurteilung) ist noch nicht bestätigt.');
                        $balance = $this->number($type['trace']['sum_exact']);
                        $amount = $balance->compareTo(0) >= 0 ? $config['grading_part_plus_adjustment'] : $config['grading_part_minus_adjustment'];
                        $trace['adjustments'][] = ['type_id' => $type['id'], 'balance' => $balance->toFloat(), 'amount' => $amount,
                            'delta' => is_numeric($amount) ? $balance->multipliedBy($this->number($amount))->negated()->toFloat() : null, 'applied' => false];
                    }

                    continue;
                }
                $parentControls = $part->allowed_entry_types === 'points' && $part->points_assessment_mode === 'individual';
                if (! $parentControls && $config['grading_part_assessment_mode'] === 'other'
                    && ! ($config['properties_mode'] === 'points' && in_array($config['grading_part_other_assessment_mode'], ['points', 'weighted'], true))) {
                    $issues[] = $this->issue('other_rule_missing', 'Für die andere Beurteilung dieses Eintragstyps ist keine auswertbare Regel festgelegt.');

                    continue;
                }
                $usesPointsWeight = $parentControls ? $part->individual_points_weighting_mode === 'points'
                    : ($config['grading_part_assessment_mode'] === 'other' && $config['grading_part_other_assessment_mode'] === 'points');
                $weight = $usesPointsWeight ? $config['maximum_points'] : $config['grading_part_weight'];
                if ($type['result_exact'] !== null && is_numeric($weight) && $weight > 0) {
                    $trace['values'][] = $type['result_exact'];
                    $trace['weights'][] = $weight;
                } elseif ($type['status'] !== 'incomplete') {
                    $issues[] = $this->issue('missing_weight_or_result', 'Eine gültige Gewichtung oder Typbeurteilung fehlt.');
                }
            }
            if ($trace['weights'] !== []) {
                $result = $this->weightedMean($trace['values'], $trace['weights'], $trace);
            }
        }
        foreach ($active as $type) {
            if ($type['status'] === 'incomplete') {
                $issues[] = $this->issue('incomplete_type', 'Ein Eintragstyp ist nicht vollständig auswertbar: '.$type['name']);
            }
        }
        if ($active === [] && $part->is_required) {
            $issues[] = $this->issue('required_part_empty', 'Für diesen verpflichtenden Benotungsteil fehlt eine Bewertung.');
        }
        if ($this->hasErrors($issues)) {
            $result = null;
        }

        return ['id' => $part->id, 'name' => $part->name, 'config' => (new TeachingEntryGradingPartResource($part))->resolve(), 'types' => $types,
            'result' => $result?->toFloat(), 'result_exact' => $result ? (string) $result : null,
            'status' => $this->hasErrors($issues) ? 'incomplete' : ($active === [] ? 'empty' : ($result === null ? 'incomplete' : 'complete')), 'trace' => $trace, 'issues' => $issues];
    }

    /** @return array{BigRational|null, array<string, mixed>, list<array<string, mixed>>} */
    private function standardGradeMean(array $type, bool $periodIncomplete, bool $separateSemesters): array
    {
        $configuration = $type['config']['standard_grade_occurrences'];
        $mean = $configuration['mean'];
        $count = $configuration['count'] ?? null;
        $rows = collect($type['entries'])->sortBy('date')->values()->all();
        $mode = $mean['mode'] ?? null;
        $weights = $mode === 'weighted' ? ($mean['weights'] ?? []) : [];
        $trace = ['method' => 'standard_grade_mean', 'rule_label' => $mode === 'weighted'
            ? 'Chronologische Arbeiten je Semester: Summe aus Note × Prozentgewicht / 100, ohne Rundung.'
            : 'Arithmetischer Mittelwert der Arbeiten je Semester, ohne Rundung.',
            'weighting_mode' => $mode, 'expected_count' => $count, 'actual_count' => count($rows), 'weights' => $weights, 'work_slots' => []];
        if (! $separateSemesters) {
            return [null, $trace, [$this->issue('standard_grade_semester_structure', 'Anzahl und Gewichte gelten pro Semester. Der Bereich wertet bisher das ganze Schuljahr als einen Zeitraum aus; die Semesterzuordnung muss zuerst geklärt werden.')]];
        }
        if ($periodIncomplete || $type['status'] === 'incomplete' || ! is_int($count) || $count < 2 || count($rows) !== $count
            || collect($rows)->contains(fn (array $row): bool => $row['status'] !== 'included' || ! is_numeric($row['numeric_value']) || $row['numeric_value'] < 1 || $row['numeric_value'] > 5)) {
            return [null, $trace, [$this->issue('standard_grade_count_incomplete', 'Für dieses Semester werden genau '.$count.' vollständige Noten benötigt. Fehlende, unbekannte oder zusätzliche Bewertungen werden nicht ersetzt oder verworfen.')]];
        }
        if (! in_array($mode, ['equal', 'weighted'], true) || ($mode === 'weighted'
            && (! is_array($weights) || count($weights) !== $count || ! array_is_list($weights)
                || collect($weights)->contains(fn (mixed $weight): bool => ! is_numeric($weight) || ! is_finite((float) $weight) || $weight < 0 || $weight > 100)
                || $this->sum($weights)->compareTo(100) !== 0))) {
            return [null, $trace, [$this->issue('standard_grade_weights_invalid', 'Die Prozentgewichte müssen zur Anzahl der Arbeiten passen und zusammen genau 100 % ergeben.')]];
        }
        if ($mode === 'weighted') {
            $dateWeights = [];
            foreach ($rows as $index => $row) {
                $dateWeights[$row['date']][] = (string) $this->number($weights[$index]);
            }
            foreach ($dateWeights as $sameDateWeights) {
                if (count(array_unique($sameDateWeights)) > 1) {
                    return [null, $trace, [$this->issue('standard_grade_order_ambiguous', 'Mehrere Arbeiten liegen am selben Datum und haben unterschiedliche Gewichte. Eine eindeutige Reihenfolge ist nicht gespeichert.')]];
                }
            }
        }
        $result = BigRational::of(0);
        foreach ($rows as $index => $row) {
            $weight = $mode === 'weighted' ? $this->number($weights[$index])->dividedBy(100) : BigRational::of(1)->dividedBy($count);
            $result = $result->plus($this->number($row['numeric_value'])->multipliedBy($weight));
            $trace['work_slots'][] = ['slot' => $index + 1, 'entry_id' => $row['id'], 'date' => $row['date'], 'grade' => $row['numeric_value'], 'weight' => $mode === 'weighted' ? $weights[$index] : null];
        }

        return [$result, $trace, []];
    }

    private function total(array $parts): array
    {
        $active = array_values(array_filter($parts, fn ($part): bool => $part['status'] !== 'empty'));
        $issues = [];
        $values = [];
        $weights = [];
        $fixed = $this->sum(array_map(fn ($part) => $part['config']['fixed_percentage'] ?? 0, $active));
        $relative = $this->sum(array_map(fn ($part) => $part['config']['fixed_percentage'] === null ? $part['config']['weight'] : 0, $active));
        foreach ($active as $part) {
            if ($part['status'] !== 'complete') {
                $issues[] = $this->issue('incomplete_part', 'Ein Benotungsteil ist nicht vollständig auswertbar: '.$part['name']);

                continue;
            }
            $values[] = $part['result_exact'];
            $weights[] = $part['config']['fixed_percentage'] ?? (string) ($relative->compareTo(0) > 0 ? $this->number(100)->minus($fixed)->multipliedBy($this->number($part['config']['weight']))->dividedBy($relative) : $this->number(0));
        }
        if ($fixed->compareTo(100) > 0 || ($active !== [] && $relative->compareTo(0) === 0 && $fixed->compareTo(100) !== 0)) {
            $issues[] = $this->issue('unallocated_weight', 'Die festen Anteile ergeben keine vollständige Gesamtgewichtung.');
        }
        $trace = ['method' => 'part_weighting', 'rule_label' => 'Feste Prozentanteile und proportional gewichteter Rest', 'values' => $values, 'weights' => $weights];
        $result = $issues === [] && $this->sum($weights)->compareTo(0) > 0 ? $this->weightedMean($values, $weights, $trace) : null;

        return ['result' => $result?->toFloat(), 'result_exact' => $result ? (string) $result : null, 'status' => $issues !== [] ? 'incomplete' : ($active === [] ? 'empty' : 'complete'), 'trace' => $trace, 'issues' => $issues];
    }

    private function year(array $reports, array $grading, int $semesterCount): array
    {
        if ($semesterCount === 1) {
            return array_intersect_key($reports[0], array_flip(['result', 'result_exact', 'status', 'trace', 'issues']));
        }
        $weights = [$grading['semester_1_weight'] ?? null, $grading['semester_2_weight'] ?? null];
        if (count($reports) !== 2 || collect($reports)->contains(fn ($report): bool => $report['status'] !== 'complete') || collect($weights)->contains(fn ($weight): bool => ! is_numeric($weight) || $weight < 0) || array_sum($weights) <= 0) {
            return ['result' => null, 'status' => 'incomplete', 'trace' => ['method' => 'semester_weighting', 'rule_label' => 'Jahresbeurteilung aus konfigurierten Semestergewichten'], 'issues' => [$this->issue('year_prerequisites', 'Zwei vollständige Semester und festgelegte Semestergewichte sind erforderlich.')]];
        }
        $trace = ['method' => 'semester_weighting', 'rule_label' => 'Gewichteter Mittelwert der Semester ohne manuelle Notenübernahme', 'values' => array_column($reports, 'result_exact'), 'weights' => $weights];
        $result = $this->weightedMean($trace['values'], $weights, $trace);
        $structured = collect($reports)->contains(fn (array $report): bool => ($report['trace']['rounding'] ?? null) === 'half_up');
        if ($structured) {
            $trace['rounding'] = 'half_up';
        }

        return ['result' => $structured ? $result->toScale(0, RoundingMode::HalfUp)->toInt() : $result->toFloat(), 'result_exact' => (string) $result, 'status' => 'complete', 'trace' => $trace, 'issues' => []];
    }

    private function thresholdGrade(BigRational $value, ?array $thresholds, bool $ascending): ?int
    {
        if ($thresholds === null || ($ascending && $value->compareTo(0) < 0)) {
            return null;
        }
        foreach ([1, 2, 3, 4] as $grade) {
            $threshold = $thresholds[$grade] ?? null;
            if (! is_numeric($threshold) || ! is_finite((float) $threshold) || ($ascending && $threshold < 0)
                || ($grade > 1 && ($ascending ? $thresholds[$grade - 1] >= $threshold : $thresholds[$grade - 1] <= $threshold))) {
                return null;
            }
        }
        foreach ([1, 2, 3, 4] as $grade) {
            $comparison = $value->compareTo($this->number($thresholds[$grade]));
            if ($ascending ? $comparison <= 0 : $comparison >= 0) {
                return $grade;
            }
        }

        return 5;
    }

    private function percentageGrade(BigRational $ratio): int
    {
        foreach ([1 => '0.875', 2 => '0.75', 3 => '0.625', 4 => '0.5'] as $grade => $threshold) {
            if ($ratio->compareTo($this->number($threshold)) >= 0) {
                return $grade;
            }
        }

        return 5;
    }

    private function weightedMean(array $values, array $weights, array &$trace): BigRational
    {
        $contributions = array_map(fn ($value, $weight) => (string) $this->number($value)->multipliedBy($this->number($weight)), $values, $weights);
        $numerator = $this->sum($contributions);
        $denominator = $this->sum($weights);
        $trace = [...$trace, 'contributions' => $contributions, 'numerator' => (string) $numerator, 'denominator' => (string) $denominator];

        return $numerator->dividedBy($denominator);
    }

    private function number(mixed $value): BigRational
    {
        return BigRational::of((string) ($value ?? 0));
    }

    private function signedValue(?string $value): int|float|null
    {
        $normalized = str_replace('−', '-', trim($value ?? ''));
        if ($normalized === '0') {
            return 0;
        }
        if ($normalized === '~') {
            return 0.5;
        }
        if (preg_match('/^\++$/', $normalized) === 1) {
            return strlen($normalized);
        }
        if (preg_match('/^-+$/', $normalized) === 1) {
            return -strlen($normalized);
        }

        return null;
    }

    private function sum(array $values): BigRational
    {
        return array_reduce($values, fn (BigRational $sum, $value) => $sum->plus($this->number($value)), $this->number(0));
    }

    private function validDate(?string $value): bool
    {
        return $value !== null && preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private function entryDate(TeachingCourseStudentEntry $entry): ?string
    {
        $finish = $entry->source === 'course_work' ? $entry->teachingCourseWork?->finish_until_date?->format('Y-m-d') : null;

        return $this->validDate($finish) ? $finish : $entry->date?->format('Y-m-d');
    }

    private function hasErrors(array $issues): bool
    {
        return collect($issues)->contains('severity', 'error');
    }

    private function issue(string $code, string $message, string $severity = 'error'): array
    {
        return compact('code', 'message', 'severity');
    }
}
