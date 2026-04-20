<?php

namespace App\Services\Teaching;

use App\Models\TeachingCurriculum;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class CurriculumExportService
{
    public function toWord(TeachingCurriculum $curriculum, $user = null): string
    {
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
        if ($user) {
            $infoParts[] = 'Lehrperson: '.$user->name;
            if ($user->school) {
                $infoParts[] = 'Schule: '.$user->school->long_name;
            }
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

        Pdf::view('pdfs.curriculum-export', [
            'curriculum' => $curriculum,
            'topics' => $topics,
            'freeWeeks' => $freeWeeks,
            'assignmentLabels' => $this->buildAssignmentLabels($topics),
            'userName' => $user?->name,
            'schoolName' => $user?->school?->long_name,
            'printDate' => now()->format('d.m.Y, H:i'),
        ])
            ->format(Format::A4)
            ->save($path);

        return $path;
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

        if ($type === 'weeks') {
            $weekKeys = is_array($item['week_keys'] ?? null) ? $item['week_keys'] : [];
            if (! $weekKeys) {
                return null;
            }

            return $this->weekKeysToLabel($weekKeys);
        }

        return null;
    }

    private function topicDateRange(array $topic): ?string
    {
        $units = is_array($topic['units'] ?? null) ? $topic['units'] : [];
        if (! $units) {
            return null;
        }

        $allWeekKeys = [];
        $allMonthNames = [];

        if (($topic['assignment_type'] ?? 'none') === 'weeks' && is_array($topic['week_keys'] ?? null)) {
            $allWeekKeys = array_merge($allWeekKeys, $topic['week_keys']);
        }
        if (($topic['assignment_type'] ?? 'none') === 'month' && is_array($topic['month_keys'] ?? null)) {
            foreach ($topic['month_keys'] as $mk) {
                $allMonthNames[] = $this->monthKeyToName($mk);
            }
        }

        foreach ($units as $unit) {
            if (($unit['assignment_type'] ?? 'none') === 'weeks' && is_array($unit['week_keys'] ?? null)) {
                $allWeekKeys = array_merge($allWeekKeys, $unit['week_keys']);
            }
            if (($unit['assignment_type'] ?? 'none') === 'month' && is_array($unit['month_keys'] ?? null)) {
                foreach ($unit['month_keys'] as $mk) {
                    $allMonthNames[] = $this->monthKeyToName($mk);
                }
            }
        }

        $allWeekKeys = array_unique($allWeekKeys);
        sort($allWeekKeys);

        foreach ($allWeekKeys as $weekKey) {
            $date = date_create($weekKey);
            if ($date) {
                $allMonthNames[] = $this->monthNameFromDate($date);
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

        if ($allWeekKeys) {
            $kws = array_map(function ($wk) {
                $date = date_create($wk);

                return $date ? (int) $date->format('W') : 0;
            }, $allWeekKeys);
            sort($kws);
            $first = $kws[0];
            $last = end($kws);
            $parts[] = $first === $last ? "KW {$first}" : "KW {$first}–{$last}";
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
}
