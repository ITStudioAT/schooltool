<?php

namespace App\Services;

use App\Models\RestaurantSepaMandate;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RestaurantSepaMandatePdfService
{
    public function createPdf(RestaurantSepaMandate $mandate): string
    {
        $mandate->loadMissing(['user.selectedSchool']);

        $path = $this->pdfDirectory().DIRECTORY_SEPARATOR.$this->filename($mandate);

        DomPdf::loadView('pdfs.restaurantSepaMandate', [
            'mandate' => $this->viewData($mandate),
        ])
            ->setPaper('a4', 'portrait')
            ->save($path);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(RestaurantSepaMandate $mandate): array
    {
        $user = $mandate->user;
        $school = $user?->selectedSchool;
        $children = collect($mandate->child_entries ?: [])
            ->map(function (array $entry): array {
                return [
                    'name' => trim((string) ($entry['name'] ?? '')),
                    'schoolclass' => trim((string) ($entry['schoolclass'] ?? '')),
                ];
            })
            ->filter(fn (array $entry): bool => $entry['name'] !== '' || $entry['schoolclass'] !== '')
            ->values()
            ->all();

        return [
            'title' => 'SEPA-Lastschriftmandat',
            'school_name' => (string) ($school?->long_name ?: $school?->short_name ?: ''),
            'email' => trim((string) ($user?->email ?? '')),
            'account_holder_name' => trim((string) ($mandate->account_holder_name ?? '')),
            'address_line' => trim((string) ($mandate->address_line ?? '')),
            'postal_code' => trim((string) ($mandate->postal_code ?? '')),
            'city' => trim((string) ($mandate->city ?? '')),
            'country' => trim((string) ($mandate->country ?? 'Österreich')) ?: 'Österreich',
            'signature_location' => trim((string) ($mandate->city ?? '')),
            'iban' => trim((string) ($mandate->iban ?? '')),
            'bic' => trim((string) ($mandate->bic ?? '')),
            'child_entries' => $children,
            'sepa_payee' => $mandate->sepa_payee_snapshot ?: '',
            'sepa_mandate_text' => $mandate->sepa_mandate_text_snapshot ?: '',
            'confirmed_at_label' => $mandate->confirmed_at?->format('d.m.Y') ?? $mandate->accepted_at?->format('d.m.Y') ?? '',
            'signature_uuid' => $mandate->flow_uuid,
            'flow_uuid' => $mandate->flow_uuid,
        ];
    }

    private function filename(RestaurantSepaMandate $mandate): string
    {
        return Str::slug('sepa_lastschriftmandat', '_').'_'.Str::slug((string) $mandate->flow_uuid, '_').'.pdf';
    }

    private function pdfDirectory(): string
    {
        $directory = storage_path('app/private/pdf');
        File::ensureDirectoryExists($directory);

        return $directory;
    }
}
