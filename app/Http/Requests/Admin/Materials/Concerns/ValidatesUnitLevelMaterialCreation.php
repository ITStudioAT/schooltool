<?php

namespace App\Http\Requests\Admin\Materials\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesUnitLevelMaterialCreation
{
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateUnitLevelMaterialClassifications($validator);
            },
        ];
    }

    private function validateUnitLevelMaterialClassifications(Validator $validator): void
    {
        $classifications = $this->input('data.classifications');

        if (! is_array($classifications)) {
            return;
        }

        foreach ($classifications as $index => $classification) {
            if (! is_array($classification)) {
                continue;
            }

            $subject = trim((string) ($classification['subject'] ?? ''));
            $topic = trim((string) ($classification['topic'] ?? ''));
            $unit = trim((string) ($classification['unit'] ?? ''));

            if ($subject === '' && $topic === '' && $unit === '') {
                continue;
            }

            if ($subject !== '' && $topic !== '' && $unit !== '') {
                continue;
            }

            $validator->errors()->add(
                "data.classifications.{$index}.unit",
                'Materialien können nur in Bereichen mit Fach und Thema erstellt werden.'
            );
        }
    }
}
