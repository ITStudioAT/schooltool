<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeacherDeleteTeachersRequest;
use App\Http\Requests\Admin\TeacherIndexRequest;
use App\Http\Requests\Admin\TeacherStoreRequest;
use App\Http\Requests\Admin\TeacherUpdateRequest;
use App\Http\Requests\Admin\UpdateTeacherClassHeadRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\TeacherResource;
use App\Models\Import116;
use App\Models\Schoolyear;
use App\Models\TeachingClassHead;
use App\Models\User;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherController extends Controller
{
    public function exportCsv(): StreamedResponse
    {
        if (! $authUser = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $teachers = User::query()
            ->where('school_id', $authUser->school_id)
            ->role('teacher')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get(['short', 'last_name', 'first_name', 'email', 'is_active']);

        return response()->streamDownload(static function () use ($teachers): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Kurz', 'Nachname', 'Vorname', 'Email', 'Aktiv'], ';', '"', '', "\r\n");

            foreach ($teachers as $teacher) {
                $values = [$teacher->short, $teacher->last_name, $teacher->first_name, $teacher->email, $teacher->is_active ? 'Ja' : 'Nein'];
                $values = array_map(static function (?string $value): string {
                    $value ??= '';

                    return preg_match('/\A(?:[\t\r\n]|\s*[=+\-@])/u', $value) ? "'".$value : $value;
                }, $values);
                fputcsv($stream, $values, ';', '"', '', "\r\n");
            }

            fclose($stream);
        }, 'Lehrer_Schule_'.$authUser->school_id.'_'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(TeacherIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;

        $teachers = User::teachers($auth_user->school_id)
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('short', 'like', "%{$search_string}%")
                        ->orWhere('last_name', 'like', "%{$search_string}%")
                        ->orWhere('first_name', 'like', "%{$search_string}%")
                        ->orWhere('email', 'like', "%{$search_string}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(config('schooltool.pagination'));

        $classHeads = TeachingClassHead::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->whereIn('user_id', $teachers->getCollection()->modelKeys())
            ->get(['user_id', 'class_name'])
            ->groupBy('user_id');

        $teachers->getCollection()->each(function (User $teacher) use ($classHeads): void {
            $teacher->setAttribute('class_head_classes', $classHeads->get($teacher->id)?->pluck('class_name')->sort()->values()->all() ?? []);
        });

        $classes = Import116::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        return response()->json([
            'data' => TeacherResource::collection($teachers),
            'meta' => new PaginateResource($teachers),
            'classes' => $classes,
        ]);
    }

    public function updateClassHead(UpdateTeacherClassHeadRequest $request, User $user): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $user->school_id !== (int) $authUser->school_id || ! $user->hasRole('teacher')) {
            abort(404);
        }

        $schoolyear = Schoolyear::query()
            ->where('school_id', $authUser->school_id)
            ->findOrFail($authUser->schoolyear_id);

        $validated = $request->validated();

        $classNames = collect($validated['class_names'])->sort()->values()->all();

        DB::transaction(function () use ($authUser, $schoolyear, $user, $classNames): void {
            Schoolyear::query()->whereKey($schoolyear->id)->lockForUpdate()->firstOrFail();
            $scope = ['school_id' => $authUser->school_id, 'schoolyear_id' => $schoolyear->id];
            $previousClasses = TeachingClassHead::query()
                ->where($scope)
                ->where('user_id', $user->id)
                ->pluck('class_name')
                ->all();

            foreach (array_diff($previousClasses, $classNames) as $removedClass) {
                TeachingClassHead::query()
                    ->where($scope)
                    ->where('user_id', $user->id)
                    ->where('class_name', $removedClass)
                    ->delete();

                if (! TeachingClassHead::query()->where($scope)->where('class_name', $removedClass)->whereNotNull('user_id')->exists()) {
                    TeachingClassHead::query()->firstOrCreate([
                        ...$scope,
                        'class_name' => $removedClass,
                        'user_id' => null,
                    ]);
                }
            }

            foreach (array_diff($classNames, $previousClasses) as $addedClass) {
                TeachingClassHead::query()
                    ->where($scope)
                    ->where('class_name', $addedClass)
                    ->whereNull('user_id')
                    ->delete();

                TeachingClassHead::query()->firstOrCreate([
                    ...$scope,
                    'user_id' => $user->id,
                    'class_name' => $addedClass,
                ]);
            }
        });

        return response()->json(['class_names' => $classNames]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TeacherStoreRequest $request, TeacherService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $teacher = $service->create($auth_user->school_id, $validated);

        return response()->json(new TeacherResource($teacher), 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TeacherUpdateRequest $request, User $user, TeacherService $service)
    {

        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $teacher = $service->update($auth_user, $validated);

        return response()->json(new TeacherResource($teacher), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }

    public function deleteTeachers(TeacherDeleteTeachersRequest $request, TeacherService $service)
    {

        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $service->deleteTeachers($auth_user->school_id, $validated['data']);

        return response()->noContent();
    }
}
