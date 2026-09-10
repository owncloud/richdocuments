# AI Agent Guidelines for Collabora Online (richdocuments)

This file provides context for AI coding agents (Claude Code, GitHub Copilot, Cursor, etc.) working in this repository.

## Repository Overview

`richdocuments` is the ownCloud Server app that integrates Collabora Online for real-time
collaborative editing of documents, spreadsheets and presentations. ownCloud acts as the WOPI host;
Collabora Online is the WOPI client. The app ships in two flavours: a classic server-rendered
frontend and a connector for ownCloud Web.

- **Product family:** Classic (ownCloud Server)
- **Supported server versions:** `master` targets ownCloud 11 with PHP 8.3; branch `4.2` targets ownCloud 10.11+ with PHP 7.4 (see `appinfo/info.xml`)
- **Primary language(s):** PHP, TypeScript/Vue, JavaScript
- **Build system:** Composer, Make, pnpm + Vite
- **Test framework:** PHPUnit (unit), karma + Jasmine (JavaScript unit), Behat (webUI acceptance)
- **CI system:** GitHub Actions
- **License:** AGPL-3.0

## Architecture & Key Paths

- `appinfo/` - App metadata and registration (`info.xml`, `routes.php`, `app.php`, `Migrations/`)
- `lib/` - PHP backend: `Controller/` (WOPI, document, settings, federation endpoints), `Db/` (WOPI token storage), `Panels/` (admin and personal settings), `BackgroundJob/` (expired WOPI token cleanup), plus the `DiscoveryService`, `DocumentService`, `FederationService` and `FileService` classes
- `src/` - Vue/TypeScript source of the ownCloud Web connector (`index.ts`, `editor.vue`), built with `@ownclouders/extension-sdk`
- `js/` - Classic frontend JavaScript, hand-written (`documents.js`, `settings-admin.js`, `settings-personal.js`, `viewer/`). `js/web/richdocuments.js` is the committed Vite build output of `src/` - do not edit it by hand
- `css/` - Stylesheets
- `templates/` - Server-side PHP templates
- `assets/` - Empty office document templates used when creating new files
- `img/` - App icons and images
- `l10n/` - Translations
- `tests/` - PHPUnit, JavaScript and acceptance tests (`tests/unit/`, `tests/js/`, `tests/acceptance/`)
- `admin.php` / `settings.php` - Settings entry points
- `Makefile` - Build and test automation
- `composer.json` - PHP dependencies
- `package.json` - JavaScript dependencies
- `vite.config.ts` - Vite build configuration for the Web connector
- `phpunit.xml` - PHPUnit configuration (single `unit` test suite)
- `phpcs.xml` - PHP_CodeSniffer configuration
- `.php-cs-fixer.dist.php` - php-cs-fixer configuration
- `phpstan.neon` - PHPStan configuration
- `.phan/` - Phan static analysis configuration
- `sonar-project.properties` - SonarCloud configuration
- `vendor-bin/` - Isolated tool dependencies (phpunit, php-cs-fixer, phpcs, phan, phpstan, behat)

## Development Conventions

