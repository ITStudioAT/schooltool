<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class TeachingSimulationMailpit
{
    private const BASE = 'http://127.0.0.1:8025/api/v1';

    public function start(): void
    {
        abort_unless(app()->isLocal() && PHP_OS_FAMILY === 'Windows', 403, 'Mailpit kann hier nur lokal unter Windows gestartet werden.');
        $result = Process::timeout(20)->run(['pwsh.exe', '-NoProfile', '-NonInteractive', '-File', base_path('scripts/start-teaching-simulation-mailpit.ps1')]);
        abort_unless($result->successful(), 409, 'Mailpit konnte nicht gestartet werden. Bitte eine bereits laufende Instanz zuerst in Laragon beenden.');
        $this->assertSafe();
    }

    public function assertSafe(): void
    {
        abort_unless(app()->isLocal() && app(TeachingWorkEmailDelivery::class)->context()['available'], 403,
            'Der Simulator ist ausschließlich lokal mit Mailpit verfügbar.');
        $ui = $this->request('GET', '/webui')->json();
        $info = $this->request('GET', '/info')->json();
        $proofPath = storage_path('app/private/teaching-simulator/mailpit-safety.json');
        abort_unless(is_file($proofPath), 409, 'Bitte Mailpit einmal über „Simulator-Mailpit starten“ starten.');
        $proof = json_decode(file_get_contents($proofPath), true, 16, JSON_THROW_ON_ERROR);
        $started = strtotime($proof['started_at'] ?? '');
        $expected = str_replace('\\', '/', storage_path('app/private/teaching-simulator/mailpit.sqlite'));
        abort_unless(($ui['MessageRelay']['Enabled'] ?? null) === false
            && ($ui['MessageForward']['Enabled'] ?? false) === false
            && ($ui['Label'] ?? null) === 'Schooltool-Simulator'
            && ($proof['kind'] ?? null) === 'schooltool.mailpit-safety' && ($proof['relay_environment_removed'] ?? null) === true
            && ($proof['forwarding_environment_removed'] ?? null) === true && ($proof['loopback_only'] ?? null) === true
            && $started !== false && isset($info['RuntimeStats']['Uptime'])
            && abs((microtime(true) - $info['RuntimeStats']['Uptime']) - $started) <= 10
            && strcasecmp(str_replace('\\', '/', $info['Database'] ?? ''), $expected) === 0,
            409, 'Bitte die lokale Simulator-Mailpit-Instanz ohne Relay und Weiterleitung starten. Es wurde nichts gesendet.');
    }

    public function send(array $payload): string
    {
        $this->assertSafe();
        $result = $this->request('POST', '/send', $payload)->json();
        abort_unless(is_string($result['ID'] ?? null) && preg_match('/\A[A-Za-z0-9_-]{8,80}\z/', $result['ID']), 502,
            'Mailpit hat die Rückgabe nicht eindeutig bestätigt.');

        return $result['ID'];
    }

    public function message(string $id): array
    {
        $this->identifier($id);

        return $this->request('GET', '/message/'.$id)->json();
    }

    public function raw(string $id): string
    {
        $this->identifier($id);

        return $this->request('GET', '/message/'.$id.'/raw')->body();
    }

    public function part(string $id, string $part): string
    {
        $this->identifier($id);
        abort_unless(preg_match('/\A[0-9.]{1,30}\z/', $part), 422, 'Mailpit-Anhangkennung ungültig.');

        return $this->request('GET', '/message/'.$id.'/part/'.$part)->body();
    }

    public function find(string $run, string $participant, int $version): ?string
    {
        $query = 'message-id:sim.'.$run.'.'.str_replace(':', '-', $participant).'.v'.$version.'@schooltool.test';
        $data = $this->request('GET', '/search', ['query' => $query, 'limit' => 10])->json();
        $matches = $data['messages'] ?? [];
        abort_if(count($matches) > 1, 409, 'Mehrere identische Simulator-Nachrichten gefunden; bitte den Lauf prüfen.');

        return $matches[0]['ID'] ?? null;
    }

    public function findMessage(string $messageId): ?string
    {
        abort_unless(preg_match('/\Aresult\.[a-f0-9]{64}@schooltool\.test\z/', $messageId), 422);
        $data = $this->request('GET', '/search', ['query' => 'message-id:'.$messageId, 'limit' => 10])->json();
        $matches = $data['messages'] ?? [];
        abort_if(count($matches) > 1, 409, 'Die Ergebnisnachricht ist mehrfach vorhanden. Bitte den Versandlauf prüfen.');

        return $matches[0]['ID'] ?? null;
    }

    private function identifier(string $id): void
    {
        abort_unless(preg_match('/\A[A-Za-z0-9_-]{8,80}\z/', $id), 422, 'Mailpit-Nachrichtenkennung ungültig.');
    }

    private function request(string $method, string $path, array $data = []): Response
    {
        $response = Http::connectTimeout(3)->timeout(15)->withoutRedirecting()
            ->send($method, self::BASE.$path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        abort_unless($response->successful(), 502, 'Die lokale Mailpit-Instanz ist nicht erreichbar oder hat den Vorgang abgelehnt.');

        return $response;
    }
}
