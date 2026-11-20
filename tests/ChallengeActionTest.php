<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Altcha\ChallengeAction;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ChallengeAction::class)]
final class ChallengeActionTest
{
    public function returnsTheChallengeAsUncachedJson(): void
    {
        $psr17 = new Psr17Factory();
        $action = new ChallengeAction(new ChallengeIssuer(Browser::config()), $psr17, $psr17);

        $response = $action->handle(new ServerRequest('GET', '/altcha/challenge'));

        Assert::same($response->getStatusCode(), 200);
        Assert::same($response->getHeaderLine('Content-Type'), 'application/json');
        Assert::same($response->getHeaderLine('Cache-Control'), 'no-store');
        Assert::json((string) $response->getBody())
            ->isObject()
            ->hasKeys('parameters', 'signature');
    }

    public function theServedChallengeIsSolvable(): void
    {
        $psr17 = new Psr17Factory();
        $action = new ChallengeAction(new ChallengeIssuer(Browser::config()), $psr17, $psr17);

        $challenge = json_decode((string) $action->handle(new ServerRequest('GET', '/'))->getBody(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);

        Assert::true(Browser::solve($challenge) !== '');
    }

    public function slashesInTheChallengeAreNotEscaped(): void
    {
        $psr17 = new Psr17Factory();
        $action = new ChallengeAction(new ChallengeIssuer(Browser::config()), $psr17, $psr17);

        Assert::string((string) $action->handle(new ServerRequest('GET', '/'))->getBody())->contains('PBKDF2/SHA-256');
    }
}
