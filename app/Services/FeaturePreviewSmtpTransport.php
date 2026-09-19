<?php

namespace App\Services;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class FeaturePreviewSmtpTransport extends EsmtpTransport
{
    private bool $ehloSucceeded = false;

    /** @param array<string, mixed> $options */
    public function __construct(EsmtpTransport $transport, array $options = [])
    {
        /** @var SocketStream $stream */
        $stream = clone $transport->getStream();
        parent::__construct($stream->getHost(), $stream->getPort(), $stream->isTLS(), stream: $stream);

        $this->setUsername($transport->getUsername());
        $this->setPassword($transport->getPassword());
        $this->setLocalDomain($transport->getLocalDomain());
        $this->setAutoTls($transport->isAutoTls());
        $this->setRequireTls(true);
        $this->setMaxPerSecond((float) ($options['max_per_second'] ?? 0));
        $this->setRestartThreshold((int) ($options['restart_threshold'] ?? 100), (int) ($options['restart_threshold_sleep'] ?? 0));
        $this->setPingThreshold((int) ($options['ping_threshold'] ?? 100));
    }

    /**
     * Symfony's legacy HELO fallback returns before its required-TLS check.
     * Reject that fallback before any envelope, credentials or body are sent.
     *
     * @param  array<int, int>  $codes
     */
    public function executeCommand(string $command, array $codes): string
    {
        $helo = $codes === [250] && str_starts_with($command, 'HELO ');
        if ($helo) {
            $this->ehloSucceeded = false;
        }

        $response = parent::executeCommand($command, $codes);
        if ($codes === [250] && str_starts_with($command, 'EHLO ')) {
            $this->ehloSucceeded = true;
        }

        if ($helo && ! $this->ehloSucceeded) {
            throw new TransportException('Preview SMTP requires ESMTP with verified TLS; legacy HELO fallback is disabled.');
        }

        return $response;
    }
}
