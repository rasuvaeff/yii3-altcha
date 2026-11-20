<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Rasuvaeff\Yii3Altcha\AltchaConfig;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

// Server: the secret comes from the environment; a small cost keeps the demo instant.
$config = new AltchaConfig(secret: str_repeat('s', 32), cost: 100, maxCounter: 200);

// 1. The challenge endpoint hands this to the widget.
$challenge = (new ChallengeIssuer($config))->issue();
echo "challenge: algorithm={$challenge['parameters']['algorithm']} cost={$challenge['parameters']['cost']}\n";

// 2. The browser burns CPU searching for the counter (here: the official solver plays the browser).
$parsed = Challenge::fromArray($challenge);
$solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(
    challenge: $parsed,
    algorithm: $config->algorithm->deriveKey(),
));
$payload = (new Payload(challenge: $parsed, solution: $solution ?? throw new RuntimeException('unsolved')))->toBase64();

// 3. The form posts the payload; verification is local — no outgoing call.
$verifier = new AltchaVerifier($config, replayCache: new MemorySimpleCache());
echo 'first submit:  ' . $verifier->verify($payload)->value . "\n";
echo 'second submit: ' . $verifier->verify($payload)->value . "\n";
echo 'garbage:       ' . $verifier->verify('not a payload')->value . "\n";
