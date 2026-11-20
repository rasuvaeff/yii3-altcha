# AGENTS.md — yii3-altcha

Guidance for AI agents working on this package. Read before changing code.

## What this is

Self-hosted ALTCHA proof-of-work CAPTCHA for Yii3, namespace `Rasuvaeff\Yii3Altcha`. The server issues a signed,
expiring challenge (`ChallengeIssuer`, served by `ChallengeAction`), the browser solves it, and `AltchaVerifier`
verifies the payload locally — no outgoing HTTP. Form glue: `AltchaRule` + `AltchaRuleHandler` for
`yiisoft/validator`, `AltchaWidget` for the `<altcha-widget>` tag, `AltchaRegistry` as the static holder the
no-argument rule handler reads (the default `yiisoft/validator` resolver builds handlers with `new`).

All cryptography belongs to the official `altcha-org/altcha`. Only `ChallengeIssuer`, `AltchaVerifier` and the
`@internal` `Internal\AlgorithmMap` and `Internal\AltchaFactory` may touch it, and no library type appears in a public signature, so a bump of the
library stays local. Do not reimplement the protocol.

The library ships breaking changes needed to match its JS twin in **minor** releases (its own CHANGELOG says so;
the canonical JSON of the signature already changed within 2.x). Keep the constraint on one minor (`~2.4.0`) and
raise it by hand after reading the library CHANGELOG and running the full suite.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Fail closed, never trust the payload.** An unsigned or unsignable challenge never verifies, challenges always
   expire, the signature is checked before any key derivation, and `verify()` returns `Malformed` instead of
   throwing on hostile input. Do not add a switch that disables expiry or signature checks.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`composer.lock` is gitignored (library).
`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- `AltchaConfig` bounds everything the server pays for: secret >= 32 bytes, `cost` <= 100 000 (issuing runs one
  derivation on a public endpoint), positive `ttlSeconds`/`maxCounter`. Keep new settings validated in the constructor.
- `Internal\AltchaFactory` is the only place that builds the library object, so issuer and verifier always agree. It
  sets a key-signature secret derived from the configured one (`hash_hmac` over a fixed context string): the issuer
  signs the derived key into the challenge and the verifier checks a solution with one HMAC, no derivation. On that
  path the posted counter is not compared — the proof is the derived key. Changing the context string invalidates
  outstanding challenges. A challenge without `keySignature` (another issuer, same secret) falls back to one derivation.
- The challenge counter must be passed to `createChallenge()`: without it the library picks a random prefix nobody can
  solve. `ChallengeIssuer` draws it from `[1, maxCounter]`.
- Replay protection uses PSR-16 (no atomic add): document the tiny race, do not claim exactly-once. A challenge is
  spent by its first signature-valid attempt (also a wrong solution); the marker lives until `expiresAt` +
  `REPLAY_MARGIN_SECONDS`, never the current config TTL. A refused write throws. The cache key is exactly 64
  characters (the PSR-16 guaranteed length): keep `REPLAY_KEY_PREFIX` + hash within it.
- The library checks expiry against the system time only. `AltchaVerifier` checks it against its own clock first, and
  counts the marker lifetime by the same clock: without that check a clock ahead of the system gave a negative TTL
  and the marker was never stored (replay protection silently off, found 2026-11-20). A missing or zero `expiresAt`
  means "never expires" to the library: the marker then takes the config TTL.
- The verification algorithm comes from the signed challenge (`AltchaAlgorithm::tryFrom`), never from the config.
- `config/bootstrap.php` registers lazy factories in `AltchaRegistry`: never resolve the verifier or the translator
  eagerly there, or an app without secret/cache stops booting (console included).
- `AltchaWidget` targets the v3 widget (`challenge` attribute). Checked in Chrome with npm `altcha` 3.7.0 on
  2026-11-20: six algorithms, key signature in the challenge, verified then replayed. The unit suite plays the
  browser with the PHP solver, so after raising the library or naming another widget version repeat the check in a
  real browser: a page with the widget, `ChallengeAction` behind it, the posted payload into `AltchaVerifier`.
- `config/di.php` builds `AltchaVerifier` with the container's `CacheInterface` only when `replayProtection` is on;
  a missing cache then fails loudly. Run `bin/config-merge-harness yii3-altcha` from the monorepo root after any
  change to `config/`.
- `tests/Support/Browser.php` plays the widget with the official solver; keep `cost`/`maxCounter` tiny in tests.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- **Validation patterns anchor with `\z`, never `$`.** In PCRE `$` also matches
  immediately before a trailing newline, so `/^…$/` silently accepts one `\n` —
  the value the whitelist was supposed to reject then travels on as if it were
  clean. `/D` does the same job. Cover it with a data-provider case carrying
  `"value\n"`, or the regression comes back unnoticed.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment
  (e.g. `actions/checkout@<sha> # v4`). Never revert to floating `@vN` tags.
  Updates go through Dependabot, which bumps the SHA and preserves the comment.
  Workflows also carry `permissions: { contents: read }` at workflow level and
  `persist-credentials: false` on every `actions/checkout` step. Verify with
  `zizmor --persona=auditor .github/` — must report no `unpinned-uses`,
  `excessive-permissions`, or `artipacked` findings.

## When you finish

- Update `README.md` **and `README.ru.md`** (both languages, same commit; and
  `examples/` if usage changed); update `CHANGELOG.md` when releasing.
- Re-run `composer build`; if the change affects the public API or release
  process, also run `make release-check`. Paste the output.
