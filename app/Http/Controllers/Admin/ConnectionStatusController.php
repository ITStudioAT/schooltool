<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DropboxWorkImport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ConnectionStatusController extends Controller
{
    public function __invoke(Request $request, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->hasRole('super_admin'), 403);
        if ($request->isMethod('post')) {
            $response = Cache::store('file')->lock('connections-check:'.$actor->id, 90)->get(function () use ($actor, $dropbox): JsonResponse {
                $key = 'connections-last-check:'.$actor->id;
                $cached = Cache::store('file')->get($key);
                if (is_array($cached)) {
                    return response()->json($cached);
                }
                $result = $this->report($actor, $dropbox, true);
                Cache::store('file')->put($key, $result, now()->addMinute());

                return response()->json($result);
            });

            return $response ?: response()->json(['message' => 'Eine Verbindungsprüfung läuft bereits.'], 429);
        }

        $data = $request->validate(['probe' => ['sometimes', 'string', 'in:dropbox,postmark']]);

        return response()->json($this->report($actor, $dropbox, probe: $data['probe'] ?? null));
    }

    private function report(User $actor, DropboxWorkImport $dropbox, bool $verify = false, ?string $probe = null): array
    {
        $dropboxStatus = $dropbox->connectionStatus($actor, $verify || $probe === 'dropbox');
        if ($verify && $dropboxStatus['connected'] && $dropboxStatus['write_granted']) {
            try {
                $dropbox->verifyTemporaryWrite($actor);
                $dropboxStatus = $dropbox->connectionStatus($actor);
            } catch (ValidationException $exception) {
                $dropboxStatus['error'] = collect($exception->errors())->flatten()->implode(' ');
            }
        }
        $token = (string) config('services.postmark.token');
        $postmarkKey = 'postmark-check:'.hash('sha256', $token);
        $postmark = Cache::store('file')->get($postmarkKey, []);
        $mailKey = 'postmark-mail-check:'.$actor->id.':'.hash('sha256', $token);
        $mail = Cache::store('file')->get($mailKey);
        $apiConfirmed = false;
        if (($verify || $probe === 'postmark') && $token !== '') {
            $postmark = [];
            try {
                $response = Http::withHeaders(['X-Postmark-Server-Token' => $token])->acceptJson()->connectTimeout(5)->timeout(12)
                    ->withOptions(['allow_redirects' => false])->get('https://api.postmarkapp.com/server');
                if ($response->successful() && filter_var($response->json('ID'), FILTER_VALIDATE_INT) !== false && (int) $response->json('ID') > 0) {
                    $postmark = ['checked_at' => now()->toISOString()];
                    $apiConfirmed = true;
                } else {
                    $postmark['http_status'] = $response->status();
                    $postmark['error_code'] = is_int($response->json('ErrorCode')) ? $response->json('ErrorCode') : null;
                    $postmark['error'] = $response->status() === 401 && $postmark['error_code'] === 10
                        ? 'Postmark akzeptiert den konfigurierten Server-API-Token nicht (HTTP 401, Code 10). Bitte den Server-API-Token prüfen. Der SMTP-Zugang wird hier nicht geprüft.'
                        : 'Der Postmark-API-Zugang konnte nicht bestätigt werden (HTTP '.$response->status().').';
                }
            } catch (ConnectionException) {
                $postmark['error'] = 'Postmark ist momentan nicht erreichbar.';
            }
            Cache::store('file')->put($postmarkKey, $postmark, now()->addDays(30));
        }
        if ($verify) {
            $mail = $apiConfirmed ? $this->testMail($actor, $token)
                : ['accepted' => false, 'message' => 'Keine Testmail gesendet: Zuerst muss der Postmark-API-Zugang bestätigt werden.'];
            Cache::store('file')->put($mailKey, $mail, now()->addDays(30));
        }

        return ['dropbox' => $dropboxStatus,
            'can_connect_dropbox' => true,
            'postmark' => ['configured' => $token !== '', 'used_for_mail' => config('mail.default') === 'postmark',
                'smtp_selected' => config('mail.default') === 'smtp',
                'checked_at' => $postmark['checked_at'] ?? null, 'error' => $postmark['error'] ?? null,
                'http_status' => $postmark['http_status'] ?? null, 'error_code' => $postmark['error_code'] ?? null, 'can_check' => true, 'test_mail' => $mail],
            'office' => ['configured' => false, 'message' => 'Keine direkte Office-/Microsoft-Verbindung in Schooltool eingerichtet.']];
    }

    private function testMail(User $actor, string $token): array
    {
        if (! $actor->email_verified_at || ! filter_var($actor->email, FILTER_VALIDATE_EMAIL)
            || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) || config('schooltool.preview.instance')) {
            return ['accepted' => false, 'message' => 'Keine Testmail gesendet: Eigene bestätigte E-Mail-Adresse, Absender oder zulässiger Versandkontext fehlen.'];
        }
        $payload = ['From' => config('mail.from.address'), 'To' => $actor->email, 'Subject' => 'Schooltool · Postmark-Verbindungsprüfung',
            'TextBody' => 'Diese Testnachricht wurde durch Ihre ausdrücklich gestartete Verbindungsprüfung in Schooltool ausgelöst. Sie enthält keine Schülerdaten.'];
        $stream = config('mail.mailers.postmark.message_stream_id');
        if (is_string($stream) && $stream !== '') {
            $payload['MessageStream'] = $stream;
        }
        try {
            $response = Http::withHeaders(['X-Postmark-Server-Token' => $token])->acceptJson()->connectTimeout(5)->timeout(15)
                ->withOptions(['allow_redirects' => false])->post('https://api.postmarkapp.com/email', $payload);
            if ($response->successful() && $response->json('ErrorCode') === 0 && is_string($response->json('MessageID'))) {
                return ['accepted' => true, 'accepted_at' => now()->toISOString(), 'message' => 'Postmark hat die Testmail angenommen. Zustellung und Lesen sind damit nicht bestätigt.'];
            }

            return ['accepted' => false, 'message' => 'Postmark hat die Testmail nicht angenommen (HTTP '.$response->status().', Code '.(int) $response->json('ErrorCode').'). Bitte Absender und Mailstream prüfen.'];
        } catch (ConnectionException) {
            return ['accepted' => null, 'message' => 'Die Annahme der Testmail ist unklar. Bitte das eigene Postfach und Postmark prüfen, bevor Sie erneut testen.'];
        }
    }

    public function connect(Request $request, DropboxWorkImport $dropbox): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $state = bin2hex(random_bytes(32));
        $request->session()->put('teaching_dropbox_oauth', ['state' => $state, 'user_id' => $request->user()->id,
            'connection_status' => true, 'expires_at' => now()->addMinutes(10)->timestamp]);

        return response()->json(['url' => $dropbox->authorizationUrl($state, true)]);
    }

    public function complete(Request $request, DropboxWorkImport $dropbox): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        if ($request->query('error')) {
            return redirect('/admin?dropbox=cancelled');
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:2048']]);
        $dropbox->connect($request->user(), $data['code'], true);

        return redirect('/admin?dropbox=connected');
    }
}
