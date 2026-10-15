<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Number\Digits;
use RtlyKit\Text\Utf8;

/**
 * An immutable lookup table that starts from the bundled data and that the caller can change.
 *
 * The bundled tables (bank BINs, Sheba bank codes, national-code prefixes) are conservative on purpose:
 * an entry needs two agreeing sources (resources/data/SOURCES.md). An application that knows more, or
 * that follows another list, builds its own table and passes it to the validators:
 *
 *     $bins = ReferenceTable::bankBins()->with(504172, 'بانک رسالت')->without(603799);
 *     BankCard::validate($card, $bins)->details()['bank_name'];
 *     BankCard::getBankName($card, $bins);
 *
 * Every change returns a new table; the bundled data and other tables are never touched, so there is no
 * shared state. Whether a card, a Sheba or a national code is valid never depends on the table (the checksums
 * do not use it); only the name or the place in the result does. The caller answers for custom entries.
 *
 * Keys are checked and normalised per table: a BIN is 6 digits (int or text, Persian digits allowed), a Sheba
 * bank code is 3 digits (text such as "017", or an int from 0 to 999), a national-code prefix is 1 to 999 (int)
 * or 3 digits (text). A value is text of 1 to 100 characters; for a national-code prefix it is
 * `['province' => ..., 'city' => ...]`.
 *
 * @throws RtlyKitException with ErrorCode::InvalidArgument for a key or value that does not fit the table
 */
final class ReferenceTable implements \Countable
{
    public const MAX_VALUE_LENGTH = 100;

    /**
     * @param array<int|string, string|array{province: string, city: string}> $entries
     */
    private function __construct(
        public readonly TableKind $kind,
        private readonly array $entries,
    ) {}

    /** The bundled table of card BINs. */
    public static function bankBins(): self
    {
        /** @var array<int, string> $entries */
        $entries = DataTables::load('bank-bins');

        return new self(TableKind::BankBin, $entries);
    }

    /** The bundled table of Sheba bank codes. */
    public static function shebaBanks(): self
    {
        /** @var array<string, string> $entries */
        $entries = DataTables::load('sheba-banks');

        return new self(TableKind::ShebaBank, $entries);
    }

    /** The bundled table of national-code prefixes (place of issue). */
    public static function nationalCodeLocations(): self
    {
        /** @var array<int, array{province: string, city: string}> $entries */
        $entries = DataTables::load('national-code-locations');

        return new self(TableKind::NationalCodePrefix, $entries);
    }

    /**
     * A table with only the given entries, for a caller that wants none of the bundled ones.
     *
     * @param array<int|string, mixed> $entries key => value, checked like {@see self::with()}
     */
    public static function from(TableKind $kind, array $entries): self
    {
        $table = new self($kind, []);
        foreach ($entries as $key => $value) {
            $table = $table->with($key, $value);
        }

        return $table;
    }

    /**
     * A table with the entry added, or replaced when the key exists.
     *
     * @param mixed $value text (1 to 100 characters), or for national-code prefixes exactly ['province' => text, 'city' => text]; anything else throws
     */
    public function with(int|string $key, mixed $value): self
    {
        $normalKey = $this->normalizeKey($key);
        $entries = $this->entries;
        $entries[$normalKey] = $this->checkValue($value, $normalKey);
        ksort($entries);

        return new self($this->kind, $entries);
    }

    /**
     * A table without the entry.
     *
     * @throws RtlyKitException when the key is not in the table (a misspelt key would otherwise go unnoticed)
     */
    public function without(int|string $key): self
    {
        $normalKey = $this->normalizeKey($key);
        if (! array_key_exists($normalKey, $this->entries)) {
            throw $this->invalid('The key is not in the table', $key);
        }
        $entries = $this->entries;
        unset($entries[$normalKey]);

        return new self($this->kind, $entries);
    }

    /**
     * The entry for a key, or null when the table has none. A key that cannot exist in this kind of table (a card
     * BIN that starts with 0, a prefix 000) gives null, not an error: it is a lookup, not an edit.
     *
     * @return string|array{province: string, city: string}|null
     */
    public function get(int|string $key): string|array|null
    {
        $normal = $this->tryKey($key);

        return $normal === null ? null : ($this->entries[$normal] ?? null);
    }

    public function has(int|string $key): bool
    {
        $normal = $this->tryKey($key);

        return $normal !== null && array_key_exists($normal, $this->entries);
    }

    /** @return array<int|string, string|array{province: string, city: string}> */
    public function all(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /** Key as the table stores it: an int for BINs and prefixes, a 3-digit string for Sheba codes. */
    private function normalizeKey(int|string $key): int|string
    {
        $normal = $this->tryKey($key);
        if ($normal === null) {
            throw $this->invalid(match ($this->kind) {
                TableKind::BankBin => 'A BIN is 6 digits and does not start with 0',
                TableKind::ShebaBank => 'A Sheba bank code is 3 digits, for example "017"',
                TableKind::NationalCodePrefix => 'A national-code prefix is 1 to 999 (3 digits as text)',
            }, $key);
        }

        return $normal;
    }

    /** The stored form of a key, or null when this kind of table can never hold it. */
    private function tryKey(int|string $key): int|string|null
    {
        $text = is_int($key) ? (string) $key : Digits::toEnglish(trim($key));

        return match ($this->kind) {
            TableKind::BankBin => preg_match('/^[1-9]\d{5}$/D', $text) === 1 ? (int) $text : null,
            TableKind::ShebaBank => is_int($key)
                ? ($key >= 0 && $key <= 999 ? sprintf('%03d', $key) : null)
                : (preg_match('/^\d{3}$/D', $text) === 1 ? $text : null),
            TableKind::NationalCodePrefix => is_int($key)
                ? ($key >= 1 && $key <= 999 ? $key : null)
                : (preg_match('/^\d{3}$/D', $text) === 1 && (int) $text >= 1 ? (int) $text : null),
        };
    }

    /** @return string|array{province: string, city: string} */
    private function checkValue(mixed $value, int|string $key): string|array
    {
        if ($this->kind === TableKind::NationalCodePrefix) {
            if (! is_array($value) || array_keys($value) !== ['province', 'city'] || ! is_string($value['province']) || ! is_string($value['city'])) {
                throw $this->invalid("The place needs exactly ['province' => ..., 'city' => ...] as text", $key);
            }
            self::assertText($value['province'], $this, $key);
            self::assertText($value['city'], $this, $key);

            return ['province' => $value['province'], 'city' => $value['city']];
        }

        if (! is_string($value)) {
            throw $this->invalid('The bank name must be text', $key);
        }
        self::assertText($value, $this, $key);

        return $value;
    }

    private static function assertText(string $text, self $table, int|string $key): void
    {
        if (trim($text) === '' || ! Utf8::isValid($text) || preg_match('/[\p{Cc}]/u', $text) === 1 || (int) preg_match_all('/./su', $text) > self::MAX_VALUE_LENGTH) {
            throw $table->invalid('The name must be 1 to '.self::MAX_VALUE_LENGTH.' characters of text without control characters', $key);
        }
    }

    private function invalid(string $message, int|string $key): RtlyKitException
    {
        return RtlyKitException::because(ErrorCode::InvalidArgument, $message.': '.Utf8::excerpt((string) $key, 20), ['table' => $this->kind->value]);
    }
}
