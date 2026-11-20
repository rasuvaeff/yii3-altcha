# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 — 2026-11-20

- Verifying a solution no longer repeats the key derivation: the challenge carries an HMAC of the derived key (made with a key derived from the secret), so verification is one HMAC. The posted counter is not compared any more — the proof is the derived key.
- Fixed: replay protection was silently off when the verifier's clock ran ahead of the system time (negative marker lifetime). The verifier now checks expiry against its own clock too; a zero `expiresAt` is treated as "no expiry", like the library does.
- The replay cache key is 64 characters (`yii3-altcha.` + 52 hex) instead of 81: PSR-16 guarantees only 64.
- `AltchaRegistry::configure()` takes a `translatorFactory`; the bootstrap no longer resolves the translator on every boot.
- `AltchaRule` is no longer repeatable on a property.
- Docs: the library constraint, the cost of the `cost` cap per algorithm, the optional `yiisoft/translator-message-php`, one validation per request, the widget version checked in a browser.
- Initial implementation (review fixes folded in: v3 widget `challenge` attribute, lazy bootstrap, challenge spent on first signature-valid attempt, marker lifetime from the challenge expiry, algorithm from the signed challenge, `cost` capped at 100 000, library pinned to `~2.4.0`): `ChallengeIssuer` / `ChallengeAction` issue signed, expiring ALTCHA challenges; `AltchaVerifier` verifies the posted payload locally (no outgoing request) with an optional one-time-use replay cache and an 8 KiB payload cap; `AltchaRule` / `AltchaRuleHandler` for `yiisoft/validator`; `AltchaWidget` renders the `<altcha-widget>` tag; config-plugin wiring (`di`, `params`, `bootstrap`). Cryptography is delegated to the official `altcha-org/altcha`.
