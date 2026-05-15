# Federation Allowlist via System Config — Design

**Date:** 2026-05-15
**Scope:** Replace `OCA\Federation\TrustedServers` with a `config.php` system config allowlist as the sole authority for permitted federation servers in `FederationService`.

---

## Problem

The previous PR (OC10-100) fixed an SSRF by wiring `TrustedServers` from the federation app into `FederationService`. However, this creates a hard runtime dependency on the federation app being installed. Admins who don't use the federation app but do want to permit specific Collabora federation endpoints have no way to configure this.

This PR replaces `TrustedServers` entirely with a flat system config key, giving admins a direct, federation-app-independent allowlist.

---

## Config Key

```php
// config/config.php
'richdocuments.federation_allowlist' => [
    'https://collab1.example.com',
    'https://collab2.example.com',
],
```

- Type: `array` of URL strings
- Location: `config/config.php` (system config), read via `IConfig::getSystemValue()`
- Default: absent (treated as `[]`) → deny all
- If set to a non-array value: treated as `[]` → deny all

---

## Architecture

### `FederationService`

**Dependencies change:**
- Remove: `?TrustedServers $trustedServers`
- Add: `IConfig $config`
- Remove: all four Phan suppression comments (no longer needed)
- Remove: `use OCA\Federation\TrustedServers`
- Add: `use OCP\IConfig`

**`isServerAllowed(string $remote): bool`**

1. `$allowlist = $this->config->getSystemValue('richdocuments.federation_allowlist', [])` 
2. If `!is_array($allowlist) || empty($allowlist)` → return `false`
3. `$normalized = rtrim($remote, '/')`
4. For each `$entry` in `$allowlist`:
   - `$e = rtrim($entry, '/')`
   - If `$normalized === $e` → return `true`
   - Compute scheme-swapped variant of `$normalized` (http↔https)
   - If scheme-swapped variant `=== $e` → return `true`
5. Return `false`

### `Application.php`

DI registration simplifies — no `isInstalled('federation')` check:

```php
$container->registerService(FederationService::class, function () use ($server) {
    return new FederationService(
        $server->getLogger(),
        $server->getURLGenerator(),
        $server->getHTTPClientService(),
        $server->getConfig()
    );
});
```

The docblock comment about null-as-secure-default is removed (no longer applicable).

---

## Test Cases (`FederationServiceTest`)

Replace `TrustedServers` mock with `IConfig` mock. All `isTrustedServer` setups replaced with `getSystemValue('richdocuments.federation_allowlist', [])` stubs.

| Test | Input | Config value | Expected |
|---|---|---|---|
| `testIsServerAllowedReturnsFalseWhenKeyIsAbsent` | any URL | `[]` (default) | `false` |
| `testIsServerAllowedReturnsFalseWhenListIsEmpty` | any URL | `[]` | `false` |
| `testIsServerAllowedReturnsFalseForNonArrayConfig` | any URL | `'not-an-array'` | `false` |
| `testIsServerAllowedReturnsTrueForExactMatch` | `https://trusted.example.com` | `['https://trusted.example.com']` | `true` |
| `testIsServerAllowedStripsTrailingSlash` | `https://trusted.example.com/` | `['https://trusted.example.com']` | `true` |
| `testIsServerAllowedStripsMultipleTrailingSlashes` | `https://trusted.example.com///` | `['https://trusted.example.com']` | `true` |
| `testIsServerAllowedSwapsHttpToHttps` | `http://trusted.example.com` | `['https://trusted.example.com']` | `true` |
| `testIsServerAllowedSwapsHttpsToHttp` | `https://trusted.example.com` | `['http://trusted.example.com']` | `true` |
| `testIsServerAllowedReturnsFalseForUntrustedServer` | `https://evil.attacker.com` | `['https://trusted.example.com']` | `false` |

---

## Error Handling

- **Key absent:** `getSystemValue` returns `[]` default → deny all. No exception.
- **Non-array value:** `is_array()` guard → deny all. No exception.
- **Malformed entries:** Empty strings or non-URLs in the list never match → harmlessly ignored.
- **Logging:** Unchanged. Existing `"Server {server} is not allowed."` log lines in `getWopiForToken()` and `getRemoteWopiSrc()` cover the denial case.

---

## Known Limitations

- Path-prefixed installs (e.g. `https://cloud.example.com/owncloud`) are not specially handled — trailing-slash stripping only. Carried forward from previous PR.
- No admin UI. Config is file-only (`config.php`).

---

## Files Changed

| File | Change |
|---|---|
| `lib/FederationService.php` | Replace `TrustedServers` → `IConfig`; rewrite `isServerAllowed()` |
| `lib/AppInfo/Application.php` | Simplify DI registration |
| `tests/unit/FederationServiceTest.php` | Replace mock type; update/add test cases |
