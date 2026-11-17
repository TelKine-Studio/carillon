<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Outgoing connections chosen by project members (webhooks, integrations):
 * refused towards loopback, private, link-local (cloud metadata) and
 * reserved addresses, unless an instance administrator allows the private
 * network (Settings → Security), e.g. for a database next to Carillon.
 */
final class NetworkGuard
{
    public const BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.168.0.0/16', '198.18.0.0/15', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', 'fc00::/7', 'fe80::/10', 'ff00::/8',
    ];

    /** Tests resolve names without DNS. */
    public static ?\Closure $resolver = null;

    public static function allowsPrivate(): bool
    {
        return app(Settings::class)->boolean('security.private_network');
    }

    /** Error message for a URL, or null when it may be called. */
    public static function checkUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return __('Only http and https addresses are allowed.');
        }

        return self::checkHost($parts['host']);
    }

    /** Error message for a host name or address, or null when it may be reached. */
    public static function checkHost(string $host): ?string
    {
        $host = trim($host, '[]');
        $ips = self::resolve($host);
        if ($ips === []) {
            return __('The host :host cannot be resolved.', ['host' => $host]);
        }
        if (self::allowsPrivate()) {
            return null;
        }
        foreach ($ips as $ip) {
            if (IpUtils::checkIp($ip, self::BLOCKED)) {
                return __('The host :host is on a private or reserved network. An instance administrator can allow private networks in Settings → Security.', ['host' => $host]);
            }
        }

        return null;
    }

    /**
     * Options of the HTTP client pinning the host to the address checked, so
     * that a DNS answer changed in between (rebinding) is not followed.
     *
     * @return array<string, mixed>
     */
    public static function pin(string $url): array
    {
        $parts = parse_url($url);
        $host = trim($parts['host'] ?? '', '[]');
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }
        $ip = self::resolve($host)[0] ?? null;
        if (! $ip) {
            return [];
        }
        $port = $parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'http' ? 80 : 443);

        return ['curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)]]];
    }

    /** @return list<string> */
    public static function resolve(string $host): array
    {
        if (self::$resolver) {
            return (self::$resolver)($host);
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
