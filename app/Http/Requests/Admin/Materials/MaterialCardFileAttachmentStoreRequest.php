<?php

namespace App\Http\Requests\Admin\Materials;

use App\Models\SchoolTool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaterialCardFileAttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $maxUploadSizeKb = $this->maxUploadSizeKbForUser();

        return [
            'file' => ['required', 'file', 'max:'.$maxUploadSizeKb],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function maxUploadSizeKbForUser(): int
    {
        $default = 20480;
        $schoolId = (int) (Auth::user()?->school_id ?? 0);

        if ($schoolId <= 0) {
            return $default;
        }

        $schoolTool = SchoolTool::query()->firstOrCreate(
            ['school_id' => $schoolId],
            [
                'material_max_file_upload_size' => $default,
            ]
        );

        $value = (int) ($schoolTool->material_max_file_upload_size ?? 0);
        if ($value <= 0) {
            return $default;
        }

        return $value;
    }
}
