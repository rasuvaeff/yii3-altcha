<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Rasuvaeff\Yii3Altcha\AltchaAlgorithm;
use Rasuvaeff\Yii3Altcha\AltchaConfig;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(AltchaConfig::class)]
final class AltchaConfigTest
{
    public function defaultsAreSolvableInABrowserAndShortLived(): void
    {
        $config = new AltchaConfig(secret: Browser::SECRET);

        Assert::same($config->algorithm, AltchaAlgorithm::Pbkdf2Sha256);
        Assert::same($config->cost, 5000);
        Assert::same($config->maxCounter, 1000);
        Assert::same($config->ttlSeconds, 600);
    }

    public function exactlyTheMinimumSecretLengthIsAccepted(): void
    {
        Assert::same((new AltchaConfig(secret: str_repeat('s', 32)))->secret, str_repeat('s', 32));
    }

    public function theSmallestCounterAndTtlAreLegal(): void
    {
        $config = new AltchaConfig(secret: Browser::SECRET, maxCounter: 1, ttlSeconds: 1);

        Assert::same($config->maxCounter, 1);
        Assert::same($config->ttlSeconds, 1);
    }

    public function theCostCeilingAndFloorAreLegal(): void
    {
        Assert::same((new AltchaConfig(secret: Browser::SECRET, cost: 1))->cost, 1);
        Assert::same((new AltchaConfig(secret: Browser::SECRET, cost: 100_000))->cost, 100_000);
    }

    #[DataProvider('invalidProvider')]
    public function rejectsInvalidSettings(string $secret, int $cost, int $maxCounter, int $ttl, string $message): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessage($message);

        new AltchaConfig(secret: $secret, cost: $cost, maxCounter: $maxCounter, ttlSeconds: $ttl);
    }

    public static function invalidProvider(): iterable
    {
        yield 'empty secret' => ['', 5000, 1000, 600, 'The ALTCHA secret must be at least 32 bytes'];
        yield 'short secret' => [str_repeat('s', 31), 5000, 1000, 600, 'The ALTCHA secret must be at least 32 bytes'];
        yield 'zero cost' => [Browser::SECRET, 0, 1000, 600, 'The ALTCHA cost must be between 1 and 100000'];
        yield 'huge cost' => [Browser::SECRET, 100_001, 1000, 600, 'The ALTCHA cost must be between 1 and 100000'];
        yield 'zero counter' => [Browser::SECRET, 5000, 0, 600, 'The ALTCHA max counter must be positive'];
        yield 'zero ttl' => [Browser::SECRET, 5000, 1000, 0, 'The ALTCHA challenge TTL must be positive'];
    }
}
