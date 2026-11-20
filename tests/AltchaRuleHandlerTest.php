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
        AltchaRegistry::reset();
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
