<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Benchmarks;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Rasuvaeff\Yii3Altcha\AltchaConfig;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Rasuvaeff\Yii3Altcha\Internal\AlgorithmMap;
use Rasuvaeff\Yii3Altcha\VerificationStatus;
use Testo\Bench;

/**
 * Server-side cost of one challenge: issuing it vs verifying a solved payload at the default PBKDF2 cost.
 */
final class VerifyBench
{
    private static ?string $payload = null;

    #[Bench(
        callables: [
            'issue' => [self::class, 'issue'],
        ],
        calls: 200,
        iterations: 5,
    )]
    public static function verify(): VerificationStatus
    {
        return (new AltchaVerifier(self::config()))->verify(self::payload());
    }

    /**
     * @return array<string, mixed>
     */
    public static function issue(): array
    {
        return (new ChallengeIssuer(self::config()))->issue();
    }

    private static function config(): AltchaConfig
    {
        return new AltchaConfig(secret: 'benchmark-secret-0123456789-abcdefghijk', maxCounter: 5);
    }

    private static function payload(): string
    {
        if (self::$payload === null) {
            $challenge = Challenge::fromArray((new ChallengeIssuer(self::config()))->issue());
            $config = self::config();
            $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(
                challenge: $challenge,
                algorithm: AlgorithmMap::deriveKey($config->algorithm),
            ));

            self::$payload = (new Payload(challenge: $challenge, solution: $solution ?? throw new \RuntimeException('unsolved')))->toBase64();
        }

        return self::$payload;
    }
}
