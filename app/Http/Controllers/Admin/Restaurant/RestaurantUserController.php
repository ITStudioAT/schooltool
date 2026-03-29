<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\RestaurantUserIndexRequest;
use App\Http\Requests\Admin\Restaurant\RestaurantUserSepaUpdateRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\Import116;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class RestaurantUserController extends Controller
{
    public function index(RestaurantUserIndexRequest $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $validated = $request->validated();
        $searchString = $validated['search_string'] ?? null;

        $users = User::query()
            ->bySchoolAndRole($authUser->school_id, 'lunch_user')
            ->with('roles')
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

        $users->getCollection()->transform(function (User $user) use ($childrenByEmail): User {
            $normalizedEmail = mb_strtolower(trim((string) $user->email));
            $user->setAttribute('import116_children', $childrenByEmail[$normalizedEmail] ?? []);

            return $user;
        });

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => new PaginateResource($users),
        ]);
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
        $targetUser->sepa_at = $hasSepa ? ($targetUser->sepa_at ?? now()) : null;
        $targetUser->save();

        return response()->json([
            'data' => new UserResource($targetUser->fresh()->load('roles')),
        ]);
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
}
