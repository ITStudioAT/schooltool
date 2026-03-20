<?php

namespace Database\Factories;

use App\Models\Aba;
use App\Models\AbaAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbaAttachment>
 */
class AbaAttachmentFactory extends Factory
{
    protected $model = AbaAttachment::class;

    public function definition(): array
    {
        $fileName = fake()->word().'.pdf';

        return [
            'aba_id' => Aba::factory(),
            'document_kind' => AbaAttachment::DOCUMENT_KIND_ADDITIONAL,
            'original_name' => $fileName,
            'path' => 'aba-attachments/'.fake()->uuid().'-'.$fileName,
            'stored_name' => fake()->uuid().'-'.$fileName,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10000, 5000000),
            'uploaded_by_user_id' => null,
        ];
    }
}
