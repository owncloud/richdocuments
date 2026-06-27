# Richdocuments (Collabora Online for ownCloud)

<!-- OSPO-managed README | Generated: 2026-04-16 | v2 -->

[![License](https://img.shields.io/badge/License-See%20Repository-blue.svg)](LICENSE) [![ownCloud OSPO](https://img.shields.io/badge/OSPO-ownCloud-blue)](https://kiteworks.com/opensource) [![Docker Hub](https://img.shields.io/docker/pulls/owncloud)](https://hub.docker.com/r/owncloud/server)

Richdocuments integrates Collabora Online with ownCloud Server, enabling real-time collaborative editing of documents, spreadsheets and presentations directly within the ownCloud web interface. The app uses the WOPI (Web Application Open Platform Interface) protocol to bridge ownCloud's file storage with the Collabora Online document editing server.

## Part of Classic (OC10)

This app is part of the [ownCloud Server (OC10)](https://github.com/owncloud/core) ecosystem, providing online document editing capabilities through the Collabora Online integration. It is available on the [ownCloud Marketplace](https://marketplace.owncloud.com/apps/richdocuments).

The ownCloud Server is available on [Docker Hub](https://hub.docker.com/r/owncloud/server).

## Getting Started

Follow the steps below to install and configure Collabora Online integration.

### Installation

Install from the [ownCloud Marketplace](https://marketplace.owncloud.com/apps/richdocuments), or manually:

```bash
cd apps
git clone https://github.com/owncloud/richdocuments.git
php occ app:enable richdocuments
```

### Configuration

Set the WOPI server URL:

```bash
occ config:app:set richdocuments wopi_url --value [your-host-public-ip]:8098
```

Enable Secure View:

```bash
occ config:app:set richdocuments secure_view_option --value true
occ config:app:set richdocuments watermark_text --value "Restricted to {viewer-email}"
occ config:app:set richdocuments secure_view_open_action_default --value true
```

### Development

Start a Collabora server for development (SSL disabled):

```bash
docker run -t -d -p 9980:9980 -e "extra_params=--o:ssl.enable=false" \
  -e "username=admin" -e "password=admin" \
  --name collabora --cap-add MKNOD collabora/code:6.4.8.6
```

Build the ownCloud Web connector:

```bash
pnpm install
pnpm build
```

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

See [LICENSE](LICENSE) for license details.

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

Individual repositories will migrate as their audit is completed. The LICENSE file
in each repo reflects its **current** license status (not the target).

**Current license: Not detected.** The OSPO will determine the current license status of this
repository before planning any migration steps. If you know the intended license, please open an
issue or contact ospo@kiteworks.com.
