<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3Altcha\Internal\AlgorithmMap;
use Rasuvaeff\Yii3Altcha\Internal\AltchaFactory;

/**
 * Verifies a posted payload locally, without any outgoing call.
 *
 * Never throws on hostile input: anything undecodable is `Malformed`. The algorithm comes from the signed challenge,
 * not from the current config, so changing the configured algorithm does not invalidate challenges already issued.
 *
 * A challenge of this package carries a signature of its derived key, so a solution is checked with one HMAC and
 * no key derivation; the proof is the derived key, the posted counter is not compared. A challenge signed with the
 * same secret but without that signature (another issuer) is checked by repeating the derivation once.
 *
 * Expiry is checked twice: against the given clock and, by the official library, against the system time. A
 * challenge has to be alive by both, so a clock that disagrees with the system only rejects more.
 *
 * @api
 */
final readonly class AltchaVerifier
{
    /** A real payload is about one kilobyte; the cap stops decoding of megabytes of junk. */
    public const int MAX_PAYLOAD_BYTES = 8192;

    /** A replay marker outlives its challenge by this much, covering clock skew between servers. */
    public const int REPLAY_MARGIN_SECONDS = 60;

    public const string REPLAY_KEY_PREFIX = 'yii3-altcha.';

    private Altcha $altcha;

    /**
     * @param CacheInterface|null $replayCache when given, a challenge is spent by its first attempt with a valid
     *                                         signature: a solved payload is accepted once, and a wrong solution
     *                                         cannot be retried against the same challenge. Cache errors propagate.
     *                                         Keys are 64 characters long
     * @param ClockInterface|null $clock time source of the expiry check and of the lifetime of replay markers; the
     *                                   official library checks expiry against the system time as well
     */
    public function __construct(
        private AltchaConfig $config,
        private ?CacheInterface $replayCache = null,
        private ?ClockInterface $clock = null,
    ) {
        $this->altcha = AltchaFactory::create($config);
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
        $expiresAt = $decoded->challenge->parameters->expiresAt;

        if ($this->replayCache instanceof CacheInterface && $signature !== null && $this->replayCache->has($this->replayKey($signature))) {
            return VerificationStatus::Replayed;
        }

        // the library reads expiry from the system time; a challenge already dead by our clock must not get as far as
        // the replay marker, whose lifetime is counted by the same clock
        if ($this->expires($expiresAt) && $expiresAt < $this->now()) {
            return VerificationStatus::Expired;
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

        // the signature is ours: one challenge gets one attempt, right or wrong
        $this->spend($signature, $expiresAt);

        return $result->verified ? VerificationStatus::Verified : VerificationStatus::InvalidSolution;
    }

    private function spend(string $signature, int|float|null $expiresAt): void
    {
        if (!$this->replayCache instanceof CacheInterface) {
            return;
        }

        // verify() has let through only a challenge alive by this clock, so the remaining lifetime is not negative
        $lifetime = $this->expires($expiresAt)
            ? (int) ceil((float) $expiresAt - $this->now())
            : $this->config->ttlSeconds;

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

    /**
     * The library treats a missing and a zero expiry alike: the challenge never expires.
     *
     * @psalm-assert-if-true int|float $expiresAt
     */
    private function expires(int|float|null $expiresAt): bool
    {
        return $expiresAt !== null && (float) $expiresAt !== 0.0;
    }

    /**
     * PSR-16 guarantees keys of up to 64 characters: the prefix plus 208 bits of the hash is exactly that.
     */
    private function replayKey(string $signature): string
    {
        return self::REPLAY_KEY_PREFIX . substr(hash('sha256', $signature), offset: 0, length: 64 - \strlen(self::REPLAY_KEY_PREFIX));
    }
}
