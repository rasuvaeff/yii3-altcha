<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Altcha\AltchaRegistry;
use Rasuvaeff\Yii3Altcha\AltchaRule;
use Rasuvaeff\Yii3Altcha\AltchaRuleHandler;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Rasuvaeff\Yii3Altcha\Tests\Support\RegistryReset;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Exception\UnexpectedRuleException;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\ValidationContext;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(AltchaRule::class)]
#[Covers(AltchaRuleHandler::class)]
#[Covers(AltchaRegistry::class)]
final class AltchaRuleHandlerTest
{
    #[BeforeTest]
    #[AfterTest]
    public function resetRegistry(): void
    {
        RegistryReset::reset();
    }

    public function aSolvedPayloadPasses(): void
    {
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());

        $result = $this->handler()->validate($payload, new AltchaRule(), new ValidationContext());

        Assert::true($result->isValid());
    }

    #[DataProvider('badValueProvider')]
    public function badValuesFailWithTheRuleMessage(mixed $value): void
    {
        $result = $this->handler()->validate($value, new AltchaRule(), new ValidationContext());

        Assert::false($result->isValid());
        Assert::same($result->getErrorMessages(), ['The CAPTCHA verification failed.']);
    }

    public static function badValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'int' => [42];
        yield 'array' => [['payload']];
        yield 'empty string' => [''];
        yield 'garbage string' => ['garbage'];
    }

    public function aCustomMessageIsUsed(): void
    {
        $result = $this->handler()->validate('x', new AltchaRule(message: 'Prove you are human.'), new ValidationContext());

        Assert::same($result->getErrorMessages(), ['Prove you are human.']);
    }

    public function theErrorParametersNameTheStatus(): void
    {
        $result = $this->handler()->validate('x', new AltchaRule(), new ValidationContext());

        Assert::same($result->getErrors()[0]->getParameters()['status'], 'malformed');
    }

    public function theErrorParametersCarryTheProperty(): void
    {
        $result = $this->handler()->validate('x', new AltchaRule(), new ValidationContext());

        Assert::true(array_key_exists('property', $result->getErrors()[0]->getParameters()));
    }

    public function anExplicitVerifierWinsOverTheRegistry(): void
    {
        AltchaRegistry::configure(new AltchaVerifier(Browser::config(secret: 'registry-secret-0123456789-abcdefghijk')));
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());

        $result = $this->handler()->validate($payload, new AltchaRule(), new ValidationContext());

        Assert::true($result->isValid());
    }

    public function anExplicitTranslatorWinsOverTheRegistry(): void
    {
        $registry = Understudy::for(TranslatorInterface::class);
        when(fn() => $registry->translate(Arg::any(), Arg::any(), Arg::any()))->returns('registry');
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()), $registry);
        $explicit = Understudy::for(TranslatorInterface::class);
        when(fn() => $explicit->translate(Arg::any(), Arg::any(), Arg::any()))->returns('explicit');

        $handler = new AltchaRuleHandler(verifier: new AltchaVerifier(Browser::config()), translator: $explicit);

        Assert::same($handler->validate('x', new AltchaRule(), new ValidationContext())->getErrorMessages(), ['explicit']);
    }

    public function theMessageIsTranslatedWhenATranslatorIsAvailable(): void
    {
        $translator = Understudy::for(TranslatorInterface::class);
        when(fn() => $translator->translate('The CAPTCHA verification failed.', [], 'yii3-altcha'))->returns('translated');

        $handler = new AltchaRuleHandler(verifier: new AltchaVerifier(Browser::config()), translator: $translator);
        $result = $handler->validate('x', new AltchaRule(), new ValidationContext());

        Assert::same($result->getErrorMessages(), ['translated']);
    }

    public function theRegistryFeedsAHandlerBuiltWithoutArguments(): void
    {
        $translator = Understudy::for(TranslatorInterface::class);
        when(fn() => $translator->translate(Arg::any(), Arg::any(), 'yii3-altcha'))->returns('from registry');
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()), $translator);
        $payload = Browser::solve((new ChallengeIssuer(Browser::config()))->issue());

        $handler = new AltchaRuleHandler();

        Assert::true($handler->validate($payload, new AltchaRule(), new ValidationContext())->isValid());
        Assert::same($handler->validate('x', new AltchaRule(), new ValidationContext())->getErrorMessages(), ['from registry']);
    }

    public function aRegistryFactoryIsCalledOnlyOnFirstUseAndOnlyOnce(): void
    {
        $calls = 0;
        AltchaRegistry::configure(static function () use (&$calls): AltchaVerifier {
            $calls++;

            return new AltchaVerifier(Browser::config());
        });

        Assert::same($calls, 0);

        $handler = new AltchaRuleHandler();
        $handler->validate('x', new AltchaRule(), new ValidationContext());
        $handler->validate('y', new AltchaRule(), new ValidationContext());

        Assert::same($calls, 1);
    }

    public function aTranslatorFactoryIsCalledOnlyWhenAMessageIsTranslatedAndOnlyOnce(): void
    {
        $calls = 0;
        $translator = Understudy::for(TranslatorInterface::class);
        when(fn() => $translator->translate(Arg::any(), Arg::any(), 'yii3-altcha'))->returns('lazy');
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()), static function () use (&$calls, $translator): TranslatorInterface {
            $calls++;

            return $translator;
        });
        $handler = new AltchaRuleHandler();

        $handler->validate(Browser::solve((new ChallengeIssuer(Browser::config()))->issue()), new AltchaRule(), new ValidationContext());

        Assert::same($calls, 0);
        Assert::same($handler->validate('x', new AltchaRule(), new ValidationContext())->getErrorMessages(), ['lazy']);
        Assert::same($handler->validate('y', new AltchaRule(), new ValidationContext())->getErrorMessages(), ['lazy']);
        Assert::same($calls, 1);
    }

    public function aTranslatorFactoryMayFindNoTranslator(): void
    {
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()), static fn(): ?TranslatorInterface => null);

        $result = (new AltchaRuleHandler())->validate('x', new AltchaRule(), new ValidationContext());

        Assert::same($result->getErrorMessages(), ['The CAPTCHA verification failed.']);
    }

    public function reconfiguringDropsATranslatorFactoryThatHasNotRun(): void
    {
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()), static fn(): TranslatorInterface => throw new \LogicException('factory must not run'));
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()));

        Assert::same(AltchaRegistry::translator(), null);
    }

    public function reconfiguringWithATranslatorFactoryForgetsTheInstance(): void
    {
        $first = Understudy::for(TranslatorInterface::class);
        $second = Understudy::for(TranslatorInterface::class);
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()), $first);
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()), static fn(): TranslatorInterface => $second);

        Assert::same(AltchaRegistry::translator(), $second);
    }

    public function theRuleIsNotRepeatableOnAProperty(): void
    {
        $attribute = (new \ReflectionClass(AltchaRule::class))->getAttributes(\Attribute::class)[0]->newInstance();

        Assert::same($attribute->flags, \Attribute::TARGET_PROPERTY);
    }

    public function reconfiguringWithAnInstanceReplacesAFactory(): void
    {
        AltchaRegistry::configure(static fn(): AltchaVerifier => throw new \LogicException('factory must not run'));
        $verifier = new AltchaVerifier(Browser::config());
        AltchaRegistry::configure($verifier);

        Assert::same(AltchaRegistry::verifier(), $verifier);
    }

    public function reconfiguringWithAFactoryForgetsTheInstance(): void
    {
        AltchaRegistry::configure(new AltchaVerifier(Browser::config()));
        $fresh = new AltchaVerifier(Browser::config());
        AltchaRegistry::configure(static fn(): AltchaVerifier => $fresh);

        Assert::same(AltchaRegistry::verifier(), $fresh);
    }

    public function withoutAVerifierTheHandlerFailsLoudly(): void
    {
        Expect::exception(\RuntimeException::class)->withMessageContaining('AltchaVerifier is not available');

        (new AltchaRuleHandler())->validate('x', new AltchaRule(), new ValidationContext());
    }

    public function aForeignRuleIsRejected(): void
    {
        Expect::exception(UnexpectedRuleException::class);

        $this->handler()->validate('x', new Required(), new ValidationContext());
    }

    public function theRuleNamesItsHandler(): void
    {
        Assert::same((new AltchaRule())->getHandler(), AltchaRuleHandler::class);
    }

    private function handler(): AltchaRuleHandler
    {
        return new AltchaRuleHandler(verifier: new AltchaVerifier(Browser::config()));
    }
}
