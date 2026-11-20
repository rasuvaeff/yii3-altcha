<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3Altcha\Internal\AlgorithmMap;

/**
 * Verifies a posted payload locally, without any outgoing call. The second class that touches the official library.
 *
 * Never throws on hostile input: anything undecodable is `Malformed`. Expiry is checked against the system time.
 * The algorithm comes from the signed challenge, not from the current config, so changing the configured algorithm
 * does not invalidate challenges already issued.
 *
 * @api
 */
final readonly class AltchaVerifier
{
    /** A real payload is about one kilobyte; the cap stops decoding of megabytes of junk. */
    public const int MAX_PAYLOAD_BYTES = 8192;

    /** A replay marker outlives its challenge by this much, covering clock skew between servers. */
    public const int REPLAY_MARGIN_SECONDS = 60;

    private Altcha $altcha;

    /**
     * @param CacheInterface|null $replayCache when given, a challenge is spent by its first attempt with a valid
     *                                         signature: a solved payload is accepted once, and a wrong solution
     *                                         cannot be retried against the same challenge. Cache errors propagate
     * @param ClockInterface|null $clock time source for the lifetime of replay markers; the expiry check itself
     *                                   is done by the official library against the system time
     */
    public function __construct(
        private AltchaConfig $config,
        private ?CacheInterface $replayCache = null,
        private ?ClockInterface $clock = null,
    ) {
        $this->altcha = new Altcha(hmacSignatureSecret: $config->secret);
    }

    /**
     * @param string $payload the raw base64 string the widget posts
     *
     * @throws \RuntimeException when the replay cache refuses to store a spent challenge
     */
    public function verify(string $payload): VerificationStatus
    {
        if (\strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            return VerificationStatus::Malformed;
        }

        try {
            $decoded = Payload::fromBase64($payload);
        } catch (\InvalidArgumentException) {
            return VerificationStatus::Malformed;
        }

        $algorithm = AltchaAlgorithm::tryFrom($decoded->challenge->parameters->algorithm);

        if ($algorithm === null) {
            return VerificationStatus::Malformed;
        }

        $signature = $decoded->challenge->signature;

        if ($this->replayCache instanceof CacheInterface && $signature !== null && $this->replayCache->has($this->replayKey($signature))) {
            return VerificationStatus::Replayed;
        }

        $result = $this->altcha->verifySolution(new VerifySolutionOptions(
            payload: $decoded,
            algorithm: AlgorithmMap::deriveKey($algorithm),
        ));

        if ($result->expired) {
            return VerificationStatus::Expired;
        }

        if ($result->invalidSignature === true || $signature === null) {
            return VerificationStatus::InvalidSignature;
        }

        // the signature is ours: this attempt has cost one key derivation, so the challenge is spent either way
        $this->spend($signature, $decoded->challenge->parameters->expiresAt);

        return $result->verified ? VerificationStatus::Verified : VerificationStatus::InvalidSolution;
    }

    private function spend(string $signature, int|float|null $expiresAt): void
    {
        if (!$this->replayCache instanceof CacheInterface) {
            return;
        }

        // only an unexpired challenge gets here, so the remaining lifetime is positive
        $lifetime = $expiresAt === null
            ? $this->config->ttlSeconds
            : (int) ceil((float) $expiresAt - $this->now());

        if (!$this->replayCache->set($this->replayKey($signature), true, $lifetime + self::REPLAY_MARGIN_SECONDS)) {
            throw new \RuntimeException('The ALTCHA replay cache did not store the spent challenge');
        }
    }

    private function now(): float
    {
        return $this->clock instanceof ClockInterface
            ? (float) $this->clock->now()->format('U.u')
            : microtime(as_float: true);
    }

    private function replayKey(string $signature): string
    {
        return 'yii3-altcha.used.' . hash('sha256', $signature);
    }
}
