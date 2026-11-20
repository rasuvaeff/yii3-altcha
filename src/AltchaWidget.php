<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use Yiisoft\Html\Html;
use Yiisoft\Widget\Widget;

/**
 * Renders the `<altcha-widget>` tag. The widget script itself is loaded by the application.
 *
 * @api
 */
final class AltchaWidget extends Widget
{
    private ?string $challengeUrl = null;
    private string $fieldName = 'altcha';

    public function withChallengeUrl(string $url): self
    {
        $new = clone $this;
        $new->challengeUrl = $url;

        return $new;
    }

    public function withFieldName(string $name): self
    {
        $new = clone $this;
        $new->fieldName = $name;

        return $new;
    }

    #[\Override]
    public function render(): string
    {
        $url = $this->challengeUrl ?? throw new \RuntimeException('challengeUrl is required');

        return Html::tag('altcha-widget')
            ->attribute('challengeurl', $url)
            ->attribute('name', $this->fieldName)
            ->render();
    }
}
