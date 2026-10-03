<?php

use App\Services\TeachingSynchronisationGraph;

function teachingGraphFixture(): array
{
    return ['tables' => array_fill_keys(array_keys(TeachingSynchronisationGraph::TABLES), []), 'columns' => [],
        'schoolyears' => [['id' => 10, 'school_id' => 1, 'from' => '2025-09-01', 'until' => '2026-08-31']],
        'users' => [['id' => 20, 'email' => 'teacher@example.test', 'import116_id' => null, 'teaching_behaviour_by_schoolyear' => '{"10":["good"]}']],
        'import116' => [], 'school_tools' => [], 'max_ids' => ['import116' => 100, 'users' => 100, 'schoolyears' => 100]];
}

function teachingLinkedStudentFixture(): array
{
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $source['users'][0] = ['id' => 20, 'email' => 'cloud@example.test', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'import116_id' => 50];
    $local['users'][0] = ['id' => 40, 'email' => 'local@example.test', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'import116_id' => null];
    $student = ['id' => 50, 'school_id' => 1, 'schoolyear_id' => 10, 'student_code' => 'S1', 'user_id' => 20,
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'birth_date' => '2002-08-03', 'email' => 'cloud@example.test'];
    $source['import116'] = [$student];
    $local['import116'] = [[...$student, 'id' => 60, 'user_id' => 40, 'email' => 'local@example.test']];
    $local['user_roles'] = [40 => ['student']];

    return [$source, $local];
}

test('RFC-valid Unicode email addresses map existing accounts and teacher roster entries', function (string $email) {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $source['users'][0]['email'] = $email;
    $local['users'][0]['email'] = $email;
    $local['users'][0]['id'] = 40;
    $teacher = ['id' => 21, 'school_id' => 1, 'email' => $email, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'short' => 'AL'];
    $source['teachers'] = [$teacher];
    $local['teachers'] = [[...$teacher, 'id' => 137]];

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect($plan['conflicts'])->toBe([])->and($plan['maps']['users'][20])->toBe(40)
        ->and($plan['new_users'])->toBe([])->and($plan['new_teachers'])->toBe([]);
})->with(['müller@example.test', 'teacher@büro.test']);

test('invalid email syntax still blocks account and roster creation', function () {
    $source = teachingGraphFixture();
    $source['users'][0]['email'] = 'invalid@@example.test';
    $source['teachers'] = [['id' => 21, 'email' => 'invalid@@example.test']];

    expect(implode(' ', (new TeachingSynchronisationGraph)->plan($source, teachingGraphFixture(), 1)['conflicts']))
        ->toContain('E-Mail-Zuordnung', 'Lehrerlisten-Identität');
});

test('changed student email reuses the proven existing account across years without changing login identity', function () {
    [$source, $local] = teachingLinkedStudentFixture();
    $source['schoolyears'][] = ['id' => 11, 'school_id' => 1, 'from' => '2026-09-01', 'until' => '2027-08-31'];
    $source['import116'][] = [...$source['import116'][0], 'id' => 51, 'schoolyear_id' => 11];
    $source['users'][0]['import116_id'] = 51;
    $source['tables']['teaching_courses'] = [['id' => 1, 'school_id' => 1, 'schoolyear_id' => 10]];
    $source['tables']['teaching_course_works'] = [['id' => 3, 'teaching_course_id' => 1,
        'groups' => '[{"student_ids":[20],"grades":[{"student_id":20,"grade":"1"}]}]']];

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect($plan['conflicts'])->toBe([])->and($plan['maps']['users'][20])->toBe(40)
        ->and($plan['new_users'])->toBe([])->and($plan['updated_imports'][0]['user_id'])->toBe(40)
        ->and($plan['new_imports'][0]['user_id'])->toBe(40)
        ->and(array_keys($plan['users'][40]))->not->toContain('email')
        ->and(json_decode($plan['tables']['teaching_course_works'][0]['groups'], true)[0])
        ->toBe(['student_ids' => [40], 'grades' => [['student_id' => 40, 'grade' => '1']]])
        ->and($local['users'][0]['email'])->toBe('local@example.test');
});

test('student bridge refuses conflicting names dates privileged accounts and reverse links', function (string $conflict) {
    [$source, $local] = teachingLinkedStudentFixture();
    if ($conflict === 'name') {
        $local['users'][0]['last_name'] = 'Other';
    } elseif ($conflict === 'birthdate') {
        $local['import116'][0]['birth_date'] = '2001-08-03';
    } elseif ($conflict === 'privilege') {
        $local['user_roles'][40][] = 'super_admin';
    } else {
        $local['users'][0]['import116_id'] = 61;
        $local['import116'][] = [...$local['import116'][0], 'id' => 61, 'student_code' => 'Other'];
    }

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect($plan['maps']['users'][20])->not->toBe(40)->and($plan['conflicts'])->not->toBe([]);
})->with(['name', 'birthdate', 'privilege', 'reverse']);

