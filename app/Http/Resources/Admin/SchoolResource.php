<?php

namespace App\Http\Resources\Admin;

use App\Models\SchoolUserLicence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userLicenceCounts = $this->relationLoaded('licences') && $this->licences->isNotEmpty()
            ? SchoolUserLicence::query()
                ->where('school_id', $this->id)
                ->selectRaw('licence_id, assignment_type, COUNT(*) as count')
                ->groupBy('licence_id', 'assignment_type')
                ->get()
                ->groupBy('licence_id')
            : collect();

        return [
            'id' => $this->id,
            'long_name' => $this->long_name,
            'short_name' => $this->short_name,
            'logo' => $this->logo,
            'email' => $this->email,
            'licences' => $this->relationLoaded('licences')
                ? $this->licences->map(function ($licence) use ($userLicenceCounts) {
                    $licenceModel = $this->decodeLicenceModel($licence->pivot?->licence_model);
                    $schoolLicenceRequired = true;
                    if (is_array($licenceModel) && array_key_exists('school_licence_required', $licenceModel)) {
                        $schoolLicenceRequired = (bool) $licenceModel['school_licence_required'];
                    }

                    return [
                        'id' => $licence->id,
                        'name' => $licence->name,
                        'long_name' => $licence->long_name,
                        'valid_until' => $licence->pivot?->valid_until,
                        'school_licence_id' => $licence->pivot?->id,
                        'school_licence_required' => $schoolLicenceRequired,
                        'licence_model' => $licenceModel,
                        'school_licence_enabled' => (bool) $licence->school_licence_enabled,
                        'charged_school_price' => $licence->pivot?->charged_school_price,
                        'extra_storage_units' => $licence->pivot?->extra_storage_units,
                        'extra_storage_unit_price' => $licence->pivot?->extra_storage_unit_price,
                        'start_day_month' => $this->formatDayMonth($licence->start_day_month),
                        'end_day_month' => $this->formatDayMonth($licence->end_day_month),
                        'school_price_per_year' => $licence->school_price_per_year,
                        'school_included_storage_gb' => $licence->school_included_storage_gb,
                        'school_extra_storage_step_gb' => $licence->school_extra_storage_step_gb,
                        'school_extra_storage_step_price' => $licence->school_extra_storage_step_price,
                        'admin_licence_enabled' => (bool) $licence->admin_licence_enabled,
                        'admin_price_per_year' => $licence->admin_price_per_year,
                        'admin_included_storage_gb' => $licence->admin_included_storage_gb,
                        'admin_extra_storage_step_gb' => $licence->admin_extra_storage_step_gb,
                        'admin_extra_storage_step_price' => $licence->admin_extra_storage_step_price,
                        'admin_licence_count' => (int) ($userLicenceCounts->get($licence->id)?->firstWhere('assignment_type', 'admin')?->count ?? 0),
                        'user_licence_enabled' => (bool) $licence->user_licence_enabled,
                        'user_price_per_year' => $licence->user_price_per_year,
                        'user_included_storage_gb' => $licence->user_included_storage_gb,
                        'user_extra_storage_step_gb' => $licence->user_extra_storage_step_gb,
                        'user_extra_storage_step_price' => $licence->user_extra_storage_step_price,
                        'user_licence_count' => (int) ($userLicenceCounts->get($licence->id)?->firstWhere('assignment_type', 'user')?->count ?? 0),
                    ];
                })->values()
                : [],
            'is_selectable' => $this->is_selectable ? true : false,
        ];
    }

    private function formatDayMonth(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', trim($value), $matches) === 1) {
            return sprintf('%s.%s.', $matches[2], $matches[1]);
        }

        return $value;
    }

    private function decodeLicenceModel($licenceModel): ?array
    {
        if (is_array($licenceModel)) {
            return $licenceModel;
        }

        if (! is_string($licenceModel) || trim($licenceModel) === '') {
            return null;
        }

        $decoded = json_decode($licenceModel, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
