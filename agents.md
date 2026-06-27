# agents.md — richdocuments

## Repository Overview

Richdocuments is an ownCloud Server (OC10) app that integrates Collabora Online for real-time collaborative editing of office documents. It uses the WOPI protocol to connect ownCloud's file storage with the Collabora Online editing server, supporting documents, spreadsheets and presentations.

- **Classification:** Classic (OC10)
- **Activity Status:** Active
- **License:** No license detected (pending OSPO audit)
- **Language:** JavaScript, PHP

## Architecture & Key Paths

- `appinfo/` — ownCloud app metadata (info.xml, routes, etc.)
- `lib/` — PHP backend logic (WOPI integration, controllers)
- `src/` — TypeScript/JavaScript frontend source (ownCloud Web connector)
- `js/` — Compiled JavaScript output
- `css/` — Stylesheets
- `templates/` — PHP templates for server-side rendering
- `l10n/` — Localization/translation files
- `tests/` — PHPUnit and integration tests
- `admin.php` / `settings.php` — Admin settings UI
- `Makefile` — Build orchestration
- `composer.json` — PHP dependencies
- `package.json` — JavaScript dependencies
- `vite.config.ts` — Vite build configuration for Web connector

## Development Conventions

- PHP backend follows ownCloud app structure (`appinfo/info.xml`, `lib/`)
- JavaScript/TypeScript frontend built with Vite
- Code style enforced by phpcs (`phpcs.xml`) and Prettier
- `CONTRIBUTING.md` present at repo root
- SonarCloud used for quality analysis

## Build & Test Commands

```bash
make appstore              # Build for marketplace distribution
pnpm install               # Install JavaScript dependencies
pnpm build                 # Build ownCloud Web connector
composer install            # Install PHP dependencies
phpunit -c phpunit.xml      # Run PHP unit tests
```

## Important Constraints

- **No license file detected:** This repository currently lacks a formal LICENSE file. The OSPO is working on the license audit as part of the Apache 2.0 migration strategy.
- **Copyleft + Apache 2.0 migration:** The broader ownCloud organization is migrating repositories to Apache 2.0. Copyleft dependencies must be audited before migration.
- **WOPI dependency:** Requires a running Collabora Online server reachable from the ownCloud server, and vice versa.
- **Dual frontend:** Has both a classic OC10 frontend (`js/`) and an ownCloud Web connector (`src/` built with Vite).


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

- This is an ownCloud Server (OC10) app, not an oCIS extension.
- The PHP backend handles WOPI protocol communication with Collabora Online.
- The `src/` directory contains a Vite-based TypeScript frontend for the ownCloud Web connector.
- Configuration is done via `occ config:app:set` commands.
- The app has both SonarCloud and phpcs integration for code quality.
