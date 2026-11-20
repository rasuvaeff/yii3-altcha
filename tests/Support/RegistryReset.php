<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests\Support;

use Rasuvaeff\Yii3Altcha\AltchaRegistry;

/**
 * Forgets what the static registry holds, without a test-only method on the production class.
 */
final class RegistryReset
{
    public static function reset(): void
    {
        \Closure::bind(static function (): void {
            AltchaRegistry::$factory = null;
            AltchaRegistry::$verifier = null;
            AltchaRegistry::$translator = null;
            AltchaRegistry::$translatorFactory = null;
        }, null, AltchaRegistry::class)();
    }
}
