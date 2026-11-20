<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use Closure;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Static registry populated during application bootstrap: yiisoft/validator builds rule handlers
 * without arguments (`SimpleRuleHandlerContainer`), so the handler reads its collaborators here.
 *
 * Factory closures keep both collaborators lazy: an application without an ALTCHA secret or a PSR-16 cache
 * boots normally and fails only when a form actually verifies a CAPTCHA, and the translator is not built
 * for requests that never show the rule message.
 *
 * @api
 */
final class AltchaRegistry
{
    /** @var (Closure(): AltchaVerifier)|null */
    private static ?Closure $factory = null;
    private static ?AltchaVerifier $verifier = null;

    /** @var (Closure(): ?TranslatorInterface)|null */
    private static ?Closure $translatorFactory = null;
    private static ?TranslatorInterface $translator = null;

    /**
     * @param AltchaVerifier|(Closure(): AltchaVerifier) $verifier the verifier, or a factory called on first use
     * @param TranslatorInterface|(Closure(): ?TranslatorInterface)|null $translator the translator, or a factory
     *                                                                               called on first use
     */
    public static function configure(AltchaVerifier|Closure $verifier, TranslatorInterface|Closure|null $translator = null): void
    {
        self::$verifier = $verifier instanceof AltchaVerifier ? $verifier : null;
        self::$factory = $verifier instanceof Closure ? $verifier : null;
        self::$translator = $translator instanceof TranslatorInterface ? $translator : null;
        self::$translatorFactory = $translator instanceof TranslatorInterface ? null : $translator;
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
        if (self::$translatorFactory instanceof Closure) {
            self::$translator = (self::$translatorFactory)();
            self::$translatorFactory = null;
        }

        return self::$translator;
    }
}
