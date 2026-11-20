# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Initial implementation (review fixes folded in: v3 widget `challenge` attribute, lazy bootstrap, challenge spent on first signature-valid attempt, marker lifetime from the challenge expiry, algorithm from the signed challenge, `cost` capped at 100 000, library pinned to `~2.4.0`): `ChallengeIssuer` / `ChallengeAction` issue signed, expiring ALTCHA challenges; `AltchaVerifier` verifies the posted payload locally (no outgoing request) with an optional one-time-use replay cache and an 8 KiB payload cap; `AltchaRule` / `AltchaRuleHandler` for `yiisoft/validator`; `AltchaWidget` renders the `<altcha-widget>` tag; config-plugin wiring (`di`, `params`, `bootstrap`). Cryptography is delegated to the official `altcha-org/altcha`.