test('student bridge never merges different accounts used in different years', function () {
    [$source, $local] = teachingLinkedStudentFixture();
    $year = ['id' => 11, 'school_id' => 1, 'from' => '2026-09-01', 'until' => '2027-08-31'];
    $source['schoolyears'][] = $year;
    $local['schoolyears'][] = $year;
    $source['import116'][] = [...$source['import116'][0], 'id' => 51, 'schoolyear_id' => 11];
    $local['users'][] = [...$local['users'][0], 'id' => 41, 'email' => 'other-local@example.test'];
    $local['import116'][] = [...$local['import116'][0], 'id' => 61, 'schoolyear_id' => 11, 'user_id' => 41];

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect($plan['maps']['users'][20])->not->toBeIn([40, 41])->and($plan['conflicts'])->not->toBe([]);
});

test('deleted historical student aliases map only in proven import snapshots', function () {
    [$source, $local] = teachingLinkedStudentFixture();
    $source['missing_user_ids'] = [999];
    $snapshot = [...$source['import116'][0], 'user_id' => 999];
    $source['tables']['import116_run_changes'] = [['id' => 1, 'after_snapshot' => json_encode($snapshot)]];

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect($plan['conflicts'])->toBe([])->and($plan['maps']['historical_users'][999])->toBe(40)
        ->and($plan['maps']['users'])->not->toHaveKey(999)
        ->and(json_decode($plan['tables']['import116_run_changes'][0]['after_snapshot'], true)['user_id'])->toBe(40)
        ->and($plan['new_users'])->toBe([]);

    $source['tables']['teaching_courses'] = [['id' => 1, 'school_id' => 1, 'schoolyear_id' => 10]];
    $source['tables']['teaching_course_works'] = [['id' => 3, 'teaching_course_id' => 1, 'groups' => '[{"student_ids":[999]}]']];
    expect(implode(' ', (new TeachingSynchronisationGraph)->plan($source, $local, 1)['conflicts']))->toContain('Eingebettete Schüleridentität fehlt');
});

test('historical aliases require global deletion proof and matching identity school year and email', function (string $conflict) {
    [$source, $local] = teachingLinkedStudentFixture();
    $source['missing_user_ids'] = $conflict === 'not-deleted' ? [] : [999];
    $snapshot = [...$source['import116'][0], 'user_id' => 999];
    if ($conflict !== 'not-deleted') {
        $field = ['school' => 'school_id', 'year' => 'schoolyear_id', 'name' => 'last_name', 'date' => 'birth_date', 'email' => 'email'][$conflict];
        $snapshot[$field] = in_array($field, ['school_id', 'schoolyear_id'], true) ? 999 : 'Different';
    }
    $source['tables']['import116_run_changes'] = [['id' => 1, 'after_snapshot' => json_encode($snapshot)]];

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect($plan['maps']['historical_users'])->not->toHaveKey(999)->and($plan['conflicts'])->not->toBe([]);
})->with(['not-deleted', 'school', 'year', 'name', 'date', 'email']);

test('teaching planner remaps all years and embedded attendance and work identities', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $local['schoolyears'][0]['id'] = 30;
    $local['users'][0]['id'] = 40;
    $source['tables']['teaching_courses'] = [['id' => 1, 'school_id' => 1, 'schoolyear_id' => 10, 'user_id' => 20]];
    $source['tables']['teaching_course_dates'] = [['id' => 2, 'teaching_course_id' => 1,
        'attendance' => '{"s_20":true}', 'status' => '["att:20:1"]']];
    $source['tables']['teaching_course_works'] = [['id' => 3, 'teaching_course_id' => 1,
        'groups' => '[{"student_ids":[20],"grades":[{"student_id":20,"grade":"1"}]}]']];
    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);
    expect($plan['conflicts'])->toBe([])
        ->and($plan['tables']['teaching_courses'][0]['schoolyear_id'])->toBe(30)
        ->and($plan['tables']['teaching_courses'][0]['user_id'])->toBe(40)
        ->and($plan['tables']['teaching_course_dates'][0]['attendance'])->toBe('{"s_40":true}')
        ->and($plan['tables']['teaching_course_dates'][0]['status'])->toBe('["att:40:1"]')
        ->and(json_decode($plan['tables']['teaching_course_works'][0]['groups'], true)[0]['student_ids'])->toBe([40])
        ->and(json_decode($plan['users'][40]['teaching_behaviour_by_schoolyear'], true))->toBe([30 => ['good']]);
});

