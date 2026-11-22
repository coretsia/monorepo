<!--
  Coretsia Framework (Monorepo)

  Project: Coretsia Framework (Monorepo)
  Authors: Vladyslav Mudrichenko and contributors
  Copyright (c) 2026 Vladyslav Mudrichenko

  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
  SPDX-License-Identifier: Apache-2.0

  For contributors list, see git history.
  See LICENSE and NOTICE in the project root for full license information.
-->

# HTTP debug endpoints

Goal: enable and use bounded HTTP diagnostics in an isolated development application without exposing credentials, configuration values, or internal artifact data.

Scope: Consumer applications based on `coretsia/skeleton`. Configuration paths and commands in this guide are relative to the **Skeleton application root**, not the Coretsia monorepo root.

This is an operational guide. Normative endpoint behavior belongs to `docs/ssot/http-system-endpoints.md`; shared redaction policy belongs to `docs/ssot/sensitive-data-redaction.md`.

---

## 1) Prerequisites and activation

HTTP debug endpoints are disabled by default. They are available only when all of the following conditions hold:

- The authoritative application environment frozen at bootstrap is exactly `local` or `dev` (case-sensitive).
- `platform.http` and `platform.problem-details` are enabled in the target's runtime `ModulePlan`. Installing the Problem Details package alone is not sufficient.
- `http.debug.endpoints.enabled` is `true`.

The selected Problem Details boundary provides canonical forbidden-response rendering. Redaction is a required runtime dependency of HTTP and participates in the existing module dependency closure.

The target must already have a working HTTP runtime with its selected driver and compatible PSR-7/17 factories. Debug activation does not select a transport or install a factory implementation.

File: `config/app.php`

```php
<?php

declare(strict_types=1);

return [
    'appEnv' => 'local',
    'moduleOverrides' => [
        'web' => [
            'include' => [
                'platform.http',
                'platform.problem-details',
            ],
        ],
    ],
];
```

The example selects modules for the `web` target. Merge these entries into the application's existing bootstrap configuration, retaining its other target selections and overrides. Ensure the selected target has its physical Composer dependencies reconciled through the canonical DependencySync workflow.

Use `dev` instead of `local` when appropriate. Higher-priority bootstrap sources may select another environment; consult `docs/ssot/config-precedence-matrix.md`. An artifact-only runtime restores the frozen application environment from its loaded config artifact. Changing the process environment or `kernel.boot.default_env` after boot does not change the authoritative runtime environment.

---

## 2) Generate a dedicated debug token

Generate a fresh token from 32 cryptographically secure random bytes and represent it as exactly 64 lowercase hexadecimal characters. Use a private Bash session with shell tracing disabled:

```bash
set +x
unset debug_token
debug_token="$(php -r 'echo bin2hex(random_bytes(32));')" &&
    printf '%s' "$debug_token" | php -r 'echo hash("sha256", stream_get_contents(STDIN)), PHP_EOL;'
```

The second command prints the lowercase SHA-256 digest of the hexadecimal token, not the token itself. There is no trailing newline in the hashed input.

Keep the raw token private for the authorized client. Store only the printed digest in `http.debug.token_sha256`. The raw token MUST NOT be stored in application configuration, dotenv files used to compile config, generated artifacts, or source control.

An empty digest never authorizes a request. A non-empty digest MUST consist of exactly 64 lowercase hexadecimal characters.

---

## 3) Configure the HTTP debug policy

File: `config/http.php`

```php
<?php

declare(strict_types=1);

return [
    'debug' => [
        'endpoints' => [
            'enabled' => true,
        ],
        'allowlist' => [
            '127.0.0.1',
            '::1',
        ],
        'token_sha256' => '', // Insert the digest generated in section 2.
        'paths' => [
            'enabled' => ['headers', 'modules', 'config', 'cache'],
        ],
        'config' => [
            'allowlist' => ['kernel.uow.attributes.max_keys'],
        ],
    ],
];
```

Merge the `debug` entry into existing application-owned HTTP configuration. Replace the empty digest before attempting an authorized request.

The package defaults are:

- `http.debug.endpoints.enabled`: `false`.
- `http.debug.allowlist`: `['127.0.0.1', '::1']`.
- `http.debug.token_sha256`: `''`.
- `http.debug.paths.enabled`: `['headers', 'modules', 'config', 'cache']`.
- `http.debug.config.allowlist`: `[]`.

### 3.1 Client IP allowlist and trusted proxies

`http.debug.allowlist` accepts at most 64 unique canonical IPv4 or IPv6 addresses. Matching is exact. CIDRs, hostnames, wildcards, addresses with ports, noncanonical IP spellings, and duplicate entries are not supported. An empty list denies all clients.

For a direct connection, the immediate peer from `REMOTE_ADDR` MUST be a valid canonical address present in the debug allowlist. For example, `127.0.0.1` and `::1` are valid; `127.0.0.1/32` and `0:0:0:0:0:0:0:1` are not valid debug allowlist entries.

Proxy processing is disabled by default (`http.proxy.enabled = false`), with an empty `http.proxy.trusted_proxies` list. When a reverse proxy is necessary:

