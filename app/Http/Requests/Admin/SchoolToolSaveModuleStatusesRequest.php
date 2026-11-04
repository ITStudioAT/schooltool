<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolTool;
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
            'data.version_four_visible' => ['sometimes', 'boolean'],
            'data.register_visible_admin' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.register_visible_user' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.register_user_test_mode' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.register_user_comming_soon' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.teaching_visible_admin' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.teaching_visible_user' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.teaching_user_test_mode' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.teaching_user_comming_soon' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.materials_visible_admin' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.materials_visible_user' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.materials_user_test_mode' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.materials_user_comming_soon' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.restaurant_visible_admin' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.restaurant_visible_user' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.restaurant_user_test_mode' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.restaurant_user_comming_soon' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.aba_visible_admin' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.aba_visible_user' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.aba_user_test_mode' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.aba_user_comming_soon' => ['exclude_with:data.version_four_visible', 'required', 'boolean'],
            'data.students_timetables_visible_admin' => ['exclude_with:data.version_four_visible', 'sometimes', 'boolean'],
            'data.students_timetables_visible_user' => ['exclude_with:data.version_four_visible', 'sometimes', 'boolean'],
            'data.students_timetables_user_test_mode' => ['exclude_with:data.version_four_visible', 'sometimes', 'boolean'],
            'data.students_timetables_user_comming_soon' => ['exclude_with:data.version_four_visible', 'sometimes', 'boolean'],
            'data.students_timetables_admin_version' => [
                'exclude_with:data.version_four_visible',
                'sometimes',
                'string',
                Rule::in(SchoolTool::STUDENTS_TIMETABLES_ADMIN_VERSIONS),
            ],
        ];
    }
}
