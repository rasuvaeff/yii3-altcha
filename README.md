# rasuvaeff/yii3-altcha

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/yii3-altcha/v)](https://packagist.org/packages/rasuvaeff/yii3-altcha)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-altcha/downloads)](https://packagist.org/packages/rasuvaeff/yii3-altcha)
[![Build](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-altcha/php)](https://packagist.org/packages/rasuvaeff/yii3-altcha)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[Русская версия](README.ru.md)

[ALTCHA](https://altcha.org) proof-of-work CAPTCHA for Yii3: the server issues a signed challenge, the browser spends CPU
solving it, and the server verifies the answer locally. No third-party service, no outgoing request, no site key
account — the data never leaves your server.

Built on the official [`altcha-org/altcha`](https://github.com/altcha-org/altcha-lib-php) library, which owns all the
cryptography; this package is the Yii3 wiring around it.

> Using an AI coding assistant? [llms.txt](llms.txt) contains a compact API reference you can share with the model.

## Requirements

- PHP 8.3+
- `altcha-org/altcha` 2.4.x (pinned to one minor: the library ships breaking changes in minors)
- `yiisoft/validator`, `yiisoft/widget`, `yiisoft/html`, `yiisoft/translator`
- A PSR-16 cache (`Psr\SimpleCache\CacheInterface`) in the container — only while replay protection is on (the default)
- Optional: `yiisoft/translator-message-php` for the bundled Russian translation of the rule message; without it
  the message stays in English

## Installation

```bash
composer require rasuvaeff/yii3-altcha
```

With `yiisoft/config` the package registers its DI definitions, params and bootstrap automatically.

## Usage

### Configuration

Set the secret through the environment-backed params of your application; everything else has a default:

```php
// config/params.php
return [
    'rasuvaeff/yii3-altcha' => [
        'secret' => $_ENV['ALTCHA_SECRET'],   // >= 32 bytes, never committed
        'algorithm' => 'PBKDF2/SHA-256',
        'cost' => 5000,
        'maxCounter' => 1000,
        'ttlSeconds' => 600,
        'replayProtection' => true,
    ],
];
```

| Param | Default | Meaning |
|---|---|---|
| `secret` | `''` (must be set) | HMAC secret signing every challenge, at least 32 bytes. An unset secret fails the container loudly |
| `algorithm` | `PBKDF2/SHA-256` | `PBKDF2/SHA-256\|384\|512` or `SHA-256\|384\|512` — the algorithms the browser widget solves natively |
| `cost` | `5000` | Key-derivation iterations per attempt; issuing a challenge costs the server one derivation, so it is capped at 100 000 |
| `maxCounter` | `1000` | Upper bound of the secret counter the browser searches; the average solve is half of it |
| `ttlSeconds` | `600` | Lifetime of a challenge, hence of a solved payload |
| `replayProtection` | `true` | Spend a challenge on its first attempt with a valid signature (needs a PSR-16 cache in the container) |
| `translation.category` | `yii3-altcha` | Translator category of the rule message |

Difficulty is `cost × maxCounter / 2` iterations in the browser, on average. The defaults aim at roughly a second on a
laptop; measure on your slowest supported device and tune `maxCounter` first.

### Challenge endpoint

`ChallengeAction` is a PSR-15 request handler returning a fresh challenge as uncached JSON. Put it on any route:

```php
use Rasuvaeff\Yii3Altcha\ChallengeAction;
use Yiisoft\Router\Route;

Route::get('/altcha/challenge')->action(ChallengeAction::class);
```

Every challenge costs the server one key derivation (about 3 ms at the default cost, 60–110 ms at the cap of
100 000 depending on the algorithm), and the route is public: put a rate limiter in front of it.

### Widget

`AltchaWidget` renders the `<altcha-widget>` tag of the v3 widget (npm `altcha` 3.x), which takes the challenge URL in
its `challenge` attribute (v2 widgets used `challengeurl`). The widget script itself is loaded by your application —
pin a concrete version rather than `latest`. Checked in Chrome with `altcha` 3.7.0: all six algorithms solve and
verify.

```php
use Rasuvaeff\Yii3Altcha\AltchaWidget;

echo AltchaWidget::widget()
    ->withChallengeUrl('/altcha/challenge')
    ->withFieldName('altcha')   // the name of the posted form field, `altcha` by default
    ->withLanguage('ru')        // widget strings; load the matching `altcha/i18n/ru` file
    ->withAttributes(['auto' => 'onsubmit'])   // any other widget attribute
    ->render();
```

If the challenge URL lives on another origin, send `Access-Control-Allow-Origin` from the challenge route.

### Validation

`AltchaRule` plugs into `yiisoft/validator`; the value is the raw base64 string the widget posts:

```php
use Rasuvaeff\Yii3Altcha\AltchaRule;

final class RegisterForm
{
    #[AltchaRule]
    public string $altcha = '';
}
```

Rule handlers are built without arguments by the default `yiisoft/validator` resolver, so the handler reads the
verifier from `AltchaRegistry`. The package bootstrap registers a factory there, so the verifier is built on the first
verification: an application without a secret or a cache still boots, and fails only when a form checks a CAPTCHA.
The translator is looked up the same way, on the first message that needs it.

The first check spends the challenge. Validate the form once per request: a second validation of the same payload
answers `replayed`. After any failed submit the visitor needs a new challenge — the widget fetches one when the page
is rendered again. Use one `AltchaRule` per property.

### Verifying directly

```php
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\VerificationStatus;

$status = $verifier->verify($_POST['altcha'] ?? '');

if ($status !== VerificationStatus::Verified) {
    // $status->value: expired | invalid_signature | invalid_solution | replayed | malformed
}
```

`verify()` never throws on hostile input — whatever cannot be decoded is `Malformed`. Replay-cache errors do
propagate, including a cache that refuses the write.

### Public API

| Class | Description |
|---|---|
| `AltchaConfig` | Validated settings VO: secret, algorithm, cost, maxCounter, ttlSeconds |
| `AltchaAlgorithm` | Enum of the algorithms the widget can solve |
| `ChallengeIssuer` | Issues a signed, expiring challenge as the array the widget expects |
| `ChallengeAction` | PSR-15 handler serving a challenge as JSON |
| `AltchaVerifier` | Verifies a posted payload locally; optional replay cache spending each challenge on first use |
| `VerificationStatus` | `Verified`, `Expired`, `InvalidSignature`, `InvalidSolution`, `Replayed`, `Malformed` |
| `AltchaRule` / `AltchaRuleHandler` | `yiisoft/validator` rule |
| `AltchaRegistry` | Static holder of the verifier (or its lazy factory) and translator for the rule handler |
| `AltchaWidget` | Renders the `<altcha-widget>` tag (v3 widget: `challenge`, `name`, `language`, extra attributes) |

## Security

- **Proof-of-work is a cost, not proof of a human.** It makes bulk automation expensive, not impossible; a determined
  bot with CPU still passes. Combine with rate limiting for anything that matters.
- **The secret signs every challenge.** Without a signature secret nothing can verify (fail-closed). Keep it in the
  environment, at least 32 bytes; rotating it invalidates outstanding challenges.
- **Challenges always expire.** There is no switch to disable expiry; the replay window is `ttlSeconds`.
- **Replay protection** (on by default) spends a challenge through a PSR-16 cache on its first attempt with a valid
  signature: a solved payload is accepted once, and a wrong solution cannot be retried against the same challenge.
  The marker lives until the challenge expires plus 60 s of clock-skew margin; its cache key is 64 characters, the
  length every PSR-16 cache has to accept. PSR-16 has no atomic add, so two simultaneous submissions of the same payload can both pass in
  a tiny window; use a shared cache (Redis) if several workers serve the form. A cache that refuses the write raises
  an exception instead of silently dropping the protection. Switching it off makes one solve good for every
  submission until expiry.
- **Issuing costs CPU, verifying does not.** Issuing a challenge runs one key derivation (about 3 ms at the default
  cost, the reason `cost` is capped at 100 000). The challenge carries an HMAC of the derived key, made with a key
  derived from your secret, so a solution is verified with one HMAC (microseconds) and no derivation: the proof is
  the derived key itself, the posted counter is not compared. Rate-limit the challenge route.
- **Hostile input is bounded.** Payloads over 8 KiB are rejected before decoding; the signature is checked before any
  key derivation, so a forged `cost` cannot burn server CPU.
- The signed parameters — algorithm, cost, expiry — come from the challenge, not from your config at verify time:
  changing the config affects new challenges only and never invalidates outstanding ones. Expiry is checked against
  the system time, and against the PSR-20 clock too if you pass one to `AltchaVerifier`: a challenge has to be alive
  by both, so a clock that disagrees with the system rejects more and never weakens replay protection.

## Examples

See [examples/](examples/) for runnable scripts.
Examples are expected to execute without fatal errors and stay aligned with the
documented public API.

| Script | Shows | Needs server? |
|---|---|---|
| [`round-trip.php`](examples/round-trip.php) | Issue, solve as the browser would, verify; replay and garbage rejection | no |
| [`widget.php`](examples/widget.php) | Rendering the `<altcha-widget>` tag | no |

## Development

No PHP/Composer on the host — run in Docker via the `composer:2` image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make install
make build
make cs-fix
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## License

[BSD-3-Clause](LICENSE.md)
