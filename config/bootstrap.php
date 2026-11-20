<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Rasuvaeff\Yii3Altcha\AltchaRegistry;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Yiisoft\Translator\TranslatorInterface;

return [
    static function (ContainerInterface $container): void {
        AltchaRegistry::configure(
            verifier: static fn(): AltchaVerifier => $container->get(AltchaVerifier::class),
            translator: static fn(): ?TranslatorInterface => $container->has(TranslatorInterface::class)
                ? $container->get(TranslatorInterface::class)
                : null,
        );
    },
];
