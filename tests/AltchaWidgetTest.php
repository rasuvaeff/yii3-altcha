<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Rasuvaeff\Yii3Altcha\AltchaWidget;
use Rasuvaeff\Yii3Altcha\Tests\Support\Tag;
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

        Assert::same(Tag::attributes($html), ['challenge' => '/altcha/challenge', 'name' => 'altcha']);
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

        Assert::same(Tag::attributes($html), ['challenge' => '/c', 'language' => 'ru', 'name' => 'altcha']);
    }

    public function extraAttributesAreRendered(): void
    {
        $html = AltchaWidget::widget()
            ->withChallengeUrl('/c')
            ->withAttributes(['auto' => 'onsubmit', 'hidefooter' => true, 'debug' => false])
            ->render();

        Assert::same(Tag::attributes($html), ['auto' => 'onsubmit', 'challenge' => '/c', 'hidefooter' => true, 'name' => 'altcha']);
    }

    public function extraAttributesCannotOverrideTheChallengeUrlOrTheFieldName(): void
    {
        $html = AltchaWidget::widget()
            ->withChallengeUrl('/c')
            ->withAttributes(['challenge' => '/evil', 'name' => 'other'])
            ->render();

        Assert::same(Tag::attributes($html), ['challenge' => '/c', 'name' => 'altcha']);
    }

    public function languageAndAttributeWithersDoNotMutateTheOriginal(): void
    {
        $base = AltchaWidget::widget()->withChallengeUrl('/c');
        $base->withLanguage('ru');
        $base->withAttributes(['auto' => 'onload']);

        Assert::same(Tag::attributes($base->render()), ['challenge' => '/c', 'name' => 'altcha']);
    }

    public function theChallengeUrlIsEscaped(): void
    {
        $html = AltchaWidget::widget()->withChallengeUrl('/c?a=1&b="2"')->render();

        Assert::false(str_contains($html, 'b="2"'));
        Assert::same(Tag::attributes($html)['challenge'], '/c?a=1&amp;b=&quot;2&quot;');
    }

    public function renderingWithoutAChallengeUrlFails(): void
    {
        Expect::exception(\RuntimeException::class)->withMessage('challengeUrl is required');

        AltchaWidget::widget()->render();
    }
}
