<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use Closure;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Static registry populated during application bootstrap: yiisoft/validator builds rule handlers
 * without arguments (`SimpleRuleHandlerContainer`), so the handler reads its collaborators here.
 *
 * A factory closure keeps the verifier lazy: an application without an ALTCHA secret or a PSR-16 cache
 * boots normally and fails only when a form actually verifies a CAPTCHA.
 *
 * @api
 */
final class AltchaRegistry
{
    /** @var (Closure(): AltchaVerifier)|null */
    private static ?Closure $factory = null;
    private static ?AltchaVerifier $verifier = null;
    private static ?TranslatorInterface $translator = null;

    /**
     * @param AltchaVerifier|(Closure(): AltchaVerifier) $verifier the verifier, or a factory called on first use
     */
    public static function configure(AltchaVerifier|Closure $verifier, ?TranslatorInterface $translator = null): void
    {
        self::$verifier = $verifier instanceof AltchaVerifier ? $verifier : null;
        self::$factory = $verifier instanceof Closure ? $verifier : null;
        self::$translator = $translator;
    }

    public static function verifier(): ?AltchaVerifier
    {
        if (!self::$verifier instanceof AltchaVerifier && self::$factory instanceof Closure) {
            self::$verifier = (self::$factory)();
        }

        return self::$verifier;
    }

    public static function translator(): ?TranslatorInterface
    {
        return self::$translator;
    }
}
