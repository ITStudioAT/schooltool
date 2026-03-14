<?php

use App\ABA\Services\ResolveSeedSourcePlaceholders;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    $this->registryPath = base_path('ai/knowledge/aba/sources/source-registry.json');

    File::ensureDirectoryExists($this->proposalsDir);
});

test('focused source resolve enriches remaining ahs source placeholders with authoritative entry points', function () {
    $registryBackup = File::exists($this->registryPath) ? File::get($this->registryPath) : null;
    $verificationPath = $this->proposalsDir.'/ai-seed-verification-focused-test-'.Str::lower(Str::random(8)).'.json';
    $resolutionPath = $this->proposalsDir.'/ai-seed-source-resolution-2099-01-01.json';

    Carbon::setTestNow(Carbon::parse('2099-01-01 10:00:00'));

    $registry = [
        'sources' => [
            [
                'source_id' => 'BMBWF-2025',
                'title' => 'BMBWF-Erlass 2025 zur Durchführung der ABA (AHS)',
                'url' => null,
                'enabled' => false,
                'status' => 'needs_verification',
            ],
            [
                'source_id' => 'AHS-HB-2025',
                'title' => 'AHS-Handbuch / BMBWF-Richtlinien für AHS-ABA 2025',
                'url' => null,
                'enabled' => false,
                'status' => 'needs_identification',
            ],
        ],
    ];

    $verification = [
        'results' => [
            [
                'issue_id' => 'issue-013',
                'issue_type' => 'source_placeholder',
                'verification_status' => 'unverified',
                'original_text' => '- **BMBWF-Erlass 2025 (AHS)**: Amtliche Quelle, bindend – URL noch zu recherchieren',
                'source_refs' => [],
            ],
            [
                'issue_id' => 'issue-014',
                'issue_type' => 'source_placeholder',
                'verification_status' => 'unverified',
                'original_text' => '- **AHS-Handbuch / BMBWF-Richtlinien für AHS**: Offizielle Orientierung – Quelle noch zu identifizieren und zu verlinken',
                'source_refs' => [],
            ],
        ],
    ];

    File::put($this->registryPath, json_encode($registry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    File::put($verificationPath, json_encode($verification, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    try {
        $service = app(ResolveSeedSourcePlaceholders::class);
        $result = $service->resolve($verificationPath);

        expect($result['success'])->toBeTrue()
            ->and($result['total'])->toBe(2)
            ->and($result['resolved'])->toBe(0)
            ->and($result['partially_resolved'])->toBe(2)
            ->and($result['unresolved'])->toBe(0);

        $itemsBySource = collect($result['items'])->keyBy('source_id');

        expect($itemsBySource->get('BMBWF-2025'))->not->toBeNull()
            ->and($itemsBySource->get('BMBWF-2025')['resolution_status'])->toBe('partially_resolved')
            ->and($itemsBySource->get('BMBWF-2025')['confidence'])->toBe('medium')
            ->and($itemsBySource->get('BMBWF-2025')['url'])->toBe('https://www.ahs-aba.at/schueler/planen/richtlinien')
            ->and($itemsBySource->get('BMBWF-2025')['reason'])->toContain('Erlass')
            ->and($itemsBySource->get('BMBWF-2025')['evidence_level'])->toBe('portal_reference')
            ->and($itemsBySource->get('BMBWF-2025')['is_direct_document'])->toBeFalse()
            ->and($itemsBySource->get('BMBWF-2025')['is_citable'])->toBeFalse()
            ->and($itemsBySource->get('BMBWF-2025')['missing_requirements'])->toContain('Eindeutiger offizieller Dokumenttitel');

        expect($itemsBySource->get('AHS-HB-2025'))->not->toBeNull()
            ->and($itemsBySource->get('AHS-HB-2025')['resolution_status'])->toBe('partially_resolved')
            ->and($itemsBySource->get('AHS-HB-2025')['confidence'])->toBe('medium')
            ->and($itemsBySource->get('AHS-HB-2025')['url'])->toBe('https://www.ahs-aba.at/schueler/einreichen/aba-portal')
            ->and($itemsBySource->get('AHS-HB-2025')['reason'])->toContain('Handbuch')
            ->and($itemsBySource->get('AHS-HB-2025')['evidence_level'])->toBe('portal_reference')
            ->and($itemsBySource->get('AHS-HB-2025')['is_direct_document'])->toBeFalse()
            ->and($itemsBySource->get('AHS-HB-2025')['is_citable'])->toBeFalse();

        expect(File::exists($resolutionPath))->toBeTrue();
    } finally {
        Carbon::setTestNow();

        if ($registryBackup !== null) {
            File::put($this->registryPath, $registryBackup);
        } else {
            File::delete($this->registryPath);
        }

        if (File::exists($verificationPath)) {
            File::delete($verificationPath);
        }

        if (File::exists($resolutionPath)) {
            File::delete($resolutionPath);
        }
    }
});
