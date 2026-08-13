<?php

namespace App\Services\StudentsTimetables;

use App\Models\StudentTimetableV3State;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StudentTimetableV3StateService
{
    /** @return array<string, mixed>|null */
    public function stateForUser(User $authUser, array $context = []): ?array
    {
        $storedState = StudentTimetableV3State::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $this->schoolyearIdForUser($authUser))
            ->where('user_id', $authUser->id)
            ->value('state');

        if (! is_array($storedState)) {
            return null;
        }

        $requestedContext = $context === [] ? null : $this->normalizedContext($context, false);

        if (! $this->isContextEnvelope($storedState)) {
            if ($requestedContext === null) {
                return $storedState;
            }

            $legacyContext = $this->contextFromState($storedState);

            return $legacyContext !== null
                && $this->contextKey($legacyContext) === $this->contextKey($requestedContext)
                    ? $storedState
                    : null;
        }

        $contextKey = $requestedContext === null
            ? trim((string) ($storedState['latest_context_key'] ?? ''))
            : $this->contextKey($requestedContext);
        $contextState = $storedState['contexts'][$contextKey]['state'] ?? null;

        return is_array($contextState) ? $contextState : null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function updateForUser(User $authUser, array $context, array $state): array
    {
        $schoolyearId = $this->schoolyearIdForUser($authUser);
        $normalizedContext = $this->normalizedContext($context);
        $contextKey = $this->contextKey($normalizedContext);
        $stateContext = $this->contextFromState($state);

        if (
            $stateContext !== null
            && (
                $stateContext['planning_mode'] !== $normalizedContext['planning_mode']
                || $stateContext['student_code'] !== $normalizedContext['student_code']
            )
        ) {
            abort(422, 'Der V3-Arbeitsstand stimmt nicht mit seinem Planungskontext überein.');
        }

        return Cache::lock($this->stateLockKey($authUser, $schoolyearId), 10)
            ->block(5, function () use (
                $authUser,
                $schoolyearId,
                $normalizedContext,
                $contextKey,
                $state,
            ): array {
                $record = StudentTimetableV3State::query()
                    ->where('school_id', $authUser->school_id)
                    ->where('schoolyear_id', $schoolyearId)
                    ->where('user_id', $authUser->id)
                    ->first();
                $stateEnvelope = $this->stateEnvelope($record?->state);
                $stateEnvelope['latest_context_key'] = $contextKey;
                $stateEnvelope['contexts'][$contextKey] = [
                    'workspace_id' => $normalizedContext['workspace_id'],
                    'planning_mode' => $normalizedContext['planning_mode'],
                    'student_code' => $normalizedContext['student_code'],
                    'state' => $state,
                ];

                StudentTimetableV3State::query()->updateOrCreate(
                    [
                        'school_id' => $authUser->school_id,
                        'schoolyear_id' => $schoolyearId,
                        'user_id' => $authUser->id,
                    ],
                    ['state' => $stateEnvelope],
                );

                return $state;
            });
    }

    /**
     * @param  array<string, mixed>|null  $storedState
     * @return array{version: int, latest_context_key: string|null, contexts: array<string, array<string, mixed>>}
     */
    private function stateEnvelope(?array $storedState): array
    {
        if ($storedState !== null && $this->isContextEnvelope($storedState)) {
            return [
                'version' => 2,
                'latest_context_key' => isset($storedState['latest_context_key'])
                    ? (string) $storedState['latest_context_key']
                    : null,
                'contexts' => $storedState['contexts'],
            ];
        }

        $stateEnvelope = [
            'version' => 2,
            'latest_context_key' => null,
            'contexts' => [],
        ];

        if ($storedState === null) {
            return $stateEnvelope;
        }

        $legacyContext = $this->contextFromState($storedState);

        if ($legacyContext === null) {
            return $stateEnvelope;
        }

        $legacyContextKey = $this->contextKey($legacyContext);
        $stateEnvelope['latest_context_key'] = $legacyContextKey;
        $stateEnvelope['contexts'][$legacyContextKey] = [
            'planning_mode' => $legacyContext['planning_mode'],
            'student_code' => $legacyContext['student_code'],
            'state' => $storedState,
        ];

        return $stateEnvelope;
    }

    /** @param array<string, mixed> $storedState */
    private function isContextEnvelope(array $storedState): bool
    {
        return ($storedState['version'] ?? null) === 2
            && isset($storedState['contexts'])
            && is_array($storedState['contexts']);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{planning_mode: string, student_code: string|null}|null
     */
    private function contextFromState(array $state): ?array
    {
        $planningMode = trim((string) ($state['entrySelection']['mode'] ?? ''));
        $studentCode = trim((string) (
            $state['entrySelection']['student']['studentCode']
            ?? $state['entrySelection']['student']['student_code']
            ?? ''
        ));

        if ($planningMode === 'without_student') {
            return [
                'planning_mode' => $planningMode,
                'student_code' => null,
            ];
        }

        if ($planningMode !== 'with_student' || $studentCode === '') {
            return null;
        }

        return [
            'planning_mode' => $planningMode,
            'student_code' => $studentCode,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{workspace_id: string, planning_mode: string, student_code: string|null}
     */
    private function normalizedContext(array $context, bool $requirePlanningContext = true): array
    {
        $workspaceId = trim((string) ($context['workspace_id'] ?? ''));
        $planningMode = trim((string) ($context['planning_mode'] ?? ''));
        $studentCode = trim((string) ($context['student_code'] ?? ''));

        if (! Str::isUuid($workspaceId)) {
            abort(422, 'Der V3-Arbeitsstand hat keine gültige Arbeitsbereich-ID.');
        }

        if (! $requirePlanningContext && $planningMode === '') {
            return [
                'workspace_id' => $workspaceId,
                'planning_mode' => '',
                'student_code' => null,
            ];
        }

        if ($planningMode === 'without_student') {
            return [
                'workspace_id' => $workspaceId,
                'planning_mode' => $planningMode,
                'student_code' => null,
            ];
        }

        if ($planningMode !== 'with_student' || $studentCode === '') {
            abort(422, 'Der V3-Arbeitsstand hat keinen gültigen Planungskontext.');
        }

        return [
            'workspace_id' => $workspaceId,
            'planning_mode' => $planningMode,
            'student_code' => $studentCode,
        ];
    }

    /** @param array{workspace_id?: string|null, planning_mode: string, student_code: string|null} $context */
    private function contextKey(array $context): string
    {
        $workspaceId = trim((string) ($context['workspace_id'] ?? ''));

        if ($workspaceId !== '') {
            return 'workspace:'.hash('sha256', $workspaceId);
        }

        return $context['planning_mode'] === 'without_student'
            ? 'without-student'
            : 'student:'.hash('sha256', (string) $context['student_code']);
    }

    private function stateLockKey(User $authUser, int $schoolyearId): string
    {
        return 'students-timetables:timetable-v3:state:'.hash('sha256', implode('|', [
            (string) $authUser->school_id,
            (string) $schoolyearId,
            (string) $authUser->id,
        ]));
    }

    private function schoolyearIdForUser(User $authUser): int
    {
        if (! $authUser->school_id || ! $authUser->schoolyear_id) {
            abort(422, 'Bitte wählen Sie zuerst eine Schule und ein Schuljahr aus.');
        }

        return (int) $authUser->schoolyear_id;
    }
}
