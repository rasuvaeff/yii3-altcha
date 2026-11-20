<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Altcha\AltchaAlgorithm;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Rasuvaeff\Yii3Altcha\Tests\Support\FixedClock;
use Rasuvaeff\Yii3Altcha\VerificationStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(AltchaVerifier::class)]
#[Covers(VerificationStatus::class)]
final class AltchaVerifierTest
{
    public function aSolvedChallengeIsVerified(): void
    {
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());

        Assert::same((new AltchaVerifier(Browser::config()))->verify($payload), VerificationStatus::Verified);
        Assert::true(VerificationStatus::Verified->isVerified());
    }

    public function onlyVerifiedCountsAsVerified(): void
    {
        foreach (VerificationStatus::cases() as $status) {
            Assert::same($status->isVerified(), $status === VerificationStatus::Verified);
        }
    }

    public function anExpiredChallengeIsRejectedEvenWhenSolved(): void
    {
        $issuer = new ChallengeIssuer(Browser::config(ttlSeconds: 60), new FixedClock(time() - 3600));
        $payload = Browser::solve($issuer->issue());

        Assert::same((new AltchaVerifier(Browser::config()))->verify($payload), VerificationStatus::Expired);
    }

    public function aChallengeSignedWithAnotherSecretIsRejected(): void
    {
        $other = Browser::config(secret: 'another-secret-0123456789-abcdefghijk');
        $payload = Browser::solve((new ChallengeIssuer($other))->issue());

        Assert::same((new AltchaVerifier(Browser::config()))->verify($payload), VerificationStatus::InvalidSignature);
    }

    public function loweringTheCostInAnIssuedChallengeBreaksTheSignature(): void
    {
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        $solution = Browser::solution($challenge);
        $challenge['parameters']['cost'] = 1;

        $status = (new AltchaVerifier(Browser::config()))->verify(Browser::encode($challenge, $solution));

        Assert::same($status, VerificationStatus::InvalidSignature);
    }

    public function aChallengeWithoutASignatureIsRejected(): void
    {
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        unset($challenge['signature']);

        $status = (new AltchaVerifier(Browser::config()))->verify(Browser::solve($challenge));

        Assert::same($status, VerificationStatus::InvalidSignature);
    }

    public function aWrongDerivedKeyIsAnInvalidSolution(): void
    {
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        $solution = Browser::solution($challenge);
        $solution['derivedKey'] = str_repeat('0', \strlen($solution['derivedKey']));

        $status = (new AltchaVerifier(Browser::config()))->verify(Browser::encode($challenge, $solution));

        Assert::same($status, VerificationStatus::InvalidSolution);
    }

    public function aWrongCounterIsAnInvalidSolution(): void
    {
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        $solution = Browser::solution($challenge);
        $solution['counter'] += 1;

        $status = (new AltchaVerifier(Browser::config()))->verify(Browser::encode($challenge, $solution));

        Assert::same($status, VerificationStatus::InvalidSolution);
    }

    #[DataProvider('malformedProvider')]
    public function undecodablePayloadsAreMalformedNotExceptions(string $payload): void
    {
        Assert::same((new AltchaVerifier(Browser::config()))->verify($payload), VerificationStatus::Malformed);
    }

    public static function malformedProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'not base64' => ['!!! not base64 !!!'];
        yield 'base64 of non-json' => [base64_encode('hello')];
        yield 'base64 of a json scalar' => [base64_encode('42')];
        yield 'json object without parts' => [base64_encode('{}')];
        yield 'challenge is not an object' => [base64_encode('{"challenge":1,"solution":{}}')];
        yield 'parameters of the wrong types' => [base64_encode('{"challenge":{"parameters":{"algorithm":1,"nonce":[],"salt":null,"cost":"x"}},"solution":{"counter":"a","derivedKey":1}}')];
        yield 'oversize payload' => [str_repeat('A', AltchaVerifier::MAX_PAYLOAD_BYTES + 1)];
    }

    public function aValidPayloadPaddedToExactlyTheSizeCapIsStillVerified(): void
    {
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());
        $padded = $payload . str_repeat(' ', AltchaVerifier::MAX_PAYLOAD_BYTES - \strlen($payload));

        Assert::same(\strlen($padded), AltchaVerifier::MAX_PAYLOAD_BYTES);
        Assert::same((new AltchaVerifier(Browser::config()))->verify($padded), VerificationStatus::Verified);
    }

    public function aValidPayloadOneByteOverTheCapIsRejectedBeforeDecoding(): void
    {
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());
        $padded = $payload . str_repeat(' ', AltchaVerifier::MAX_PAYLOAD_BYTES + 1 - \strlen($payload));

        Assert::same((new AltchaVerifier(Browser::config()))->verify($padded), VerificationStatus::Malformed);
    }

    public function theReplayMarkerIsStoredUnderANamespacedKeyUntilTheChallengeExpiresPlusAMargin(): void
    {
        $cache = Understudy::for(CacheInterface::class);
        $key = Arg::captor();
        $value = Arg::captor();
        $ttl = Arg::captor();
        when(fn() => $cache->has(Arg::any()))->returns(false);
        when(fn() => $cache->set($key->capture(), $value->capture(), $ttl->capture()))->returns(true);
        // the verifier's config says 5 s, the challenge says ~321 s: the challenge wins
        $verifier = new AltchaVerifier(Browser::config(ttlSeconds: 5), $cache);

        $verifier->verify(Browser::solve((new ChallengeIssuer(Browser::config(ttlSeconds: 321)))->issue()));

        Assert::true(str_starts_with($key->last(), 'yii3-altcha.used.'));
        Assert::same(\strlen($key->last()), \strlen('yii3-altcha.used.') + 64);
        Assert::same($value->last(), expected: true);
        Assert::true($ttl->last() >= 321 - 2 + AltchaVerifier::REPLAY_MARGIN_SECONDS);
        Assert::true($ttl->last() <= 321 + AltchaVerifier::REPLAY_MARGIN_SECONDS);
    }

    public function theMarkerLifetimeRoundsAFractionalExpiryUp(): void
    {
        $cache = Understudy::for(CacheInterface::class);
        $ttl = Arg::captor();
        when(fn() => $cache->has(Arg::any()))->returns(false);
        when(fn() => $cache->set(Arg::any(), Arg::any(), $ttl->capture()))->returns(true);
        $now = time();
        // a JS issuer may sign a fractional expiry; 10.2 s left must keep the marker for 11 s, not 10
        $altcha = new \AltchaOrg\Altcha\Altcha(hmacSignatureSecret: Browser::SECRET);
        $challenge = $altcha->createChallenge(new \AltchaOrg\Altcha\CreateChallengeOptions(
            algorithm: new \AltchaOrg\Altcha\Algorithm\Pbkdf2(),
            cost: 10,
            counter: 3,
            expiresAt: $now + 10.2,
        ))->toArray();

        (new AltchaVerifier(Browser::config(), $cache, new FixedClock($now)))->verify(Browser::solve($challenge));

        Assert::same($ttl->last(), 11 + AltchaVerifier::REPLAY_MARGIN_SECONDS);
    }

    public function aChallengeWithoutExpiryFallsBackToTheConfiguredLifetime(): void
    {
        $cache = Understudy::for(CacheInterface::class);
        $ttl = Arg::captor();
        when(fn() => $cache->has(Arg::any()))->returns(false);
        when(fn() => $cache->set(Arg::any(), Arg::any(), $ttl->capture()))->returns(true);
        $altcha = new \AltchaOrg\Altcha\Altcha(hmacSignatureSecret: Browser::SECRET);
        $challenge = $altcha->createChallenge(new \AltchaOrg\Altcha\CreateChallengeOptions(
            algorithm: new \AltchaOrg\Altcha\Algorithm\Pbkdf2(),
            cost: 10,
            counter: 3,
        ))->toArray();

        (new AltchaVerifier(Browser::config(ttlSeconds: 77), $cache))->verify(Browser::solve($challenge));

        Assert::same($ttl->last(), 77 + AltchaVerifier::REPLAY_MARGIN_SECONDS);
    }

    public function withoutAReplayCacheASolvedPayloadWorksRepeatedly(): void
    {
        $verifier = new AltchaVerifier(Browser::config());
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());

        Assert::same($verifier->verify($payload), VerificationStatus::Verified);
        Assert::same($verifier->verify($payload), VerificationStatus::Verified);
    }

    public function withAReplayCacheASolvedPayloadWorksOnce(): void
    {
        $verifier = new AltchaVerifier(Browser::config(), new MemorySimpleCache());
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());

        Assert::same($verifier->verify($payload), VerificationStatus::Verified);
        Assert::same($verifier->verify($payload), VerificationStatus::Replayed);
    }

    public function replayProtectionDoesNotBlockADifferentChallenge(): void
    {
        $verifier = new AltchaVerifier(Browser::config(), new MemorySimpleCache());
        $issuer = new ChallengeIssuer(Browser::config());

        Assert::same($verifier->verify(Browser::solve($issuer->issue())), VerificationStatus::Verified);
        Assert::same($verifier->verify(Browser::solve($issuer->issue())), VerificationStatus::Verified);
    }

    public function anAttemptWithAValidSignatureSpendsTheChallengeEvenWhenTheSolutionIsWrong(): void
    {
        $verifier = new AltchaVerifier(Browser::config(), new MemorySimpleCache());
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        $solution = Browser::solution($challenge);
        $broken = $solution;
        $broken['counter'] += 1;

        Assert::same($verifier->verify(Browser::encode($challenge, $broken)), VerificationStatus::InvalidSolution);
        Assert::same($verifier->verify(Browser::encode($challenge, $solution)), VerificationStatus::Replayed);
    }

    public function aSpentChallengeIsRejectedBeforeAnyKeyDerivation(): void
    {
        $cache = Understudy::for(CacheInterface::class);
        when(fn() => $cache->has(Arg::any()))->returns(true);
        $verifier = new AltchaVerifier(Browser::config(), $cache);
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();

        $status = $verifier->verify(Browser::encode($challenge, ['counter' => 1, 'derivedKey' => 'ab']));

        Assert::same($status, VerificationStatus::Replayed);
        Understudy::verify(fn() => $cache->set(Arg::any(), Arg::any(), Arg::any()), never: true);
    }

    public function aForgedSignatureDoesNotSpendAnything(): void
    {
        $cache = Understudy::for(CacheInterface::class);
        when(fn() => $cache->has(Arg::any()))->returns(false);
        $verifier = new AltchaVerifier(Browser::config(), $cache);
        $other = Browser::config(secret: 'another-secret-0123456789-abcdefghijk');

        $status = $verifier->verify(Browser::solve((new ChallengeIssuer($other))->issue()));

        Assert::same($status, VerificationStatus::InvalidSignature);
        Understudy::verify(fn() => $cache->set(Arg::any(), Arg::any(), Arg::any()), never: true);
    }

    public function anExpiredChallengeIsNotSpent(): void
    {
        $cache = Understudy::for(CacheInterface::class);
        when(fn() => $cache->has(Arg::any()))->returns(false);
        $verifier = new AltchaVerifier(Browser::config(), $cache);
        $issuer = new ChallengeIssuer(Browser::config(ttlSeconds: 60), new FixedClock(time() - 3600));

        Assert::same($verifier->verify(Browser::solve($issuer->issue())), VerificationStatus::Expired);
        Understudy::verify(fn() => $cache->set(Arg::any(), Arg::any(), Arg::any()), never: true);
    }

    public function withoutAReplayCacheAWrongSolutionCanBeRetried(): void
    {
        $verifier = new AltchaVerifier(Browser::config());
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        $solution = Browser::solution($challenge);
        $broken = $solution;
        $broken['counter'] += 1;

        Assert::same($verifier->verify(Browser::encode($challenge, $broken)), VerificationStatus::InvalidSolution);
        Assert::same($verifier->verify(Browser::encode($challenge, $solution)), VerificationStatus::Verified);
    }

    public function aCacheThatRefusesTheWriteFailsLoudlyInsteadOfDroppingTheProtection(): void
    {
        $cache = Understudy::for(CacheInterface::class);
        when(fn() => $cache->has(Arg::any()))->returns(false);
        when(fn() => $cache->set(Arg::any(), Arg::any(), Arg::any()))->returns(false);
        $verifier = new AltchaVerifier(Browser::config(), $cache);
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());

        Expect::exception(\RuntimeException::class)->withMessage('The ALTCHA replay cache did not store the spent challenge');

        $verifier->verify($payload);
    }

    public function theAlgorithmComesFromTheSignedChallengeNotFromTheCurrentConfig(): void
    {
        $issued = Browser::config(algorithm: AltchaAlgorithm::Sha256);
        $payload = Browser::solve((new ChallengeIssuer($issued))->issue(), AltchaAlgorithm::Sha256);

        $status = (new AltchaVerifier(Browser::config(algorithm: AltchaAlgorithm::Pbkdf2Sha512)))->verify($payload);

        Assert::same($status, VerificationStatus::Verified);
    }

    public function anUnknownAlgorithmIsMalformed(): void
    {
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        $solution = Browser::solution($challenge);
        $challenge['parameters']['algorithm'] = 'MD5';

        Assert::same((new AltchaVerifier(Browser::config()))->verify(Browser::encode($challenge, $solution)), VerificationStatus::Malformed);
    }

    #[Property(runs: 300)]
    public function noInputEverThrowsOrVerifies(string $payload): void
    {
        $status = (new AltchaVerifier(Browser::config()))->verify($payload);

        Assert::false($status->isVerified());
    }

    /** @return array<string, ArbitraryInterface> */
    public static function noInputEverThrowsOrVerifiesGenerators(): array
    {
        return [
            'payload' => Gen::frequency([
                [3, Gen::string()],
                [2, Gen::naughtyString()],
                [2, Gen::map(Gen::string(), static fn(string $s): string => base64_encode($s))],
                [2, Gen::map(Gen::jsonString(), static fn(string $s): string => base64_encode($s))],
            ]),
        ];
    }

    #[Property(runs: 120)]
    public function anyTamperingOfASolvedPayloadIsRejected(string $field, int $delta): void
    {
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        $solution = Browser::solution($challenge);

        if (\in_array($field, ['counter', 'derivedKey'], strict: true)) {
            $solution[$field] = \is_int($solution[$field]) ? $solution[$field] + $delta : $solution[$field] . dechex($delta % 16);
        } elseif ($field === 'signature') {
            $challenge['signature'] = strrev((string) $challenge['signature']);
        } elseif ($field === 'algorithm') {
            $challenge['parameters']['algorithm'] = AltchaAlgorithm::Sha256->value;
        } else {
            $value = $challenge['parameters'][$field];
            $challenge['parameters'][$field] = \is_int($value) ? $value + $delta : $value . dechex($delta % 16);
        }

        Classify::label($field);

        $status = (new AltchaVerifier(Browser::config()))->verify(Browser::encode($challenge, $solution));

        Assert::false($status->isVerified());
    }

    /** @return array<string, ArbitraryInterface> */
    public static function anyTamperingOfASolvedPayloadIsRejectedGenerators(): array
    {
        return [
            'field' => Gen::elements(['cost', 'nonce', 'salt', 'keyPrefix', 'keyLength', 'expiresAt', 'algorithm', 'signature', 'counter', 'derivedKey']),
            'delta' => Gen::intBetween(1, 1000),
        ];
    }
}
