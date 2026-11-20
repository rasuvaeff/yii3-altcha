<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests\Support;

/**
 * Reads a rendered `<altcha-widget>` tag as a map of its attributes: yiisoft/html 3 and 4 put the same
 * attributes in a different order, and the order means nothing to the browser.
 */
final readonly class Tag
{
    /**
     * @return array<string, string|true> attribute name to its raw (still escaped) value, `true` for a bare one, sorted by name
     */
    public static function attributes(string $html): array
    {
        if (preg_match('~\A<altcha-widget((?:\s+[a-z-]+(?:="[^"]*")?)*)></altcha-widget>\z~', $html, $tag) !== 1) {
            throw new \RuntimeException('Not an <altcha-widget> tag: ' . $html);
        }

        preg_match_all('~([a-z-]+)(?:="([^"]*)")?~', $tag[1], $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $attributes = [];

        foreach ($found as $attribute) {
            $attributes[(string) $attribute[1]] = $attribute[2] ?? true;
        }

        ksort($attributes);

        return $attributes;
    }
}
