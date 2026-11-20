<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests\Support;

use Psr\Clock\ClockInterface;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * A PSR-20 clock standing at one moment: an Understudy double, not a class of our own.
 */
final readonly class Clock
{
    public static function at(int $timestamp): ClockInterface
    {
        $clock = Understudy::for(ClockInterface::class);
        when(static fn() => $clock->now())->returns((new \DateTimeImmutable())->setTimestamp($timestamp));

        return $clock;
    }
}
