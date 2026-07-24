<?php

namespace Database\Factories;

use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialV2Attachment>
 */
class MaterialV2AttachmentFactory extends Factory
{
    protected $model = MaterialV2Attachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_v2_item_id' => MaterialV2Item::factory(),
            'disk' => 'local',
            'path' => 'materials-v2/'.fake()->uuid().'.txt',
            'original_name' => fake()->word().'.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => fake()->numberBetween(100, 100000),
            'extracted_text' => fake()->paragraph(),
            'extraction_status' => MaterialV2Attachment::STATUS_READY,
            'extraction_error' => null,
            'extracted_at' => now(),
        ];
    }
}
