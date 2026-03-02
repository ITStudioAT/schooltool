<?php

namespace App\Http\Resources\Admin\Teaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hour' => (int) $this->hour,
            'from' => $this->formatTime((string) $this->from),
            'until' => $this->formatTime((string) $this->until),
        ];
    }

    private function formatTime(string $value): string
    {
        return substr($value, 0, 5);
    }
}
