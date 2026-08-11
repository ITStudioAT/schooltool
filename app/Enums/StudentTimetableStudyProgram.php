<?php

namespace App\Enums;

enum StudentTimetableStudyProgram: string
{
    case Normalstudium = 'normalstudium';
    case Kompaktstudium = 'kompaktstudium';

    public static function fromSubjectPlan(mixed $subjectPlan): ?self
    {
        $subjectPlan = mb_strtoupper(trim((string) $subjectPlan), 'UTF-8');

        if (str_starts_with($subjectPlan, 'AHS-KS-')) {
            return self::Kompaktstudium;
        }

        if (str_starts_with($subjectPlan, 'AHS-')) {
            return self::Normalstudium;
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Normalstudium => 'Normalstudium',
            self::Kompaktstudium => 'Kompaktstudium',
        };
    }
}
