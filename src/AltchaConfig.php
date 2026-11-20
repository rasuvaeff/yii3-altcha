<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

/**
 * @api
 */
final readonly class AltchaConfig
{
    public const int MIN_SECRET_BYTES = 32;

    /**
     * Issuing a challenge and verifying a solution each cost the server one derivation at this many iterations,
     * on endpoints anyone can call: the cap keeps that under ~60 ms for PBKDF2/SHA-256.
     */
    public const int MAX_COST = 100_000;

    /**
     * @param string $secret HMAC secret signing every challenge; at least 32 bytes, from the environment
     * @param int $cost key-derivation iterations per attempt; issuing and verifying each repeat it once, so it is capped
     * @param int $maxCounter upper bound of the secret counter the browser has to find: the average solve takes half of it
     * @param int $ttlSeconds lifetime of a challenge and therefore of a solved payload
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $secret,
        public AltchaAlgorithm $algorithm = AltchaAlgorithm::Pbkdf2Sha256,
        public int $cost = 5000,
        public int $maxCounter = 1000,
        public int $ttlSeconds = 600,
    ) {
        if (\strlen($secret) < self::MIN_SECRET_BYTES) {
            throw new \InvalidArgumentException('The ALTCHA secret must be at least 32 bytes');
        }

        if ($cost < 1 || $cost > self::MAX_COST) {
            throw new \InvalidArgumentException('The ALTCHA cost must be between 1 and 100000');
        }

        if ($maxCounter < 1) {
            throw new \InvalidArgumentException('The ALTCHA max counter must be positive');
        }

        if ($ttlSeconds < 1) {
            throw new \InvalidArgumentException('The ALTCHA challenge TTL must be positive');
        }
    }
}
