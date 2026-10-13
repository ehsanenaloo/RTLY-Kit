<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel\Casts\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Model of the `events` table; the test sets the casts before it creates instances. */
final class EventModel extends Model
{
    /** @var array<string, string> */
    public static array $castDef = [];

    protected $table = 'events';

    public $timestamps = false;

    protected $guarded = [];

    public function getCasts(): array
    {
        return self::$castDef;
    }
}
