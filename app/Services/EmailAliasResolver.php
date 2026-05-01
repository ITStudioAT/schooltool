<?php

namespace App\Services;

use Illuminate\Notifications\AnonymousNotifiable;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailAliasResolver
{
    public static function resolveConfigured(mixed $route): mixed
    {
        return app(self::class)->resolveRoute($route);
    }

    public function resolve(string $email): string
    {
        $email = trim($email);
        $normalizedEmail = mb_strtolower($email);

        return $this->aliases()[$normalizedEmail] ?? $email;
    }

    public function rewriteMessageRecipients(Email $message): void
    {
        $this->rewriteTo($message);
        $this->rewriteCc($message);
        $this->rewriteBcc($message);
    }

    public function rewriteNotificationMailRoute(mixed $notifiable): void
    {
        if (! $notifiable instanceof AnonymousNotifiable) {
            return;
        }

        if (! array_key_exists('mail', $notifiable->routes)) {
            return;
        }

        $notifiable->routes['mail'] = $this->resolveRoute($notifiable->routes['mail']);
    }

    /**
     * @return array<string, string>
     */
    private function aliases(): array
    {
        $aliases = config('schooltool.email_aliases', []);

        if (! is_array($aliases)) {
            return [];
        }

        $normalized = [];
        foreach ($aliases as $virtualEmail => $realEmail) {
            $virtualEmail = mb_strtolower(trim((string) $virtualEmail));
            $realEmail = trim((string) $realEmail);

            if ($virtualEmail === '' || $realEmail === '') {
                continue;
            }

            $normalized[$virtualEmail] = $realEmail;
        }

        return $normalized;
    }

    private function rewriteTo(Email $message): void
    {
        $addresses = $message->getTo();

        if ($addresses === []) {
            return;
        }

        $message->to(...$this->resolveAddresses($addresses));
    }

    private function rewriteCc(Email $message): void
    {
        $addresses = $message->getCc();

        if ($addresses === []) {
            return;
        }

        $message->cc(...$this->resolveAddresses($addresses));
    }

    private function rewriteBcc(Email $message): void
    {
        $addresses = $message->getBcc();

        if ($addresses === []) {
            return;
        }

        $message->bcc(...$this->resolveAddresses($addresses));
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, Address>
     */
    private function resolveAddresses(array $addresses): array
    {
        $resolvedAddresses = [];
        $seenEmails = [];

        foreach ($addresses as $address) {
            $resolvedAddress = $this->resolveAddress($address);
            $dedupeKey = mb_strtolower($resolvedAddress->getAddress());

            if (isset($seenEmails[$dedupeKey])) {
                continue;
            }

            $seenEmails[$dedupeKey] = true;
            $resolvedAddresses[] = $resolvedAddress;
        }

        return $resolvedAddresses;
    }

    private function resolveAddress(Address $address): Address
    {
        $resolvedEmail = $this->resolve($address->getAddress());

        if (mb_strtolower($resolvedEmail) === mb_strtolower($address->getAddress())) {
            return $address;
        }

        return new Address($resolvedEmail, $address->getName());
    }

    public function resolveRoute(mixed $route): mixed
    {
        if (is_string($route)) {
            return $this->resolve($route);
        }

        if (! is_array($route)) {
            return $route;
        }

        $resolvedRoute = [];

        foreach ($route as $email => $name) {
            if (is_int($email)) {
                $resolvedRoute[] = is_string($name) ? $this->resolve($name) : $name;

                continue;
            }

            $resolvedRoute[$this->resolve((string) $email)] = $name;
        }

        return $resolvedRoute;
    }
}
