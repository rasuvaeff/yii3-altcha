# AGENTS.md — yii3-altcha

Guidance for AI agents working on this package. Read before changing code.

## What this is

Self-hosted ALTCHA proof-of-work CAPTCHA for Yii3, namespace `Rasuvaeff\Yii3Altcha`. The server issues a signed,
expiring challenge (`ChallengeIssuer`, served by `ChallengeAction`), the browser solves it, and `AltchaVerifier`
verifies the payload locally — no outgoing HTTP. Form glue: `AltchaRule` + `AltchaRuleHandler` for
`yiisoft/validator`, `AltchaWidget` for the `<altcha-widget>` tag, `AltchaRegistry` as the static holder the
no-argument rule handler reads (the default `yiisoft/validator` resolver builds handlers with `new`).

All cryptography belongs to the official `altcha-org/altcha`. Only two classes may touch it — `ChallengeIssuer` and
`AltchaVerifier` (plus the `AltchaAlgorithm` enum that maps to its algorithm objects) — so a major bump of the
library stays local. Do not reimplement the protocol.

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

- `AltchaConfig` bounds everything the server pays for: secret >= 32 bytes, `cost` <= 1 000 000 (verification repeats
  one derivation), positive `ttlSeconds`/`maxCounter`. Keep new settings validated in the constructor.
- The challenge counter must be passed to `createChallenge()`: without it the library picks a random prefix nobody can
  solve. `ChallengeIssuer` draws it from `[1, maxCounter]`.
- Replay protection uses PSR-16 (no atomic add): document the tiny race, do not claim exactly-once.
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
