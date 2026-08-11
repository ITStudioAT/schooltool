<?php

namespace App\Http\Resources\Admin;

use App\Models\SchoolTool;
use App\Services\SchoolToolModuleStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolToolResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $moduleStatusService = app(SchoolToolModuleStatusService::class);
        $defaultAttributes = $moduleStatusService->defaultAttributes();
        $moduleVisibilityFields = collect($moduleStatusService->moduleVisibilityFields())
            ->mapWithKeys(fn (string $field): array => [$field => (bool) ($this->{$field} ?? $defaultAttributes[$field])])
            ->all();

        return [
            'id' => $this->id,
            'active_schoolyear_id' => $this->active_schoolyear_id,
            'module_rows' => $moduleStatusService->configurableModuleRows(),
            ...$moduleVisibilityFields,
            'students_timetables_admin_version' => SchoolTool::normalizeStudentsTimetablesAdminVersion(
                $this->students_timetables_admin_version,
            ),
            'tutoring_student_must_be_confirmed' => $this->tutoring_student_must_be_confirmed ? true : false,
            'tutoring_confirmer_email' => $this->tutoring_confirmer_email,
            'tutoring_max_offers_per_student' => $this->tutoring_max_offers_per_student,
            'may_visible_for_other_schools' => $this->may_visible_for_other_schools ? true : false,
        ];
    }
}
