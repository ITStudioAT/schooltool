<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class FeaturePreviewMailService
{
    public function __construct(private FeaturePreviewService $preview) {}

    public function configurationIsSafe(?string $mailer = null): bool
    {
        $origin = rtrim((string) config('app.url'), '/');
        $previewUrl = $this->preview->adminUrl('url');
        $liveUrl = $this->preview->adminUrl('live_url');
        $parts = parse_url($origin);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment'])
            && $previewUrl === $origin.'/admin'
            && strtolower((string) ($parts['host'] ?? '')) === strtolower((string) config('schooltool.preview.expected_host'))
            && $liveUrl !== null
            && strtolower((string) parse_url($liveUrl, PHP_URL_HOST)) !== strtolower((string) ($parts['host'] ?? ''))
            && $this->transportIsSafe($mailer);
    }

    private function transportIsSafe(?string $mailer): bool
    {
        try {
            $resolvedMailer = Mail::mailer($mailer);
            $transport = $resolvedMailer->getSymfonyTransport();
            if ($transport instanceof ArrayTransport) {
                return app()->runningUnitTests();
            }

            if (! $transport instanceof EsmtpTransport || ! $transport->getStream() instanceof SocketStream) {
                return false;
            }

            $stream = $transport->getStream();
            $ssl = $stream->getStreamOptions()['ssl'] ?? [];
            $host = $stream->getHost();

            $safe = ($stream->isTLS() || ($transport->isTlsRequired() && $transport->isAutoTls()))
                && filter_var($ssl['verify_peer'] ?? true, FILTER_VALIDATE_BOOLEAN)
                && filter_var($ssl['verify_peer_name'] ?? true, FILTER_VALIDATE_BOOLEAN)
                && ! filter_var($ssl['allow_self_signed'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
                    || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false)
                && $stream->getPort() > 0 && $stream->getPort() <= 65535
                && trim($transport->getUsername()) !== '' && $transport->getPassword() !== ''
                && is_finite($stream->getTimeout()) && $stream->getTimeout() > 0;

            if ($safe && $this->preview->isPreview() && ! $transport instanceof FeaturePreviewSmtpTransport) {
                $options = (new ConfigurationUrlParser)->parseConfiguration(config('mail.mailers.'.($mailer ?? config('mail.default')), []));
                $resolvedMailer->setSymfonyTransport(new FeaturePreviewSmtpTransport($transport, $options));
            }

            return $safe;
        } catch (Throwable) {
            return false;
        }
    }

    public function filterNotification(NotificationSending $event): ?bool
    {
        if (! $this->preview->isPreview() || $event->channel !== 'mail') {
            return null;
        }

        $notification = $event->notification;
        $context = $notification instanceof StandardEmail ? $notification->previewAuthentication : null;
        $recipients = [];

        try {
            $route = $event->notifiable->routeNotificationFor('mail', $notification);
            $recipients = $this->notificationRecipients($route);
            $reason = $this->rejectionReason($context, $recipients, (bool) ($notification->attachments ?? null));

            if ($reason === null) {
                URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
                URL::forceScheme('https');

                return null;
            }
        } catch (Throwable) {
            $reason = 'authorization_unavailable';
        }

        $this->audit('notification', $notification::class, $context, $recipients, $reason);

        return false;
    }

    public function filterMessage(MessageSending $event): ?bool
    {
        if (! $this->preview->isPreview()) {
            return null;
        }

        $context = ($event->data['__laravel_notification'] ?? null) === StandardEmail::class
            ? ($event->data['__schooltool_preview_auth'] ?? null)
            : null;
        $message = $event->message;
        $recipients = array_map(
            fn (Address $address): string => $address->getAddress(),
            [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()],
        );

        try {
            $reason = $this->rejectionReason($context, $recipients, $message->getAttachments() !== [], $event->data['mailer'] ?? null);

            if ($reason === null && ($message->getCc() !== [] || $message->getBcc() !== [])) {
                $reason = 'additional_recipients';
            }

            if ($reason === null && ! $this->messageLinksAreSafe($message)) {
                $reason = 'non_preview_link';
            }

            if ($reason === null) {
                $this->markAsPreview($message, $context['login_path']);

                return null;
            }
        } catch (Throwable) {
            $reason = 'authorization_unavailable';
        }

        $this->audit('mail', (string) ($event->data['__laravel_notification'] ?? $event->data['__laravel_mailable'] ?? 'mail'), $context, $recipients, $reason);

        return false;
    }

    /** @param array<int, string> $recipients */
    private function rejectionReason(mixed $context, array $recipients, bool $hasAttachments, ?string $mailer = null): ?string
    {
        if (! $this->configurationIsSafe($mailer)) {
            return 'unsafe_configuration';
        }

        if (! is_array($context)
            || ! in_array($context['purpose'] ?? null, ['login', 'password_reset'], true)
            || ! in_array($context['recipient_kind'] ?? null, ['account', 'second_factor', 'teaching_parent', 'restaurant_parent'], true)
            || ! in_array($context['login_path'] ?? null, ['/', '/admin'], true)
            || ! is_int($context['user_id'] ?? null)
            || ! is_int($context['school_id'] ?? null)
            || ! is_string($context['recipient'] ?? null)
            || ! array_key_exists('schoolyear_id', $context)
            || ($context['schoolyear_id'] !== null && ! is_int($context['schoolyear_id']))) {
            return 'unmarked_message';
        }

        if ($hasAttachments) {
            return 'authentication_attachment';
        }

        $expectedRecipient = app(EmailAliasResolver::class)->resolve($context['recipient']);
        if (count($recipients) !== 1
            || ! filter_var($expectedRecipient, FILTER_VALIDATE_EMAIL)
            || strcasecmp(trim($recipients[0]), trim($expectedRecipient)) !== 0) {
            return 'recipient_mismatch';
        }

        $user = User::query()->find($context['user_id']);
        if (! $user || (int) $user->school_id !== $context['school_id']
            || ! $this->preview->authenticationRecipientAllowed($user, $context['recipient'], $context['recipient_kind'], $context['schoolyear_id'])) {
            return 'account_not_authorized';
        }

        return null;
    }

    /** @return array<int, string> */
    private function notificationRecipients(mixed $route): array
    {
        if (is_string($route)) {
            return [$route];
        }

        if (! is_array($route)) {
            return [];
        }

        $recipients = [];
        foreach ($route as $address => $name) {
            $recipient = is_int($address) ? $name : $address;
            if (! is_string($recipient)) {
                return [];
            }
            $recipients[] = $recipient;
        }

        return $recipients;
    }

    private function messageLinksAreSafe(Email $message): bool
    {
        preg_match_all('~(?:href|src)\s*=\s*["\']([^"\']+)["\']~i', $message->getHtmlBody() ?? '', $htmlLinks);
        preg_match_all('~https?://[^\s<>]+~i', $message->getTextBody() ?? '', $textLinks);

        $origin = parse_url((string) config('app.url'));
        foreach ([...$htmlLinks[1], ...$textLinks[0]] as $link) {
            $link = html_entity_decode($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! preg_match('~^(?:https?:)?//~i', $link)) {
                continue;
            }

            $parts = parse_url($link);
            if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
                || strtolower((string) ($parts['host'] ?? '')) !== strtolower((string) ($origin['host'] ?? ''))
                || ($parts['port'] ?? 443) !== ($origin['port'] ?? 443)
                || isset($parts['user']) || isset($parts['pass'])) {
                return false;
            }
        }

        return true;
    }

    private function markAsPreview(Email $message, string $loginPath): void
    {
        $url = rtrim((string) config('app.url'), '/').$loginPath;
        $notice = 'VORSCHAU: Diese Nachricht stammt aus der SchoolTool-Testumgebung. Änderungen gelten nur für die Vorschau.';
        $message->subject('[VORSCHAU] '.preg_replace('/^(?:\[VORSCHAU\]\s*)+/u', '', $message->getSubject() ?? ''));

        $banner = '<div style="padding:16px;border:2px solid #b45309;background:#fffbeb"><strong>'.e($notice).'</strong><br><a href="'.e($url).'">Vorschau öffnen</a></div>';
        $html = $message->getHtmlBody();
        if ($html !== null) {
            $htmlWithBanner = preg_replace_callback('/<body\b[^>]*>/i', fn (array $match): string => $match[0].$banner, $html, 1, $count);
            $message->html($count > 0 ? $htmlWithBanner : $banner.$html);
        }

        $message->text($notice."\nVorschau öffnen: {$url}\n\n".($message->getTextBody() ?? ''));
    }

    /** @param array<int, string> $recipients */
    private function audit(string $stage, string $messageType, mixed $context, array $recipients, string $reason): void
    {
        Log::channel('single')->info('feature_preview.mail_intercepted', [
            'stage' => $stage,
            'message_type' => $messageType,
            'reason' => $reason,
            'purpose' => is_array($context) && in_array($context['purpose'] ?? null, ['login', 'password_reset'], true) ? $context['purpose'] : null,
            'user_id' => is_array($context) && is_int($context['user_id'] ?? null) ? $context['user_id'] : null,
            'school_id' => is_array($context) && is_int($context['school_id'] ?? null) ? $context['school_id'] : null,
            'recipient_count' => count($recipients),
            'recipient_hashes' => array_map(fn (string $recipient): string => hash('sha256', strtolower(trim($recipient))), $recipients),
        ]);
    }
}
