<?php

namespace App\Http\Requests\Admin;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SynchronizeCloudwaysSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) $this->user()?->hasRole('super_admin');
    }

    public function rules(): array
    {
        $school = $this->route('school');
        $schoolName = $school instanceof School ? (string) $school->long_name : '';

        return [
            'confirmation' => ['required', 'string', Rule::in([$schoolName])],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.in' => 'Zur Bestätigung muss der vollständige Schulname eingegeben werden.',
        ];
    }
}
