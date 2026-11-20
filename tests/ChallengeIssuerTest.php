<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Rasuvaeff\Yii3Altcha\AltchaConfig;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Rasuvaeff\Yii3Altcha\Tests\Support\FixedClock;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ChallengeIssuer::class)]
final class ChallengeIssuerTest
{
    public function issuesASignedChallengeWithTheConfiguredParameters(): void
    {
        $challenge = (new ChallengeIssuer(Browser::config(ttlSeconds: 120), new FixedClock(1_800_000_000)))->issue();

        Assert::same($challenge['parameters']['algorithm'], 'PBKDF2/SHA-256');
        Assert::same($challenge['parameters']['cost'], 10);
        Assert::same($challenge['parameters']['expiresAt'], 1_800_000_120);
        Assert::true(\is_string($challenge['signature']) && $challenge['signature'] !== '');
    }

    public function withoutAClockTheExpiryIsMeasuredFromNow(): void
    {
        $before = time();
        $challenge = (new ChallengeIssuer(Browser::config(ttlSeconds: 120)))->issue();
        $after = time();

        Assert::true($challenge['parameters']['expiresAt'] >= $before + 120);
        Assert::true($challenge['parameters']['expiresAt'] <= $after + 120);
    }

    public function everyChallengeIsUnique(): void
    {
        $issuer = new ChallengeIssuer(Browser::config());
        $first = $issuer->issue();
        $second = $issuer->issue();

        Assert::true($first['parameters']['nonce'] !== $second['parameters']['nonce']);
        Assert::true($first['signature'] !== $second['signature']);
    }

    public function aSingleValueRangeIssuesCounterOne(): void
    {
        $config = new AltchaConfig(secret: Browser::SECRET, cost: 10, maxCounter: 1);

        $issuer = new ChallengeIssuer($config);

        for ($i = 0; $i < 30; $i++) {
            Assert::same(Browser::solution($issuer->issue())['counter'], 1);
        }
    }

    public function theSecretCounterStaysWithinTheConfiguredRange(): void
    {
        $issuer = new ChallengeIssuer(Browser::config());

        for ($i = 0; $i < 20; $i++) {
            $counter = Browser::solution($issuer->issue())['counter'];

            Assert::true($counter >= 1 && $counter <= 50);
        }
    }
}
