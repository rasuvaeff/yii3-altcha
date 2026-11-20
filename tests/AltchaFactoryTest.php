<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\CreateChallengeOptions;
use Rasuvaeff\Yii3Altcha\Internal\AltchaFactory;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(AltchaFactory::class)]
final class AltchaFactoryTest
{
    public function theChallengeIsSignedWithTheConfiguredSecret(): void
    {
        $challenge = AltchaFactory::create(Browser::config())->createChallenge($this->options());

        Assert::same(
            $challenge->signature,
            hash_hmac('sha256', $challenge->parameters->toCanonicalJson(), Browser::SECRET),
        );
    }

    public function theKeySignatureUsesAKeyDerivedFromTheSecretNotTheSecretItself(): void
    {
        $challenge = AltchaFactory::create(Browser::config())->createChallenge($this->options());
        $derivedKey = (string) hex2bin(Browser::solution($challenge->toArray())['derivedKey']);
        $keySecret = hash_hmac('sha256', 'rasuvaeff/yii3-altcha key signature v1', Browser::SECRET);

        Assert::same($challenge->parameters->keySignature, hash_hmac('sha256', $derivedKey, $keySecret));
        Assert::notSame($challenge->parameters->keySignature, hash_hmac('sha256', $derivedKey, Browser::SECRET));
    }

    public function anotherSecretGivesAnotherKeySignatureForTheSameChallenge(): void
    {
        $other = Browser::config(secret: 'another-secret-0123456789-abcdefghijk');
        $options = new CreateChallengeOptions(algorithm: new Pbkdf2(), cost: 10, counter: 3, nonce: 'aa', salt: 'bb');

        Assert::notSame(
            AltchaFactory::create(Browser::config())->createChallenge($options)->parameters->keySignature,
            AltchaFactory::create($other)->createChallenge($options)->parameters->keySignature,
        );
    }

    private function options(): CreateChallengeOptions
    {
        return new CreateChallengeOptions(algorithm: new Pbkdf2(), cost: 10, counter: 3, expiresAt: time() + 60);
    }
}
