<?php

namespace Extensions\Modules\Tickets\Support;

use Illuminate\Support\Str;

class InboundMailParser
{
    public static function parse(string $raw): InboundMailMessage
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        [$headerBlock, $body] = static::splitHeadersAndBody($raw);
        $headers = static::parseHeaders($headerBlock);

        $from = static::extractEmails($headers['from'] ?? '');
        $fromName = static::extractDisplayName($headers['from'] ?? '');

        $recipients = array_values(array_unique(array_merge(
            static::extractEmails($headers['to'] ?? ''),
            static::extractEmails($headers['cc'] ?? ''),
            static::extractEmails($headers['delivered-to'] ?? ''),
            static::extractEmails($headers['x-original-to'] ?? ''),
            static::extractEmails($headers['envelope-to'] ?? ''),
            static::extractEmails($headers['x-forwarded-to'] ?? ''),
        )));

        $text = static::extractText($headers, $body);
        $text = static::stripQuotedReply($text);

        return new InboundMailMessage(
            fromEmail: $from[0] ?? null,
            fromName: $fromName,
            subject: static::decodeMimeHeader($headers['subject'] ?? ''),
            body: $text,
            messageId: static::normalizeMessageId($headers['message-id'] ?? null),
            recipients: $recipients,
            inReplyTo: static::extractMessageIds($headers['in-reply-to'] ?? ''),
            references: static::extractMessageIds($headers['references'] ?? ''),
            automatic: static::isAutomatic($headers),
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected static function extractText(array $headers, string $body): string
    {
        $contentType = strtolower($headers['content-type'] ?? 'text/plain');
        $type = static::mediaType($contentType);

        if (str_starts_with($type, 'multipart/')) {
            $plain = null;
            $html = null;

            foreach (static::parts($headers, $body) as $part) {
                [$partHeaders, $partBody] = $part;
                $partType = static::mediaType($partHeaders['content-type'] ?? '');
                $disposition = strtolower($partHeaders['content-disposition'] ?? '');

                if (str_starts_with($disposition, 'attachment')) {
                    continue;
                }

                $extracted = static::extractText($partHeaders, $partBody);

                if ($extracted === '') {
                    continue;
                }

                if ($partType === 'text/plain' || (str_starts_with($partType, 'multipart/') && $plain === null)) {
                    $plain ??= $extracted;
                }

                if ($partType === 'text/html') {
                    $html ??= $extracted;
                }
            }

            return $plain ?? $html ?? '';
        }

        $decoded = static::decodeBody($body, $headers);

        if ($type === 'text/html') {
            return static::htmlToText($decoded);
        }

        return trim($decoded);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<int, array{0: array<string, string>, 1: string}>
     */
    protected static function parts(array $headers, string $body): array
    {
        $contentType = $headers['content-type'] ?? '';
        $boundary = static::headerParameter($contentType, 'boundary');

        if ($boundary === null || $boundary === '') {
            return [];
        }

        $parts = explode('--'.$boundary, $body);
        $parsed = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || $part === '--') {
                continue;
            }

            [$partHeaders, $partBody] = static::splitHeadersAndBody($part);

            if ($partHeaders === '' && $partBody === '') {
                continue;
            }

            $parsed[] = [static::parseHeaders($partHeaders), $partBody];
        }

        return $parsed;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected static function splitHeadersAndBody(string $raw): array
    {
        $split = preg_split("/\n\n/", $raw, 2);

        if ($split === false || count($split) < 2) {
            return [$raw, ''];
        }

        return [$split[0], $split[1]];
    }

    /**
     * @return array<string, string>
     */
    protected static function parseHeaders(string $headerBlock): array
    {
        $headerBlock = preg_replace("/\n[ \t]+/", ' ', $headerBlock) ?? $headerBlock;
        $headers = [];

        foreach (preg_split("/\n/", $headerBlock) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));

            if ($key === '') {
                continue;
            }

            $value = trim($value);
            $headers[$key] = isset($headers[$key]) ? $headers[$key].' '.$value : $value;
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected static function decodeBody(string $body, array $headers): string
    {
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '7bit');

        $decoded = match ($encoding) {
            'base64' => (string) base64_decode($body, true),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };

        $charset = static::headerParameter($headers['content-type'] ?? '', 'charset') ?: 'UTF-8';
        $charset = strtoupper($charset);

        if ($charset !== 'UTF-8' && $charset !== 'UTF8' && $charset !== 'US-ASCII' && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);

            if (is_string($converted) && $converted !== '') {
                $decoded = $converted;
            }
        }

        return str_replace("\r\n", "\n", $decoded);
    }

    protected static function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/p>/i', "\n\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected static function stripQuotedReply(string $body): string
    {
        $body = str_replace("\r\n", "\n", $body);

        $delimiters = [
            '/\nOn .{10,240} wrote:\s*$/mu',
            '/\n-+ ?Original Message ?-+/i',
            '/\n_{5,}\s*\n/',
            '/\nFrom:\s.+\n(?:Sent|Date):\s/i',
        ];

        foreach ($delimiters as $delimiter) {
            $parts = preg_split($delimiter, $body, 2);

            if (is_array($parts) && count($parts) === 2) {
                $body = $parts[0];
            }
        }

        $lines = preg_split("/\n/", $body) ?: [];

        while ($lines !== []) {
            $last = end($lines);

            if ($last === false) {
                break;
            }

            if (trim($last) === '' || preg_match('/^\s*>/', $last)) {
                array_pop($lines);

                continue;
            }

            break;
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @return array<int, string>
     */
    protected static function extractEmails(string $value): array
    {
        if ($value === '') {
            return [];
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);

        return array_values(array_unique(array_map(
            fn (string $email) => Str::lower($email),
            $matches[0] ?? []
        )));
    }

    protected static function extractDisplayName(string $value): ?string
    {
        if (preg_match('/^\s*"([^"]+)"/', $value, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^\s*([^<]+)</', $value, $matches)) {
            $name = trim($matches[1]);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected static function extractMessageIds(string $value): array
    {
        if ($value === '') {
            return [];
        }

        preg_match_all('/<([^>]+)>/', $value, $matches);

        $ids = $matches[1] ?? [];

        if ($ids === [] && trim($value) !== '') {
            $ids = [trim($value, "<> \t")];
        }

        return array_values(array_filter(array_map(
            fn (string $id) => static::normalizeMessageId($id),
            $ids
        )));
    }

    protected static function normalizeMessageId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value, "<> \t\"");

        return $value !== '' ? Str::lower($value) : null;
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected static function isAutomatic(array $headers): bool
    {
        $autoSubmitted = strtolower($headers['auto-submitted'] ?? '');
        $precedence = strtolower($headers['precedence'] ?? '');
        $xAutoReply = strtolower($headers['x-autoreply'] ?? $headers['x-autorespond'] ?? '');

        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        if (in_array($precedence, ['bulk', 'list', 'junk'], true)) {
            return true;
        }

        return $xAutoReply === 'yes' || $xAutoReply === 'true' || $xAutoReply === '1';
    }

    protected static function mediaType(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    protected static function headerParameter(string $header, string $name): ?string
    {
        if (preg_match('/(?:^|;)\s*'.preg_quote($name, '/').'\s*=\s*("?)([^";]+)\1/i', $header, $matches)) {
            return trim($matches[2]);
        }

        return null;
    }

    protected static function decodeMimeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($value);
        }

        return $value;
    }
}
