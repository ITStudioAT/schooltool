<?php

namespace App\Http\Resources\Admin;

use App\Services\LicenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $licenceModel = $this->pivot?->licence_model ?? app(LicenceService::class)->editableLicenceConfiguration($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'long_name' => $this->long_name,
            'valid_until' => $this->pivot?->valid_until,
            'price_per_year' => $this->normalizedPricePerYear(),
            'start_day_month' => $this->formattedDayMonth($this->start_day_month),
            'end_day_month' => $this->formattedDayMonth($this->end_day_month),
            'school_licence_id' => $this->pivot?->id,
            'licence_model' => $licenceModel,
            'licence_schema_version' => $this->licence_schema_version,
            'is_selectable' => $this->is_selectable ? true : false,
        ];
    }

    protected function normalizedPricePerYear(): int|string|null
    {
        if ($this->price_per_year === null) {
            return null;
        }

        $normalized = trim((string) $this->price_per_year);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[1-9][0-9]*[.,]0+$/', $normalized) === 1) {
            return (int) strtok(str_replace(',', '.', $normalized), '.');
        }

        if (preg_match('/^[1-9][0-9]*$/', $normalized) === 1) {
            return (int) $normalized;
        }

        return $normalized;
    }

    protected function formattedDayMonth(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $normalized, $matches) === 1) {
            return sprintf('%s.%s.', $matches[2], $matches[1]);
        }

        return $normalized;
    }
}
