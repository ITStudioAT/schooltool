<?php

namespace App\Http\Resources\Admin\Teaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Import116Resource extends JsonResource
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
            'school_id' => $this->school_id,
            'class' => $this->class,
            'student_code' => $this->student_code,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'email' => $this->email,
            'phone_1' => $this->phone_1,
            'phone_2' => $this->phone_2,
            'sex' => $this->sex,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'age' => $this->birth_date?->age,
            'mother_name' => $this->mother_name,
            'mother_email' => $this->mother_email,
            'mother_phone_1' => $this->mother_phone_1,
            'mother_phone_2' => $this->mother_phone_2,
            'father_name' => $this->father_name,
            'father_email' => $this->father_email,
            'father_phone_1' => $this->father_phone_1,
            'father_phone_2' => $this->father_phone_2,
            'import_date' => $this->import_date,
            'exists_date' => $this->exists_date,
        ];
    }
}
