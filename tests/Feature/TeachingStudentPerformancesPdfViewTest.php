<?php

it('renders only the simplified performance table with semester as the first column', function () {
    $source = file_get_contents(resource_path('views/pdfs/teachingStudentPerformances.blade.php'));

    expect($source)->toContain('<th style="width: 4%;">S</th>')
        ->and($source)->toContain("{{ \$report['course_title'] }} · {{ \$report['student_name'] }} · {{ \$report['school_name'] ?: '-' }} · {{ \$report['schoolyear_name'] ?: '-' }}")
        ->and($source)->toContain('border-bottom: 2px solid #0f172a;')
        ->and($source)->toContain('padding-top: 22px;')
        ->and($source)->toContain("font-size: 13px;\n            font-weight: 700;\n            color: #0f172a;")
        ->and($source)->toContain('<th style="width: 9%;">Datum</th>')
        ->and($source)->toContain("td {\n            font-size: 11px;\n        }")
        ->and($source)->toContain('<th style="width: 22%;">Typ</th>')
        ->and($source)->toContain('<th style="width: 24%;">Arbeit</th>')
        ->and($source)->toContain('<th style="width: 8%;">Note</th>')
        ->and($source)->toContain('<th>Beschreibung</th>')
        ->and($source)->not->toContain('Zusammenfassung')
        ->and($source)->not->toContain('Kategoriebewertungen')
        ->and($source)->not->toContain('meta-grid')
        ->and($source)->not->toContain('page-break-after: always;')
        ->and($source)->not->toContain('Leistungsübersicht');
});
