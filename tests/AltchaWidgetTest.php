<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Rasuvaeff\Yii3Altcha\AltchaWidget;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Widget\WidgetFactory;

#[Test]
#[Covers(AltchaWidget::class)]
final class AltchaWidgetTest
{
    #[BeforeTest]
    public function setUp(): void
    {
        WidgetFactory::initialize();
    }

    public function rendersTheWidgetTagWithTheChallengeUrl(): void
    {
        $html = AltchaWidget::widget()->withChallengeUrl('/altcha/challenge')->render();

        Assert::same($html, '<altcha-widget challengeurl="/altcha/challenge" name="altcha"></altcha-widget>');
    }

    public function theFieldNameIsConfigurable(): void
    {
        $html = AltchaWidget::widget()->withChallengeUrl('/c')->withFieldName('captcha')->render();

        Assert::string($html)->contains('name="captcha"');
    }

    public function withersDoNotMutateTheOriginal(): void
    {
        $base = AltchaWidget::widget()->withChallengeUrl('/c');
        $base->withFieldName('other');

        Assert::string($base->render())->contains('name="altcha"');
    }

    public function theUrlWitherDoesNotMutateTheOriginal(): void
    {
        $base = AltchaWidget::widget();
        $base->withChallengeUrl('/c');

        Expect::exception(\RuntimeException::class)->withMessage('challengeUrl is required');

        $base->render();
    }

    public function theChallengeUrlIsEscaped(): void
    {
        $html = AltchaWidget::widget()->withChallengeUrl('/c?a=1&b="2"')->render();

        Assert::false(str_contains($html, 'b="2"'));
    }

    public function renderingWithoutAChallengeUrlFails(): void
    {
        Expect::exception(\RuntimeException::class)->withMessage('challengeUrl is required');

        AltchaWidget::widget()->render();
    }
}
