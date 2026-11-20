<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use Yiisoft\Html\Html;
use Yiisoft\Widget\Widget;

/**
 * Renders the `<altcha-widget>` tag of the v3 widget, which takes the challenge URL in its `challenge` attribute.
 * The widget script itself is loaded by the application.
 *
 * @api
 */
final class AltchaWidget extends Widget
{
    private ?string $challengeUrl = null;
    private string $fieldName = 'altcha';
    private ?string $language = null;

    /** @var array<string, string|bool> */
    private array $attributes = [];

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

    /**
     * ISO 639-1 code of the widget strings (`ru`, `de`); the matching i18n file of the widget must be loaded.
     */
    public function withLanguage(string $language): self
    {
        $new = clone $this;
        $new->language = $language;

        return $new;
    }

    /**
     * Any other widget attribute (`auto`, `type`, `display`, `workers`…). `true` renders a bare attribute,
     * `false` omits it. `challenge` and `name` always come from their withers and win over this map.
     *
     * @param array<string, string|bool> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        $new = clone $this;
        $new->attributes = $attributes;

        return $new;
    }

    #[\Override]
    public function render(): string
    {
        $url = $this->challengeUrl ?? throw new \RuntimeException('challengeUrl is required');

        $tag = Html::tag('altcha-widget')
            ->attributes($this->attributes)
            ->attribute('challenge', $url)
            ->attribute('name', $this->fieldName);

        if ($this->language !== null) {
            $tag = $tag->attribute('language', $this->language);
        }

        return $tag->render();
    }
}
