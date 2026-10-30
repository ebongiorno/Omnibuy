<?php

declare(strict_types=1);

$testFailures = 0;

function testPass(string $message): void
{
    echo "PASSED: {$message}" . PHP_EOL;
}

function testFail(string $message): void
{
    global $testFailures;

    echo "FAILED: {$message}" . PHP_EOL;
    $testFailures++;
}

function assertContains(
    string $needle,
    string $haystack,
    string $message
): void {
    if (str_contains($haystack, $needle)) {
        testPass($message);
    } else {
        testFail($message);
    }
}

function assertNotContains(
    string $needle,
    string $haystack,
    string $message
): void {
    if (!str_contains($haystack, $needle)) {
        testPass($message);
    } else {
        testFail($message);
    }
}

function httpGet(
    string $path,
    array $params = []
): string {
    $url = 'http://127.0.0.1' . $path;

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $response = file_get_contents(
        $url,
        false,
        $context
    );

    if ($response === false) {
        testFail("HTTP request failed: {$url}");
        return '';
    }

    return $response;
}

function finishTests(): never
{
    global $testFailures;

    echo PHP_EOL;

    if ($testFailures > 0) {
        echo "{$testFailures} test(s) failed." . PHP_EOL;
        exit(1);
    }

    echo "All tests passed." . PHP_EOL;
    exit(0);
}