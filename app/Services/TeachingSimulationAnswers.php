<?php

namespace App\Services;

use App\Ai\Agents\TeachingSimulationAnswerAgent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class TeachingSimulationAnswers
{
    public function generate(string $assignment): array
    {
        abort_unless(mb_strlen(trim($assignment)) >= 30 && mb_strlen($assignment) <= 60000, 422,
            'Die Aufgabenstellung konnte nicht vollständig gelesen werden.');
        $response = (new TeachingSimulationAnswerAgent)->prompt("Erzeuge die drei Testbearbeitungen dieser Aufgabenstellung.\n\nAUFGABENQUELLE:\n".$assignment);
        $variants = $response['variants'];
        abort_unless(is_array($variants) && count($variants) === 3
            && collect($variants)->pluck('quality')->sort()->values()->all() === ['good', 'partial', 'weak'],
            422, 'Die automatisch erzeugten Varianten sind unvollständig. Bitte erneut versuchen.');
        foreach ($variants as $variant) {
            foreach (['subject' => 200, 'message' => 6000, 'answer' => 30000] as $key => $limit) {
                abort_unless(is_string($variant[$key] ?? null) && trim($variant[$key]) !== '' && mb_strlen($variant[$key]) <= $limit,
                    422, 'Die erzeugte Testbearbeitung enthält fehlende oder zu lange Angaben.');
            }
            abort_unless(is_array($variant['files']) && count($variant['files']) <= 30, 422, 'Zu viele erzeugte Testdateien.');
            foreach ($variant['files'] as $file) {
                $this->validateFile($file);
            }
        }
        abort_if(($response['warnings'] ?? []) !== [], 422,
            'Für eine nachvollziehbare Simulation fehlen Aufgabeninhalte: '.implode(' ', $response['warnings']));

        return collect($variants)->keyBy('quality')->all();
    }

    public function files(string $directory, array $person, string $title, array $variant, int $version): array
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);
        $filename = 'Ausarbeitung_v'.$version.'.pdf';
        $identity = $person['first_name'].' '.$person['last_name'].' · '.$person['class_name'];
        $body = e($variant['answer']);
        $html = '<html><head><meta charset="utf-8"><style>@page{margin:38px}body{font-family:"DejaVu Sans";font-size:11px;color:#263835;line-height:1.5}h1{font-size:20px;color:#173a40}.note{color:#765e16;border-bottom:1px solid #ddd;padding-bottom:9px}.identity{margin-bottom:20px}p{white-space:pre-wrap}</style></head><body>'
            .'<div class="note">Simulation · automatisch erzeugte Testabgabe</div><h1>'.e($title).'</h1>'
            .'<div class="identity">'.e($identity).'<br>'.now()->timezone('Europe/Vienna')->format('d.m.Y').'</div><p>'.$body.'</p></body></html>';
        $bytes = Pdf::loadHTML($html)->setPaper('a4')->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->output();
        $disk->put($directory.'/'.$filename, $bytes);
        $files = [['name' => $filename, 'path' => $directory.'/'.$filename, 'sha256' => hash('sha256', $bytes), 'mime' => 'application/pdf']];
        if ($variant['files'] !== []) {
            $zipPath = $directory.'/Ordner_v'.$version.'.zip';
            $zip = new ZipArchive;
            abort_unless($zip->open($disk->path($zipPath), ZipArchive::CREATE | ZipArchive::EXCL) === true, 500, 'ZIP-Datei konnte nicht angelegt werden.');
            try {
                foreach ($variant['files'] as $file) {
                    $this->validateFile($file);
                    $zip->addFromString($file['path'], $file['content']);
                }
            } finally {
                $zip->close();
            }
            $files[] = ['name' => basename($zipPath), 'path' => $zipPath,
                'sha256' => hash_file('sha256', $disk->path($zipPath)), 'mime' => 'application/zip'];
        }

        return $files;
    }

    public function validateFile(array $file): void
    {
        $path = $file['path'] ?? '';
        abort_unless(is_string($path) && strlen($path) <= 180 && preg_match('#\A[a-zA-Z0-9_ äöüÄÖÜß.-]+(?:/[a-zA-Z0-9_ äöüÄÖÜß.-]+)*\.(?:txt|csv|md)\z#u', $path)
            && ! str_contains($path, '..') && ! str_starts_with($path, '/')
            && is_string($file['content'] ?? null) && strlen($file['content']) <= 30000, 422,
            'Ein erzeugter Dateipfad oder Dateiinhalt ist für eine Simulation nicht zulässig.');
    }
}
