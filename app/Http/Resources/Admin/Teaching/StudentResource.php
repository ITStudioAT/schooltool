<?php

namespace App\Http\Resources\Admin\Teaching;

use App\Jobs\Teaching\Import116Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPlaceholder = Import116Job::isPlaceholderEmail($this->email);

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'sex' => $this->sex,
            'schoolclass' => $this->schoolclass,
            'email' => $isPlaceholder ? null : $this->email,
            'email_is_placeholder' => $isPlaceholder,
            'phone' => $this->phone,
        ];
    }
}
