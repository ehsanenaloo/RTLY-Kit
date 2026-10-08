<?php

declare(strict_types=1);

/*
 * Machine-readable copy of the structure in CONTENT-SPEC.md.
 * Groups: key => order. Pages: slug => [group, order].
 * Change both files together.
 */

return [
    'groups' => [
        'start' => 1,
        'calendars' => 2,
        'validation' => 3,
        'numbers-text' => 4,
        'dates-and-times' => 5,
        'laravel' => 6,
        'reference' => 7,
    ],
    'pages' => [
        'quick-start' => ['start', 10],
        'installation' => ['start', 20],

        'jalali' => ['calendars', 10],
        'hijri' => ['calendars', 20],
        'hebrew' => ['calendars', 30],
        'convert-and-compare' => ['calendars', 40],
        'carbon-macros' => ['calendars', 50],

        'validators-overview' => ['validation', 10],
        'national-code' => ['validation', 20],
        'sheba-and-bank-card' => ['validation', 30],
        'mobile-postal-plate' => ['validation', 40],

        'digits-and-format' => ['numbers-text', 10],
        'number-words' => ['numbers-text', 20],
        'text-tools' => ['numbers-text', 30],

        'holidays' => ['dates-and-times', 10],
        'prayer-times' => ['dates-and-times', 20],

        'laravel-setup' => ['laravel', 10],
        'laravel-validation-and-cast' => ['laravel', 20],

        'helpers-and-globals' => ['reference', 10],
        'error-handling' => ['reference', 20],
        'api-stability' => ['reference', 30],
        'accuracy-and-data' => ['reference', 40],
        'benchmarks' => ['reference', 50],
        'limits' => ['reference', 60],
        'troubleshooting-faq' => ['reference', 70],
        'upgrade' => ['reference', 80],
    ],
];
