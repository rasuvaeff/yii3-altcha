# Examples

Runnable, self-contained scripts demonstrating yii3-altcha usage.

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| [`round-trip.php`](round-trip.php) | Issue a challenge, solve it as the browser would, verify it, replay and garbage rejection | no |
| [`widget.php`](widget.php) | Rendering the `<altcha-widget>` tag | no |

`round-trip.php` needs the dev dependencies (`composer install`): it uses the in-memory PSR-16 cache from
`yiisoft/test-support` and the official solver in the role of the browser.

## Running

```bash
docker run --rm -v "$PWD/..":/app -w /app composer:2 php examples/round-trip.php
docker run --rm -v "$PWD/..":/app -w /app composer:2 php examples/widget.php
```
