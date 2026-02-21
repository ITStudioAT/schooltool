<?php

namespace App\Http\Resources\Admin;

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
        return [
            'id' => $this->id,
            'long_name' => $this->long_name,
            'short_name' => $this->short_name,
            'logo' => $this->logo,
            'email' => $this->email,
            'licences' => $this->relationLoaded('licences')
                ? $this->licences->map(function ($licence) {
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
                    ];
                })->values()
                : [],
            'is_selectable' => $this->is_selectable ? true : false,
        ];
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
