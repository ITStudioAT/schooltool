<?php

namespace App\Support;

use InvalidArgumentException;

class RemoteUrlGuard
{
    /**
     * @return array{url:string,host:string,port:int,ip:string}
     */
    public function resolve(string $url): array
    {
        $url = trim($url);
        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);

        if (! is_string($validatedUrl) || $validatedUrl === '' || mb_strlen($validatedUrl) > 2048) {
            throw new InvalidArgumentException('Invalid remote URL.');
        }

        $parts = parse_url($validatedUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim(strtolower(rtrim((string) ($parts['host'] ?? ''), '.')), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('Invalid remote URL.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Credentials are not allowed in remote URLs.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('Only standard HTTP ports are allowed.');
        }

        $addresses = $this->resolveAddresses($host);
        $publicAddress = collect($addresses)->first(fn (string $address): bool => $this->isPublicAddress($address));

        if (! is_string($publicAddress)) {
            throw new InvalidArgumentException('The remote host does not resolve to a public address.');
        }

        return [
            'url' => $validatedUrl,
            'host' => $host,
            'port' => $port,
            'ip' => $publicAddress,
        ];
    }

    /** @return array<int, string> */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return [];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        return collect($records)
            ->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter(fn (?string $address): bool => is_string($address) && $address !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function isPublicAddress(string $address): bool
    {
        $normalizedAddress = strtolower($address);

        if (str_starts_with($normalizedAddress, '::ffff:')) {
            $mappedAddress = substr($normalizedAddress, 7);

            if (filter_var($mappedAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $normalizedAddress = $mappedAddress;
            }
        }

        return filter_var(
            $normalizedAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
