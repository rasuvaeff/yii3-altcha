<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Psr\SimpleCache\CacheInterface;

/**
 * Verifies a posted payload locally, without any outgoing call. The second class that touches
 * the official library. Never throws on hostile input: anything undecodable is `Malformed`.
 *
 * @api
 */
final readonly class AltchaVerifier
{
    /** A real payload is about one kilobyte; the cap stops decoding of megabytes of junk. */
    public const int MAX_PAYLOAD_BYTES = 8192;

    private Altcha $altcha;

    /**
     * @param CacheInterface|null $replayCache when given, a solved payload is accepted once: the second
     *                                         submission of the same challenge is `Replayed`
     */
    public function __construct(
        private AltchaConfig $config,
        private ?CacheInterface $replayCache = null,
    ) {
        $this->altcha = new Altcha(hmacSignatureSecret: $config->secret);
    }

    /**
     * @param string $payload the raw base64 string the widget posts
     */
    public function verify(string $payload): VerificationStatus
    {
        if (\strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            return VerificationStatus::Malformed;
        }

        try {
            $options = new VerifySolutionOptions(
                payload: $payload,
                algorithm: $this->config->algorithm->deriveKey(),
            );
        } catch (\InvalidArgumentException) {
            return VerificationStatus::Malformed;
        }

        $result = $this->altcha->verifySolution($options);

        if ($result->expired) {
            return VerificationStatus::Expired;
        }

        if ($result->invalidSignature === true) {
            return VerificationStatus::InvalidSignature;
        }

        if (!$result->verified) {
            return VerificationStatus::InvalidSolution;
        }

        if ($this->replayCache instanceof CacheInterface) {
            $key = 'yii3-altcha.used.' . hash('sha256', (string) $options->payload->challenge->signature);

            if ($this->replayCache->has($key)) {
                return VerificationStatus::Replayed;
            }

            $this->replayCache->set($key, true, $this->config->ttlSeconds);
        }

        return VerificationStatus::Verified;
    }
}