test('teaching planner creates missing identities and reports shared contact changes without deleting students', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $source['import116'] = [['id' => 50, 'school_id' => 1, 'schoolyear_id' => 10,
        'student_code' => 'S1', 'user_id' => null, 'mother_email' => 'new@example.test']];
    $local['import116'] = [['id' => 60, 'school_id' => 1, 'schoolyear_id' => 10,
        'student_code' => 'S1', 'user_id' => null, 'mother_email' => 'old@example.test']];
    $source['schoolyears'][] = ['id' => 11, 'school_id' => 1, 'from' => '2026-09-01', 'until' => '2027-08-31'];
    $source['users'][] = ['id' => 21, 'email' => 'new@example.test', 'import116_id' => null];
    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);
    expect($plan['conflicts'])->toBe([])->and($plan['new_years'])->toHaveCount(1)
        ->and($plan['new_users'])->toHaveCount(1)
        ->and($plan['updated_imports'][0]['id'])->toBe(60)
        ->and($plan['changed_contacts'][0]['fields'])->toContain('mother_email');
});

test('foreign school ID collisions remap direct children and embedded teaching references together', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $local['users'][0]['id'] = 40;
    $local['occupied_ids'] = ['teaching_courses' => [1], 'teaching_course_works' => [3]];
    $local['max_ids']['teaching_courses'] = 100;
    $local['max_ids']['teaching_course_works'] = 200;
    $source['tables']['teaching_courses'] = [['id' => 1, 'school_id' => 1, 'schoolyear_id' => 10, 'user_id' => 20]];
    $source['tables']['teaching_course_works'] = [['id' => 3, 'teaching_course_id' => 1,
        'groups' => '[{"teaching_course_id":1,"student_ids":[20]}]']];
    $source['tables']['teaching_course_student_entries'] = [['id' => 4, 'teaching_course_id' => 1,
        'teaching_course_work_id' => 3, 'user_id' => 20]];
    $source['tables']['import116_run_changes'] = [['id' => 5,
        'before_snapshot' => '{"teaching_course_id":1,"teaching_course_work_id":3,"user_id":20}']];

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect($plan['conflicts'])->toBe([])
        ->and($plan['tables']['teaching_courses'][0]['id'])->toBe(101)
        ->and($plan['tables']['teaching_course_works'][0]['id'])->toBe(201)
        ->and($plan['tables']['teaching_course_works'][0]['teaching_course_id'])->toBe(101)
        ->and(json_decode($plan['tables']['teaching_course_works'][0]['groups'], true)[0])
        ->toBe(['teaching_course_id' => 101, 'student_ids' => [40]])
        ->and($plan['tables']['teaching_course_student_entries'][0]['teaching_course_work_id'])->toBe(201)
        ->and($plan['tables']['teaching_course_student_entries'][0]['teaching_course_id'])->toBe(101)
        ->and(json_decode($plan['tables']['import116_run_changes'][0]['before_snapshot'], true))
        ->toBe(['teaching_course_id' => 101, 'teaching_course_work_id' => 201, 'user_id' => 40])
        ->and($source['tables']['teaching_courses'][0]['id'])->toBe(1)
        ->and($local['occupied_ids']['teaching_courses'])->toBe([1]);
});

test('shared student timetable planning fields are preserved while contact fields update', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $student = ['id' => 50, 'school_id' => 1, 'schoolyear_id' => 10, 'student_code' => 'S1', 'user_id' => null,
        'mother_email' => 'cloud@example.test', 'study_selection' => '{"cloud":true}', 'course_results' => '{"grade":1}'];
    $source['import116'] = [$student];
    $student['mother_email'] = 'local@example.test';
    $student['study_selection'] = '{"local":true}';
    $student['course_results'] = '{"grade":2}';
    $local['import116'] = [$student];
    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);
    expect($plan['conflicts'])->toBe([])
        ->and($plan['updated_imports'][0]['mother_email'])->toBe('cloud@example.test')
        ->and($plan['updated_imports'][0]['study_selection'])->toBe('{"local":true}')
        ->and($plan['updated_imports'][0]['course_results'])->toBe('{"grade":2}');
});

