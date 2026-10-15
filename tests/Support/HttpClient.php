<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Support;

/**
 * Minimal browser for functional tests: keeps cookies between requests
 * and does not follow redirects, so tests can check where they point.
 */
final class HttpClient
{
    /** @var array<string, string> */
    private array $cookies = [];

    /** @var array<string, string> raw Set-Cookie headers from the last response, by cookie name */
    public array $lastSetCookies = [];

    public function __construct(private readonly string $baseUrl)
    {
    }

    public static function fromEnvironment(): self
    {
        return new self(rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));
    }

    /**
     * @return array{status: int, location: ?string, body: string}
     */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @return array{status: int, location: ?string, body: string}
     */
    public function post(string $path, array $fields): array
    {
        return $this->request('POST', $path, $fields);
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * Reads the hidden CSRF token from a page's form.
     */
    public static function csrfToken(string $html): string
    {
        if (!preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches)) {
            throw new \RuntimeException('No csrf_token field found on the page');
        }

        return $matches[1];
    }

    /**
     * @return array{status: int, location: ?string, body: string}
     */
    private function request(string $method, string $path, array $fields = []): array
    {
        $headers = [];
        $handle = curl_init($this->baseUrl . $path);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[] = [strtolower(trim($parts[0])), trim($parts[1])];
                }
                return strlen($line);
            },
        ]);

        if ($this->cookies !== []) {
            $pairs = array_map(
                static fn (string $name, string $value): string => "$name=$value",
                array_keys($this->cookies),
                $this->cookies
            );
            curl_setopt($handle, CURLOPT_COOKIE, implode('; ', $pairs));
        }

        if ($method === 'POST') {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        $body = curl_exec($handle);
        if ($body === false) {
            throw new \RuntimeException('Request to ' . $path . ' failed: ' . curl_error($handle));
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        $location = null;
        $this->lastSetCookies = [];
        foreach ($headers as [$name, $value]) {
            if ($name === 'location') {
                $location = $value;
            }
            if ($name === 'set-cookie') {
                [$pair] = explode(';', $value, 2);
                [$cookieName, $cookieValue] = array_pad(explode('=', $pair, 2), 2, '');
                $this->lastSetCookies[$cookieName] = $value;

                if ($cookieValue === '' || $cookieValue === 'deleted' || stripos($value, 'expires=Thu, 01 Jan 1970') !== false) {
                    unset($this->cookies[$cookieName]);
                } else {
                    $this->cookies[$cookieName] = $cookieValue;
                }
            }
        }

        return ['status' => $status, 'location' => $location, 'body' => (string) $body];
    }
}
