<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use Psr\Clock\ClockInterface;
use Rasuvaeff\Yii3Altcha\Internal\AlgorithmMap;
use Rasuvaeff\Yii3Altcha\Internal\AltchaFactory;

/**
 * Issues signed, expiring challenges. Each challenge carries a signature of the derived key, so verifying a solution
 * later costs one HMAC instead of a key derivation.
 *
 * A clock other than the system one is for tests: the verifier also checks expiry against the system time.
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
        $this->altcha = AltchaFactory::create($config);
    }

    /**
     * The challenge as the widget expects it (`parameters` + `signature`). Costs one key derivation.
     *
     * @return array<string, mixed>
     */
    public function issue(): array
    {
        $now = $this->clock instanceof ClockInterface ? $this->clock->now()->getTimestamp() : time();

        return $this->altcha->createChallenge(new CreateChallengeOptions(
            algorithm: AlgorithmMap::deriveKey($this->config->algorithm),
            cost: $this->config->cost,
            counter: random_int(1, $this->config->maxCounter),
            expiresAt: $now + $this->config->ttlSeconds,
        ))->toArray();
    }
}
