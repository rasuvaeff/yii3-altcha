<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use Yiisoft\Translator\TranslatorInterface;

/**
 * Static registry populated during application bootstrap: yiisoft/validator builds rule handlers
 * without arguments (`SimpleRuleHandlerContainer`), so the handler reads its collaborators here.
 *
 * @api
 */
final class AltchaRegistry
{
    private static ?AltchaVerifier $verifier = null;
    private static ?TranslatorInterface $translator = null;

    /** @api */
    public static function configure(AltchaVerifier $verifier, ?TranslatorInterface $translator = null): void
    {
        self::$verifier = $verifier;
        self::$translator = $translator;
    }

    /** @internal for tests: forgets the configured collaborators */
    public static function reset(): void
    {
        self::$verifier = null;
        self::$translator = null;
    }

    public static function verifier(): ?AltchaVerifier
    {
        return self::$verifier;
    }

    public static function translator(): ?TranslatorInterface
    {
        return self::$translator;
    }
}
