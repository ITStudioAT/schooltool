<?php

use App\Models\SchoolTool;

it('uses timetable v3 as the default while preserving an explicit v2 selection', function () {
    expect(SchoolTool::normalizeStudentsTimetablesAdminVersion(null))->toBe('v3')
        ->and(SchoolTool::normalizeStudentsTimetablesAdminVersion('invalid'))->toBe('v3')
        ->and(SchoolTool::normalizeStudentsTimetablesAdminVersion('v2'))->toBe('v2')
        ->and(SchoolTool::normalizeStudentsTimetablesAdminVersion('v3'))->toBe('v3');
});
