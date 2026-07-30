<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use App\Models\MaterialV2Cluster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialV2IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'cluster_id' => [
                'nullable',
                'integer',
                Rule::exists(MaterialV2Cluster::class, 'id')->where(
                    fn ($query) => $query
                        ->where('school_id', $user?->school_id)
                        ->where('user_id', $user?->id),
                ),
            ],
            'reminder_from' => ['nullable', 'date_format:Y-m-d'],
            'reminder_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:reminder_from'],
            'reminder_order' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:6', 'max:48'],
        ];
    }
}
