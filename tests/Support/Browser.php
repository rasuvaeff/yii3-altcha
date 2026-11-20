<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests\Support;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Rasuvaeff\Yii3Altcha\AltchaAlgorithm;
use Rasuvaeff\Yii3Altcha\AltchaConfig;
use Rasuvaeff\Yii3Altcha\Internal\AlgorithmMap;

/**
 * Plays the part of the widget: solves a challenge and returns the base64 payload it would post.
 */
final class Browser
{
    public const string SECRET = 'test-secret-0123456789-abcdefghijklmnop';

    public static function config(
        string $secret = self::SECRET,
        AltchaAlgorithm $algorithm = AltchaAlgorithm::Pbkdf2Sha256,
        int $ttlSeconds = 600,
    ): AltchaConfig {
        return new AltchaConfig(secret: $secret, algorithm: $algorithm, cost: 10, maxCounter: 50, ttlSeconds: $ttlSeconds);
    }

    /**
     * @param array<string, mixed> $challenge
     */
    public static function solve(array $challenge, AltchaAlgorithm $algorithm = AltchaAlgorithm::Pbkdf2Sha256): string
    {
        $parsed = Challenge::fromArray($challenge);
        $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(
            challenge: $parsed,
            algorithm: AlgorithmMap::deriveKey($algorithm),
            timeout: 30.0,
        ));

        if (!$solution instanceof \AltchaOrg\Altcha\Solution) {
            throw new \RuntimeException('The challenge was not solved');
        }

        return (new Payload(challenge: $parsed, solution: $solution))->toBase64();
    }

    /**
     * The solution the widget would post for a challenge, as an array.
     *
     * @param array<string, mixed> $challenge
     * @return array{counter: int, derivedKey: string}
     */
    public static function solution(array $challenge): array
    {
        /** @var array{solution: array{counter: int, derivedKey: string}} $decoded */
        $decoded = json_decode((string) base64_decode(self::solve($challenge), strict: true), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);

        return $decoded['solution'];
    }

    /**
     * @param array<string, mixed> $challenge
     * @param array<string, mixed> $solution
     */
    public static function encode(array $challenge, array $solution): string
    {
        return base64_encode(json_encode(['challenge' => $challenge, 'solution' => $solution], JSON_THROW_ON_ERROR));
    }
}
