<?php

namespace App\Gateways\Prism;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Keeps calls to a user-typed base URL on the public internet. Our server makes the request, so without this any
 * account could aim it at localhost, the private network or the cloud metadata address (SSRF).
 */
class PublicEndpoint
{
    /**
     * The public IP to call for this URL, or null when it isn't plain HTTPS or its host doesn't resolve, or resolves
     * to any private, loopback, link-local or reserved address.
     */
    public function addressFor(string $url): ?string
    {
        $host = self::host($url);

        if ($host === null) {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->lookup($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                return null;
            }
        }

        return $addresses[0];
    }

    /**
     * Guzzle options that connect to the address checked above, so the host can't resolve somewhere else between the
     * check and the call (DNS rebinding), and that never follow a redirect to another host.
     *
     * @return array<string, mixed>
     */
    public function pinnedClientOptions(string $url, string $address): array
    {
        $host = (string) self::host($url);

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return ['allow_redirects' => false];
        }

        $port = parse_url($url, PHP_URL_PORT) ?? 443;

        return ['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]]];
    }

    /** @return list<string> */
    protected function lookup(string $host): array
    {
        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : $addresses;
    }

    /** The host of an `https://` URL without credentials in it; IPv6 brackets removed. */
    private static function host(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return trim($parts['host'], '[]');
    }

    private static function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! IpUtils::isPrivateIp($address);
    }
}