- **Branching:** `master` for the ownCloud 11 line, `4.2` for the ownCloud 10.x line. Fixes that apply to both need a PR per branch.
- **Commit messages:** DCO sign-off required (`git commit -s`). Must follow [Conventional Commits](https://www.conventionalcommits.org/) format - enforced by CI via `owncloud/reusable-workflows/.github/workflows/semantic-git-message.yml`. The repository squash-merges and takes the PR title as the commit subject, so the PR title must follow the same format.
- **Code style:** php-cs-fixer with the ownCloud coding standard, plus PHP_CodeSniffer (`phpcs.xml`) for the backend; ESLint and Prettier for the frontend.
- **Static analysis:** Phan and PHPStan.
- **PR process:** Open a PR against the target branch. All CI checks must pass.
- **Quality gate:** SonarCloud analyses the repository.

## Build & Test Commands

```bash
# Show all available targets
make help

# Build distribution tarball
make dist

# Install PHP dependencies
composer install

# Build the ownCloud Web connector (src/ -> js/web/)
pnpm install
pnpm build

# Test (PHPUnit)
make test-php-unit

# Test (JavaScript unit, js/ only)
make test-js
# same, on a machine without Firefox
KARMA_BROWSER=ChromeHeadless make test-js

# Test (WebUI Acceptance)
make test-acceptance-webui

# Lint (PHP code style)
make test-php-style

# Fix code style
make test-php-style-fix

# Lint (JavaScript/TypeScript)
pnpm lint

# Static analysis
make test-php-phan
make test-php-phpstan

# Clean build artifacts and dependencies
make clean
```

## Important Constraints

- **Tests need a core checkout:** `make test-php-unit` resolves PHPUnit at `../../lib/composer/bin/phpunit`, so the app must be checked out as `apps/richdocuments` inside an ownCloud Server tree. It cannot be run from a standalone clone. The same holds for `make test-js`: the scripts in `js/` expect the globals the server puts on the page, so `tests/js/karma.config.cjs` loads jQuery, jQuery UI and `OC` from the surrounding core checkout (`core/js/core.json`, `core/vendor/`, `core/js/tests/specHelper.js`). Core's node dependencies have to be installed there once (`make` in the core root), which is what creates the `core/vendor` symlink.
- **Node dependencies need pnpm 9:** `pnpm-lock.yaml` is a pnpm 9 lockfile whose `easygettext` git tarball entry has no integrity hash, which pnpm 10 rejects (`ERR_PNPM_MISSING_TARBALL_INTEGRITY`). The Makefile therefore installs with `npx --yes pnpm@9`; use the same for a manual `pnpm install`.
- **JavaScript unit tests only cover `js/`:** `tests/js/` runs the classic frontend. The Vue connector in `src/` has no unit tests yet; it needs a separate vitest setup, as used by `owncloud/web-extensions`.
- **`make appstore` is release-only:** it unconditionally calls `occ integrity:sign-app` and needs a signing key and certificate in `~/.owncloud/certificates/`. Use `make dist` for a local build; `make dist` skips signing when no certificate is present.
- **WOPI dependency:** Requires a running Collabora Online server that the ownCloud server can reach, and that can reach the ownCloud server in turn.
- **Dual frontend:** Has both a classic frontend (`js/`) and an ownCloud Web connector (`src/`, built with Vite into `js/web/`). Frontend changes usually need to be made in both places.
- **Generated frontend bundle is committed:** regenerate `js/web/richdocuments.js` with `pnpm build` and commit the result; never hand-edit it.
- **License:** AGPL-3.0, as declared in `appinfo/info.xml` and in the header of every source file. All contributions must be compatible with it. Note that this repository has no root `LICENSE`/`COPYING` file yet; adding one is tracked as OSPO follow-up work.
- **Copyleft + Apache 2.0 migration:** The broader ownCloud organization is migrating repositories to Apache 2.0. AGPL-3.0 is Category X under Apache policy, so migration requires full relicensing. Do not introduce new copyleft dependencies without discussion in an issue first.
- **Translations:** Must be submitted via Transifex, not as pull requests.


## OSPO Policy Constraints

### GitHub Actions
- **Only** use actions owned by `owncloud`, created by GitHub (`actions/*`), verified on the GitHub Marketplace, or verified by the ownCloud Maintainers.
- Pin all actions to their full commit SHA (not tags): `uses: actions/checkout@<SHA> # vX.Y.Z`
- Never introduce actions from unverified third parties.

### Dependency Management
- Dependabot is configured for automated dependency updates.
- Review and merge Dependabot PRs as part of regular maintenance.
- Do not introduce new dependencies without discussion in an issue first.

### Git Workflow
- **Rebase policy**: Always rebase; never create merge commits. Use `git pull --rebase` and `git rebase` before pushing.
- **Signed commits**: All commits **must** be PGP/GPG signed (`git commit -S -s`).
- **DCO sign-off**: Every commit needs a `Signed-off-by` line (`git commit -s`).
- **Conventional Commits & Squash Merge**: Use the [Conventional Commits](https://www.conventionalcommits.org/) format where the repository enforces it. Many repos use squash merge, where the PR title becomes the commit message on the default branch — apply Conventional Commits format to PR titles as well. A reusable GitHub Actions workflow enforces this.

## Context for AI Agents

- This is an ownCloud Server app (the Classic product line), not an oCIS extension.
- The PHP backend implements the WOPI host side of the protocol; Collabora Online is the WOPI client.
- WOPI access tokens live in the app's own `richdocuments_wopi` table (created by `appinfo/Migrations/`) and are cleaned up by the `CleanupExpiredWopiTokens` background job - be careful with their lifetime and validation when touching `lib/Controller/WopiController.php`.
- Secure View (watermarking, restricted download) is gated behind an enterprise license via `ILicenseManager`, see `AppConfig::enterpriseFeaturesEnabled()`. Its acceptance coverage lives in `tests/acceptance/features/webUISecureView/`, which is the only acceptance suite in this repo - `make test-acceptance-api` exists but has no features to run here.
- Runtime configuration is done via `occ config:app:set richdocuments <key> --value <value>`; see `lib/AppConfig.php` for the supported keys.
- Match existing code style, keep PRs focused, and do not refactor unrelated code in the same PR.
- Write tests for new functionality.
