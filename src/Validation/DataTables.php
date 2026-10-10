<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;

/**
 * Lazy, cached loader for the lookup tables in `resources/data/*.php`.
 *
 * Each file returns a plain PHP array and is read at most once per process.
 *
 * @internal
 */
final class DataTables
{
    /** @var array<string, array<array-key, mixed>> */
    private static array $cache = [];

    /**
     * @return array<array-key, mixed>
     *
     * @throws RtlyKitException when the table is missing or corrupt (a packaging problem)
     */
    public static function load(string $name): array
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $path = dirname(__DIR__, 2).'/resources/data/'.$name.'.php';

        if (preg_match('/^[a-z0-9-]+$/D', $name) !== 1 || ! is_file($path)) {
            throw RtlyKitException::because(ErrorCode::DataUnavailable, sprintf("Data table '%s' is not available.", $name), ['table' => $name]);
        }

        $table = require $path;

        // Bundled data files always return arrays; this guards against a damaged install.
        // @codeCoverageIgnoreStart
        if (! is_array($table)) {
            throw RtlyKitException::because(ErrorCode::DataUnavailable, sprintf("Data table '%s' is corrupt.", $name), ['table' => $name]);
        }
        // @codeCoverageIgnoreEnd

        return self::$cache[$name] = $table;
    }
}
