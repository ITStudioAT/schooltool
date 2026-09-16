<?php

namespace App\Models;

use Database\Factories\FeaturePreviewSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeaturePreviewSetting extends Model
{
    /** @use HasFactory<FeaturePreviewSettingFactory> */
    use HasFactory;

    protected $fillable = ['enabled'];

    protected $attributes = ['enabled' => false];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
