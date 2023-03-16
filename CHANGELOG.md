# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).


## [2.8.0] - 2023-03-16

### Changed

- [#456](https://github.com/owncloud/richdocuments/pull/456) - web: add Drawing filetype and add it to + menu

### Fixed

- [#467](https://github.com/owncloud/richdocuments/pull/467) - Set appropriate icon for web
- [#462](https://github.com/owncloud/richdocuments/pull/462) - Adjust 'if' conditionals that were reported by phpstan
- [#457](https://github.com/owncloud/richdocuments/pull/457) - typo fix (templates/documents.php)
- [#455](https://github.com/owncloud/richdocuments/pull/455) - ensure ODG Drawing compatibility across integration
- [#454](https://github.com/owncloud/richdocuments/pull/454) - Change Richdocuments app name to Collabora Online
- [#451](https://github.com/owncloud/richdocuments/pull/451) - disable secure view js and settings when not available


## [2.7.0] - 2022-01-19

## Changed

- added Diagram document type to + button - [#436](https://github.com/owncloud/richdocuments/pull/436)
- ownCloud Web compatibility - [#423](https://github.com/owncloud/richdocuments/pull/423)
- Update .drone.star and drop PHP 7.2 - [#424](https://github.com/owncloud/richdocuments/pull/424)
- Library and translation updates

## Fixed

- Make upload work again - [#437](https://github.com/owncloud/richdocuments/pull/437)

## [2.6.0] - 2021-05-31

## Fixed

- Only verify path if filename is given, additional log error - [#418](https://github.com/owncloud/richdocuments/pull/418)
- Don't log warning message on PUT in favour of debug - [#407](https://github.com/owncloud/richdocuments/pull/407)
- Prevent documents with tabs in filenames / or any other invalid chars from being created - [enterprise#4628](https://github.com/owncloud/enterprise/issues/4628)

### Changed

- Introduced "Open documents in Secure View with watermark by default" setting - [#400](https://github.com/owncloud/richdocuments/pull/400) - [#402](https://github.com/owncloud/richdocuments/pull/402)
- Enable comments on PDFs - [#404](https://github.com/owncloud/richdocuments/pull/404)
- Use app icon for Open in Collabora action - [#406](https://github.com/owncloud/richdocuments/pull/406)


- Library updates


## [2.5.0] - 2021-04-28

### Changed

- In OC10.7 we changed the logic for encryption events -  [#392](https://github.com/owncloud/richdocuments/pull/392)
- Improved auditing capabilities for access via Collabora - [#371](https://github.com/owncloud/richdocuments/pull/371)
- Changes to allow opening documents explicitly with Collabora - [#370](https://github.com/owncloud/richdocuments/pull/370)
- Let wopi client decide the actions when token about to expire
- Translation updates
- Library updates

### Fixed
- Fix Public Links shared from Local Storage - [#385](https://github.com/owncloud/richdocuments/pull/385)
- Make Secure View licensing compatible with new license manager - [#356](https://github.com/owncloud/richdocuments/pull/356)
- Fix wrong default name


## [2.4.1] - 2020-10-19

### Changed
- Add warning for secure view regarding license
- Translation updates

### Fixed
- Hotfix for checking license for a specific feature in richdocuments


## [2.4.0] - 2020-07-30


[Unreleased]: https://github.com/owncloud/guests/compare/v3.0.0...master
[3.0.0]: https://github.com/owncloud/guests/compare/v2.7.0...v3.0.0
[2.7.0]: https://github.com/owncloud/guests/compare/v2.6.0...v2.7.0
[2.6.0]: https://github.com/owncloud/guests/compare/v2.5.0...v2.6.0
[2.5.0]: https://github.com/owncloud/guests/compare/v2.4.1...v2.5.0
[2.4.1]: https://github.com/owncloud/guests/compare/v2.4.0...v2.4.1
[2.4.0]: https://github.com/owncloud/guests/compare/v2.2.0...v2.4.0

