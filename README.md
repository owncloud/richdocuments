# Richdocuments (Collabora Online for ownCloud)

<!-- OSPO-managed README | Generated: 2026-04-16 | v2 -->

[![License](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](#license) [![ownCloud OSPO](https://img.shields.io/badge/OSPO-ownCloud-blue)](https://kiteworks.com/opensource) [![Docker Hub](https://img.shields.io/docker/pulls/owncloud/server)](https://hub.docker.com/r/owncloud/server)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=owncloud_richdocuments&metric=alert_status)](https://sonarcloud.io/dashboard?id=owncloud_richdocuments) [![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=owncloud_richdocuments&metric=security_rating)](https://sonarcloud.io/dashboard?id=owncloud_richdocuments) [![Coverage](https://sonarcloud.io/api/project_badges/measure?project=owncloud_richdocuments&metric=coverage)](https://sonarcloud.io/dashboard?id=owncloud_richdocuments)

Richdocuments integrates Collabora Online with ownCloud Server, enabling real-time collaborative editing of documents, spreadsheets and presentations directly within the ownCloud web interface. The app uses the WOPI (Web Application Open Platform Interface) protocol to bridge ownCloud's file storage with the Collabora Online document editing server.

## Part of ownCloud Server (Classic)

This app is part of the [ownCloud Server](https://github.com/owncloud/core) ecosystem, providing online document editing capabilities through the Collabora Online integration. It is available on the [ownCloud Marketplace](https://marketplace.owncloud.com/apps/richdocuments).

Branch `master` targets ownCloud Server 11 (PHP 8.3); branch `4.2` targets ownCloud Server 10.11 and
later (PHP 7.4). See `appinfo/info.xml` for the authoritative version constraints.

The ownCloud Server is available on [Docker Hub](https://hub.docker.com/r/owncloud/server).

## Getting Started

Follow the steps below to install and configure Collabora Online integration.

### Installation

Install from the [ownCloud Marketplace](https://marketplace.owncloud.com/apps/richdocuments), or manually
from the root of your ownCloud Server installation:

```bash
git clone https://github.com/owncloud/richdocuments.git apps/richdocuments
php occ app:enable richdocuments
```

The Collabora Online server must be reachable from the ownCloud server, and the ownCloud server must
be reachable from the Collabora Online server. It is also possible to use Collabora Online's
integration with re-compiled and/or re-branded backends.

### Configuration

Set the WOPI server URL:

```bash
occ config:app:set richdocuments wopi_url --value [your-host-public-ip]:8098
```

Alternatively, set the Collabora Online server under
`Settings -> Admin -> Additional -> Collabora Online server`.

Enable Secure View (requires an ownCloud Enterprise license):

```bash
occ config:app:set richdocuments secure_view_option --value true
occ config:app:set richdocuments watermark_text --value "Restricted to {viewer-email}"
occ config:app:set richdocuments secure_view_open_action_default --value true
```

### Installing the connector for ownCloud Web

To use the app from ownCloud Web you additionally need [ownCloud Web](https://github.com/owncloud/web)
itself, either installed as an app or built from source.

Register the connector in the ownCloud Web `config.json`:

- ownCloud Web installed as an app on the server: `<owncloud-root>/config/config.json`
- ownCloud Web built from source following [these instructions](https://owncloud.dev/clients/web/backend-oc10/#running-web): `<owncloud-web-root>/config/config.json`

```json
"external_apps": [
    {
        "id": "richdocuments",
        "path": "http(s)://<owncloud-server-address>/index.php/apps/richdocuments/js/richdocuments.js"
    }
]
```

### Development

Start a Collabora server for development, with SSL disabled (adjust the image tag to a current
Collabora Online release):

```bash
docker run -t -d -p 9980:9980 -e "extra_params=--o:ssl.enable=false" \
  -e "username=admin" -e "password=admin" \
  --name collabora --cap-add MKNOD collabora/code:6.4.8.6
```

The Collabora admin interface is then available at
`http://[your-host-public-ip]:9980/loleaflet/dist/admin/admin.html`.

Build the ownCloud Web connector (compiles `src/` into `js/web/richdocuments.js`, which is committed):

```bash
pnpm install
pnpm build
```

Run the tests. Note that `make test-php-unit` resolves PHPUnit inside the surrounding ownCloud Server
tree, so the app has to be checked out as `apps/richdocuments`:

```bash
make test-php-unit
make test-acceptance-webui
make test-php-style
```

See [AGENTS.md](AGENTS.md) for a fuller description of the layout and the available make targets.

## Documentation

- [Collabora Online for ownCloud](https://owncloud.com/collabora/collaborative-editing/)
- [ownCloud Server Admin Manual](https://doc.owncloud.com/server/latest/admin_manual/)

## Community & Support

**[Star](https://github.com/owncloud/richdocuments)** this repo and **Watch** for release notifications!

- [ownCloud Website](https://owncloud.com)
- [Community Discussions](https://github.com/orgs/owncloud/discussions)
- [Matrix Chat](https://app.element.io/#/room/#owncloud:matrix.org)
- [Documentation](https://doc.owncloud.com)
- [Enterprise Support](https://owncloud.com/contact-us/)
- [OSPO Home](https://kiteworks.com/opensource)

## Contributing

We welcome contributions! Please read the [Contributing Guidelines](CONTRIBUTING.md)
and our [Code of Conduct](CODE_OF_CONDUCT.md) before getting started.

### Workflow

- **Rebase Early, Rebase Often!** We use a rebase workflow. Always rebase on the target branch before submitting a PR.
- **Dependabot**: Automated dependency updates are managed via Dependabot. Review and merge dependency PRs promptly.
- **Signed Commits**: All commits **must** be PGP/GPG signed. See [GitHub's signing guide](https://docs.github.com/en/authentication/managing-commit-signature-verification).
- **DCO Sign-off**: Every commit must carry a `Signed-off-by` line:
  ```
  git commit -s -S -m "your commit message"
  ```
- **GitHub Actions Policy**: Workflows may only use actions that are (a) owned by `owncloud`, (b) created by GitHub (`actions/*`), or (c) verified in the GitHub Marketplace.

## Translations

Help translate this project on Transifex:
**<https://explore.transifex.com/owncloud-org/owncloud/>**

Please submit translations via Transifex -- do not open pull requests for translation changes.

## Security

**Do not open a public GitHub issue for security vulnerabilities.**

Report vulnerabilities at **<https://security.owncloud.com>** -- see [SECURITY.md](SECURITY.md).

Bug bounty: [YesWeHack ownCloud Program](https://yeswehack.com/programs/owncloud-bug-bounty-program)

## License

This project is licensed under the **AGPL-3.0**, as declared in `appinfo/info.xml` and in the header
of every source file.

> Note: this repository does not carry a root `LICENSE`/`COPYING` file yet. Adding one is tracked as
> OSPO follow-up work; it does not change the license that already applies.

## About the ownCloud OSPO

The [Kiteworks Open Source Program Office](https://kiteworks.com/opensource), operating under
the [ownCloud](https://owncloud.com) brand, launched on May 5, 2026, to steward the open source
ecosystem around ownCloud's products. The OSPO ensures transparent governance, license compliance,
community health, and sustainable collaboration between the open source community and
[Kiteworks](https://www.kiteworks.com), which acquired ownCloud in 2023.

- **OSPO Home**: <https://kiteworks.com/opensource>
- **GitHub**: <https://github.com/owncloud>
- **ownCloud**: <https://owncloud.com>

For questions about the OSPO or licensing, contact ospo@kiteworks.com.

### License Migration to Apache 2.0

The OSPO is driving a strategic relicensing of ownCloud repositories toward the
[Apache License 2.0](https://www.apache.org/licenses/LICENSE-2.0), following
the [Apache Software Foundation's third-party license policy](https://www.apache.org/legal/resolved.html).

Individual repositories will migrate as their audit is completed. The license declared in each repo
reflects its **current** license status (not the target).

**Current license: AGPL-3.0** (Category X per Apache policy -- cannot be included in Apache-2.0 works).

Migration prerequisites for this repository:

- **CLA/DCO coverage**: All past contributors must have signed agreements permitting relicensing
- **Copyleft dependency audit**: All AGPL/GPL dependencies must be replaced or isolated
- **Third-party heritage review**: This app originated as Collabora Productivity work based on code by
  Frank Karlitschek and Victor Dubiniuk, so its copyright history requires legal analysis
- **Complete relicensing**: AGPL-3.0 is a strong copyleft license; migration requires full relicensing of all files, not just a header change
- **Add a license file**: The repository needs a root `LICENSE`/`COPYING` file recording its current AGPL-3.0 status before any migration step