test('reciprocal synthetic student accounts map without replacing their identity or privileges', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $source['users'][0] = ['id' => 20, 'email' => 'student@example.test', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'import116_id' => 50];
    $local['users'][0] = ['id' => 40, 'email' => 'import116.60@schooltool.noemail', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'import116_id' => 60];
    $source['import116'] = [['id' => 50, 'school_id' => 1, 'schoolyear_id' => 10, 'student_code' => 'S1', 'user_id' => 20, 'first_name' => 'Ada', 'last_name' => 'Lovelace']];
    $local['import116'] = [['id' => 60, 'school_id' => 1, 'schoolyear_id' => 10, 'student_code' => 'S1', 'user_id' => 40, 'first_name' => 'Ada', 'last_name' => 'Lovelace']];
    $local['user_roles'] = [40 => ['student']];
    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);
    expect($plan['conflicts'])->toBe([])->and($plan['new_users'])->toBe([])
        ->and($plan['maps']['users'][20])->toBe(40)->and($plan['maps']['import116'][50])->toBe(60);
    $local['user_roles'][40][] = 'super_admin';
    expect((new TeachingSynchronisationGraph)->plan($source, $local, 1)['conflicts'])->not->toBe([]);
});

test('missing LIVE links preserve local shared accounts and their association with another schoolyear', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $local['users'][0]['id'] = 40;
    $local['users'][0]['import116_id'] = 61;
    $local['schoolyears'][] = ['id' => 11, 'school_id' => 1, 'from' => '2026-09-01', 'until' => '2027-08-31'];
    $source['import116'] = [['id' => 50, 'school_id' => 1, 'schoolyear_id' => 10,
        'student_code' => 'S1', 'user_id' => null, 'mother_email' => 'cloud@example.test']];
    $local['import116'] = [
        ['id' => 60, 'school_id' => 1, 'schoolyear_id' => 10, 'student_code' => 'S1', 'user_id' => 40, 'mother_email' => 'local@example.test'],
        ['id' => 61, 'school_id' => 1, 'schoolyear_id' => 11, 'student_code' => 'S1', 'user_id' => 40],
    ];

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect($plan['conflicts'])->toBe([])
        ->and($plan['updated_imports'][0]['user_id'])->toBe(40)
        ->and($plan['updated_imports'][0]['mother_email'])->toBe('cloud@example.test')
        ->and($plan['changed_contacts'][0]['fields'])->not->toContain('user_id')
        ->and($plan['users'][40]['import116_id'])->toBe(61)
        ->and($source['import116'][0]['user_id'])->toBeNull()
        ->and($source['users'][0]['import116_id'])->toBeNull();
});

test('explicit different LIVE links still block changes to existing shared relationships', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $source['users'][0]['import116_id'] = 50;
    $local['users'][0]['id'] = 40;
    $local['users'][0]['import116_id'] = 61;
    $source['import116'] = [['id' => 50, 'school_id' => 1, 'schoolyear_id' => 10, 'student_code' => 'S1', 'user_id' => 20]];
    $local['import116'] = [['id' => 60, 'school_id' => 1, 'schoolyear_id' => 10, 'student_code' => 'S1', 'user_id' => 41]];

    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);

    expect(implode(' ', $plan['conflicts']))->toContain('Schüler-Kontozuordnung #60', 'Schülerverknüpfung des Kontos #40');
});

test('unresolvable explicit LIVE account links cannot be silently discarded', function () {
    $source = teachingGraphFixture();
    $source['users'][0]['import116_id'] = 999;

    expect(implode(' ', (new TeachingSynchronisationGraph)->plan($source, teachingGraphFixture(), 1)['conflicts']))
        ->toContain('Schülerverknüpfung des Cloud-Kontos #20 fehlt');
});

test('JSON aliases require a proven MariaDB JSON validity constraint', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $source['columns']['teaching_courses'] = [['name' => 'teaching_student_grade_columns', 'type' => 'longtext']];
    $local['columns']['teaching_courses'] = [['name' => 'teaching_student_grade_columns', 'type' => 'json']];
    $local['json_columns']['teaching_courses'] = ['teaching_student_grade_columns'];
    expect((new TeachingSynchronisationGraph)->plan($source, $local, 1)['conflicts'])->not->toBe([]);
    $source['json_columns']['teaching_courses'] = ['teaching_student_grade_columns'];
    expect((new TeachingSynchronisationGraph)->plan($source, $local, 1)['conflicts'])->toBe([]);
});

test('teaching planner collects incomplete graphs and mismatched identities instead of dropping rows', function () {
    $source = teachingGraphFixture();
    $local = teachingGraphFixture();
    $local['users'][] = $local['users'][0];
    $source['tables']['teaching_course_student_entry_notifications'] = [['id' => 1, 'teaching_course_student_entry_id' => 999]];
    $plan = (new TeachingSynchronisationGraph)->plan($source, $local, 1);
    expect(implode(' ', $plan['conflicts']))->toContain('E-Mail-Zuordnung', 'Beziehung');
});
