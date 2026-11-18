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

# Maintenance mode

Goal: enable, inspect, and disable application-local HTTP maintenance mode without rebuilding configuration artifacts or restarting application processes.

Scope: Consumer applications based on `coretsia/skeleton` with `platform.cli` and `platform.http` selected for the Console target. Commands in this guide run from the **Skeleton application root**, not the Coretsia monorepo root.

This is an operational guide. Canonical HTTP pipeline and completion behavior belongs to `docs/ssot/http-middleware-wiring.md`; Kernel artifact and fingerprint policy belongs to `docs/ssot/artifacts-and-fingerprint.md`.

---

## 1) Static participation versus runtime activation

Maintenance mode has two independent boundaries:

- **Static participation:** the effective target-local `http.maintenance.enabled` configuration controls whether maintenance middleware can act on HTTP requests.
- **Runtime activation:** the application-root-local maintenance flag controls whether participating middleware serves maintenance responses.

The default HTTP configuration is:

```php
'maintenance' => [
    'enabled' => true,
    'retry_after_default_seconds' => 60,
],
```

The feature toggle MUST be a boolean. The default retry delay MUST be an integer in `1..86400` seconds.

An application can override these values in `config/http.php` or through an applicable target-local configuration source, such as `apps/api/config/http.php`.

All participating HTTP targets within the same application root consume the same runtime activation. A target with `http.maintenance.enabled = false` continues its normal HTTP execution even when the shared flag exists, including when maintenance middleware is manually appended.

Selecting `platform.http` for Console makes its maintenance commands available independently of HTTP middleware participation. The runtime flag does NOT suspend CLI commands or Worker execution.

---

## 2) Enable, inspect, and disable maintenance mode

Use the canonical CLI launcher from the application root. The commands also work through the Composer-installed `php vendor/bin/coretsia` entrypoint.

Discover the commands without changing runtime maintenance state:

```bash
php coretsia list
php coretsia help maintenance:enable
php coretsia help maintenance:status
php coretsia help maintenance:disable
```

### 2.1 Enable maintenance mode

Enable maintenance with the generic public response:

```bash
php coretsia maintenance:enable
```

To specify a public message:

```bash
php coretsia maintenance:enable --message="Scheduled maintenance."
```

To specify a public message and override the configured retry delay:

```bash
php coretsia maintenance:enable --message="Scheduled maintenance." --retry-after=300
```

The optional arguments have the following rules:

- `--message` MUST contain non-empty valid UTF-8 text of at most 1024 bytes, without control characters, line separators, or leading/trailing whitespace.
- `--retry-after` is allowed only together with `--message`. Its value MUST be a canonical positive decimal integer in `1..86400` seconds (no sign, leading zero, or surrounding whitespace).
- Both options are non-repeatable. None of the three maintenance commands accepts positional arguments or target-specific options such as `--target`.

The maintenance message is intentionally public HTTP response content. It MUST NOT contain secrets, credentials, personal data, internal paths, or other sensitive information. Do not pass sensitive values through command-line message arguments: shell history, process listings, and external command logging may retain them.

Successful enablement returns exit code `0` and the structured payload `{"enabled":true}`.

### 2.2 Inspect maintenance status

```bash
php coretsia maintenance:status --format=json
```

The status command reports the runtime flag, NOT effective middleware participation for an individual HTTP target.

The structured status payload contains:

- `enabled`: whether the runtime flag is active;
- `message_state`: one of `ignored` (inactive), `absent`, `valid`, or `invalid` (active);
- `retry_after_seconds`: present only when an active, valid message supplies an explicit retry delay.

For example, after enabling with a message and a retry delay of 300 seconds:

```json
{
  "enabled": true,
  "message_state": "valid",
  "retry_after_seconds": 300
}
```

This is the command payload inside the canonical `coretsia.cli-output@1` JSON envelope. Status does not expose the message text or filesystem paths.

Inactive state and active state with an absent or valid message return exit code `0`. Active state with an invalid message returns exit code `1` and retains the structured status payload with `enabled = true` and `message_state = invalid`. Unavailable state returns exit code `1` with a fixed error diagnostic instead of a fabricated inactive status.

### 2.3 Disable maintenance mode

```bash
php coretsia maintenance:disable
php coretsia maintenance:status
```

Successful disablement returns exit code `0` and the structured payload `{"enabled":false}`.

Enable and disable operations are logically idempotent:

