# Contributing to laravel-device-sessions

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting issues

- **Bugs:** a [Bug report](https://github.com/kirchDev/laravel-device-sessions/issues/new?template=bug_report.yml), ideally with a failing Pest test.
- **Features:** a [Feature request](https://github.com/kirchDev/laravel-device-sessions/issues/new?template=feature_request.yml).
- **Questions or unconfirmed bugs:** the [Discord forum](https://discord.kirch.dev/).
- **Security:** never a public issue — follow [SECURITY.md](SECURITY.md).

## Setup

PHP 8.4+, Composer 2, Node 24+ (`.nvmrc`) and pnpm 12 (`packageManager` in `package.json`).

```bash
git clone https://github.com/kirchDev/laravel-device-sessions.git
cd laravel-device-sessions
composer install
pnpm install   # wires the husky hooks
```

## Checks

`pnpm check && composer larastan && composer test` must pass before you push; `composer pint` checks PHP style. `pnpm check:fix` and `composer pint:fix` fix what they can. Husky runs lint-staged and commitlint on commit — if a hook fails, fix it and commit again; don't `--no-verify` unless asked.

## Pull requests

- Branch off `dev` and target `dev`; `main` only receives release promotions.
- Conventional Commits; mark breaking changes with `!` or a `BREAKING CHANGE:` footer.
- One concern per PR.
- Code changes need tests: a regression test for a fix, the happy path and one edge case for a feature.
- Update the README and the config's inline docs when behaviour or a default changes.

## Releases

release-please opens a release PR from the `feat` / `fix` commits on `main`; merging it tags the release, which Packagist picks up.

## Agent instructions

Agent rules live in [AGENTS.md](AGENTS.md). Claude Code (v2.1.277+) loads it only while no `CLAUDE.md` or `CLAUDE.local.md` sits in the working directory or above, so don't commit one.

## License

Contributions are licensed under the [MIT License](LICENSE).
