#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * PHPUnit launcher used only by Infection (tools/infection.json5).
 *
 * vendor/bin/phpunit loads Composer's autoloader first, and src/helpers.php
 * (an autoload "files" entry) boots classes such as AutoLoader and CarbonMacros
 * at that moment, before Infection's mutation interceptor is active. Mutants of
 * those classes would then never be seen by the tests. This launcher registers
 * the interceptor (the bootstrap file of the generated configuration) first.
 */

$args = $_SERVER['argv'] ?? [];
$config = null;

foreach ($args as $i => $arg) {
    if ($arg === '-c' || $arg === '--configuration') {
        $config = $args[$i + 1] ?? null;
    } elseif (str_starts_with((string) $arg, '--configuration=')) {
        $config = substr((string) $arg, strlen('--configuration='));
    }
}

if (is_string($config) && is_file($config)) {
    $xml = @simplexml_load_file($config);
    $bootstrap = $xml !== false ? (string) ($xml['bootstrap'] ?? '') : '';

    if ($bootstrap !== '' && is_file($bootstrap)) {
        require_once $bootstrap;
    }
}

require dirname(__DIR__).'/vendor/phpunit/phpunit/phpunit';
