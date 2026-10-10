<?php

declare(strict_types=1);

namespace RtlyKit\Text;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;

/**
 * Persian / Arabic aware slug generator.
 *
 * Letters (any script) and digits are kept; whitespace and ZWNJ become the
 * separator; existing hyphens and underscores are preserved (repeats are
 * collapsed); everything else is dropped.
 *
 * Invalid UTF-8: both the text and the separator must be valid UTF-8; otherwise
 * an exception with {@see ErrorCode::InvalidArgument} is thrown (a slug is an
 * identifier, so garbage is rejected instead of passed through, unlike the
 * best-effort {@see Normalizer}). Dots are not letters, digits, hyphens or
 * underscores, so they are dropped ("v1.2" becomes "v12"). Lower-casing uses the
 * Unicode simple case mapping.
 *
 * @throws RtlyKitException when the text or separator is not valid UTF-8, or the separator is longer than 64 bytes
 */
final class Slugify
{
    /** Maximum bytes of the separator. */
    private const MAX_SEPARATOR_BYTES = 64;

    public static function make(string $text, string $separator = '-'): string
    {
        if (strlen($separator) > self::MAX_SEPARATOR_BYTES) {
            throw new RtlyKitException('The slug separator is too long.', errorCode: ErrorCode::InputTooLong, context: ['argument' => 'separator', 'limit' => self::MAX_SEPARATOR_BYTES]);
        }

        if (! Utf8::isValid($separator)) {
            throw new RtlyKitException('The slug separator must be valid UTF-8.', errorCode: ErrorCode::InvalidArgument, context: ['argument' => 'separator']);
        }

        if (! Utf8::isValid($text)) {
            throw new RtlyKitException('The text to slugify must be valid UTF-8.', errorCode: ErrorCode::InvalidArgument, context: ['argument' => 'text']);
        }

        // U+0001 is the placeholder below; a literal one in the input must not act as a separator.
        $text = Normalizer::normalize(str_replace("\x01", '', $text));

        // Whitespace and half-space become the placeholder, which is replaced by the separator last,
        // so text that happens to equal the separator (or contain it) is never collapsed or trimmed.
        $text = preg_replace('/[\s\x{200C}]+/u', "\x01", $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{M}\p{N}_\-\x01]+/u', '', $text) ?? $text;

        // A literal hyphen (or underscore) next to a hyphen (underscore) separator is one separator.
        if ($separator === '-' || $separator === '_') {
            $text = preg_replace('/'.preg_quote($separator, '/').'+/', "\x01", $text) ?? $text;
        }

        // Collapse runs of separators, hyphens and underscores, then trim the separators.
        $text = preg_replace('/\x01+/', "\x01", $text) ?? $text;
        $text = preg_replace('/-{2,}/', '-', $text) ?? $text;
        $text = preg_replace('/_{2,}/', '_', $text) ?? $text;
        $text = trim($text, "\x01");
        $text = str_replace("\x01", $separator, $text);

        return Utf8::lower($text);
    }
}
