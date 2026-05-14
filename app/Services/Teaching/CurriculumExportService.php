<?php

namespace App\Services\Teaching;

use App\Models\TeachingCurriculum;
use App\Models\User;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class CurriculumExportService
{
    /**
     * @return array{
     *     export_type: string,
     *     schema_version: int,
     *     curriculum_key: string,
     *     exported_at: string,
     *     curriculum: array{
     *         title: string,
     *         description: ?string,
     *         semester_count: int,
     *         free_weeks: array<int, string>,
     *         topics: array<int, array{
     *             id: string,
     *             title: string,
     *             assignment_type: string,
     *             month_key: ?string,
     *             month_keys: array<int, string>,
     *             week_keys: array<int, string>,
     *             units: array<int, array{
     *                 id: string,
     *                 title: string,
     *                 is_exam: bool,
     *                 assignment_type: string,
     *                 month_key: ?string,
     *                 month_keys: array<int, string>,
     *                 week_keys: array<int, string>,
     *                 checked_week_keys: array<int, string>
     *             }>
     *         }>
     *     }
     * }
     */
    public function transferPayload(TeachingCurriculum $curriculum): array
    {
        return [
            'export_type' => 'teaching_curriculum',
            'schema_version' => 1,
            'curriculum_key' => $curriculum->ensureExportKey(),
            'exported_at' => now()->toIso8601String(),
            'curriculum' => [
                'title' => (string) $curriculum->title,
                'description' => $curriculum->description !== null ? (string) $curriculum->description : null,
                'semester_count' => (int) ($curriculum->semester_count ?? 2),
                'free_weeks' => array_values(array_filter(
                    is_array($curriculum->free_weeks) ? $curriculum->free_weeks : [],
                    fn (mixed $weekKey): bool => is_string($weekKey) && $weekKey !== ''
                )),
                'topics' => collect(is_array($curriculum->topics) ? $curriculum->topics : [])
                    ->filter(fn (mixed $topic): bool => is_array($topic))
                    ->map(fn (array $topic): array => $this->transferTopic($topic))
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function toWord(TeachingCurriculum $curriculum, $user = null): string
    {
        $teacherName = $this->teacherName($curriculum, $user);
        $schoolName = $this->schoolName($curriculum, $user);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);
        $phpWord->getSettings()->setThemeFontLang(new Language(config('app.locale', 'de') === 'de' ? 'de-AT' : config('app.locale')));

        $section = $phpWord->addSection();

        $section->addText(
            'CURRICULUM',
            ['bold' => true, 'size' => 10, 'color' => '6366F1', 'allCaps' => true],
            ['spaceAfter' => 40]
        );

        $section->addText(
            $curriculum->title,
            ['bold' => true, 'size' => 18],
            ['spaceAfter' => 80]
        );

        if ($curriculum->description) {
            $section->addText(
                $curriculum->description,
                ['size' => 10, 'italic' => true, 'color' => '475569'],
                ['spaceAfter' => 80]
            );
        }

        $topics = is_array($curriculum->topics) ? $curriculum->topics : [];
        $semesterCount = $curriculum->semester_count ?? 2;
        $freeWeeks = is_array($curriculum->free_weeks) ? count($curriculum->free_weeks) : 0;

        $metaParts = [];
        $metaParts[] = $semesterCount.' Semester';
        $metaParts[] = count($topics).' Themen';
        if ($freeWeeks > 0) {
            $metaParts[] = $freeWeeks.' freie Wochen';
        }

        $section->addText(
            implode('  ·  ', $metaParts),
            ['size' => 10, 'color' => '64748B'],
            ['spaceAfter' => 40]
        );

        $infoParts = [];
        if ($teacherName !== null) {
            $infoParts[] = 'Lehrperson: '.$teacherName;
        }
        if ($schoolName !== null) {
            $infoParts[] = 'Schule: '.$schoolName;
        }
        $infoParts[] = 'Erstellt am: '.now()->format('d.m.Y, H:i').' Uhr';

        $section->addText(
            implode('  ·  ', $infoParts),
            ['size' => 10, 'color' => '94A3B8'],
            ['spaceAfter' => 60]
        );

        $section->addLine(['weight' => 1, 'width' => 500, 'height' => 0, 'color' => 'E2E8F0']);
        $section->addTextBreak(1);

        foreach ($topics as $index => $topic) {
            $title = ($index + 1).'. '.($topic['title'] ?? '');
            $assignment = $this->assignmentLabel($topic);

            $section->addText(
                $title.($assignment ? '   '.$assignment : ''),
                ['bold' => true, 'size' => 14],
                ['spaceBefore' => 200, 'spaceAfter' => 50]
            );

            $dateRange = $this->topicDateRange($topic);
            if ($dateRange) {
                $section->addText(
                    $dateRange,
                    ['size' => 10, 'color' => '6366F1'],
                    ['spaceAfter' => 100, 'indentation' => ['left' => 300]]
                );
            }

            $units = is_array($topic['units'] ?? null) ? $topic['units'] : [];
            foreach ($units as $unit) {
                $unitTitle = $unit['title'] ?? '';
                $isExam = ! empty($unit['is_exam']);
                $unitAssignment = $this->assignmentLabel($unit);

                $section->addText(
                    $unitTitle.($isExam ? ' (Prüfung)' : ''),
                    [
                        'size' => 12,
                        'bold' => $isExam,
                        'color' => $isExam ? 'DC2626' : '334155',
                    ],
                    ['indentation' => ['left' => 400], 'spaceAfter' => 30]
                );

                if ($unitAssignment) {
                    $section->addText(
                        $unitAssignment,
                        ['size' => 10, 'color' => '6366F1'],
                        ['indentation' => ['left' => 400], 'spaceAfter' => 50]
                    );
                }
            }
        }

        $path = storage_path('app/private/curriculum_export_'.$curriculum->id.'.docx');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);

        return $path;
    }

    public function toPdf(TeachingCurriculum $curriculum, $user = null): string
    {
        $topics = is_array($curriculum->topics) ? $curriculum->topics : [];
        $freeWeeks = is_array($curriculum->free_weeks) ? count($curriculum->free_weeks) : 0;
        $path = storage_path('app/private/curriculum_export_'.$curriculum->id.'.pdf');
        $teacherName = $this->teacherName($curriculum, $user);
        $schoolName = $this->schoolName($curriculum, $user);
        $embeddedFontCss = $this->embeddedPdfFontCss();
        $pdfFontFamily = $embeddedFontCss !== ''
            ? "'CurriculumPdfArial', Arial, Helvetica, sans-serif"
            : 'Arial, Helvetica, sans-serif';

        Pdf::view('pdfs.curriculum-export', [
            'curriculum' => $curriculum,
            'topics' => $topics,
            'freeWeeks' => $freeWeeks,
            'assignmentLabels' => $this->buildAssignmentLabels($topics),
            'userName' => $teacherName,
            'schoolName' => $schoolName,
            'printDate' => now()->format('d.m.Y, H:i'),
            'embeddedFontCss' => $embeddedFontCss,
            'pdfFontFamily' => $pdfFontFamily,
        ])
            ->format(Format::A4)
            ->save($path);

        return $path;
    }

    private function teacherName(TeachingCurriculum $curriculum, mixed $fallbackUser = null): ?string
    {
        $curriculum->loadMissing('user');

        $teacherName = trim((string) $curriculum->user?->full_name);
        if ($teacherName !== '') {
            return $teacherName;
        }

        if (! $fallbackUser instanceof User) {
            return null;
        }

        $fallbackTeacherName = trim((string) $fallbackUser->full_name);

        return $fallbackTeacherName !== '' ? $fallbackTeacherName : null;
    }

    private function schoolName(TeachingCurriculum $curriculum, mixed $fallbackUser = null): ?string
    {
        $curriculum->loadMissing('school');

        $schoolName = trim((string) ($curriculum->school?->long_name ?: $curriculum->school?->short_name));
        if ($schoolName !== '') {
            return $schoolName;
        }

        if (! $fallbackUser instanceof User) {
            return null;
        }

        $fallbackSchoolName = trim((string) ($fallbackUser->selectedSchool?->long_name ?: $fallbackUser->selectedSchool?->short_name));

        return $fallbackSchoolName !== '' ? $fallbackSchoolName : null;
    }

    private function buildAssignmentLabels(array $topics): array
    {
        $labels = [];
        foreach ($topics as $index => $topic) {
            $labels[$index] = [
                'assignment' => $this->assignmentLabel($topic),
                'dateRange' => $this->topicDateRange($topic),
                'units' => [],
            ];
            $units = is_array($topic['units'] ?? null) ? $topic['units'] : [];
            foreach ($units as $unitIndex => $unit) {
                $labels[$index]['units'][$unitIndex] = $this->assignmentLabel($unit);
            }
        }

        return $labels;
    }

    private function embeddedPdfFontCss(): string
    {
        $fontFaces = array_filter([
            $this->embeddedPdfFontFace(
                'CurriculumPdfArial',
                $this->arialFontCandidatePaths('regular'),
                400,
                'normal'
            ),
            $this->embeddedPdfFontFace(
                'CurriculumPdfArial',
                $this->arialFontCandidatePaths('bold'),
                700,
                'normal'
            ),
            $this->embeddedPdfFontFace(
                'CurriculumPdfArial',
                $this->arialFontCandidatePaths('italic'),
                400,
                'italic'
            ),
            $this->embeddedPdfFontFace(
                'CurriculumPdfArial',
                $this->arialFontCandidatePaths('boldItalic'),
                700,
                'italic'
            ),
        ]);

        return implode("\n", $fontFaces);
    }

    private function embeddedPdfFontFace(string $family, array $paths, int $weight, string $style): ?string
    {
        $dataUri = $this->fontDataUri($paths);

        if ($dataUri === null) {
            return null;
        }

        return <<<CSS
@font-face {
    font-family: '{$family}';
    src: url("{$dataUri}") format('truetype');
    font-weight: {$weight};
    font-style: {$style};
}
CSS;
    }

    private function fontDataUri(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false || $contents === '') {
                continue;
            }

            return 'data:font/truetype;base64,'.base64_encode($contents);
        }

        return null;
    }

    private function arialFontCandidatePaths(string $variant): array
    {
        return match ($variant) {
            'regular' => [
                storage_path('app/pdf-fonts/arial.ttf'),
                resource_path('fonts/arial.ttf'),
                'C:\Windows\Fonts\arial.ttf',
                '/Library/Fonts/Arial.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/Arial.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/arial.ttf',
            ],
            'bold' => [
                storage_path('app/pdf-fonts/arialbd.ttf'),
                resource_path('fonts/arialbd.ttf'),
                'C:\Windows\Fonts\arialbd.ttf',
                '/Library/Fonts/Arial Bold.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/Arial_Bold.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/arialbd.ttf',
            ],
            'italic' => [
                storage_path('app/pdf-fonts/ariali.ttf'),
                resource_path('fonts/ariali.ttf'),
                'C:\Windows\Fonts\ariali.ttf',
                '/Library/Fonts/Arial Italic.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/Arial_Italic.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/ariali.ttf',
            ],
            'boldItalic' => [
                storage_path('app/pdf-fonts/arialbi.ttf'),
                resource_path('fonts/arialbi.ttf'),
                'C:\Windows\Fonts\arialbi.ttf',
                '/Library/Fonts/Arial Bold Italic.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/Arial_Bold_Italic.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/arialbi.ttf',
            ],
            default => [],
        };
    }

    private function assignmentLabel(array $item): ?string
    {
        $type = $item['assignment_type'] ?? 'none';
        if ($type === 'none') {
            return null;
        }
        if ($type === 'all_weeks') {
            return 'Ganzes Jahr';
        }

        if ($type === 'month') {
            $monthKeys = is_array($item['month_keys'] ?? null) ? $item['month_keys'] : [];
            if (! $monthKeys) {
                return null;
            }

            return $this->monthRangeLabel($monthKeys);
        }

        return null;
    }

    private function topicDateRange(array $topic): ?string
    {
        $units = is_array($topic['units'] ?? null) ? $topic['units'] : [];
        if (! $units) {
            return null;
        }

        $allMonthNames = [];

        if (($topic['assignment_type'] ?? 'none') === 'month' && is_array($topic['month_keys'] ?? null)) {
            foreach ($topic['month_keys'] as $mk) {
                $allMonthNames[] = $this->monthKeyToName($mk);
            }
        }

        foreach ($units as $unit) {
            if (($unit['assignment_type'] ?? 'none') === 'month' && is_array($unit['month_keys'] ?? null)) {
                foreach ($unit['month_keys'] as $mk) {
                    $allMonthNames[] = $this->monthKeyToName($mk);
                }
            }
        }

        $allMonthNames = array_values(array_unique($allMonthNames));

        $parts = [];

        if ($allMonthNames) {
            if (count($allMonthNames) === 1) {
                $parts[] = $allMonthNames[0];
            } else {
                $parts[] = $allMonthNames[0].' – '.end($allMonthNames);
            }
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    private function weekKeysToLabel(array $weekKeys): ?string
    {
        sort($weekKeys);
        $dates = array_map(fn ($k) => date_create($k), $weekKeys);
        $dates = array_filter($dates);

        if (! $dates) {
            return null;
        }

        $dates = array_values($dates);
        $first = $dates[0];
        $last = end($dates);

        $firstMonth = $this->monthNameFromDate($first);
        $lastMonth = $this->monthNameFromDate($last);
        $firstKw = (int) $first->format('W');
        $lastKw = (int) $last->format('W');

        if ($firstKw === $lastKw) {
            return "{$firstMonth} (KW {$firstKw})";
        }

        if ($firstMonth === $lastMonth) {
            return "{$firstMonth} (KW {$firstKw}–{$lastKw})";
        }

        return "{$firstMonth} (KW {$firstKw}) – {$lastMonth} (KW {$lastKw})";
    }

    private function monthRangeLabel(array $monthKeys): string
    {
        sort($monthKeys);
        $names = array_map(fn ($k) => $this->monthKeyToName($k), $monthKeys);

        if (count($names) === 1) {
            return $names[0];
        }

        return $names[0].' – '.end($names);
    }

    private function monthKeyToName(string $key): string
    {
        $map = [
            '01' => 'Jänner', '02' => 'Februar', '03' => 'März', '04' => 'April',
            '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
        ];

        $parts = explode('-', $key);

        return $map[$parts[1] ?? ''] ?? $key;
    }

    private function monthNameFromDate(\DateTimeInterface $date): string
    {
        $map = [
            1 => 'Jänner', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return $map[(int) $date->format('n')] ?? '';
    }

    /**
     * @param  array<string, mixed>  $topic
     * @return array{
     *     id: string,
     *     title: string,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     week_keys: array<int, string>,
     *     units: array<int, array{
     *         id: string,
     *         title: string,
     *         is_exam: bool,
     *         assignment_type: string,
     *         month_key: ?string,
     *         month_keys: array<int, string>,
     *         week_keys: array<int, string>,
     *         checked_week_keys: array<int, string>
     *     }>
     * }
     */
    private function transferTopic(array $topic): array
    {
        return [
            'id' => (string) ($topic['id'] ?? ''),
            'title' => (string) ($topic['title'] ?? ''),
            'assignment_type' => (string) ($topic['assignment_type'] ?? 'none'),
            'month_key' => isset($topic['month_key']) ? (string) $topic['month_key'] : null,
            'month_keys' => $this->stringList($topic['month_keys'] ?? []),
            'week_keys' => $this->stringList($topic['week_keys'] ?? []),
            'units' => collect(is_array($topic['units'] ?? null) ? $topic['units'] : [])
                ->filter(fn (mixed $unit): bool => is_array($unit))
                ->map(fn (array $unit): array => $this->transferUnit($unit))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $unit
     * @return array{
     *     id: string,
     *     title: string,
     *     is_exam: bool,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     week_keys: array<int, string>,
     *     checked_week_keys: array<int, string>
     * }
     */
    private function transferUnit(array $unit): array
    {
        return [
            'id' => (string) ($unit['id'] ?? ''),
            'title' => (string) ($unit['title'] ?? ''),
            'is_exam' => (bool) ($unit['is_exam'] ?? false),
            'assignment_type' => (string) ($unit['assignment_type'] ?? 'none'),
            'month_key' => isset($unit['month_key']) ? (string) $unit['month_key'] : null,
            'month_keys' => $this->stringList($unit['month_keys'] ?? []),
            'week_keys' => $this->stringList($unit['week_keys'] ?? []),
            'checked_week_keys' => $this->stringList($unit['checked_week_keys'] ?? []),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        return array_values(array_filter(
            is_array($values) ? $values : [],
            fn (mixed $value): bool => is_string($value) && $value !== ''
        ));
    }
}
