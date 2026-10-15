<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

/** The lookup tables that a {@see ReferenceTable} can hold. */
enum TableKind: string
{
    /** Card BIN (first 6 digits) to bank name. */
    case BankBin = 'bank_bin';

    /** Sheba bank identifier (3 digits) to bank name. */
    case ShebaBank = 'sheba_bank';

    /** First 3 digits of the national code to the place of issue. */
    case NationalCodePrefix = 'national_code_prefix';
}
