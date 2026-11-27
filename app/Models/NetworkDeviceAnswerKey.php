<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NetworkDeviceAnswerKey extends Model
{
    private const QUESTIONS = [
        'patch_panel' => 'Was ist die Aufgabe des Patchpanels?',
        'switch' => 'Was ist die Aufgabe des Switchs?',
        'router' => 'Was ist die Aufgabe des Routers?',
        'data_server' => 'Was ist die Aufgabe des Datenservers?',
        'access_point' => 'Was ist die Aufgabe des WLAN-Access-Points?',
    ];

    public static function initializeQuestionsForSchool(int $schoolId): void
    {
        $answerKeys = self::query()->where('school_id', $schoolId);
        if ((clone $answerKeys)->where('questions_initialized', true)->exists()) {
            return;
        }

        DB::transaction(function () use ($answerKeys, $schoolId): void {
            // Schools without a legacy answer key also need a lock for their first conversion.
            School::query()->lockForUpdate()->findOrFail($schoolId);
            $answerKey = $answerKeys->lockForUpdate()->first();
            if ($answerKey?->questions_initialized) {
                return;
            }

            if ($answerKey === null) {
                $answerKey = self::query()->whereNull('school_id')->firstOrFail()->replicate();
                $answerKey->school_id = $schoolId;
            }

            $now = now();
            $questions = [];
            foreach (self::QUESTIONS as $key => $question) {
                $questions[] = [
                    'school_id' => $schoolId,
                    'user_id' => null,
                    'question' => $question,
                    'answer' => $answerKey->answers[$key],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            NetworkTestQuestion::query()->insert($questions);
            $answerKey->questions_initialized = true;
            $answerKey->save();
        }, 3);
    }

    protected function casts(): array
    {
        return ['answers' => 'array', 'revision' => 'integer', 'questions_initialized' => 'boolean'];
    }
}
