<?php

namespace Database\Factories;

use App\Models\FeaturePreviewSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeaturePreviewSetting>
 */
class FeaturePreviewSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'enabled' => false,
        ];
    }
}
