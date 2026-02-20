<?php

namespace Database\Factories;

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialCardAttachment>
 */
class MaterialCardAttachmentFactory extends Factory
{
    protected $model = MaterialCardAttachment::class;

    public function definition(): array
    {
        return [
            'material_card_id' => MaterialCard::factory(),
            'attachment_type' => fake()->randomElement([MaterialCardAttachment::TYPE_FILE, MaterialCardAttachment::TYPE_LINK]),
            'name' => fake()->words(2, true),
            'url' => fake()->optional()->url(),
            'source_url' => fake()->optional()->url(),
            'file_path' => fake()->optional()->filePath(),
            'mime_type' => fake()->optional()->mimeType(),
            'size_bytes' => fake()->optional()->numberBetween(100, 1000000),
            'downloaded_at' => fake()->optional()->dateTime(),
        ];
    }
}
