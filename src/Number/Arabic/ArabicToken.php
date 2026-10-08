<?php

declare(strict_types=1);

namespace RtlyKit\Number\Arabic;

/**
 * One word (or one hundreds compound) of an Arabic number phrase, before case
 * endings and annexation are resolved.
 *
 * @internal Not part of the public API.
 */
final readonly class ArabicToken
{
    /** Word that takes tanwin or a bare ending: ثلاثة، ألف، مئة، آلاف. */
    public const TRIPTOTE = 'tri';

    /** Like TRIPTOTE but never annexed to a counted noun (واحد، صفر). */
    public const STANDALONE = 'one';

    /** Diptote plural (ملايين، بلايين): fatha when not annexed, kasra when annexed. */
    public const DIPTOTE = 'dip';

    /** The series-B eight (ثماني): defective noun. */
    public const EIGHT = 'eight';

    /** Dual (اثنان، مئتان، ألفان, مليونان): stem without the dual ending. */
    public const DUAL = 'dual';

    /** Tens: stem without the ون/ين ending. */
    public const TENS = 'tens';

    /** Invariable word built on fatha (the parts of 11-19). */
    public const FIXED = 'fix';

    /** Hundreds 300-900: multiplier plus مئة. */
    public const HUNDRED = 'hund';

    /** Case role: takes the case of the whole phrase. */
    public const ROLE_CASE = 'c';

    /** Case role: always genitive (annexed to a number). */
    public const ROLE_GENITIVE = 'g';

    /** Case role: always accusative singular (tamyiz of 11-99). */
    public const ROLE_ACCUSATIVE = 'a';

    /** Not annexed. */
    public const ANNEX_NONE = 'n';

    /** Annexed to the next word (a scale word). */
    public const ANNEX_NEXT = 'x';

    /** Annexed to the counted noun when this is the last word of a `noun` phrase. */
    public const ANNEX_NOUN = 'l';

    /**
     * @param string $aux    HUNDRED only: the spelling of مئة / مائة
     * @param bool   $joined HUNDRED only: multiplier and مئة are one word
     */
    public function __construct(
        public string $kind,
        public string $stem,
        public string $role = self::ROLE_CASE,
        public string $annex = self::ANNEX_NONE,
        public bool $and = false,
        public string $aux = '',
        public bool $joined = true,
    ) {}

    public function withAnd(): self
    {
        return new self($this->kind, $this->stem, $this->role, $this->annex, true, $this->aux, $this->joined);
    }

    public function withAndIf(bool $and): self
    {
        return $and ? $this->withAnd() : $this;
    }
}
