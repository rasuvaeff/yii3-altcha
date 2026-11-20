<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3Altcha\AltchaAlgorithm;
use Rasuvaeff\Yii3Altcha\AltchaConfig;
use Rasuvaeff\Yii3Altcha\AltchaRuleHandler;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\IdMessageReader;
use Yiisoft\Translator\IntlMessageFormatter;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;

/** @var array $params */

return [
    AltchaConfig::class => [
        'class' => AltchaConfig::class,
        '__construct()' => [
            'secret' => $params['rasuvaeff/yii3-altcha']['secret'],
            'algorithm' => AltchaAlgorithm::from($params['rasuvaeff/yii3-altcha']['algorithm']),
            'cost' => $params['rasuvaeff/yii3-altcha']['cost'],
            'maxCounter' => $params['rasuvaeff/yii3-altcha']['maxCounter'],
            'ttlSeconds' => $params['rasuvaeff/yii3-altcha']['ttlSeconds'],
        ],
    ],
    AltchaVerifier::class => static fn(ContainerInterface $container): AltchaVerifier => new AltchaVerifier(
        config: $container->get(AltchaConfig::class),
        replayCache: $params['rasuvaeff/yii3-altcha']['replayProtection']
            ? $container->get(CacheInterface::class)
            : null,
    ),
    AltchaRuleHandler::class => [
        '__construct()' => [
            'translationCategory' => $params['rasuvaeff/yii3-altcha']['translation.category'],
        ],
    ],
    'yii3-altcha.categorySource' => [
        'definition' => static function () use ($params): CategorySource {
            $reader = class_exists(MessageSource::class)
                ? new MessageSource(dirname(__DIR__) . '/messages')
                : new IdMessageReader();

            $formatter = extension_loaded('intl')
                ? new IntlMessageFormatter()
                : new SimpleMessageFormatter();

            return new CategorySource(
                $params['rasuvaeff/yii3-altcha']['translation.category'],
                $reader,
                $formatter,
            );
        },
        'tags' => ['translation.categorySource'],
    ],
];
