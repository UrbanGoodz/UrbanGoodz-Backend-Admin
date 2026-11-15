<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Fetches a customer-supplied web page without letting the customer aim our server at itself.
 *
 * The server will request whatever address it is given, which makes it a proxy into everything it can reach: the
 * database host, cloud metadata endpoints, admin panels on a private network. So every hop is checked:
 *   - http/https only, standard ports only, no credentials in the URL;
 *   - the hostname is resolved HERE and every address must be public (no loopback, private, link-local, reserved);
 *   - the connection is pinned to that exact address, so a name that resolves differently a moment later (DNS
 *     rebinding) cannot swap in a private one;
 *   - redirects are followed by hand, at most 4, each re-checked the same way;
 *   - short timeouts, HTML only, and the download is cut off at 1.5 MB.
 */
class SafeUrlFetcher
{
    private const MAX_BYTES = 1_500_000;

    private const MAX_REDIRECTS = 4;

    /** @param \Closure|null $resolver fn(string $host): list<string> of IPs; injectable for tests */
    public function __construct(private ?\Closure $resolver = null) {}

    /** @return array{scheme: string, host: string, port: int, ips: list<string>} */
    public function assertSafe(string $url): array
    {
        $parts = parse_url(trim($url));
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('That is not a valid link.');
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only web links (http or https) can be opened.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Links with a username or password are not allowed.');
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('That link uses a port we do not open.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException('That address cannot be opened.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new InvalidArgumentException('We could not find that website.');
        }
        foreach ($ips as $ip) {
            if (! $this->isPublic($ip)) {
                throw new InvalidArgumentException('That address cannot be opened.');
            }
        }

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ips' => array_values($ips)];
    }

    /** @return array{url: string, html: string} */
    public function fetch(string $url): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $t = $this->assertSafe($url);
            $ip = $t['ips'][0];
            $resolveHost = $t['host'].':'.$t['port'].':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip);

            $response = Http::withOptions([
                'allow_redirects' => false,
                'timeout' => 6,
                'connect_timeout' => 3,
                'curl' => [
                    CURLOPT_RESOLVE => [$resolveHost],
                    CURLOPT_NOPROGRESS => false,
                    // Abort the transfer once more than MAX_BYTES has arrived.
                    CURLOPT_PROGRESSFUNCTION => fn ($ch, $dlTotal, $dlNow) => $dlNow > self::MAX_BYTES ? 1 : 0,
                ],
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; UrbanGoodzLinkPreview/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])->get($url);

            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');
                if (! $location) {
                    throw new RuntimeException('The website sent a broken redirect.');
                }
                $url = $this->absolutize($url, $location);

                continue;
            }
            if (! $response->successful()) {
                throw new RuntimeException('The website did not return a page.');
            }
            $type = strtolower((string) $response->header('Content-Type'));
            if ($type !== '' && ! str_contains($type, 'html')) {
                throw new RuntimeException('That link is not a web page.');
            }

            return ['url' => $url, 'html' => substr((string) $response->body(), 0, self::MAX_BYTES)];
        }

        throw new RuntimeException('The link redirected too many times.');
    }

    private function resolve(string $host): array
    {
        if ($this->resolver) {
            return ($this->resolver)($host);
        }
        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            if (! empty($r['ip'])) {
                $ips[] = $r['ip'];
            } elseif (! empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }

        return $ips ?: (@gethostbynamel($host) ?: []);
    }

    private function isPublic(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function absolutize(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $dir = rtrim(dirname($b['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$location;
    }
}
