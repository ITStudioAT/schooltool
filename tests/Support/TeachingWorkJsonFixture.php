<?php

namespace Tests\Support;

use App\Support\SchooltoolAssessmentJson;
use Illuminate\Http\UploadedFile;

class TeachingWorkJsonFixture
{
    public static function package(): array
    {
        return json_decode(file_get_contents(__DIR__.'/schooltool-json-v1.beispiel.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function sign(array $package): array
    {
        foreach ($package['records'] as &$record) {
            unset($record['record_checksum']);
            $record['record_checksum'] = SchooltoolAssessmentJson::digest($record);
        }
        unset($record, $package['package_checksum']);
        $package['package_checksum'] = SchooltoolAssessmentJson::digest($package);

        return $package;
    }

    public static function upload(array $package): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('Schooltool-Bewertungen.json', json_encode(self::sign($package), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
