<?php

namespace App\Services;

use App\Models\RestaurantSepaMandate;
use App\Models\School;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RestaurantSepaMandatePdfService
{
    public function createPdf(RestaurantSepaMandate $mandate): string
    {
        $mandate->loadMissing(['user.selectedSchool']);

        $path = $this->pdfDirectory().DIRECTORY_SEPARATOR.$this->filename($mandate);

        $this->renderPdf($this->viewData($mandate), $path);

        return $path;
    }

    /**
     * @param  array{sepa_payee?: string, sepa_mandate_text?: string}  $settings
     */
    public function createPreviewPdf(School $school, array $settings): string
    {
        $path = $this->pdfDirectory().DIRECTORY_SEPARATOR.'sepa_lastschriftmandat_vorschau.pdf';

        $this->renderPdf([
            'title' => 'SEPA-Lastschriftmandat',
            'school_name' => (string) ($school->long_name ?: $school->short_name ?: ''),
            'email' => 'vorschau@example.test',
            'account_holder_name' => 'Max Mustermann',
            'address_line' => 'Musterstraße 1',
            'postal_code' => '5020',
            'city' => 'Salzburg',
            'country' => 'Österreich',
            'signature_location' => 'Salzburg',
            'iban' => 'AT611904300234573201',
            'bic' => 'BKAUATWW',
            'child_entries' => [
                [
                    'name' => 'Maria Mustermann',
                    'schoolclass' => '1A',
                ],
            ],
            'sepa_payee' => trim((string) ($settings['sepa_payee'] ?? '')),
            'sepa_mandate_text' => trim((string) ($settings['sepa_mandate_text'] ?? '')),
            'confirmed_at_label' => now()->format('d.m.Y'),
            'signature_uuid' => 'VORSCHAU',
            'flow_uuid' => 'VORSCHAU',
        ], $path);

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

    /**
     * @param  array<string, mixed>  $mandate
     */
    private function renderPdf(array $mandate, string $path): void
    {
        DomPdf::loadView('pdfs.restaurantSepaMandate', [
            'mandate' => $mandate,
        ])
            ->setPaper('a4', 'portrait')
            ->save($path);
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
