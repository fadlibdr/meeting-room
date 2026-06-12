<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Guards server-side outbound requests (webhooks) against SSRF: a target URL is
 * only allowed if it is http(s) and every IP its host resolves to is a public,
 * routable address. This blocks loopback (127.0.0.0/8, ::1), private ranges
 * (10/8, 172.16/12, 192.168/16, fc00::/7), and link-local — including the cloud
 * metadata endpoint 169.254.169.254.
 *
 * Resolution happens at BOTH validation time and send time, because DNS can be
 * re-pointed to an internal address after a URL is saved (DNS rebinding).
 */
final class PublicUrlGuard
{
    public static function isPublicUrl(string $url): bool
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = trim($parts['host'], '[]'); // strip IPv6 literal brackets
        $ips = self::resolve($host);

        if ($ips === []) {
            return false; // unresolvable host → reject rather than fail open
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];

        $a = @gethostbynamel($host);
        if (is_array($a)) {
            $ips = array_merge($ips, $a);
        }

        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
