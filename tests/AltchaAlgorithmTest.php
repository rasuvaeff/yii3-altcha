<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Rasuvaeff\Yii3Altcha\AltchaAlgorithm;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Rasuvaeff\Yii3Altcha\VerificationStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(AltchaAlgorithm::class)]
final class AltchaAlgorithmTest
{
    #[DataProvider('algorithmProvider')]
    public function deriveKeyReportsTheProtocolName(AltchaAlgorithm $algorithm): void
    {
        Assert::same($algorithm->deriveKey()->getAlgorithmName(), $algorithm->value);
    }

    #[DataProvider('algorithmProvider')]
    public function everyAlgorithmRoundTripsThroughIssueSolveVerify(AltchaAlgorithm $algorithm): void
    {
        $config = Browser::config(algorithm: $algorithm);
        $challenge = (new ChallengeIssuer($config))->issue();

        $status = (new AltchaVerifier($config))->verify(Browser::solve($challenge, $algorithm));

        Assert::same($status, VerificationStatus::Verified);
    }

    public static function algorithmProvider(): iterable
    {
        foreach (AltchaAlgorithm::cases() as $algorithm) {
            yield $algorithm->value => [$algorithm];
        }
    }
}
