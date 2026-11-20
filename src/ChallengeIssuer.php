<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use Psr\Clock\ClockInterface;

/**
 * Issues signed, expiring challenges. One of the two classes that touch the official library.
 *
 * @api
 */
final readonly class ChallengeIssuer
{
    private Altcha $altcha;

    public function __construct(
        private AltchaConfig $config,
        private ?ClockInterface $clock = null,
    ) {
        $this->altcha = new Altcha(hmacSignatureSecret: $config->secret);
    }

    /**
     * The challenge as the widget expects it (`parameters` + `signature`).
     *
     * @return array<string, mixed>
     */
    public function issue(): array
    {
        $now = $this->clock instanceof ClockInterface ? $this->clock->now()->getTimestamp() : time();

        return $this->altcha->createChallenge(new CreateChallengeOptions(
            algorithm: $this->config->algorithm->deriveKey(),
            cost: $this->config->cost,
            counter: random_int(1, $this->config->maxCounter),
            expiresAt: $now + $this->config->ttlSeconds,
        ))->toArray();
    }
}
