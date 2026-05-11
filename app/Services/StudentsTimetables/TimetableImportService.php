<?php

namespace App\Services\StudentsTimetables;

use App\Models\TimetableImport;
use App\Models\User;

class TimetableImportService
{
    /**
     * @return array{sections: array<string, int>, total_lines: int, tt_courses: int}
     */
    public function analyzeFile(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return ['sections' => [], 'total_lines' => 0, 'tt_courses' => 0];
        }

        $sections = [];
        $ttCourses = [];
        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            $code = trim($parts[0] ?? '');
            if ($code === '') {
                continue;
            }
            $sections[$code] = ($sections[$code] ?? 0) + 1;

            if ($code === 'TT') {
                $course = trim($parts[8] ?? '');
                if ($course !== '') {
                    $ttCourses[$course] = true;
                }
            }
        }

        ksort($sections);

        return [
            'sections' => $sections,
            'total_lines' => count($lines),
            'tt_courses' => count($ttCourses),
        ];
    }

    public function createImport(User $user, string $storedFilename, string $originalFilename, string $filePath, ?int $schoolyearId = null): TimetableImport
    {
        $fullPath = storage_path($filePath);
        $analysis = $this->analyzeFile($fullPath);

        $existing = TimetableImport::where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->first();

        if ($existing) {
            $oldFile = storage_path($existing->file_path);
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
            $existing->delete();
        }

        return TimetableImport::create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyearId,
            'user_id' => $user->id,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_path' => $filePath,
            'sections' => $analysis['sections'],
            'total_lines' => $analysis['total_lines'],
            'tt_courses' => $analysis['tt_courses'],
            'imported_at' => now(),
        ]);
    }
}
