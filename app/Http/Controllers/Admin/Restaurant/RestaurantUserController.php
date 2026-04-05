<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\RestaurantUserIndexRequest;
use App\Http\Requests\Admin\Restaurant\RestaurantUserSepaUpdateRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\Import116;
use App\Models\RestaurantSepaMandate;
use App\Models\Teacher;
use App\Models\User;
use App\Services\RestaurantHomepageAuthService;
use App\Services\RestaurantSepaMandatePdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RestaurantUserController extends Controller
{
    public function index(RestaurantUserIndexRequest $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $validated = $request->validated();
        $searchString = $validated['search_string'] ?? null;
        $onlyPendingConfirmation = (bool) ($validated['only_pending_confirmation'] ?? false);
        $onlyWithoutSepa = (bool) ($validated['only_without_sepa'] ?? false);
        $pendingConfirmationTotal = $this->restaurantCandidateUsersQuery($authUser->school_id)
            ->count();

        $users = $this->restaurantUsersQuery($authUser->school_id)
            ->with('roles')
            ->when($onlyPendingConfirmation, function ($query): void {
                $query->whereHas('roles', function (Builder $roleQuery): void {
                    $roleQuery->where('name', 'lunch_candidate');
                });
            })
            ->when($onlyWithoutSepa, function ($query): void {
                $query->whereNull('sepa_at');
            })
            ->when($searchString, function ($query, $searchString) {
                $query->where(function ($nestedQuery) use ($searchString): void {
                    $nestedQuery
                        ->where('last_name', 'like', "%{$searchString}%")
                        ->orWhere('first_name', 'like', "%{$searchString}%")
                        ->orWhere('email', 'like', "%{$searchString}%")
                        ->orWhere('schoolclass', 'like', "%{$searchString}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email')
            ->paginate(config('schooltool.pagination'));

        $childrenByEmail = $this->import116ChildrenByParentEmail($authUser->school_id, $users->getCollection()->pluck('email'));
        $teacherEmails = $this->teacherEmailsBySchool($authUser->school_id, $users->getCollection()->pluck('email'));
        $parentEmails = $this->parentEmailsBySchool($authUser->school_id, $users->getCollection()->pluck('email'));

        $users->getCollection()->transform(function (User $user) use ($childrenByEmail): User {
            $normalizedEmail = mb_strtolower(trim((string) $user->email));
            $user->setAttribute('import116_children', $childrenByEmail[$normalizedEmail] ?? []);

            return $user;
        });
        $users->getCollection()->transform(function (User $user) use ($teacherEmails, $parentEmails): User {
            $normalizedEmail = mb_strtolower(trim((string) $user->email));
            $originKeys = [];

            if (isset($teacherEmails[$normalizedEmail])) {
                $originKeys[] = 'teacher_list';
            }

            if ((int) ($user->import116_id ?? 0) > 0) {
                $originKeys[] = 'import116_student';
            }

            if (isset($parentEmails[$normalizedEmail])) {
                $originKeys[] = 'import116_parent';
            }

            if ($originKeys === []) {
                $originKeys[] = 'external';
            }

            $user->setAttribute('origin_keys', $originKeys);
            $user->setAttribute('origin_labels', array_map(
                fn (string $key): string => $this->originLabelForKey($key),
                $originKeys
            ));

            return $user;
        });

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => [
                ...(new PaginateResource($users))->toArray($request),
                'pending_confirmation_total' => $pendingConfirmationTotal,
                'only_pending_confirmation' => $onlyPendingConfirmation,
                'only_without_sepa' => $onlyWithoutSepa,
            ],
        ]);
    }

    private function restaurantUsersQuery(int $schoolId): Builder
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', function (Builder $query): void {
                $query->whereIn('name', ['lunch_user', 'lunch_candidate']);
            });
    }

    private function restaurantCandidateUsersQuery(int $schoolId): Builder
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', function (Builder $query): void {
                $query->where('name', 'lunch_candidate');
            });
    }

    private function completedSepaMandatesQuery(int $schoolId): Builder
    {
        return RestaurantSepaMandate::query()
            ->with(['user.roles'])
            ->where('school_id', $schoolId)
            ->whereIn('entry_point', ['login', 'register'])
            ->whereNotNull('completed_at')
            ->whereHas('user', function (Builder $userQuery): void {
                $userQuery
                    ->whereNotNull('sepa_at')
                    ->whereHas('roles', function (Builder $roleQuery): void {
                        $roleQuery->where('name', 'lunch_user');
                    });
            });
    }

    public function updateSepa(RestaurantUserSepaUpdateRequest $request, User $user): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $targetUser = User::query()
            ->whereKey($user->id)
            ->bySchoolAndRole($authUser->school_id, 'lunch_user')
            ->with('roles')
            ->firstOrFail();

        $hasSepa = (bool) $request->validated()['data']['has_sepa'];

        DB::transaction(function () use ($authUser, $hasSepa, $targetUser): void {
            $targetUser->sepa_at = $hasSepa ? ($targetUser->sepa_at ?? now()) : null;
            $targetUser->save();

            if (! $hasSepa) {
                RestaurantSepaMandate::query()
                    ->where('school_id', $authUser->school_id)
                    ->where('user_id', $targetUser->id)
                    ->delete();
            }
        });

        return response()->json([
            'data' => new UserResource($targetUser->fresh()->load('roles')),
        ]);
    }

    public function sepaUsers(): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $page = max(1, (int) request()->integer('page', 1));
        $perPage = (int) config('schooltool.pagination');
        $mandates = $this->completedSepaMandatesPaginator($authUser->school_id, $page, $perPage);

        return response()->json([
            'data' => $mandates->getCollection()->map(function (RestaurantSepaMandate $mandate): array {
                return $this->sepaMandatePayload($mandate);
            })->all(),
            'meta' => (new PaginateResource($mandates))->toArray(request()),
        ]);
    }

    public function printSepaMandate(
        string $flowUuid,
        RestaurantSepaMandatePdfService $pdfService
    ): BinaryFileResponse {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $mandate = $this->completedSepaMandatesQuery($authUser->school_id)
            ->where('flow_uuid', $flowUuid)
            ->firstOrFail();

        $path = $pdfService->createPdf($mandate);

        return response()
            ->download($path, basename($path), [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }

    private function completedSepaMandatesPaginator(int $schoolId, int $page, int $perPage): LengthAwarePaginator
    {
        $mandates = $this->completedSepaMandatesQuery($schoolId)
            ->get()
            ->unique('user_id')
            ->sort(function (RestaurantSepaMandate $left, RestaurantSepaMandate $right): int {
                $leftUser = $left->user;
                $rightUser = $right->user;

                $lastNameComparison = strcmp(
                    mb_strtolower(trim((string) ($leftUser?->last_name ?? ''))),
                    mb_strtolower(trim((string) ($rightUser?->last_name ?? '')))
                );

                if ($lastNameComparison !== 0) {
                    return $lastNameComparison;
                }

                $firstNameComparison = strcmp(
                    mb_strtolower(trim((string) ($leftUser?->first_name ?? ''))),
                    mb_strtolower(trim((string) ($rightUser?->first_name ?? '')))
                );

                if ($firstNameComparison !== 0) {
                    return $firstNameComparison;
                }

                return strcmp(
                    mb_strtolower(trim((string) ($leftUser?->email ?? ''))),
                    mb_strtolower(trim((string) ($rightUser?->email ?? '')))
                );
            })
            ->values();

        $items = $mandates->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $mandates->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function sepaMandatePayload(RestaurantSepaMandate $mandate): array
    {
        $user = $mandate->user;
        $fullName = trim(implode(' ', array_filter([
            trim((string) $user?->first_name),
            trim((string) $user?->last_name),
        ])));

        return [
            'id' => (int) ($user?->id ?? 0),
            'name' => $fullName !== '' ? $fullName : trim((string) ($user?->email ?? '')),
            'email' => trim((string) ($user?->email ?? '')),
            'schoolclass' => $user?->schoolclass ? (string) $user->schoolclass : null,
            'flow_uuid' => $mandate->flow_uuid,
            'entry_point' => $mandate->entry_point,
            'entry_point_label' => $mandate->entry_point === 'register' ? 'Registrierung' : 'Login',
            'completed_at' => $mandate->completed_at?->format('d.m.Y H:i'),
        ];
    }

    public function confirm(User $user, RestaurantHomepageAuthService $restaurantHomepageAuthService): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $targetUser = User::query()
            ->whereKey($user->id)
            ->where('school_id', $authUser->school_id)
            ->whereHas('roles', function (Builder $query): void {
                $query->where('name', 'lunch_candidate');
            })
            ->with('roles')
            ->firstOrFail();

        if (! $targetUser->email_verified_at) {
            abort(422, 'Die E-Mail-Adresse muss zuerst bestätigt werden.');
        }

        $targetUser->confirmed_at = $targetUser->confirmed_at ?? now();
        $targetUser->restaurant_confirmed_at = $targetUser->restaurant_confirmed_at ?? now();
        $targetUser->is_active = 1;
        $targetUser->save();

        if ($targetUser->hasRole('lunch_candidate')) {
            $targetUser->removeRole('lunch_candidate');
        }

        if (! $targetUser->hasRole('lunch_user')) {
            $targetUser->assignRole('lunch_user');
        }

        $restaurantHomepageAuthService->sendRestaurantConfirmationEmail($targetUser);

        return response()->json([
            'data' => new UserResource($targetUser->fresh()->load('roles')),
        ]);
    }

    public function destroy(User $user): Response
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $targetUser = User::query()
            ->whereKey($user->id)
            ->where('school_id', $authUser->school_id)
            ->whereHas('roles', function (Builder $query): void {
                $query->where('name', 'lunch_candidate');
            })
            ->with('roles')
            ->firstOrFail();

        DB::transaction(function () use ($targetUser): void {
            if ($targetUser->roles->count() > 1) {
                $targetUser->removeRole('lunch_candidate');

                return;
            }

            if ($targetUser->hasDependencies()) {
                abort(409, 'Der Benutzer hat noch Abhängigkeiten und kann nicht gelöscht werden.');
            }

            $targetUser->syncRoles([]);
            $targetUser->delete();
        });

        return response()->noContent();
    }

    /**
     * @param  Collection<int, string|null>  $emails
     * @return array<string, array<int, array{name: string, email: string}>>
     */
    private function import116ChildrenByParentEmail(int $schoolId, $emails): array
    {
        $normalizedEmails = $emails
            ->filter(fn (?string $email): bool => filled($email))
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter(fn (string $email): bool => $email !== '')
            ->unique()
            ->values();

        if ($normalizedEmails->isEmpty()) {
            return [];
        }

        $childrenByEmail = [];

        Import116::query()
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($normalizedEmails): void {
                $query->whereIn('mother_email', $normalizedEmails)
                    ->orWhereIn('father_email', $normalizedEmails);
            })
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'first_name',
                'last_name',
                'email',
                'mother_email',
                'father_email',
            ])
            ->each(function (Import116 $importRow) use (&$childrenByEmail): void {
                $child = [
                    'name' => trim(implode(' ', array_filter([
                        trim((string) $importRow->first_name),
                        trim((string) $importRow->last_name),
                    ]))),
                    'email' => trim((string) ($importRow->email ?? '')),
                ];

                collect([$importRow->mother_email, $importRow->father_email])
                    ->filter(fn (?string $email): bool => filled($email))
                    ->map(fn (string $email): string => mb_strtolower(trim($email)))
                    ->unique()
                    ->each(function (string $email) use (&$childrenByEmail, $child): void {
                        $childrenByEmail[$email] ??= [];

                        if (! in_array($child, $childrenByEmail[$email], true)) {
                            $childrenByEmail[$email][] = $child;
                        }
                    });
            });

        return $childrenByEmail;
    }

    /**
     * @param  Collection<int, string|null>  $emails
     * @return array<string, true>
     */
    private function teacherEmailsBySchool(int $schoolId, Collection $emails): array
    {
        $normalizedEmails = $this->normalizedEmails($emails);

        if ($normalizedEmails->isEmpty()) {
            return [];
        }

        return Teacher::query()
            ->where('school_id', $schoolId)
            ->whereIn('email', $normalizedEmails->all())
            ->get(['email'])
            ->map(fn (Teacher $teacher): string => mb_strtolower(trim((string) $teacher->email)))
            ->filter(fn (string $email): bool => $email !== '')
            ->unique()
            ->mapWithKeys(fn (string $email): array => [$email => true])
            ->all();
    }

    /**
     * @param  Collection<int, string|null>  $emails
     * @return array<string, true>
     */
    private function parentEmailsBySchool(int $schoolId, Collection $emails): array
    {
        $normalizedEmails = $this->normalizedEmails($emails);

        if ($normalizedEmails->isEmpty()) {
            return [];
        }

        return Import116::query()
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($normalizedEmails): void {
                $query->whereIn('mother_email', $normalizedEmails->all())
                    ->orWhereIn('father_email', $normalizedEmails->all());
            })
            ->get(['mother_email', 'father_email'])
            ->flatMap(function (Import116 $import): array {
                return [
                    mb_strtolower(trim((string) $import->mother_email)),
                    mb_strtolower(trim((string) $import->father_email)),
                ];
            })
            ->filter(fn (string $email): bool => $email !== '')
            ->unique()
            ->mapWithKeys(fn (string $email): array => [$email => true])
            ->all();
    }

    /**
     * @param  Collection<int, string|null>  $emails
     * @return Collection<int, string>
     */
    private function normalizedEmails(Collection $emails): Collection
    {
        return $emails
            ->filter(fn (?string $email): bool => filled($email))
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter(fn (string $email): bool => $email !== '')
            ->unique()
            ->values();
    }

    private function originLabelForKey(string $originKey): string
    {
        return match ($originKey) {
            'teacher_list' => 'Lehrerliste',
            'import116_student' => 'Import116',
            'import116_parent' => 'Eltern',
            default => 'Extern',
        };
    }
}
