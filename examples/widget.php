<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3Altcha\AltchaWidget;
use Yiisoft\Widget\WidgetFactory;

WidgetFactory::initialize();

echo "=== Default field name ===\n";
echo AltchaWidget::widget()->withChallengeUrl('/altcha/challenge')->render() . "\n\n";

echo "=== Custom field name ===\n";
echo AltchaWidget::widget()->withChallengeUrl('/altcha/challenge')->withFieldName('captcha')->render() . "\n";