- Repeated enablement with identical valid message content and retry delay preserves the existing flag and canonical message bytes.
- Repeated enablement with different message content or retry delay updates the message while preserving an existing enabled flag.
- Enablement without a message removes a retained regular message file and selects the generic response.
- Disablement removes the enabled flag if present and retains the optional message file without inspecting it. Repeated disablement preserves the inactive state.
- Inactive status reports `message_state = ignored` regardless of any retained message.

---

## 3) Runtime state and filesystem permissions

Maintenance uses fixed application-root-relative paths, independent of the current working directory and HTTP application target:

```text
<applicationRoot>/
  var/
    maintenance/
      enabled
      message.json
      maintenance.lock
```

- `enabled`: regular flag file; its presence activates maintenance. Newly published flags contain zero bytes; existing regular flag contents are not interpreted.
- `message.json`: optional version-1 public message file, containing `schemaVersion`, `message`, and an optional `retryAfterSeconds`.
- `maintenance.lock`: persistent Foundation `ScopedFileLock` anchor shared by cooperating maintenance-state operations.

State-changing commands may create missing runtime directories and the lock anchor beneath an existing application root. They use operation-owned same-directory temporary files for publication.

The application root MUST exist and be accessible. The `var` and `var/maintenance` components MUST be real directories, not symlinks. Flag, message (when consumed or changed), and lock-anchor entries MUST be regular files, not symlinks or other special filesystem entries.

### 3.1 Access requirements

- The runtime state directory MUST be operator-controlled and MUST NOT be writable by untrusted users or processes.
- HTTP and CLI processes MUST be able to inspect the application root and runtime directory components (read and search/traverse access on POSIX systems).
- State-changing CLI processes MUST have the directory access required to create missing components, publish temporary files, replace files by rename, and remove the flag or optional message.
- Participating HTTP and CLI processes MUST be able to read the optional message and open the shared lock anchor for both reading and writing. When an operation needs to create the anchor, its process also needs directory write access.
- HTTP and CLI processes MUST operate under the same filesystem identity or an equivalent access policy compatible with Foundation `ScopedFileLock` read/write opening and permission handling.

`ScopedFileLock` requests mode `0775` for newly created directories (subject to the effective umask and filesystem policy). It opens the anchor in non-truncating read/write create mode, including for shared locks, and attempts to set its permissions to `0644` after opening. A group-writable directory or a read-only lock file alone is therefore not sufficient for cross-identity use. An equivalent access policy MUST remain effective after this permission handling.

Do not remove, rename, or replace the persistent lock anchor while cooperating processes may access maintenance state.

### 3.2 Locking and publication

An active-state read uses a blocking shared lock and rechecks the flag under that lock. When the absence of the runtime directory or flag is safely established, read-only inspection returns inactive without creating the runtime state directory or lock anchor.

Enable and disable operations use a blocking exclusive lock on the same anchor. Shared operations can coexist; an exclusive operation waits for conflicting locks, and shared operations wait for a conflicting exclusive lock. Maintenance does not use a non-blocking lock mode or a maintenance-specific lock timeout. A contended lock can therefore delay a request or command.

An enable operation prepares the optional message before publishing a new flag. File publication uses a same-directory temporary file followed by rename. Publication is atomic per file, not a crash-transactional multi-file update.

Use the maintenance commands for state changes; do not bypass their locking and validation by editing runtime state directly.

---

## 4) HTTP behavior during maintenance

Active maintenance short-circuits ordinary requests through `MaintenanceMiddleware` in the canonical `system_pre` slot, before trusted-proxy, request-context enrichment, trusted-host, HTTPS redirect, request-body processing, and terminal request handling.

The maintenance response has:

- HTTP status `503`;
- `Content-Type: text/plain; charset=utf-8`;
- `Cache-Control: no-store`;
- `Retry-After` containing the validated retry delay in decimal seconds;
- the public message followed by one LF, or the generic body `Service Unavailable` followed by one LF.

For the exact `HEAD` request method, the response retains the same status and headers but has an empty body.

A valid message with an explicit retry delay overrides the configured default. A missing, malformed, oversized, unreadable, or otherwise invalid optional message uses the generic response and configured default retry delay.

If maintenance state is unavailable (for example, an inaccessible runtime directory or an unusable lock anchor), participating middleware fails closed and selects the generic maintenance response rather than assuming inactive state.

Applicable post decorators and canonical HTTP response finalization still run on successful maintenance responses. Maintenance does not establish another error boundary or request lifecycle.

### 4.1 Liveness bypass

The only automatic bypass is the exact, case-sensitive URI path:

```text
/_alive
```

A query string does not change this path match. Paths with additional segments, a trailing slash, different casing, or percent-encoded substitutes do not bypass maintenance. Debug paths have no automatic bypass.

