<?php

namespace App\Enums;

enum StudentTimetableStudyProgram: string
{
    case Normalstudium = 'normalstudium';
    case Kompaktstudium = 'kompaktstudium';

    public function label(): string
    {
        return match ($this) {
            self::Normalstudium => 'Normalstudium',
            self::Kompaktstudium => 'Kompaktstudium',
        };
    }
}
