<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('student_timetable_subject_rule_sets')
            ->select(['id', 'school_id', 'schoolyear_id', 'study_program', 'version', 'rules'])
            ->orderBy('id')
            ->eachById(function (object $ruleSet): void {
                $excludedSubjectKeys = DB::table('student_timetable_subject_rows')
                    ->where('school_id', $ruleSet->school_id)
                    ->where('schoolyear_id', $ruleSet->schoolyear_id)
                    ->where('study_program', $ruleSet->study_program)
                    ->where('branch', 'gymnasial')
                    ->whereIn('json_code', ['BE1', 'ME1'])
                    ->pluck('stable_key')
                    ->all();

                if ($excludedSubjectKeys === []) {
                    return;
                }

                $rules = json_decode((string) $ruleSet->rules, true, flags: JSON_THROW_ON_ERROR);
                $rulesChanged = false;

                foreach ($rules as &$rule) {
                    if (($rule['selection_key'] ?? null) !== 'arts_subject') {
                        continue;
                    }

                    $options = $rule['options'] ?? [];

                    foreach ($options as &$option) {
                        $subjectKeys = array_values(array_filter(
                            $option['subject_keys'] ?? [],
                            fn (string $subjectKey): bool => ! in_array($subjectKey, $excludedSubjectKeys, true),
                        ));

                        if ($subjectKeys === ($option['subject_keys'] ?? [])) {
                            continue;
                        }

                        $option['subject_keys'] = $subjectKeys;
                        $rulesChanged = true;
                    }
                    unset($option);
                    $rule['options'] = $options;
                }
                unset($rule);

                if (! $rulesChanged) {
                    return;
                }

                DB::table('student_timetable_subject_rule_sets')
                    ->where('id', $ruleSet->id)
                    ->update([
                        'version' => (int) $ruleSet->version + 1,
                        'rules' => json_encode($rules, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            });
    }
};
