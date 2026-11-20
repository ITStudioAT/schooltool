<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\DropboxWorkImport;
use App\Services\TeachingWorkAssignmentMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mime\Address;

class ConnectionStatusController extends Controller
{
    private const TEST_ACTOR = 'guenther.kron@cdgym.at';

    private const TEST_REPLY_TO = TeachingWorkAssignmentMessage::SCHOOL_REPLY_TO;

    private const TEST_RECIPIENT = 'kron@naturwelt.at';

    private const TEST_SUBJECT = 'Schooltool · Postmark-Versandtest';

    private const TEST_SIGNATURE = TeachingWorkAssignmentMessage::SCHOOL_SIGNATURE;

    private const TEST_SESSION = 'teaching_postmark_test_current';

    private const TEST_LAYOUT_VERSION = 'ai-2027-standard-v2';

    private const TEST_LAYOUT_KEY = 'test_layout';

    private const TEST_RECEIPT = 'schooltool-postmark-test-guenther-kron-cdgym-info-naturwelt-'.self::TEST_LAYOUT_VERSION.'.json';

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
                $response = $this->postmark($token, 12)->get('https://api.postmarkapp.com/server');
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
            || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            return ['accepted' => false, 'message' => 'Keine Testmail gesendet: Eigene bestätigte E-Mail-Adresse, Absender oder zulässiger Versandkontext fehlen.'];
        }
        $payload = ['From' => config('mail.from.address'), 'To' => $actor->email, 'Subject' => 'Schooltool · Postmark-Verbindungsprüfung',
            'TextBody' => 'Diese Testnachricht wurde durch Ihre ausdrücklich gestartete Verbindungsprüfung in Schooltool ausgelöst. Sie enthält keine Schülerdaten.'];
        $stream = config('mail.mailers.postmark.message_stream_id');
        if (is_string($stream) && $stream !== '') {
            $payload['MessageStream'] = $stream;
        }
        try {
            $response = $this->postmark($token)->post('https://api.postmarkapp.com/email', $payload);
            if ($response->successful() && $response->json('ErrorCode') === 0 && is_string($response->json('MessageID'))) {
                return ['accepted' => true, 'accepted_at' => now()->toISOString(), 'message' => 'Postmark hat die Testmail angenommen. Zustellung und Lesen sind damit nicht bestätigt.'];
            }

            return ['accepted' => false, 'message' => 'Postmark hat die Testmail nicht angenommen (HTTP '.$response->status().', Code '.(int) $response->json('ErrorCode').'). Bitte Absender und Mailstream prüfen.'];
        } catch (ConnectionException) {
            return ['accepted' => null, 'message' => 'Die Annahme der Testmail ist unklar. Bitte das eigene Postfach und Postmark prüfen, bevor Sie erneut testen.'];
        }
    }

    public function prepareWorkPostmarkTest(Request $request, TeachingCourseWork $course_work): JsonResponse
    {
        $actor = $this->workPostmarkTestActor($request, $course_work);
        $request->session()->forget(self::TEST_SESSION);
        [$token, $from, $stream] = $this->postmarkTestConfiguration();
        if ($from === null) {
            return $this->postmarkTestResult('sender_pending');
        }
        $path = Storage::disk('local')->path(self::TEST_RECEIPT);
        if (is_file($path)) {
            $receipt = json_decode((string) @file_get_contents($path), true);

            return $this->postmarkTestResult($receipt['status'] ?? 'uncertain',
                $this->workPostmarkTestPreview($from, $this->workPostmarkTestBody()['HtmlBody']),
                array_intersect_key(is_array($receipt) ? $receipt : [], array_flip(['http_status', 'provider_error_code'])));
        }
        if ($token === '' || $token === 'POSTMARK_API_TEST') {
            return $this->postmarkTestResult('unavailable');
        }
        try {
            $server = $this->postmark($token, 12)->get('https://api.postmarkapp.com/server');
            if (! $server->ok() || (int) $server->json('ID') <= 0 || $server->json('DeliveryType') !== 'Live') {
                return $this->postmarkTestResult('unverified');
            }
        } catch (ConnectionException) {
            return $this->postmarkTestResult('unverified');
        }

        $draft = bin2hex(random_bytes(32));
        $payload = $this->workPostmarkTestPayload($from, $stream);
        $request->session()->put(self::TEST_SESSION, ['id' => $draft, 'user_id' => $actor->id, 'work_id' => $course_work->id,
            'token_hash' => hash('sha256', $token), 'payload' => $payload, 'expires_at' => now()->addMinutes(10)->timestamp]);

        return response()->json(['status' => 'ready', 'draft_id' => $draft]
            + $this->workPostmarkTestPreview($from, $payload['HtmlBody']))->header('Cache-Control', 'private, no-store');
    }

    public function sendWorkPostmarkTest(Request $request, TeachingCourseWork $course_work): JsonResponse
    {
        $actor = $this->workPostmarkTestActor($request, $course_work);
        [$token, $from, $stream] = $this->postmarkTestConfiguration();
        if ($from === null) {
            $request->session()->forget(self::TEST_SESSION);

            return $this->postmarkTestResult('sender_pending');
        }
        $data = $request->validate(['draft_id' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']]);
        $draft = $request->session()->get(self::TEST_SESSION);
        abort_unless(is_array($draft) && hash_equals($draft['id'], $data['draft_id']) && $draft['user_id'] === $actor->id
            && $draft['work_id'] === $course_work->id && $draft['expires_at'] >= now()->timestamp
            && hash_equals($draft['token_hash'], hash('sha256', $token)) && $draft['payload']['From'] === $from->toString()
            && $draft['payload']['To'] === self::TEST_RECIPIENT
            && $draft['payload']['MessageStream'] === $stream, 409, 'Die Vorschau ist abgelaufen oder der Versandzugang wurde geändert.');
        abort_unless(($draft['payload']['ReplyTo'] ?? null) === self::TEST_REPLY_TO, 409,
            'Die Antwortadresse in dieser Vorschau ist nicht aktuell. Bitte mit „Test“ eine neue Vorschau öffnen.');
        $message = $this->workPostmarkTestBody();
        abort_unless(($draft['payload']['HtmlBody'] ?? null) === $message['HtmlBody'] && ($draft['payload']['TextBody'] ?? null) === $message['TextBody']
            && ($draft['payload']['Metadata'][self::TEST_LAYOUT_KEY] ?? null) === self::TEST_LAYOUT_VERSION,
            409, 'Die Testmail wurde inzwischen geändert. Bitte mit „Test“ eine neue Vorschau öffnen.');
        $request->session()->forget(self::TEST_SESSION);

        // Reserve the authorised single test before sending, even if the provider response is lost.
        $path = Storage::disk('local')->path(self::TEST_RECEIPT);
        $receipt = @fopen($path, 'x');
        if ($receipt === false) {
            return $this->postmarkTestResult(is_file($path) ? 'uncertain' : 'storage_unavailable');
        }
        $status = 'uncertain';
        $acceptedMessageId = null;
        $diagnostics = [];
        try {
            $response = $this->postmark($token)->post('https://api.postmarkapp.com/email', $draft['payload']);
            $diagnostics['http_status'] = $response->status();
            $messageId = $response->json('MessageID');
            $errorCode = $response->json('ErrorCode');
            if (is_int($errorCode) && $errorCode > 0) {
                $diagnostics['provider_error_code'] = $errorCode;
            }
            if ($response->ok() && $errorCode === 0 && is_string($messageId) && Str::isUuid($messageId)) {
                $status = 'accepted';
                $acceptedMessageId = $messageId;
            } elseif ($response->clientError() && is_int($errorCode) && $errorCode > 0) {
                $status = match ($errorCode) {
                    300 => 'invalid_payload',
                    400 => 'sender_signature_missing',
                    401 => 'sender_signature_unconfirmed',
                    default => preg_match('/sender signature|from address.{0,100}(?:not confirmed|not verified)/i', (string) $response->json('Message'))
                        ? 'sender_signature_rejected' : 'rejected',
                };
            }
        } catch (ConnectionException) {
            // The provider may have completed the operation. Keep the receipt locked against retries.
        } finally {
            fwrite($receipt, json_encode(['status' => $status, 'sender' => $from->toString(), 'reply_to' => self::TEST_REPLY_TO, 'recipient' => self::TEST_RECIPIENT,
                'layout_version' => self::TEST_LAYOUT_VERSION, 'message_id' => $acceptedMessageId, 'recorded_at' => now()->toIso8601String(),
                ...$diagnostics], JSON_THROW_ON_ERROR));
            fclose($receipt);
        }

        return $this->postmarkTestResult($status, diagnostics: $diagnostics);
    }

    /** @return array<string, mixed> */
    private function workPostmarkTestPayload(Address $from, string $stream): array
    {
        return [
            'From' => $from->toString(), 'ReplyTo' => self::TEST_REPLY_TO, 'To' => self::TEST_RECIPIENT,
            'Subject' => self::TEST_SUBJECT, 'MessageStream' => $stream,
            ...$this->workPostmarkTestBody(),
            'Metadata' => [self::TEST_LAYOUT_KEY => self::TEST_LAYOUT_VERSION],
            'TrackOpens' => false, 'TrackLinks' => 'None',
        ];
    }

    /** @return array{TextBody: string, HtmlBody: string} */
    private function workPostmarkTestBody(): array
    {
        $salutation = 'Guten Tag,';
        $paragraph = 'dies ist eine technische Testnachricht von Schooltool über Postmark.';
        $signature = self::TEST_SIGNATURE;

        return [
            'TextBody' => implode("\n\n", [$salutation, $paragraph, implode("\n", $signature), 'Anhänge: keine']),
            'HtmlBody' => view('emails.schooltool-postmark-test', ['subject' => self::TEST_SUBJECT, 'salutation' => $salutation,
                'paragraph' => $paragraph, 'signature' => $signature])->render(),
        ];
    }

    /** @return array{sender: string, reply_to: string, recipient: string, subject: string, html: string} */
    private function workPostmarkTestPreview(Address $from, string $html): array
    {
        return ['sender' => $from->getName().' <'.$from->getAddress().'>', 'reply_to' => self::TEST_REPLY_TO,
            'recipient' => self::TEST_RECIPIENT, 'subject' => self::TEST_SUBJECT, 'html' => $html];
    }

    private function workPostmarkTestActor(Request $request, TeachingCourseWork $work): User
    {
        abort_unless(app()->isLocal() && in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $actor->email === self::TEST_ACTOR && $actor->email_verified_at && $work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        return $actor;
    }

    /** @return array{string, ?Address, string} */
    private function postmarkTestConfiguration(): array
    {
        $sender = (string) config('services.postmark.work_test_sender');
        $name = (string) config('services.postmark.work_test_sender_name');
        $from = filter_var($sender, FILTER_VALIDATE_EMAIL) && $name !== '' ? new Address($sender, $name) : null;

        return [(string) config('services.postmark.token'), $from, (string) (config('mail.mailers.postmark.message_stream_id') ?: 'outbound')];
    }

    /** @param array<string, string> $preview */
    private function postmarkTestResult(string $status, array $preview = [], array $diagnostics = []): JsonResponse
    {
        $message = match ($status) {
            'accepted' => 'Postmark hat die Testmail angenommen. Die Zustellung ist noch nicht bestätigt.',
            'invalid_payload' => 'Postmark konnte die Versanddaten der Testmail nicht verarbeiten.',
            'rejected' => 'Postmark hat die Testmail abgelehnt. Bitte Absender und Versandstream prüfen.',
            'sender_pending' => 'Der Versandtest wartet auf die bestätigte E-Mail-Adresse zu „CDGym Info“. Es wurde keine Testmail gesendet.',
            'sender_signature_missing' => 'Postmark hat die Testmail abgelehnt. Die Absendersignatur wurde nicht gefunden.',
            'sender_signature_unconfirmed' => 'Postmark hat die Testmail abgelehnt. Die Absendersignatur ist noch nicht bestätigt.',
            'sender_signature_rejected' => 'Postmark hat die Absendersignatur abgelehnt. Bitte den Bestätigungsstatus bei Postmark prüfen.',
            'uncertain' => 'Die Annahme durch Postmark ist unklar. Bitte zuerst den Postmark-Versandstatus und das Empfängerpostfach prüfen.',
            'unverified' => 'Der Postmark-Server für den Versand konnte nicht bestätigt werden. Es wurde keine Testmail gesendet.',
            'storage_unavailable' => 'Der lokale Versandbeleg konnte nicht angelegt werden. Es wurde keine Testmail gesendet.',
            default => 'Postmark ist in dieser lokalen Schooltool-Installation nicht für den Versand eingerichtet.',
        };
        $diagnostics = array_filter($diagnostics, is_int(...));
        if ($status !== 'accepted' && isset($diagnostics['http_status']) && $diagnostics['http_status'] >= 400) {
            $message .= ' HTTP-Status '.$diagnostics['http_status'].'.';
        }
        if ($status !== 'accepted' && isset($diagnostics['provider_error_code'])) {
            $message .= ' Postmark-Fehlercode '.$diagnostics['provider_error_code'].'.';
        }

        return response()->json(['status' => $status, 'message' => $message] + $preview + $diagnostics)->header('Cache-Control', 'private, no-store');
    }

    private function postmark(string $token, int $timeout = 15): PendingRequest
    {
        return Http::withHeaders(['X-Postmark-Server-Token' => $token])->acceptJson()->connectTimeout(5)->timeout($timeout)
            ->withOptions(['allow_redirects' => false]);
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
