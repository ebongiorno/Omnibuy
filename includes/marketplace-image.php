<?php

declare(strict_types=1);

/**
 * Normalize an image URL to an approved format.
 * Invalid or missing values use the configured fallback.
 */
function marketplaceImageUrl(
    mixed $value,
    string $fallback = '/assets/images/listing/listing-default-img.jpg'
): string {
    if (!is_string($value)) {
        return $fallback;
    }

    $url = trim($value);

    // Reject empty values, control characters, quotes,
    // backslashes, whitespace, and HTML-significant characters.
    if (
        $url === ''
        || preg_match('/[\x00-\x20\x7F\\\\<>"\'`]/', $url)
    ) {
        return $fallback;
    }

    // Allow local files under OmniBuy's approved directories.
    $localUrl = $url;

    if (
        str_starts_with($localUrl, 'assets/')
        || str_starts_with($localUrl, 'uploads/')
    ) {
        $localUrl = '/' . $localUrl;
    }

    if (
        str_starts_with($localUrl, '/assets/')
        || str_starts_with($localUrl, '/uploads/')
    ) {
        $path = parse_url($localUrl, PHP_URL_PATH);

        if (!is_string($path)) {
            return $fallback;
        }

        $decodedPath = rawurldecode($path);

        foreach (explode('/', $decodedPath) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return $fallback;
            }
        }

        // Reject encoded separators and unexpected path characters.
        if (
            preg_match('/[\x00-\x20\x7F\\\\<>"\'`?#]/', $decodedPath)
            || str_contains($decodedPath, '//')
        ) {
            return $fallback;
        }

        return $localUrl;
    }

    // Keep external HTTPS images if supported by the project.
    if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
        $scheme = strtolower(
            (string) parse_url($url, PHP_URL_SCHEME)
        );

        $host = parse_url($url, PHP_URL_HOST);
        $user = parse_url($url, PHP_URL_USER);
        $pass = parse_url($url, PHP_URL_PASS);

        if (
            $scheme === 'https'
            && is_string($host)
            && $host !== ''
            && $user === null
            && $pass === null
        ) {
            return $url;
        }
    }

    return $fallback;
}
