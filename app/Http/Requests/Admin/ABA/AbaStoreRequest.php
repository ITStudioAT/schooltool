<?php

namespace App\Http\Requests\Admin\ABA;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AbaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $schoolId = (int) (Auth::user()?->school_id ?? 0);

        return [
            'data.title' => ['required', 'string', 'max:255'],
            'data.student_name' => ['required', 'string', 'max:255'],
            'data.schoolyear_id' => [
                'nullable',
                'integer',
                Rule::exists('schoolyears', 'id')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'data.evaluated_on' => ['nullable', 'date'],
        ];
    }
}
