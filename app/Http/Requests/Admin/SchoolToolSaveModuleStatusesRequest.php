<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SchoolToolSaveModuleStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.id' => [
                'required',
                'integer',
                Rule::exists('school_tools', 'id')
                    ->where('school_id', Auth::user()?->school_id),
            ],
            'data.register_visible_admin' => ['required', 'boolean'],
            'data.register_visible_user' => ['required', 'boolean'],
            'data.register_user_test_mode' => ['required', 'boolean'],
            'data.register_user_comming_soon' => ['required', 'boolean'],
            'data.tutoring_visible_admin' => ['required', 'boolean'],
            'data.tutoring_visible_user' => ['required', 'boolean'],
            'data.tutoring_user_test_mode' => ['required', 'boolean'],
            'data.tutoring_user_comming_soon' => ['required', 'boolean'],
            'data.teaching_visible_admin' => ['required', 'boolean'],
            'data.teaching_visible_user' => ['required', 'boolean'],
            'data.teaching_user_test_mode' => ['required', 'boolean'],
            'data.teaching_user_comming_soon' => ['required', 'boolean'],
            'data.materials_visible_admin' => ['required', 'boolean'],
            'data.materials_visible_user' => ['required', 'boolean'],
            'data.materials_user_test_mode' => ['required', 'boolean'],
            'data.materials_user_comming_soon' => ['required', 'boolean'],
            'data.restaurant_visible_admin' => ['required', 'boolean'],
            'data.restaurant_visible_user' => ['required', 'boolean'],
            'data.restaurant_user_test_mode' => ['required', 'boolean'],
            'data.restaurant_user_comming_soon' => ['required', 'boolean'],
            'data.aba_visible_admin' => ['required', 'boolean'],
            'data.aba_visible_user' => ['required', 'boolean'],
            'data.aba_user_test_mode' => ['required', 'boolean'],
            'data.aba_user_comming_soon' => ['required', 'boolean'],
            'data.students_timetables_visible_admin' => ['sometimes', 'boolean'],
            'data.students_timetables_visible_user' => ['sometimes', 'boolean'],
            'data.students_timetables_user_test_mode' => ['sometimes', 'boolean'],
            'data.students_timetables_user_comming_soon' => ['sometimes', 'boolean'],
        ];
    }
}
