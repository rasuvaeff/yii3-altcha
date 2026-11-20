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

        Assert::same($html, '<altcha-widget challenge="/altcha/challenge" name="altcha"></altcha-widget>');
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

    public function theLanguageIsRenderedWhenSet(): void
    {
        $html = AltchaWidget::widget()->withChallengeUrl('/c')->withLanguage('ru')->render();

        Assert::same($html, '<altcha-widget challenge="/c" name="altcha" language="ru"></altcha-widget>');
    }

    public function extraAttributesAreRendered(): void
    {
        $html = AltchaWidget::widget()
            ->withChallengeUrl('/c')
            ->withAttributes(['auto' => 'onsubmit', 'hidefooter' => true, 'debug' => false])
            ->render();

        Assert::same($html, '<altcha-widget auto="onsubmit" hidefooter challenge="/c" name="altcha"></altcha-widget>');
    }

    public function extraAttributesCannotOverrideTheChallengeUrlOrTheFieldName(): void
    {
        $html = AltchaWidget::widget()
            ->withChallengeUrl('/c')
            ->withAttributes(['challenge' => '/evil', 'name' => 'other'])
            ->render();

        Assert::same($html, '<altcha-widget challenge="/c" name="altcha"></altcha-widget>');
    }

    public function languageAndAttributeWithersDoNotMutateTheOriginal(): void
    {
        $base = AltchaWidget::widget()->withChallengeUrl('/c');
        $base->withLanguage('ru');
        $base->withAttributes(['auto' => 'onload']);

        Assert::same($base->render(), '<altcha-widget challenge="/c" name="altcha"></altcha-widget>');
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