- Enable `http.proxy.enabled` and configure `http.proxy.trusted_proxies` with only explicitly trusted immediate peers (canonical IP addresses or network CIDRs). This is a separate policy from the exact debug client allowlist.
- The trusted proxy MUST supply valid client identity through the canonical forwarding policy (`Forwarded` or `X-Forwarded-For`, not both forwarding families together). The proxy SHOULD remove untrusted client-supplied forwarding metadata at the trust boundary.
- The resolved, canonical client IP, not the proxy's address, MUST match `http.debug.allowlist`.
- A configured trusted peer without validated client identity is denied, including when proxy processing is disabled. There is no fallback to the proxy's IP.
- An untrusted peer cannot authorize a request by supplying forwarding headers or forged proxy metadata. Invalid or missing immediate peer identity is denied.

Do not add an address to the debug allowlist merely because it belongs to a proxy. Restrict the proxy trust boundary and the diagnostic client allowlist independently.

### 3.2 Endpoint allowlist

`http.debug.paths.enabled` selects a subset of the following exact operation names:

- `headers` — `GET /_debug/headers`.
- `modules` — `GET /_debug/modules`.
- `config` — `GET /_debug/config?key=<logical-path>`.
- `cache` — `GET /_debug/cache`.

The list contains at most four unique names. Use only the operations needed for the current diagnostic task. An empty list denies every recognized operation in an otherwise eligible runtime.

Only the exact case-sensitive method `GET` and the exact original URI paths are supported. The reserved `/_debug` root, unknown operations, trailing slashes, repeated slashes, and encoded aliases do not dispatch a diagnostic operation.

### 3.3 Config-key allowlist

`http.debug.config.allowlist` accepts at most 64 unique logical key prefixes. An empty list denies config diagnostics.

A prefix authorizes the exact key and its descendants separated by a dot. For example, `kernel.uow.attributes` authorizes `kernel.uow.attributes` and `kernel.uow.attributes.max_keys`, but not `kernel.uow.attributes_extra` or `kernel.uow`.

Both prefixes and requested keys MUST follow the same logical dotted-path grammar:

- A lowercase root matching `[a-z][a-z0-9_]{0,63}`.
- Subsequent segments matching `[A-Za-z_][A-Za-z0-9_]{0,63}`.
- A maximum total path length of 256 bytes.

Supply exactly one literal `key=<logical-path>` selector in the original URI query. Encoded selectors or paths, repeated selectors, additional query parameters, array notation, and empty segments are rejected. The entire `http.debug` subtree and sensitive key segments are denied before config value access, regardless of the configured allowlist.

Malformed or oversized policy lists, duplicate entries, invalid digest shapes, and unknown configuration keys are rejected by the canonical configuration and policy validation boundaries, even when debug endpoints are disabled. Do not rely on disabled activation to hide an invalid policy.

### 3.4 Apply configuration to the runtime

Use the effective bootstrap and HTTP configuration for the intended development target. If the target uses compiled artifacts, compile and publish a valid generation using the canonical Kernel Ops compilation workflow, then boot the runtime from that generation. With the canonical CLI available, the command for the `web` target is:

```bash
php coretsia config:compile --target=web
```

Configuration changes, including token rotation and disablement, do not mutate an already booted runtime. Reboot or restart the applicable runtime with the intended configuration or newly published generation.

---

## 4) Send authorized requests

Use the fixed `X-Debug-Token` request header with exactly one raw token value. Missing, empty, malformed, repeated, or incorrect tokens are denied. The token is case-sensitive and MUST NOT include a prefix, whitespace, or a comma-separated list of values.

The following examples assume the private development application is reachable at `https://localhost`, with a trusted TLS certificate and an allowlisted client address. Use the application's actual private URL if it differs.

```bash
printf 'X-Debug-Token: %s\n' "$debug_token" | curl --disable --include --header @- 'https://localhost/_debug/headers'
printf 'X-Debug-Token: %s\n' "$debug_token" | curl --disable --include --header @- 'https://localhost/_debug/modules'
printf 'X-Debug-Token: %s\n' "$debug_token" | curl --disable --include --header @- 'https://localhost/_debug/config?key=kernel.uow.attributes.max_keys'
printf 'X-Debug-Token: %s\n' "$debug_token" | curl --disable --include --header @- 'https://localhost/_debug/cache'
```

These commands pass the header through standard input rather than placing the token in the command arguments. `--disable` prevents loading curl's default configuration file. Do not enable curl request tracing or verbose request-header output. Do not follow redirects carrying the credential to another destination.

---

## 5) Read diagnostic responses

Authorized, matched diagnostic responses use status `200`, `Content-Type: application/json`, and `Cache-Control: no-store`. The JSON output is deterministic, with byte-order-sorted map keys and a final LF.

### 5.1 Header presence

`/_debug/headers` exposes only the fixed `headers` map:

- `accept`, `content-type`, and `user-agent` are presence booleans.
- `authorization`, `cookie`, `set-cookie`, and `x-debug-token` are `null` when absent or canonical Placeholder redaction summaries when present.

