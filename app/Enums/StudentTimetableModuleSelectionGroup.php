<?php

namespace App\Enums;

enum StudentTimetableModuleSelectionGroup: string
{
    case Finished = 'finished';
    case Negative = 'negative';
    case Previous = 'previous';
    case Current = 'current';
    case Additional = 'additional';

    public function label(): string
    {
        return match ($this) {
            self::Finished => 'Abgeschlossene',
            self::Negative => 'Negative',
            self::Previous => 'Fehlende',
            self::Current => 'Aktuelle',
            self::Additional => 'Vorziehen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Finished => 'Bereits befreit oder bestanden',
            self::Negative => 'Noch einmal zu absolvieren',
            self::Previous => 'Fehlende Module nachholen',
            self::Current => 'Für das aktuelle Semester',
            self::Additional => 'Aus kommenden Semestern',
        };
    }
}
