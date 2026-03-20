<?php

namespace App\Console\Commands;

use App\ABA\Services\FindSeedOpenIssues;
use App\ABA\Services\RunSeedHardening;
use Illuminate\Console\Command;

class AbaHardenSeed extends Command
{
    protected $signature = 'aba:harden-seed
                            {--ai : KI-Pipeline verwenden (Research → Verification → Draft)}
                            {--scan : Nur Issue-Scan ohne Pipeline}';

    protected $description = 'ABA Seed-Härtungs-Pipeline ausführen (pattern-basiert oder KI-gestützt)';

    public function __construct(
        private readonly RunSeedHardening $runHardening,
        private readonly FindSeedOpenIssues $findIssues,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('scan')) {
            return $this->runScan();
        }

        $useAi = (bool) $this->option('ai');
        $mode = $useAi ? 'KI-Pipeline' : 'Pattern-Analyse';

        $this->info("ABA Seed-Härtung gestartet (Modus: {$mode}) ...");

        $result = $this->runHardening->run(useAi: $useAi);

        if (! $result['success']) {
            $this->error('Fehler: '.($result['error'] ?? 'Unbekannter Fehler'));

            return self::FAILURE;
        }

        $this->info("Modus: {$result['mode']}");
        $this->info('Report: '.($result['report_file'] ?? '-'));

        $summary = $result['summary'];
        $this->table(
            ['Kennzahl', 'Wert'],
            collect($summary)
                ->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])
                ->values()
                ->toArray(),
        );

        if ($useAi && isset($summary['proposal_file'])) {
            $this->newLine();
            $this->info('Entwurf bereit für Review: '.basename($summary['proposal_file']));
            $this->warn('→ Bitte Entwurf manuell prüfen und erst dann auf die Seed-Datei anwenden.');
        }

        return self::SUCCESS;
    }

    private function runScan(): int
    {
        $this->info('Scanne ABA Seed nach offenen Issues ...');

        $result = $this->findIssues->find();
        $summary = $result['summary'];

        $this->info("Gefundene Issues: {$summary['total_issues']}");
        $this->table(
            ['Typ', 'Anzahl'],
            collect($summary['by_type'] ?? [])
                ->map(fn ($count, $type) => [$type, $count])
                ->values()
                ->toArray(),
        );

        return self::SUCCESS;
    }
}