Header names are consumed case-insensitively. No original header values, arbitrary header names, or sensitive lengths and hashes are emitted. A redaction failure does not produce unredacted fallback output.

### 5.2 Enabled modules

`/_debug/modules` exposes a `modules` list of unique, byte-order-sorted enabled module IDs from the current runtime `ModulePlan`. It does not enumerate Composer packages, scan manifests, or include excluded or unselected installed modules.

### 5.3 Config type and effective source

`/_debug/config` exposes exactly `type` and `source` for an existing authorized key:

- `type`: one of `null`, `bool`, `int`, `string`, `list`, or `map`.
- `source`: `null` when effective source metadata is unavailable; otherwise a map containing only `precedence`, `redacted`, and the canonical source `type`.

The effective source is resolved from the runtime configuration repository, including hydrated artifact source metadata where applicable. Raw values, source identifiers, file paths, collection contents, counts, lengths, and hashes are not returned. There is no configuration option to enable raw-value disclosure.

### 5.4 Boot-selected artifact status

`/_debug/cache` exposes an `artifacts` map with exactly these identifiers:

- `config`.
- `container`.
- `generation-manifest`.
- `module-manifest`.

Each status is `loaded` when the runtime has successfully validated and loaded its immutable artifact generation, or `unavailable` otherwise. A source runtime without a loaded generation reports `unavailable` for all four entries.

This is a summary of the already booted runtime, not a filesystem or cache-health probe. Changes to the current-generation reference do not alter the summary of an existing runtime. No artifact paths, identifiers of individual generations, fingerprints, payloads, or file contents are emitted, and the endpoint does not write or scan artifacts.

---

## 6) Common failures and fixes

### 6.1 Empty-body `404`

Disabled endpoints, environments other than exactly `local` or `dev`, and runtimes without selected Problem Details remain unmatched. Unsupported methods and paths also remain unmatched. An authorized config key that does not exist returns the same empty-body `404`.

Check the effective runtime environment, selected modules, activation flag, original method and path, and the requested config key. An unmatched response does not enter debug forbidden-error normalization.

### 6.2 Forbidden `403`

An enabled, eligible, recognized endpoint denied by the operation, client IP, token, or config-key policy returns a canonical RFC9457 Problem Details response (`Content-Type: application/problem+json`, `Cache-Control: no-store`). Its public body contains only the fixed safe problem members; it does not reveal which authorization condition failed.

```json
{"status":403,"title":"Forbidden","type":"about:blank"}
```

Verify the policy and client settings locally. Do not weaken the allowlists or reveal the token in order to diagnose a denial.

### 6.3 Earlier HTTP response

The debug endpoints execute only when the canonical HTTP pipeline reaches its terminal dispatcher. Maintenance and other earlier middleware may return a response first. There is no debug maintenance bypass; the existing exact liveness bypass remains limited to `/_alive`.

### 6.4 Configuration rejected

Verify the exact config key names and types, digest grammar, canonical IP spelling, logical path grammar, and unique bounded lists. Apply the corrected configuration through the canonical runtime preparation workflow rather than editing generated artifacts.

---

## 7) Security and disablement

- Keep debug endpoints off public listeners. Use an isolated development network or loopback listener, restrictive network access, and trusted TLS where requests cross a transport boundary. The debug guard is not a replacement for network isolation or application authentication.
- Use a dedicated random token for diagnostics. Never reuse passwords, API keys, session credentials, or production tokens.
- Do not log the raw token or the `X-Debug-Token` header. Exclude it from proxy logs, request tracing, shell tracing, support dumps, and monitoring captures. Canonical debug blocked warnings contain only the operation; do not add request data or denial details.
- Do not cache diagnostic responses in shared caches, CDNs, or reverse proxies. Preserve `Cache-Control: no-store` and configure intermediaries not to store or replay these responses. Treat even bounded diagnostic output as private development metadata.
- Rotate the token when exposure is suspected. When diagnostics are no longer needed, set `http.debug.endpoints.enabled` to `false`, clear `http.debug.token_sha256`, and boot the runtime with the updated configuration. Discard the client token from the private shell:

```bash
unset debug_token
```

---

## 8) Related documentation

- [HTTP System Endpoints SSoT](../ssot/http-system-endpoints.md) — terminal endpoint ownership, method/path eligibility, response behavior, and diagnostic boundaries.
- [Sensitive Data Redaction SSoT](../ssot/sensitive-data-redaction.md) — canonical structural classification, Placeholder summaries, and safe failure behavior.
- [HTTP Middleware Wiring SSoT](../ssot/http-middleware-wiring.md) — canonical HTTP pipeline, trusted-proxy processing, and terminal precedence.
- [Config Precedence Matrix SSoT](../ssot/config-precedence-matrix.md) — bootstrap environment and source precedence.
- [Modules and Manifests SSoT](../ssot/modules-and-manifests.md) — runtime module selection and ModulePlan.
- [Application Dependency Sync SSoT](../ssot/application-dependency-sync.md) — physical dependency reconciliation.
- [Artifact Generations SSoT](../ssot/artifact-generations.md) — immutable generation publication and runtime selection.
