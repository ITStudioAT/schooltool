<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingExplanationShare;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\NumberSystems\NumberSystemInvitation;
use App\Services\Teaching\TeachingExplanationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeachingExplanationController extends Controller
{
    public function show(string $explanation): JsonResponse
    {
        $teacher = $this->teacher($explanation);
        $available = Schema::hasTable('teaching_explanation_shares');
        $share = $available ? $this->shares($teacher, $explanation)->first() : null;

        return response()->json([
            'available' => $available,
            'published' => (bool) $share?->is_active,
            'access_mode' => $share?->access_mode ?? 'authenticated',
            'invitation' => $share?->is_active ? app(NumberSystemInvitation::class)->forUrl(route('explanations.entry', ['token' => $share->token])) : null,
            'courses' => $this->courses($teacher)->get(['id', 'title']),
            'groups' => $this->groups($teacher)->get(['id', 'name']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function publish(Request $request, string $explanation): JsonResponse
    {
        $teacher = $this->teacher($explanation);
        abort_unless(Schema::hasTable('teaching_explanation_shares'), 503);
        $data = $request->validate(['access_mode' => ['required', Rule::in(['public', 'authenticated'])]]);
        DB::transaction(function () use ($teacher, $explanation, $data): void {
            $share = $this->shares($teacher, $explanation)->lockForUpdate()->first()
                ?? new TeachingExplanationShare(['school_id' => $teacher->school_id, 'user_id' => $teacher->id, 'explanation' => $explanation]);
            if (! $share->is_active || $share->access_mode !== $data['access_mode']) {
                $share->token = Str::random(48);
            }
            $share->fill(['access_mode' => $data['access_mode'], 'is_active' => true, 'published_at' => now()])->save();
        });

        return $this->show($explanation);
    }

    public function revoke(string $explanation): JsonResponse
    {
        $teacher = $this->teacher($explanation);
        abort_unless(Schema::hasTable('teaching_explanation_shares'), 503);
        $this->shares($teacher, $explanation)->update(['is_active' => false]);

        return $this->show($explanation);
    }

    public function emailPreview(Request $request, string $explanation): JsonResponse
    {
        $teacher = $this->teacher($explanation);
        abort_unless(Schema::hasTable('teaching_explanation_shares'), 503);
        $share = $this->shares($teacher, $explanation)->where('is_active', true)->firstOrFail();
        $data = $request->validate(['audience_type' => ['required', Rule::in(['course', 'group'])], 'audience_id' => ['required', 'integer', 'min:1']]);
        if ($data['audience_type'] === 'course') {
            $course = $this->courses($teacher)->findOrFail($data['audience_id']);
            $contacts = $course->teachingCourseStudents()->whereNull('canceled_at')->with('user')->get()->map(fn ($student): array => [
                'name' => $student->user?->first_name ?? '',
                'email' => $student->user?->is_active && (int) $student->user->school_id === (int) $teacher->school_id ? $student->user->email : null,
            ]);
        } else {
            $group = $this->groups($teacher)->findOrFail($data['audience_id']);
            $contacts = $group->groupMembers()->where('school_id', $teacher->school_id)->where('source_status', 'active')
                ->where(fn (Builder $query) => $query->whereNull('linked_user_id')->orWhereHas('linkedUser',
                    fn (Builder $user) => $user->where('is_active', true)->where('school_id', $teacher->school_id)))
                ->where(fn (Builder $query) => $query->whereNull('source_schoolyear_id')->orWhere('source_schoolyear_id', $teacher->schoolyear_id))->get()
                ->map(fn ($member): array => ['name' => $member->display_name, 'email' => $member->display_email]);
        }
        $valid = $contacts->filter(fn (array $contact): bool => (bool) filter_var($contact['email'], FILTER_VALIDATE_EMAIL));
        $url = route('explanations.entry', ['token' => $share->token]);
        $access = $share->access_mode === 'public' ? 'Du kannst die Erklärung ohne Anmeldung öffnen.' : 'Bitte melde dich mit deinem Schooltool-Konto an.';
        $title = TeachingExplanationCatalog::find($explanation)['title'];
        $recipients = $valid->unique(fn (array $contact): string => strtolower($contact['email']))->map(fn (array $contact): array => [
            ...$contact, 'subject' => 'Erklärung: '.$title,
            'body' => "Hallo,\n\nschau dir die Erklärung „{$title}“ an:\n{$url}\n\n{$access}\nDu gehst mit „Weiter“ und „Zurück“ in deinem eigenen Tempo durch die Umrechnung.",
        ])->values();

        return response()->json(['recipients' => $recipients, 'missing_email_count' => $contacts->count() - $valid->count(), 'sent' => false])
            ->header('Cache-Control', 'private, no-store');
    }

    private function teacher(string $explanation): User
    {
        abort_unless(TeachingExplanationCatalog::find($explanation), 404);
        $teacher = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($teacher && $teacher->is_active && $teacher->school_id, 403);

        return $teacher;
    }

    private function shares(User $teacher, string $explanation): Builder
    {
        return TeachingExplanationShare::query()->where('school_id', $teacher->school_id)->where('user_id', $teacher->id)->where('explanation', $explanation);
    }

    private function courses(User $teacher): Builder
    {
        return TeachingCourse::query()->where('school_id', $teacher->school_id)->where('user_id', $teacher->id)
            ->where('schoolyear_id', $teacher->schoolyear_id)->orderBy('title');
    }

    private function groups(User $teacher): Builder
    {
        return UserGroup::query()->where('school_id', $teacher->school_id)->where('created_by_user_id', $teacher->id)
            ->where('type', UserGroup::TYPE_OWN)->where(function (Builder $query): void {
                $query->whereNull('teaching_course_group_type')->orWhere('teaching_course_group_type', UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS);
            })->where(fn (Builder $query) => $query->whereNull('teaching_course_id')->orWhereHas('teachingCourse',
                fn (Builder $course) => $course->where('user_id', $teacher->id)->where('school_id', $teacher->school_id)->where('schoolyear_id', $teacher->schoolyear_id)))
            ->orderBy('name');
    }
}
