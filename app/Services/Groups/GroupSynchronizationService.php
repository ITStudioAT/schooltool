<?php

namespace App\Services\Groups;

use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupSynchronizationService
{
    public function runHeavySync(?User $authUser, int $schoolId, int $actorUserId): void
    {
        $this->syncTeacherSchoolGroupMembers($schoolId, $actorUserId);
        $this->repairMissingImportUserLinksByEmail($schoolId);
        $this->syncClassSchoolGroupMembers($schoolId, $actorUserId);
        $this->syncParentSchoolGroupMembers($schoolId, $actorUserId);
        $this->syncAllSchoolMembersGroup($schoolId, $actorUserId);
        if ($authUser && (int) $authUser->school_id === $schoolId) {
            $this->syncOwnTeachingCourseGroups($authUser, $schoolId, $actorUserId);
        }
    }

    /**
     * @return array{registered: Collection<int, array<string, mixed>>, all: Collection<int, array<string, mixed>>}
     */
    private function allSchoolMembersCollections(int $schoolId): array
    {
        $importRows = collect();
        $usersByImportId = collect();
        if (Schema::hasTable('import116')) {
            $importRows = $this->import116QueryForActiveSchoolyear($schoolId)->orderByRaw('LOWER(class)')->orderBy('last_name')->orderBy('first_name')->orderBy('email')->get(['id', 'class', 'user_id', 'last_name', 'first_name', 'email', 'mother_name', 'mother_email', 'mother_phone_1', 'mother_phone_2', 'father_name', 'father_email', 'father_phone_1', 'father_phone_2']);
            $importIds = $importRows->pluck('id')->map(fn ($id) => (int) $id)->values();
            if ($importIds->isNotEmpty()) {
                $usersByImportId = User::query()->where('school_id', $schoolId)->whereNotNull('import116_id')->whereIn('import116_id', $importIds->all())->get(['id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass'])->keyBy(fn (User $user) => (int) $user->import116_id);
            }
        }
        $registeredStudents = $this->allSchoolStudentEntries($importRows, $usersByImportId, true);
        $allStudents = $this->allSchoolStudentEntries($importRows, $usersByImportId, false);
        $registeredParents = $this->allSchoolParentEntries($this->buildParentContacts($importRows, $usersByImportId, true));
        $allParents = $this->allSchoolParentEntries($this->buildParentContacts($importRows, $usersByImportId, false));
        $allTeachers = $this->allSchoolTeacherEntries($this->teacherSourceMembers($schoolId));
        $registeredTeachers = $this->allSchoolTeacherEntries($this->teacherSourceMembers($schoolId)->filter(fn (array $entry) => ! empty($entry['user_id']))->values());
        $adminEntries = $this->allSchoolAdminEntries($schoolId);

        return ['registered' => $this->mergeAllSchoolMemberEntries([$registeredStudents, $registeredParents, $registeredTeachers, $adminEntries]), 'all' => $this->mergeAllSchoolMemberEntries([$allStudents, $allParents, $allTeachers, $adminEntries])];
    }

    private function activeImportSchoolyearId(int $schoolId): ?int
    {
        if (! Schema::hasTable('school_tools')) {
            return null;
        }
        $schoolyearId = SchoolTool::query()->where('school_id', $schoolId)->value('active_schoolyear_id');

        return $schoolyearId ? (int) $schoolyearId : null;
    }

    private function import116QueryForActiveSchoolyear(int $schoolId): Builder
    {
        $query = Import116::query()->where('school_id', $schoolId);
        $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);
        if ($activeSchoolyearId) {
            return $query->where('schoolyear_id', $activeSchoolyearId);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return array{registered: array<string, Collection>, all: array<string, Collection>}
     */
    private function parentContactsForSchoolGroups(int $schoolId): array
    {
        $result = ['registered' => [], 'all' => []];
        if (! Schema::hasTable('import116')) {
            return $result;
        }
        $classGroups = $this->buildImportClassGroupMappings($schoolId);
        $allMappings = collect($classGroups['by_class'])->merge($classGroups['by_family']);
        if ($allMappings->isEmpty()) {
            return $result;
        }
        $importIds = $allMappings->pluck('import_ids')->flatten()->map(fn ($id) => (int) $id)->unique()->values();
        if ($importIds->isEmpty()) {
            return $result;
        }
        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)->whereIn('id', $importIds->all())->get(['id', 'class', 'user_id', 'last_name', 'first_name', 'mother_name', 'mother_email', 'mother_phone_1', 'mother_phone_2', 'father_name', 'father_email', 'father_phone_1', 'father_phone_2'])->keyBy(fn (Import116 $row) => (int) $row->id);
        $usersByImportId = User::query()->where('school_id', $schoolId)->whereNotNull('import116_id')->whereIn('import116_id', $importIds->all())->get(['id', 'import116_id'])->keyBy(fn (User $user) => (int) $user->import116_id);
        foreach ($allMappings as $mapping) {
            $groupName = $this->parentGroupName((string) $mapping['name']);
            $normalizedGroupName = $this->normalizeGroupName($groupName);
            $rows = collect($mapping['import_ids'])->map(fn ($id) => $importRows->get((int) $id))->filter();
            $result['registered'][$normalizedGroupName] = $this->buildParentContacts($rows, $usersByImportId, true);
            $result['all'][$normalizedGroupName] = $this->buildParentContacts($rows, $usersByImportId, false);
        }

        return $result;
    }

    /**
     * @param  Collection<int, Import116>  $rows
     * @param  Collection<int, User>  $usersByImportId
     * @return Collection<int, array<string, mixed>>
     */
    private function allSchoolStudentEntries(Collection $rows, Collection $usersByImportId, bool $registeredOnly): Collection
    {
        return $rows->map(function (Import116 $row) use ($usersByImportId, $registeredOnly) {
            $mappedUser = $usersByImportId->get((int) $row->id);
            $userId = $mappedUser ? (int) $mappedUser->id : ((int) ($row->user_id ?? 0) ?: null);
            if ($registeredOnly && ! $userId) {
                return null;
            }
            $name = $mappedUser ? trim((string) (($mappedUser->last_name ?? '').' '.($mappedUser->first_name ?? ''))) : trim((string) (($row->last_name ?? '').' '.($row->first_name ?? '')));
            $email = $mappedUser?->email ?: $row->email;
            $schoolclass = $mappedUser?->schoolclass ?: $row->class;
            $entryKey = $userId ? 'user:'.$userId : 'student-import:'.(int) $row->id;

            return ['entry_key' => $entryKey, 'id' => $userId ?: (int) $row->id, 'user_id' => $userId, 'import116_id' => (int) $row->id, 'name' => $name !== '' ? $name : ($email ?: 'Schüler:in'), 'email' => $email, 'schoolclass' => $schoolclass, 'phone' => null, 'children_label' => null, 'member_type_label' => 'Schüler:in'];
        })->filter()->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $contacts
     * @return Collection<int, array<string, mixed>>
     */
    private function allSchoolParentEntries(Collection $contacts): Collection
    {
        return $contacts->map(function (array $contact) {
            $contactId = (string) ($contact['id'] ?? '');

            return ['entry_key' => 'parent:'.$contactId, 'id' => $contactId !== '' ? $contactId : md5((string) json_encode($contact)), 'user_id' => null, 'import116_id' => null, 'name' => $contact['name'] ?? 'Erziehungsberechtigte:r', 'email' => $contact['email'] ?? 'Keine E-Mail', 'schoolclass' => $contact['schoolclass'] ?? null, 'phone' => $contact['phone'] ?? null, 'children_label' => $contact['children_label'] ?? null, 'member_type_label' => 'Eltern'];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $teachers
     * @return Collection<int, array<string, mixed>>
     */
    private function allSchoolTeacherEntries(Collection $teachers): Collection
    {
        return $teachers->map(function (array $entry) {
            $userId = ! empty($entry['user_id']) ? (int) $entry['user_id'] : null;
            $entryId = (string) ($entry['id'] ?? ($userId ?: 'teacher'));

            return ['entry_key' => $userId ? 'user:'.$userId : 'teacher-source:'.$entryId, 'id' => $userId ?: $entryId, 'user_id' => $userId, 'import116_id' => null, 'name' => $entry['name'] ?? 'Lehrer:in', 'email' => $entry['email'] ?? null, 'schoolclass' => $entry['schoolclass'] ?? null, 'phone' => null, 'children_label' => null, 'member_type_label' => 'Lehrer:in'];
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allSchoolAdminEntries(int $schoolId): Collection
    {
        return User::query()->where('school_id', $schoolId)->whereHas('roles', fn ($query) => $query->whereIn('name', $this->adminRoleNames()))->get(['id', 'last_name', 'first_name', 'email', 'schoolclass'])->map(function (User $user) {
            $name = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
            $userId = (int) $user->id;

            return ['entry_key' => 'user:'.$userId, 'id' => $userId, 'user_id' => $userId, 'import116_id' => $user->import116_id ? (int) $user->import116_id : null, 'name' => $name !== '' ? $name : $user->email ?? 'Admin', 'email' => $user->email, 'schoolclass' => $user->schoolclass, 'phone' => null, 'children_label' => null, 'member_type_label' => 'Admin'];
        })->values();
    }

    /**
     * @param  array<int, Collection<int, array<string, mixed>>>  $collections
     * @return Collection<int, array<string, mixed>>
     */
    private function mergeAllSchoolMemberEntries(array $collections): Collection
    {
        $merged = [];
        foreach ($collections as $collection) {
            foreach ($collection as $entry) {
                $entryKey = (string) ($entry['entry_key'] ?? '');
                if ($entryKey === '') {
                    continue;
                }
                if (! isset($merged[$entryKey])) {
                    $merged[$entryKey] = ['id' => $entry['id'] ?? $entryKey, 'user_id' => $entry['user_id'] ?? null, 'import116_id' => $entry['import116_id'] ?? null, 'name' => $entry['name'] ?? '', 'email' => $entry['email'] ?? null, 'schoolclass' => $entry['schoolclass'] ?? null, 'phone' => $entry['phone'] ?? null, 'children_label' => $entry['children_label'] ?? null, 'member_types' => []];
                }
                $typeLabel = trim((string) ($entry['member_type_label'] ?? ''));
                if ($typeLabel !== '') {
                    $merged[$entryKey]['member_types'][$typeLabel] = $typeLabel;
                }
                foreach (['name', 'email', 'schoolclass', 'phone', 'children_label'] as $field) {
                    $currentValue = trim((string) ($merged[$entryKey][$field] ?? ''));
                    $nextValue = trim((string) ($entry[$field] ?? ''));
                    if ($currentValue === '' && $nextValue !== '') {
                        $merged[$entryKey][$field] = $entry[$field];
                    }
                }
            }
        }

        return collect($merged)->map(function (array $entry) {
            $typeLabels = array_values($entry['member_types']);
            usort($typeLabels, fn (string $a, string $b) => $this->allSchoolMemberTypeSortOrder($a) <=> $this->allSchoolMemberTypeSortOrder($b));
            $entry['member_type_label'] = implode(', ', $typeLabels);
            unset($entry['member_types']);

            return $entry;
        })->sortBy([fn (array $entry) => mb_strtolower(trim((string) ($entry['name'] ?? ''))), fn (array $entry) => mb_strtolower(trim((string) ($entry['email'] ?? '')))])->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function automaticCourseStudentPayloads(TeachingCourse $course, int $schoolId): Collection
    {
        return $course->teachingCourseStudents->map(function ($courseStudent) use ($schoolId) {
            if ($courseStudent->import116) {
                return $this->payloadForImportStudent($courseStudent->import116, $courseStudent->user ?: $this->linkedUserForImportStudent($schoolId, $courseStudent->import116));
            }
            if ($courseStudent->user) {
                return $this->payloadForUserSource($courseStudent->user, 'Schüler:in');
            }

            return null;
        })->filter()->keyBy(fn (array $payload) => $this->groupMemberDeduplicationKeyFromPayload($payload))->values();
    }

    private function normalizeGroupName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function syncTeacherSchoolGroupMembers(int $schoolId, int $actorUserId): void
    {
        $teacherGroup = UserGroup::query()->where('school_id', $schoolId)->where('type', UserGroup::TYPE_SCHOOL)->whereRaw('LOWER(TRIM(name)) IN (?, ?)', [$this->normalizeGroupName($this->defaultTeacherGroupName()), $this->normalizeGroupName('Teacher')])->first();
        if (! $teacherGroup) {
            $teacherGroup = UserGroup::query()->create(['school_id' => $schoolId, 'type' => UserGroup::TYPE_SCHOOL, 'name' => $this->defaultTeacherGroupName(), 'description' => null, 'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null]);
        }
        if ($this->normalizeGroupName((string) $teacherGroup->name) !== $this->normalizeGroupName($this->defaultTeacherGroupName())) {
            $teacherGroup->name = $this->defaultTeacherGroupName();
            $teacherGroup->save();
        }
        $this->syncGroupMembersFromPayloads($teacherGroup, $this->assignableTeacherPayloads($schoolId, '', 5000), $actorUserId);
    }

    private function syncClassSchoolGroupMembers(int $schoolId, int $actorUserId): void
    {
        if (! Schema::hasTable('import116')) {
            return;
        }
        $classGroupMappings = $this->buildImportClassGroupMappings($schoolId);
        if (empty($classGroupMappings['by_class'])) {
            return;
        }
        $importIdsByClass = collect($classGroupMappings['by_class'])->map(fn (array $row) => $row['import_ids'])->flatten()->merge(collect($classGroupMappings['by_family'])->map(fn (array $row) => $row['import_ids'])->flatten())->map(fn ($id) => (int) $id)->unique()->values();
        $importsById = $importIdsByClass->isEmpty() ? collect() : Import116::query()->where('school_id', $schoolId)->whereIn('id', $importIdsByClass->all())->get(['id', 'school_id', 'schoolyear_id', 'class', 'user_id', 'last_name', 'first_name', 'email', 'phone_1', 'phone_2'])->keyBy(fn (Import116 $import) => (int) $import->id);
        $classGroups = UserGroup::query()->where('school_id', $schoolId)->where('type', UserGroup::TYPE_SCHOOL)->get();
        foreach ($classGroups as $group) {
            $normalizedGroupName = $this->normalizeGroupName((string) $group->name);
            if ($this->isTeacherGroupName($normalizedGroupName)) {
                continue;
            }
            $importIds = $classGroupMappings['by_family'][$normalizedGroupName]['import_ids'] ?? $classGroupMappings['by_class'][$normalizedGroupName]['import_ids'] ?? [];
            if (empty($importIds) || $this->isParentSchoolGroup($group) || $this->isAllSchoolMembersGroup($group)) {
                continue;
            }
            $payloads = collect($importIds)->map(fn (int $importId) => $importsById->get($importId))->filter()->map(fn (Import116 $import) => $this->payloadForImportStudent($import, $this->linkedUserForImportStudent($schoolId, $import)))->values();
            $this->syncGroupMembersFromPayloads($group, $payloads, $actorUserId);
        }
    }

    private function syncParentSchoolGroupMembers(int $schoolId, int $actorUserId): void
    {
        $parentContacts = $this->parentContactsForSchoolGroups($schoolId);
        $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);
        if (! $activeSchoolyearId) {
            return;
        }
        $groups = UserGroup::query()->where('school_id', $schoolId)->where('type', UserGroup::TYPE_SCHOOL)->get();
        foreach ($groups as $group) {
            if (! $this->isParentSchoolGroup($group)) {
                continue;
            }
            $normalizedGroupName = $this->normalizeGroupName((string) $group->name);
            $payloads = collect($parentContacts['all'][$normalizedGroupName] ?? [])->map(fn (array $contact) => $this->payloadForParentContact($schoolId, $activeSchoolyearId, $contact))->values();
            $this->syncGroupMembersFromPayloads($group, $payloads, $actorUserId);
        }
    }

    private function syncAllSchoolMembersGroup(int $schoolId, int $actorUserId): void
    {
        $group = UserGroup::query()->where('school_id', $schoolId)->where('type', UserGroup::TYPE_SCHOOL)->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeGroupName($this->defaultAllSchoolMembersGroupName())])->first();
        if (! $group) {
            return;
        }
        $payloads = $this->allSchoolMembersCollections($schoolId)['all']->map(fn (array $entry) => $this->payloadFromAllSchoolEntry($schoolId, $entry))->filter()->values();
        $this->syncGroupMembersFromPayloads($group, $payloads, $actorUserId);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function payloadFromAllSchoolEntry(int $schoolId, array $entry): ?array
    {
        $memberTypeLabel = (string) ($entry['member_type_label'] ?? '');
        $userId = (int) ($entry['user_id'] ?? 0);
        $import116Id = (int) ($entry['import116_id'] ?? 0);
        if ($import116Id > 0 && str_contains($memberTypeLabel, 'Schüler:in')) {
            $import = Import116::query()->where('school_id', $schoolId)->where('id', $import116Id)->first(['id', 'school_id', 'schoolyear_id', 'class', 'user_id', 'last_name', 'first_name', 'email', 'phone_1', 'phone_2']);
            if ($import) {
                $linkedUser = $userId > 0 ? User::query()->where('school_id', $schoolId)->where('id', $userId)->first(['id', 'school_id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass']) : $this->linkedUserForImportStudent($schoolId, $import);

                return $this->payloadForImportStudent($import, $linkedUser);
            }
        }
        if (str_contains($memberTypeLabel, 'Eltern')) {
            $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);
            if (! $activeSchoolyearId) {
                return null;
            }

            return $this->payloadForParentContact($schoolId, $activeSchoolyearId, ['name' => $entry['name'] ?? 'Erziehungsberechtigte:r', 'email' => $entry['email'] ?? null, 'phone' => $entry['phone'] ?? null, 'schoolclass' => $entry['schoolclass'] ?? null, 'children_label' => $entry['children_label'] ?? null]);
        }
        if ($userId > 0) {
            $user = User::query()->where('school_id', $schoolId)->where('id', $userId)->first(['id', 'school_id', 'schoolyear_id', 'last_name', 'first_name', 'email', 'phone', 'schoolclass']);

            return $user ? $this->payloadForUserSource($user, $memberTypeLabel !== '' ? $memberTypeLabel : 'Benutzer') : null;
        }
        if (str_contains($memberTypeLabel, 'Lehrer:in')) {
            $teacher = Teacher::query()->where('school_id', $schoolId)->whereNotNull('email')->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalizeEmail((string) ($entry['email'] ?? ''))])->first(['id', 'school_id', 'last_name', 'first_name', 'email', 'short']);

            return $teacher ? $this->payloadForTeacherSource($teacher, $this->linkedUserByEmail($schoolId, $teacher->email)) : null;
        }

        return null;
    }

    private function syncOwnTeachingCourseGroups($authUser, int $schoolId, int $actorUserId): void
    {
        if (! Schema::hasTable('teaching_courses') || ! Schema::hasTable('teaching_course_students')) {
            return;
        }
        $schoolyearId = $authUser->schoolyear_id ? (int) $authUser->schoolyear_id : null;
        $coursesQuery = TeachingCourse::query()->where('school_id', $schoolId)->where('user_id', (int) $authUser->id)->with(['teachingCourseStudents:id,teaching_course_id,user_id,import116_id', 'teachingCourseStudents.user:id,school_id,schoolyear_id,import116_id,last_name,first_name,email,phone,schoolclass', 'teachingCourseStudents.import116:id,school_id,schoolyear_id,class,user_id,last_name,first_name,email,phone_1,phone_2,mother_name,mother_email,mother_phone_1,mother_phone_2,father_name,father_email,father_phone_1,father_phone_2'])->orderByRaw('LOWER(title)')->orderBy('id');
        if ($schoolyearId) {
            $coursesQuery->where('schoolyear_id', $schoolyearId);
        }
        $courses = $coursesQuery->get(['id', 'school_id', 'schoolyear_id', 'user_id', 'title', 'classes']);
        $courseIds = $courses->pluck('id')->map(fn ($id) => (int) $id)->values();
        $automaticCourseGroups = UserGroup::query()->where('school_id', $schoolId)->where('type', UserGroup::TYPE_OWN)->whereNotNull('teaching_course_id')->where('created_by_user_id', (int) $authUser->id)->get();
        $staleGroupIds = $automaticCourseGroups->reject(fn (UserGroup $group) => $courseIds->contains((int) $group->teaching_course_id))->pluck('id')->map(fn ($id) => (int) $id)->values();
        if ($staleGroupIds->isNotEmpty()) {
            UserGroup::query()->whereIn('id', $staleGroupIds)->delete();
        }
        $activeImportSchoolyearId = $this->activeImportSchoolyearId($schoolId);
        foreach ($courses as $course) {
            $studentPayloads = $this->automaticCourseStudentPayloads($course, $schoolId);
            $studentGroup = $this->firstAutomaticOwnCourseGroup((int) $course->id, UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS);
            if (! $studentGroup) {
                $studentGroup = UserGroup::query()->create(['school_id' => $schoolId, 'type' => UserGroup::TYPE_OWN, 'name' => $this->courseGroupName($course), 'description' => $this->courseGroupDescription($course), 'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null, 'teaching_course_id' => (int) $course->id, 'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS]);
            } else {
                $studentGroup->fill(['school_id' => $schoolId, 'type' => UserGroup::TYPE_OWN, 'name' => $this->courseGroupName($course), 'description' => $this->courseGroupDescription($course), 'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null, 'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS]);
                if ($studentGroup->isDirty()) {
                    $studentGroup->save();
                }
            }
            $this->syncGroupMembersFromPayloads($studentGroup, $studentPayloads, $actorUserId);
            $parentGroup = $this->firstAutomaticOwnCourseGroup((int) $course->id, UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS);
            if (! $parentGroup) {
                $parentGroup = UserGroup::query()->create(['school_id' => $schoolId, 'type' => UserGroup::TYPE_OWN, 'name' => $this->parentCourseGroupName($course), 'description' => $this->parentCourseGroupDescription($course), 'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null, 'teaching_course_id' => (int) $course->id, 'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS]);
            } else {
                $parentGroup->fill(['school_id' => $schoolId, 'type' => UserGroup::TYPE_OWN, 'name' => $this->parentCourseGroupName($course), 'description' => $this->parentCourseGroupDescription($course), 'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null, 'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS]);
                if ($parentGroup->isDirty()) {
                    $parentGroup->save();
                }
            }
            $parentImportIds = $course->teachingCourseStudents->flatMap(function ($courseStudent) {
                $ids = [];
                if ($courseStudent->import116) {
                    $ids[] = (int) $courseStudent->import116->id;
                }
                if ((int) ($courseStudent->user?->import116_id ?? 0) > 0) {
                    $ids[] = (int) $courseStudent->user->import116_id;
                }

                return $ids;
            })->filter(fn ($id) => (int) $id > 0)->unique()->values();
            $parentImportRows = $parentImportIds->isEmpty() ? collect() : Import116::query()->where('school_id', $schoolId)->whereIn('id', $parentImportIds->all())->get(['id', 'schoolyear_id', 'class', 'user_id', 'last_name', 'first_name', 'mother_name', 'mother_email', 'mother_phone_1', 'mother_phone_2', 'father_name', 'father_email', 'father_phone_1', 'father_phone_2']);
            $usersByImportId = $parentImportIds->isEmpty() ? collect() : User::query()->where('school_id', $schoolId)->whereNotNull('import116_id')->whereIn('import116_id', $parentImportIds->all())->get(['id', 'import116_id'])->keyBy(fn (User $user) => (int) $user->import116_id);
            $parentPayloads = $this->buildParentContacts($parentImportRows, $usersByImportId, false)->map(fn (array $contact) => $this->payloadForParentContact($schoolId, $activeImportSchoolyearId ?: (int) $course->schoolyear_id, $contact))->values();
            $this->syncGroupMembersFromPayloads($parentGroup, $parentPayloads, $actorUserId);
        }
    }

    private function repairMissingImportUserLinksByEmail(int $schoolId): void
    {
        if (! Schema::hasTable('import116') || ! Schema::hasTable('users')) {
            return;
        }
        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)->whereNotNull('email')->whereRaw('TRIM(email) <> ?', [''])->orderByDesc('id')->get(['id', 'schoolyear_id', 'class', 'email', 'user_id']);
        if ($importRows->isEmpty()) {
            return;
        }
        $unlinkedUsers = User::query()->where('school_id', $schoolId)->whereNull('import116_id')->whereNotNull('email')->whereRaw('TRIM(email) <> ?', [''])->orderByDesc('id')->get(['id', 'schoolclass', 'email']);
        if ($unlinkedUsers->isEmpty()) {
            return;
        }
        $usersByEmail = $unlinkedUsers->groupBy(fn (User $user) => mb_strtolower(trim((string) ($user->email ?? ''))))->map(fn ($rows) => $rows->values());
        foreach ($importRows as $importRow) {
            $normalizedEmail = mb_strtolower(trim((string) ($importRow->email ?? '')));
            if ($normalizedEmail === '') {
                continue;
            }
            $candidateRows = $usersByEmail->get($normalizedEmail, collect());
            if ($candidateRows->isEmpty()) {
                continue;
            }
            $preferredCandidate = $candidateRows->first();
            if (! $preferredCandidate) {
                continue;
            }
            $updatePayload = ['import116_id' => (int) $importRow->id];
            if (trim((string) ($preferredCandidate->schoolclass ?? '')) === '' && trim((string) ($importRow->class ?? '')) !== '') {
                $updatePayload['schoolclass'] = trim((string) $importRow->class);
            }
            User::query()->where('id', (int) $preferredCandidate->id)->whereNull('import116_id')->update($updatePayload);
            if ((int) ($importRow->user_id ?? 0) <= 0) {
                Import116::query()->where('id', (int) $importRow->id)->whereNull('user_id')->update(['user_id' => (int) $preferredCandidate->id]);
            }
            $remainingCandidates = $candidateRows->reject(fn (User $row) => (int) $row->id === (int) $preferredCandidate->id)->values();
            if ($remainingCandidates->isEmpty()) {
                $usersByEmail->forget($normalizedEmail);
            } else {
                $usersByEmail->put($normalizedEmail, $remainingCandidates);
            }
        }
    }

    private function defaultTeacherGroupName(): string
    {
        return 'Lehrer';
    }

    private function defaultAllSchoolMembersGroupName(): string
    {
        return 'Alle Schulmitglieder';
    }

    private function parentGroupName(string $baseGroupName): string
    {
        return trim($baseGroupName).' Eltern';
    }

    private function isParentSchoolGroup(UserGroup $group): bool
    {
        return (string) $group->type === UserGroup::TYPE_SCHOOL && str_ends_with($this->normalizeGroupName((string) $group->name), $this->normalizeGroupName(' Eltern'));
    }

    private function isAllSchoolMembersGroup(UserGroup $group): bool
    {
        return (string) $group->type === UserGroup::TYPE_SCHOOL && $this->normalizeGroupName((string) $group->name) === $this->normalizeGroupName($this->defaultAllSchoolMembersGroupName());
    }

    /**
     * @param  Collection<int, Import116>  $rows
     * @param  Collection<int, User>  $usersByImportId
     * @return Collection<int, array<string, mixed>>
     */
    private function buildParentContacts(Collection $rows, Collection $usersByImportId, bool $registeredStudentsOnly): Collection
    {
        $contacts = [];
        foreach ($rows as $row) {
            $hasRegisteredStudent = (int) ($row->user_id ?? 0) > 0 || $usersByImportId->has((int) $row->id);
            if ($registeredStudentsOnly && ! $hasRegisteredStudent) {
                continue;
            }
            $studentName = trim((string) (($row->last_name ?? '').' '.($row->first_name ?? '')));
            $schoolclass = trim((string) ($row->class ?? ''));
            foreach ($this->parentContactsFromImportRow($row) as $contact) {
                $key = $this->parentContactKey($contact);
                if (! isset($contacts[$key])) {
                    $contacts[$key] = ['id' => $key, 'name' => $contact['name'], 'email' => $contact['email'] !== '' ? $contact['email'] : 'Keine E-Mail', 'schoolclass' => $schoolclass, 'phone' => $contact['phone'], 'children' => [], 'classes' => []];
                }
                if ($studentName !== '') {
                    $contacts[$key]['children'][(int) $row->id] = ['id' => (int) $row->id, 'name' => $studentName, 'last_name' => trim((string) ($row->last_name ?? '')), 'first_name' => trim((string) ($row->first_name ?? '')), 'schoolclass' => $schoolclass];
                }
                if ($schoolclass !== '') {
                    $contacts[$key]['classes'][$schoolclass] = $schoolclass;
                }
            }
        }

        return collect($contacts)->map(function (array $contact) {
            $children = collect($contact['children'])->sort(function (array $left, array $right): int {
                $lastNameComparison = strnatcasecmp(trim((string) ($left['last_name'] ?? '')), trim((string) ($right['last_name'] ?? '')));
                if ($lastNameComparison !== 0) {
                    return $lastNameComparison;
                }

                return strnatcasecmp(trim((string) ($left['first_name'] ?? '')), trim((string) ($right['first_name'] ?? '')));
            })->values()->all();
            $classes = array_values($contact['classes']);
            sort($classes, SORT_NATURAL | SORT_FLAG_CASE);
            $contact['children'] = $children;
            $contact['children_label'] = collect($children)->pluck('name')->implode(', ');
            $contact['schoolclass'] = implode(', ', $classes);
            unset($contact['classes']);

            return $contact;
        })->sortBy([fn (array $contact) => mb_strtolower(trim((string) ($contact['name'] ?? ''))), fn (array $contact) => mb_strtolower(trim((string) ($contact['email'] ?? '')))])->values();
    }

    /**
     * @return array<int, array{name: string, email: string, phone: string}>
     */
    private function parentContactsFromImportRow(Import116 $row): array
    {
        $contacts = [];
        foreach (['mother', 'father'] as $prefix) {
            $name = trim((string) ($row->{$prefix.'_name'} ?? ''));
            $email = trim((string) ($row->{$prefix.'_email'} ?? ''));
            $phones = collect([trim((string) ($row->{$prefix.'_phone_1'} ?? '')), trim((string) ($row->{$prefix.'_phone_2'} ?? ''))])->filter(fn (string $value) => $value !== '')->unique()->values()->all();
            if ($name === '' && $email === '' && empty($phones)) {
                continue;
            }
            $contacts[] = ['name' => $name !== '' ? $name : ($email !== '' ? $email : 'Erziehungsberechtigte:r'), 'email' => $email, 'phone' => implode(' / ', $phones)];
        }

        return $contacts;
    }

    /**
     * @param  array{name: string, email: string, phone: string}  $contact
     */
    private function parentContactKey(array $contact): string
    {
        $email = mb_strtolower(trim((string) ($contact['email'] ?? '')));
        if ($email !== '') {
            return 'email:'.$email;
        }

        return 'fallback:'.md5(implode('|', [mb_strtolower(trim((string) ($contact['name'] ?? ''))), mb_strtolower(trim((string) ($contact['phone'] ?? '')))]));
    }

    /**
     * @return array<int, string>
     */
    private function adminRoleNames(): array
    {
        return ['super_admin', 'admin', 'materials_admin', 'materials_moderator'];
    }

    private function allSchoolMemberTypeSortOrder(string $label): int
    {
        return match ($label) {
            'Schüler:in' => 1,
            'Eltern' => 2,
            'Lehrer:in' => 3,
            'Admin' => 4,
            default => 99,
        };
    }

    private function firstAutomaticOwnCourseGroup(int $courseId, string $groupType): ?UserGroup
    {
        return UserGroup::query()->where('type', UserGroup::TYPE_OWN)->where('teaching_course_id', $courseId)->where(function ($query) use ($groupType) {
            if ($groupType === UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS) {
                $query->where('teaching_course_group_type', UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS)->orWhereNull('teaching_course_group_type');

                return;
            }
            $query->where('teaching_course_group_type', $groupType);
        })->orderBy('id')->first();
    }

    private function courseGroupName(TeachingCourse $course): string
    {
        $title = trim((string) ($course->title ?? ''));
        $classesLabel = $this->courseClassesLabel($course);
        if ($title === '' && $classesLabel === '') {
            return 'Kurs';
        }
        if ($title === '') {
            return $classesLabel;
        }
        if ($classesLabel === '') {
            return $title;
        }

        return sprintf('%s (%s)', $title, $classesLabel);
    }

    private function courseGroupDescription(TeachingCourse $course): ?string
    {
        return null;
    }

    private function parentCourseGroupName(TeachingCourse $course): string
    {
        return trim($this->courseGroupName($course)).' Eltern';
    }

    private function parentCourseGroupDescription(TeachingCourse $course): ?string
    {
        return null;
    }

    private function courseClassesLabel(TeachingCourse $course): string
    {
        $classes = collect(is_array($course->classes) ? $course->classes : [])->map(fn ($value) => trim((string) $value))->filter(fn (string $value) => $value !== '')->unique()->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))->values()->all();

        return implode(', ', $classes);
    }

    private function isTeacherGroupName(string $normalizedGroupName): bool
    {
        return in_array($normalizedGroupName, [$this->normalizeGroupName($this->defaultTeacherGroupName()), $this->normalizeGroupName('Teacher')], true);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function teacherSourceMembers(int $schoolId, ?UserGroup $group = null): Collection
    {
        $existingMembers = $group ? $this->existingStoredGroupMembers($group) : collect();
        $teacherRowsByEmail = collect();
        if (Schema::hasTable('teachers')) {
            $teacherRowsByEmail = Teacher::query()->where('school_id', $schoolId)->whereNotNull('email')->whereRaw('TRIM(email) <> ?', [''])->orderBy('last_name')->orderBy('first_name')->orderBy('email')->get(['id', 'last_name', 'first_name', 'email', 'short'])->mapWithKeys(function (Teacher $teacher) {
                $normalizedEmail = mb_strtolower(trim((string) ($teacher->email ?? '')));

                return $normalizedEmail !== '' ? [$normalizedEmail => $teacher] : [];
            });
        }
        $matchedUsersByEmail = $teacherRowsByEmail->isEmpty() ? collect() : User::query()->where('school_id', $schoolId)->whereNotNull('email')->whereRaw('TRIM(email) <> ?', [''])->whereIn(DB::raw('LOWER(TRIM(email))'), $teacherRowsByEmail->keys()->all())->get(['id', 'last_name', 'first_name', 'email', 'schoolclass'])->keyBy(fn (User $user) => mb_strtolower(trim((string) ($user->email ?? ''))));
        $entries = collect($teacherRowsByEmail->all())->map(function (Teacher $teacher, string $normalizedEmail) use ($matchedUsersByEmail, $existingMembers) {
            $user = $matchedUsersByEmail->get($normalizedEmail);
            $teacherName = trim((string) (($teacher->last_name ?? '').' '.($teacher->first_name ?? '')));
            $userName = $user ? trim((string) (($user->last_name ?? '').' '.($user->first_name ?? ''))) : '';
            $userId = $user ? (int) $user->id : null;
            $storedKey = $this->storedGroupMemberKey(UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER, $this->teacherListTeacherMemberRef((int) $teacher->id));

            return ['id' => 'teacher-email:'.$normalizedEmail, 'user_id' => $userId, 'linked_user_id' => $userId, 'import116_id' => null, 'name' => $teacherName !== '' ? $teacherName : ($userName !== '' ? $userName : (string) ($teacher->email ?? 'Lehrer:in')), 'email' => $teacher->email ?: $user?->email, 'schoolclass' => $user?->schoolclass, 'member_provider' => UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER, 'member_ref' => $this->teacherListTeacherMemberRef((int) $teacher->id), 'member_type_label' => 'Lehrer:in', 'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE, 'linked_user_status' => $userId ? UserGroupMember::LINKED_USER_STATUS_LINKED : UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE, 'has_user_account' => (bool) $userId, 'already_member' => $existingMembers->has($storedKey)];
        })->values();
        $roleEntries = User::query()->where('school_id', $schoolId)->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))->get(['id', 'last_name', 'first_name', 'email', 'schoolclass'])->reject(function (User $user) use ($teacherRowsByEmail) {
            $normalizedEmail = mb_strtolower(trim((string) ($user->email ?? '')));

            return $normalizedEmail !== '' && $teacherRowsByEmail->has($normalizedEmail);
        })->map(function (User $user) use ($existingMembers) {
            $name = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
            $userId = (int) $user->id;
            $storedKey = $this->storedGroupMemberKey(UserGroupMember::PROVIDER_USER, $this->userMemberRef($userId));

            return ['id' => 'teacher-role:'.$userId, 'user_id' => $userId, 'linked_user_id' => $userId, 'import116_id' => null, 'name' => $name !== '' ? $name : (string) ($user->email ?? 'Lehrer:in'), 'email' => $user->email, 'schoolclass' => $user->schoolclass, 'member_provider' => UserGroupMember::PROVIDER_USER, 'member_ref' => $this->userMemberRef($userId), 'member_type_label' => 'Lehrer:in', 'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE, 'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_LINKED, 'has_user_account' => true, 'already_member' => $existingMembers->has($storedKey)];
        })->values()->toBase();

        return $entries->merge($roleEntries)->sortBy([fn (array $entry) => mb_strtolower(trim((string) ($entry['name'] ?? ''))), fn (array $entry) => mb_strtolower(trim((string) ($entry['email'] ?? '')))])->values();
    }

    /**
     * @return array{
     *     by_class: array<string, array{name: string, import_ids: array<int, int>}>,
     *     by_family: array<string, array{name: string, import_ids: array<int, int>}>
     * }
     */
    private function buildImportClassGroupMappings(int $schoolId): array
    {
        $result = ['by_class' => [], 'by_family' => []];
        if (! Schema::hasTable('import116')) {
            return $result;
        }
        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)->whereNotNull('class')->orderByRaw('LOWER(class)')->orderBy('id')->get(['id', 'class']);
        if ($importRows->isEmpty()) {
            return $result;
        }
        $familiesWithVariants = [];
        foreach ($importRows as $row) {
            $className = trim((string) ($row->class ?? ''));
            if ($className === '') {
                continue;
            }
            $normalizedClassName = $this->normalizeGroupName($className);
            if (! isset($result['by_class'][$normalizedClassName])) {
                $result['by_class'][$normalizedClassName] = ['name' => $className, 'import_ids' => []];
            }
            $result['by_class'][$normalizedClassName]['import_ids'][] = (int) $row->id;
            $familyClassName = $this->detectCombinedClassFamilyName($className);
            if (! is_string($familyClassName) || $familyClassName === '') {
                continue;
            }
            $normalizedFamilyName = $this->normalizeGroupName($familyClassName);
            if (! isset($familiesWithVariants[$normalizedFamilyName])) {
                $familiesWithVariants[$normalizedFamilyName] = ['name' => $familyClassName, 'import_ids' => [], 'class_names' => []];
            }
            $familiesWithVariants[$normalizedFamilyName]['import_ids'][] = (int) $row->id;
            $familiesWithVariants[$normalizedFamilyName]['class_names'][$normalizedClassName] = true;
        }
        foreach ($result['by_class'] as $normalizedClassName => $classData) {
            $result['by_class'][$normalizedClassName]['import_ids'] = collect($classData['import_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        }
        foreach ($familiesWithVariants as $normalizedFamilyName => $familyData) {
            if (count($familyData['class_names']) < 2) {
                continue;
            }
            $result['by_family'][$normalizedFamilyName] = ['name' => (string) $familyData['name'], 'import_ids' => collect($familyData['import_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all()];
        }

        return $result;
    }

    private function detectCombinedClassFamilyName(string $className): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $className));
        if ($value === '') {
            return null;
        }
        $base = null;
        if (preg_match('/^(.+?)[\-_\/|:;]+.+$/u', $value, $matches) === 1) {
            $base = trim((string) ($matches[1] ?? ''));
        } else {
            $parts = preg_split('/\s+/u', $value) ?: [];
            if (count($parts) >= 2) {
                $base = trim((string) ($parts[0] ?? ''));
            } elseif (preg_match('/^(\d{1,2}[[:alpha:]]{1,2})([[:alpha:]]{1,3})$/u', preg_replace('/\s+/u', '', $value) ?: '', $matches) === 1) {
                $base = trim((string) ($matches[1] ?? ''));
            }
        }
        if (! is_string($base) || $base === '') {
            return null;
        }
        $base = preg_replace('/\s+/u', '', $base) ?: '';
        if ($base === '') {
            return null;
        }
        if (preg_match('/^\d{1,2}[[:alpha:]]{1,3}$/u', $base) !== 1) {
            return null;
        }

        return mb_strtoupper($base);
    }

    private function userMemberRef(int $userId): string
    {
        return 'user:'.$userId;
    }

    private function import116StudentMemberRef(int $importId): string
    {
        return 'import116.student:'.$importId;
    }

    private function teacherListTeacherMemberRef(int $teacherId): string
    {
        return 'teacher_list.teacher:'.$teacherId;
    }

    private function import116ParentContactMemberRef(int $schoolyearId, string $contactKey): string
    {
        return 'import116.parent_contact:'.$schoolyearId.':'.base64_encode($contactKey);
    }

    private function storedGroupMemberKey(string $provider, string $memberRef): string
    {
        return $provider.'|'.$memberRef;
    }

    /**
     * @param  array{member_provider:string,member_ref:string}  $payload
     */
    private function storedGroupMemberKeyFromPayload(array $payload): string
    {
        return $this->storedGroupMemberKey((string) $payload['member_provider'], (string) $payload['member_ref']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function groupMemberDeduplicationKeyFromPayload(array $payload): string
    {
        $linkedUserId = $this->payloadLinkedUserId($payload);
        if ($linkedUserId > 0) {
            return $this->groupMemberDeduplicationKeyFromLinkedUserId($linkedUserId);
        }

        return $this->storedGroupMemberKeyFromPayload($payload);
    }

    private function groupMemberDeduplicationKeyFromLinkedUserId(int $linkedUserId): string
    {
        return 'linked-user:'.$linkedUserId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadLinkedUserId(array $payload): int
    {
        if (isset($payload['linked_user_id']) && $payload['linked_user_id'] !== null) {
            return (int) $payload['linked_user_id'];
        }
        if (isset($payload['user_id']) && $payload['user_id'] !== null) {
            return (int) $payload['user_id'];
        }

        return 0;
    }

    private function normalizeEmail(?string $value): string
    {
        return mb_strtolower(trim((string) ($value ?? '')));
    }

    private function linkedUserByEmail(int $schoolId, ?string $email): ?User
    {
        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedEmail === '') {
            return null;
        }

        return User::query()->where('school_id', $schoolId)->whereNotNull('email')->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->orderBy('id')->first(['id', 'school_id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass']);
    }

    private function linkedUserForImportStudent(int $schoolId, Import116 $import): ?User
    {
        $directUserId = (int) ($import->user_id ?? 0);
        if ($directUserId > 0) {
            $directUser = User::query()->where('school_id', $schoolId)->where('id', $directUserId)->first(['id', 'school_id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass']);
            if ($directUser) {
                return $directUser;
            }
        }
        $linkedByImportId = User::query()->where('school_id', $schoolId)->where('import116_id', (int) $import->id)->orderBy('id')->first(['id', 'school_id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass']);
        if ($linkedByImportId) {
            return $linkedByImportId;
        }

        return $this->linkedUserByEmail($schoolId, $import->email);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForUserSource(User $user, string $memberTypeLabel = 'Benutzer'): array
    {
        $name = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));

        return ['member_provider' => UserGroupMember::PROVIDER_USER, 'member_ref' => $this->userMemberRef((int) $user->id), 'linked_user_id' => (int) $user->id, 'source_schoolyear_id' => $user->schoolyear_id ? (int) $user->schoolyear_id : null, 'display_name' => $name !== '' ? $name : $user->email ?? 'Benutzer', 'display_email' => $user->email, 'display_phone' => $user->phone, 'display_schoolclass' => $user->schoolclass, 'display_children_label' => null, 'member_type_label' => $memberTypeLabel, 'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE, 'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_LINKED, 'meta' => ['user_id' => (int) $user->id]];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForImportStudent(Import116 $import, ?User $linkedUser = null): array
    {
        $name = $linkedUser ? trim((string) (($linkedUser->last_name ?? '').' '.($linkedUser->first_name ?? ''))) : trim((string) (($import->last_name ?? '').' '.($import->first_name ?? '')));
        $email = $linkedUser?->email ?: $import->email;
        $schoolclass = $linkedUser?->schoolclass ?: $import->class;

        return ['member_provider' => UserGroupMember::PROVIDER_IMPORT116_STUDENT, 'member_ref' => $this->import116StudentMemberRef((int) $import->id), 'linked_user_id' => $linkedUser ? (int) $linkedUser->id : null, 'source_schoolyear_id' => $import->schoolyear_id ? (int) $import->schoolyear_id : null, 'display_name' => $name !== '' ? $name : ($email ?: 'Schüler:in'), 'display_email' => $email, 'display_phone' => trim((string) implode(' / ', collect([trim((string) ($import->phone_1 ?? '')), trim((string) ($import->phone_2 ?? ''))])->filter()->unique()->all())), 'display_schoolclass' => $schoolclass, 'display_children_label' => null, 'member_type_label' => 'Schüler:in', 'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE, 'linked_user_status' => $linkedUser ? UserGroupMember::LINKED_USER_STATUS_LINKED : UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE, 'meta' => ['import116_id' => (int) $import->id]];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForTeacherSource(Teacher $teacher, ?User $linkedUser = null): array
    {
        $teacherName = trim((string) (($teacher->last_name ?? '').' '.($teacher->first_name ?? '')));
        $userName = $linkedUser ? trim((string) (($linkedUser->last_name ?? '').' '.($linkedUser->first_name ?? ''))) : '';

        return ['member_provider' => UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER, 'member_ref' => $this->teacherListTeacherMemberRef((int) $teacher->id), 'linked_user_id' => $linkedUser ? (int) $linkedUser->id : null, 'source_schoolyear_id' => null, 'display_name' => $teacherName !== '' ? $teacherName : ($userName !== '' ? $userName : $teacher->email ?? 'Lehrer:in'), 'display_email' => $teacher->email ?: $linkedUser?->email, 'display_phone' => null, 'display_schoolclass' => $linkedUser?->schoolclass, 'display_children_label' => null, 'member_type_label' => 'Lehrer:in', 'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE, 'linked_user_status' => $linkedUser ? UserGroupMember::LINKED_USER_STATUS_LINKED : UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE, 'meta' => ['teacher_id' => (int) $teacher->id]];
    }

    /**
     * @param  array{name:string,email:string,phone:string,schoolclass?:string|null,children_label?:string|null,children?:array<int, array<string, mixed>>,id?:string}  $contact
     * @return array<string, mixed>
     */
    private function payloadForParentContact(int $schoolId, int $schoolyearId, array $contact): array
    {
        $linkedUser = $this->linkedUserByEmail($schoolId, $contact['email'] ?? null);
        $contactKey = $this->parentContactKey(['name' => $contact['name'] ?? '', 'email' => $contact['email'] ?? '', 'phone' => $contact['phone'] ?? '']);

        return ['member_provider' => UserGroupMember::PROVIDER_IMPORT116_PARENT_CONTACT, 'member_ref' => $this->import116ParentContactMemberRef($schoolyearId, $contactKey), 'linked_user_id' => $linkedUser ? (int) $linkedUser->id : null, 'source_schoolyear_id' => $schoolyearId, 'display_name' => $contact['name'] ?? 'Erziehungsberechtigte:r', 'display_email' => $contact['email'] ?? null, 'display_phone' => $contact['phone'] ?? null, 'display_schoolclass' => $contact['schoolclass'] ?? null, 'display_children_label' => $contact['children_label'] ?? null, 'children' => $contact['children'] ?? [], 'member_type_label' => 'Eltern', 'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE, 'linked_user_status' => $linkedUser ? UserGroupMember::LINKED_USER_STATUS_LINKED : UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE, 'meta' => ['contact_key' => $contactKey]];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payloads
     * @return array{assigned_count:int,new_count:int}
     */
    private function storeGroupMembers(UserGroup $group, Collection $payloads, int $actorUserId): array
    {
        $payloads = $payloads->filter(fn (array $payload) => trim((string) ($payload['member_provider'] ?? '')) !== '' && trim((string) ($payload['member_ref'] ?? '')) !== '')->keyBy(fn (array $payload) => $this->groupMemberDeduplicationKeyFromPayload($payload));
        if ($payloads->isEmpty()) {
            return ['assigned_count' => 0, 'new_count' => 0];
        }
        $existingMembers = $group->groupMembers()->get()->keyBy(fn (UserGroupMember $member) => $this->storedGroupMemberKey((string) $member->member_provider, (string) $member->member_ref));
        $existingMembersByLinkedUser = $group->groupMembers()->whereNotNull('linked_user_id')->get()->keyBy(fn (UserGroupMember $member) => $this->groupMemberDeduplicationKeyFromLinkedUserId((int) $member->linked_user_id));
        $newCount = 0;
        foreach ($payloads as $payload) {
            $storedKey = $this->storedGroupMemberKeyFromPayload($payload);
            $linkedUserDeduplicationKey = $this->groupMemberDeduplicationKeyFromPayload($payload);
            $attributes = ['school_id' => (int) $group->school_id, 'member_provider' => (string) $payload['member_provider'], 'member_ref' => (string) $payload['member_ref'], 'linked_user_id' => isset($payload['linked_user_id']) && $payload['linked_user_id'] !== null ? (int) $payload['linked_user_id'] : null, 'source_schoolyear_id' => isset($payload['source_schoolyear_id']) && $payload['source_schoolyear_id'] !== null ? (int) $payload['source_schoolyear_id'] : null, 'display_name' => $payload['display_name'] ?? null, 'display_email' => $payload['display_email'] ?? null, 'display_phone' => $payload['display_phone'] ?? null, 'display_schoolclass' => $payload['display_schoolclass'] ?? null, 'display_children_label' => $payload['display_children_label'] ?? null, 'member_type_label' => $payload['member_type_label'] ?? null, 'source_status' => $payload['source_status'] ?? UserGroupMember::SOURCE_STATUS_ACTIVE, 'linked_user_status' => $payload['linked_user_status'] ?? UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE, 'meta' => $payload['meta'] ?? null, 'added_by_user_id' => $actorUserId > 0 ? $actorUserId : null];
            /** @var UserGroupMember|null $existingMember */
            $existingMember = $existingMembers->get($storedKey);
            if ($existingMember) {
                $existingMember->fill($attributes);
                if ($existingMember->isDirty()) {
                    $existingMember->save();
                }

                continue;
            }
            if ($linkedUserDeduplicationKey !== $storedKey && $existingMembersByLinkedUser->has($linkedUserDeduplicationKey)) {
                continue;
            }
            $group->groupMembers()->create($attributes);
            $newCount++;
        }

        return ['assigned_count' => $payloads->count(), 'new_count' => $newCount];
    }

    /**
     * @return Collection<string, UserGroupMember>
     */
    private function existingStoredGroupMembers(UserGroup $group): Collection
    {
        $members = collect();
        foreach ($group->groupMembers()->get() as $member) {
            $members->put($this->storedGroupMemberKey((string) $member->member_provider, (string) $member->member_ref), $member);
            if ((int) $member->linked_user_id > 0) {
                $members->put($this->groupMemberDeduplicationKeyFromLinkedUserId((int) $member->linked_user_id), $member);
            }
        }

        return $members;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assignableTeacherPayloads(int $schoolId, string $search, int $limit): Collection
    {
        $teacherRows = Teacher::query()->where('school_id', $schoolId)->when($search !== '', function ($query) use ($search) {
            $query->where(function ($builder) use ($search) {
                $builder->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('short', 'like', "%{$search}%");
            });
        })->orderBy('last_name')->orderBy('first_name')->orderBy('email')->limit($limit)->get(['id', 'school_id', 'last_name', 'first_name', 'email', 'short']);
        $teacherPayloads = $teacherRows->map(fn (Teacher $teacher) => $this->payloadForTeacherSource($teacher, $this->linkedUserByEmail($schoolId, $teacher->email)))->values();
        $matchedTeacherEmails = $teacherRows->map(fn (Teacher $teacher) => $this->normalizeEmail($teacher->email))->filter(fn (string $email) => $email !== '')->unique()->values()->all();
        $roleTeacherPayloads = User::query()->where('school_id', $schoolId)->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))->when($search !== '', function ($query) use ($search) {
            $query->where(function ($builder) use ($search) {
                $builder->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        })->orderBy('last_name')->orderBy('first_name')->orderBy('email')->limit($limit)->get(['id', 'school_id', 'schoolyear_id', 'last_name', 'first_name', 'email', 'phone', 'schoolclass'])->reject(fn (User $user) => in_array($this->normalizeEmail($user->email), $matchedTeacherEmails, true))->map(fn (User $user) => $this->payloadForUserSource($user, 'Lehrer:in'))->values();

        return $teacherPayloads->concat($roleTeacherPayloads)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payloads
     */
    private function syncGroupMembersFromPayloads(UserGroup $group, Collection $payloads, int $actorUserId): void
    {
        $payloads = $payloads->filter(fn (array $payload) => trim((string) ($payload['member_provider'] ?? '')) !== '' && trim((string) ($payload['member_ref'] ?? '')) !== '')->values();
        $this->storeGroupMembers($group, $payloads, $actorUserId);
        $validKeys = $payloads->map(fn (array $payload) => $this->storedGroupMemberKeyFromPayload($payload))->unique()->values()->all();
        $staleIds = $group->groupMembers()->get()->reject(fn (UserGroupMember $member) => in_array($this->storedGroupMemberKey((string) $member->member_provider, (string) $member->member_ref), $validKeys, true))->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! empty($staleIds)) {
            $group->groupMembers()->whereIn('id', $staleIds)->delete();
        }
    }
}
