<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The package must need nothing beyond PHP itself: no mbstring calls in src/
 * and no extension requirement in composer.json.
 */
final class NoMbstringTest extends TestCase
{
    public function test_src_never_calls_mb_functions(): void
    {
        $offenders = [];
        $root = dirname(__DIR__, 2).'/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && $token[0] === T_STRING && str_starts_with(strtolower($token[1]), 'mb_')) {
                    $offenders[] = $file->getPathname().':'.$token[2].' '.$token[1];
                }
            }
        }

        self::assertSame([], $offenders, 'mb_* functions need ext-mbstring; use RtlyKit\Text\Utf8 instead');
    }

    public function test_composer_requires_only_php(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
        self::assertIsArray($composer);
        self::assertIsArray($composer['require']);
        self::assertSame(['php'], array_keys($composer['require']));
    }
}
