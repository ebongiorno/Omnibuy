<?php
declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use PHPUnit\Framework\TestCase;

/** HTTP integration test base. Uses isolated cookie jars per test identity. */
abstract class SecurityTestCase extends TestCase
{
    /** @var array<string,resource> */
    private array $cookieJars = [];

    protected function config(string $key): string
    {
        return trim((string) (getenv($key) ?: ''));
    }

    protected function requireConfig(string ...$keys): void
    {
        foreach ($keys as $key) {
            if ($this->config($key) === '') {
                $this->markTestSkipped("Configure {$key} in the test environment; not assessed.");
            }
        }
    }

    protected function endpoint(string $pathKey): string
    {
        $this->requireConfig('OMNIBUY_BASE_URL', $pathKey);
        return rtrim($this->config('OMNIBUY_BASE_URL'), '/') . '/' . ltrim($this->config($pathKey), '/');
    }

    /** @return array{status:int,body:string,headers:array<string,string>} */
    protected function request(string $identity, string $method, string $pathKey, array $data = []): array
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('PHP curl extension is required; not assessed.');
        }
        $url = $this->endpoint($pathKey);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Cannot initialize cURL');
        }
        $cookieFile = $this->cookieJars[$identity] ?? tempnam(sys_get_temp_dir(), 'obsec_');
        if ($cookieFile === false) {
            throw new \RuntimeException('Cannot create temporary cookie jar');
        }
        $this->cookieJars[$identity] = $cookieFile;
        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$headers): int {
                $pieces = explode(':', $header, 2);
                if (count($pieces) === 2) {
                    $headers[strtolower(trim($pieces[0]))] = trim($pieces[1]);
                }
                return strlen($header);
            },
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Request failed: {$error}");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $body, 'headers' => $headers];
    }

    protected function login(string $identity, string $emailKey, string $passwordKey): void
    {
        $this->requireConfig($emailKey, $passwordKey, 'OMNIBUY_LOGIN_PATH');
        $result = $this->request($identity, 'POST', 'OMNIBUY_LOGIN_PATH', [
            $this->config('OMNIBUY_LOGIN_EMAIL_FIELD') ?: 'email' => $this->config($emailKey),
            $this->config('OMNIBUY_LOGIN_PASSWORD_FIELD') ?: 'password' => $this->config($passwordKey),
        ]);
        self::assertContains($result['status'], [200, 302, 303], 'Test account login must succeed');
    }

    protected function assertDenied(array $response): void
    {
        self::assertContains($response['status'], [401, 403, 302, 303], 'Expected access denial or login redirect');
        if (in_array($response['status'], [302, 303], true)) {
            $location = $response['headers']['location'] ?? '';
            self::assertNotSame('', $location, 'Redirect must include Location');
            $loginPath = $this->config('OMNIBUY_LOGIN_PATH');
            self::assertNotSame('', $loginPath, 'Configure login path to verify redirects');
            self::assertStringContainsString($loginPath, $location, 'Redirect should lead to login');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->cookieJars as $file) {
            if (is_string($file) && is_file($file)) {
                unlink($file);
            }
        }
        $this->cookieJars = [];
        parent::tearDown();
    }
}
