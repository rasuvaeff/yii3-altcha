<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Rasuvaeff\Yii3Altcha\AltchaRegistry;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Yiisoft\Translator\TranslatorInterface;

return [
    static function (ContainerInterface $container): void {
        AltchaRegistry::configure(
            verifier: $container->get(AltchaVerifier::class),
            translator: $container->has(TranslatorInterface::class)
                ? $container->get(TranslatorInterface::class)
                : null,
        );
    },
];
