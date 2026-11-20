<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Rasuvaeff\Yii3Altcha\Tests\Support\FixedClock;
use Rasuvaeff\Yii3Altcha\VerificationStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
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

    public function theReplayMarkerIsStoredUnderANamespacedKeyForTheChallengeLifetime(): void
    {
        $cache = Understudy::for(CacheInterface::class);
        $key = Arg::captor();
        $value = Arg::captor();
        $ttl = Arg::captor();
        when(fn() => $cache->has(Arg::any()))->returns(false);
        when(fn() => $cache->set($key->capture(), $value->capture(), $ttl->capture()))->returns(true);
        $verifier = new AltchaVerifier(Browser::config(ttlSeconds: 321), $cache);

        $verifier->verify(Browser::solve((new ChallengeIssuer(Browser::config(ttlSeconds: 321)))->issue()));

        Assert::true(str_starts_with($key->last(), 'yii3-altcha.used.'));
        Assert::same(\strlen($key->last()), \strlen('yii3-altcha.used.') + 64);
        Assert::same($value->last(), expected: true);
        Assert::same($ttl->last(), 321);
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

    public function aFailedVerificationDoesNotBurnTheChallenge(): void
    {
        $cache = new MemorySimpleCache();
        $verifier = new AltchaVerifier(Browser::config(), $cache);
        $challenge = (new ChallengeIssuer(Browser::config()))->issue();
        $solution = Browser::solution($challenge);
        $broken = $solution;
        $broken['counter'] += 1;

        Assert::same($verifier->verify(Browser::encode($challenge, $broken)), VerificationStatus::InvalidSolution);
        Assert::same($verifier->verify(Browser::encode($challenge, $solution)), VerificationStatus::Verified);
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
}