The exact bypass also applies when maintenance state is unavailable. The request continues through the downstream HTTP pipeline. Maintenance does not implement the endpoint, synthesize a response, or guarantee any downstream HTTP status.

---

## 5) Artifact and deployment boundaries

Runtime activation, message updates, and deactivation do not require `config:compile` or an application restart. The already constructed participating middleware observes fresh application-local state when handling requests.

The existing effective Kernel fingerprint exclusion MUST retain:

```text
kernel.fingerprint.application_ignore_prefixes
  var/maintenance
```

This exclusion covers the runtime flag, optional message, persistent lock anchor, and operation-owned temporary files. It MUST NOT be removed or replaced by target-specific maintenance paths.

Maintenance state is not compiled configuration and is not a generated artifact. Maintenance commands do not create or rewrite Kernel artifacts, change fingerprint identity, or select another artifact generation. Kernel retains ownership of artifact compilation, publication, selection, and verification.

Static HTTP configuration changes, including changing the feature toggle or default retry delay, follow normal configuration compilation and fingerprint semantics. They are not runtime maintenance commands.

For an application with existing compiled artifacts, verify them through the normal Kernel operation if needed:

```bash
php coretsia cache:verify --target=web
```

The explicit target belongs to the Kernel verification command, not to maintenance commands.

Maintenance state is local to cooperating processes using the same application root and filesystem with compatible locking and file access semantics. Separate application roots have independent maintenance state. Automatic distributed synchronization across independent filesystems or hosts is not provided; crash-transactional durability is not guaranteed.

---

## 6) Observability and failure behavior

When the corresponding HTTP observability features are enabled:

- `http.maintenance_total` counts completed maintenance decisions with `outcome = served|bypassed`;
- `http.maintenance.served` reports completed maintenance response construction;
- `http.maintenance.message_invalid` warns about an invalid optional message;
- `http.maintenance.state_unavailable` warns about unavailable state.

These decision signals are independent of canonical final HTTP request completion. Their emission is subject to the existing metrics, logging, admission, and redaction policy. They do not include public message contents or raw filesystem diagnostics.

A filesystem-operation failure does NOT guarantee rollback of filesystem mutations already completed by the operation. For example, a message update may have completed before flag publication or lock release fails.

After any unsuccessful state-changing maintenance command, the operator MUST run `maintenance:status` to establish the currently observable state before proceeding. If status remains unavailable, correct the access or filesystem problem and check again. Do not infer the state from the failed command's intended effect.

---

## 7) Common failures and fixes

### 7.1 Maintenance commands not found

Confirm that `platform.cli` and `platform.http` are installed and selected for Console. Use the normal `list` and `help` commands to inspect the available command catalog. The runtime flag is not a command-discovery toggle.

### 7.2 Invalid command input or public message

Use only the documented command options and their accepted values. Supply `--retry-after` only with a valid `--message`. Check the fixed diagnostics `CORETSIA_MAINTENANCE_COMMAND_INVALID` and `CORETSIA_MAINTENANCE_MESSAGE_INVALID` for maintenance-owned input failures. Command-catalog input rejection remains owned by CLI.

### 7.3 Active state with an invalid optional message

Check that the message file is a regular readable file. Use `maintenance:enable` with a valid public message to replace the invalid message, or without a message to select the generic response. An invalid optional message does not deactivate maintenance.

### 7.4 Maintenance state unavailable

`CORETSIA_MAINTENANCE_STATE_UNAVAILABLE` indicates a state-access or state-mutation failure. Check application-root resolution, runtime directory types and access, regular-file requirements, filesystem capacity, and the shared lock anchor's effective read/write access.

Restore the required access policy before retrying an operation. Always recheck status after a failed state change.

### 7.5 Active state but normal HTTP responses

Check the effective `http.maintenance.enabled` configuration and middleware participation for the relevant HTTP target. The status command does not report those target-local settings. Also distinguish the exact liveness bypass from ordinary request processing.

---

## 8) Related documentation

- `docs/ssot/http-middleware-wiring.md` — canonical HTTP pipeline and final-response execution.
- `docs/adr/ADR-0041-platform-http-runtime-pipeline-wiring.md` — HTTP runtime composition and ownership boundaries.
- `docs/ssot/config-merge-order.md` — target-local configuration source precedence.
- `docs/ssot/artifacts-and-fingerprint.md` — Kernel artifact production, fingerprint exclusion, and cache verification.
- `docs/ssot/artifact-generations.md` — immutable artifact generation ownership and selection.
- `docs/ssot/observability.md` — canonical maintenance-decision metric and log policy.
