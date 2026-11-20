<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests\Support;

use Psr\Clock\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private int $timestamp) {}

    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTimestamp($this->timestamp);
    }
}
