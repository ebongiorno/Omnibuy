<?php

/**
 * Sends plain-text email over SMTP (UC-03 password reset emails).
 *
 * Settings come from the web container's environment (docker-compose.yml):
 *   MAIL_HOST  SMTP server. Locally this is the Mailpit container, which
 *              catches every email; open http://localhost:8025 to read them.
 *   MAIL_PORT  default 1025 (Mailpit)
 *   MAIL_FROM  sender address, default no-reply@omnibuy.local
 *
 * With no MAIL_HOST the email is written to the PHP error log instead
 * (`docker compose logs web`), so the site still works without Mailpit.
 */

declare(strict_types=1);

/**
 * @return bool true if the SMTP server accepted the message
 */
function sendMail(string $to, string $subject, string $body): bool
{
    $host = getenv('MAIL_HOST') ?: '';
    $port = (int) (getenv('MAIL_PORT') ?: 1025);
    $from = getenv('MAIL_FROM') ?: 'no-reply@omnibuy.local';

    // Addresses and the subject go into SMTP commands and headers, so a
    // line break in them could inject extra recipients or headers.
    if (
        filter_var($to, FILTER_VALIDATE_EMAIL) === false
        || filter_var($from, FILTER_VALIDATE_EMAIL) === false
        || preg_match('/[\r\n]/', $subject)
    ) {
        error_log('sendMail: rejected an invalid address or subject');
        return false;
    }

    if ($host === '') {
        error_log("sendMail: MAIL_HOST is not set, so this email was not sent.\nTo: $to\nSubject: $subject\n\n$body");
        return false;
    }

    $socket = @stream_socket_client("tcp://$host:$port", $errorCode, $errorMessage, 5);
    if ($socket === false) {
        error_log("sendMail: could not connect to $host:$port ($errorMessage)");
        return false;
    }
    stream_set_timeout($socket, 5);

    $message = buildMailMessage($from, $to, $subject, $body);

    $sent = smtpExpect($socket, 220)
        && smtpCommand($socket, 'EHLO omnibuy.local', 250)
        && smtpCommand($socket, "MAIL FROM:<$from>", 250)
        && smtpCommand($socket, "RCPT TO:<$to>", 250)
        && smtpCommand($socket, 'DATA', 354)
        && smtpCommand($socket, $message . "\r\n.", 250);

    @fwrite($socket, "QUIT\r\n");
    fclose($socket);

    if (!$sent) {
        error_log("sendMail: $host:$port did not accept the message to $to");
    }

    return $sent;
}

/**
 * Headers and body of a plain-text email, with CRLF line endings and
 * SMTP dot-stuffing (a line starting with "." gets a second one).
 */
function buildMailMessage(string $from, string $to, string $subject, string $body): string
{
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        "From: OmniBuy <$from>",
        "To: <$to>",
        'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $lines = preg_split('/\r\n|\r|\n/', $body);
    $lines = array_map(static fn (string $line): string => str_starts_with($line, '.') ? '.' . $line : $line, $lines);

    return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $lines);
}

/**
 * @param resource $socket
 */
function smtpCommand($socket, string $command, int $expectedCode): bool
{
    return fwrite($socket, $command . "\r\n") !== false && smtpExpect($socket, $expectedCode);
}

/**
 * Reads one (possibly multi-line) SMTP reply and checks its status code.
 *
 * @param resource $socket
 */
function smtpExpect($socket, int $expectedCode): bool
{
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            return false;
        }
    } while (isset($line[3]) && $line[3] === '-'); // "250-..." means more lines follow

    return (int) substr($line, 0, 3) === $expectedCode;
}
