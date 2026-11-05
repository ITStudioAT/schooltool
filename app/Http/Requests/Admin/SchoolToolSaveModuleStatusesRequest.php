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
        $versionFourOnly = Rule::excludeIf($this->hasAny(['data.version_four_visible', 'data.version_four_features']));

        return [
            'data.id' => [
                'required',
                'integer',
                Rule::exists('school_tools', 'id')
                    ->where('school_id', Auth::user()?->school_id),
            ],
            'data.version_four_visible' => ['sometimes', 'boolean'],
            'data.version_four_features' => ['sometimes', 'array:elections,matura,homepage', 'min:1'],
            'data.version_four_features.elections' => ['sometimes', 'boolean'],
            'data.version_four_features.matura' => ['sometimes', 'boolean'],
            'data.version_four_features.homepage' => ['sometimes', 'boolean'],
            'data.register_visible_admin' => [$versionFourOnly, 'required', 'boolean'],
            'data.register_visible_user' => [$versionFourOnly, 'required', 'boolean'],
            'data.register_user_test_mode' => [$versionFourOnly, 'required', 'boolean'],
            'data.register_user_comming_soon' => [$versionFourOnly, 'required', 'boolean'],
            'data.teaching_visible_admin' => [$versionFourOnly, 'required', 'boolean'],
            'data.teaching_visible_user' => [$versionFourOnly, 'required', 'boolean'],
            'data.teaching_user_test_mode' => [$versionFourOnly, 'required', 'boolean'],
            'data.teaching_user_comming_soon' => [$versionFourOnly, 'required', 'boolean'],
            'data.materials_visible_admin' => [$versionFourOnly, 'required', 'boolean'],
            'data.materials_visible_user' => [$versionFourOnly, 'required', 'boolean'],
            'data.materials_user_test_mode' => [$versionFourOnly, 'required', 'boolean'],
            'data.materials_user_comming_soon' => [$versionFourOnly, 'required', 'boolean'],
            'data.restaurant_visible_admin' => [$versionFourOnly, 'required', 'boolean'],
            'data.restaurant_visible_user' => [$versionFourOnly, 'required', 'boolean'],
            'data.restaurant_user_test_mode' => [$versionFourOnly, 'required', 'boolean'],
            'data.restaurant_user_comming_soon' => [$versionFourOnly, 'required', 'boolean'],
            'data.aba_visible_admin' => [$versionFourOnly, 'required', 'boolean'],
            'data.aba_visible_user' => [$versionFourOnly, 'required', 'boolean'],
            'data.aba_user_test_mode' => [$versionFourOnly, 'required', 'boolean'],
            'data.aba_user_comming_soon' => [$versionFourOnly, 'required', 'boolean'],
            'data.students_timetables_visible_admin' => [$versionFourOnly, 'sometimes', 'boolean'],
            'data.students_timetables_visible_user' => [$versionFourOnly, 'sometimes', 'boolean'],
            'data.students_timetables_user_test_mode' => [$versionFourOnly, 'sometimes', 'boolean'],
            'data.students_timetables_user_comming_soon' => [$versionFourOnly, 'sometimes', 'boolean'],
            'data.students_timetables_admin_version' => [
                $versionFourOnly,
                'sometimes',
                'string',
                Rule::in(SchoolTool::STUDENTS_TIMETABLES_ADMIN_VERSIONS),
            ],
        ];
    }
}
