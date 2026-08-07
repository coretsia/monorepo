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

# Worker Control Security (SSoT)

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/worker
```

This document is the Single Source of Truth for Worker control-channel authentication, supervisor-instance credentials, private locator confidentiality, TCP loopback restriction, Unix control-socket permissions, credential rotation, redaction, and the supported threat boundary.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Authority boundary (MUST)

This document owns:

- authentication of `status`, `health`, and `stop` requests;
- generation, representation, lifetime, comparison, and rotation of the supervisor-instance control credential;
- private lifecycle-locator confidentiality requirements;
- TCP control-listener host restrictions;
- Unix control-socket creation permissions;
- credential redaction from public and diagnostic surfaces;
- the supported local-process threat boundary.

This document does not own:

- child readiness tokens;
- proc process-host authentication tokens;
- application authentication or authorization;
- remote Worker administration;
- operating-system account isolation;
- Windows ACL provisioning by deployment tooling;
- Linux-specific peer credential APIs.

## Terms

### Supervisor instance

One execution of the foreground Worker supervisor from lock acquisition until complete lifecycle cleanup.

### Control credential

A private 256-bit random bearer credential associated with exactly one supervisor instance.

### Private lifecycle locator

The owner-only filesystem capability artifact that contains the active endpoint, active shutdown deadlines, and the active control credential.

## Credential properties (MUST)

Every control credential MUST:

- contain 256 bits generated through `random_bytes(32)`;
- be encoded as exactly 64 lowercase hexadecimal characters;
- be generated after stale lifecycle cleanup and before control-listener publication;
- remain stable for the lifetime of one supervisor instance;
- rotate on every new supervisor start;
- be compared through `hash_equals()`;
- have no string-conversion, JSON-serialization, logging, fingerprint, or diagnostic API.

Child spawn and child recycle MUST NOT rotate the supervisor-instance credential.

A credential from a previous supervisor instance MUST be rejected by the active control server.

## Request authentication (MUST)

The control protocol version remains `1` while the project is pre-stable.

Every version-`1` request MUST contain the exact fields:

```text
credential
operation
request_id
version
```

The `credential` field MUST contain the active supervisor-instance credential.

The control server MUST authenticate the credential after exact protocol decoding and before creating `WorkerControlSession`.

A missing, malformed, or non-matching credential MUST cause silent connection closure. It MUST NOT:

- create a control session;
- execute an operation;
- return a detailed authentication error;
- terminate the supervisor;
- enter logs, spans, metrics, diagnostics, or exceptions.

Responses MUST NOT contain or echo the credential.

## Credential ownership (MUST)

The credential MAY exist only in:

- active supervisor memory;
- the active `WorkerControlServer` authentication state;
- the private `WorkerLifecycleLocator` and its owner-only file;
- one private request frame and decoded request object;
- tests and canonical security documentation.

`WorkerControlCredential` MUST NOT be a container service, runtime seed, public contract, driver input, child-launch argument, public state field, or observability value.

The credential MUST NOT enter:

- `worker.state.json`;
- `WorkerPoolState` or `WorkerHealthState`;
- response frames;
- CLI output;
- logs;
- spans;
- metrics;
- endpoint identifiers or endpoint hashes;
- exception messages or reasons;
- artifact fingerprints;
- child environment or argv.

## Private locator policy (MUST)

The lifecycle locator is a private capability artifact.

On POSIX it MUST:

- be written through an exclusive temporary file created under restrictive `umask(0177)`;
- use close-on-exec defense in depth;
- be verified as mode `0600` before credential bytes are written and before atomic publication;
- be rejected on read when its effective permission bits are not exactly `0600`;
- reject symlinks and non-regular files.

On Windows, deployment MUST restrict the skeleton and runtime-directory ACL to the application service account and authorized administrators. Pure-PHP `chmod()` behavior is not treated as an equivalent Windows ACL guarantee.

The raw credential MAY be stored in the private locator because lifecycle clients require it to authenticate. The locator MUST never be copied into public state or output.

## Transport policy (MUST)

TCP control transport MUST bind exactly to:

```text
127.0.0.1
```

No non-loopback or unsafe opt-in exists.

Unix control sockets MUST be created under a restrictive `0177` umask and MUST be verified as mode `0600` before the listener is published.

Post-bind `chmod(0600)` remains required as verification and defense in depth, but it MUST NOT be the only creation-time protection.

Linux-specific peer credential validation MAY be evaluated separately, but it is not required by this cross-platform contract.

## Threat boundary (MUST)

The credential protects the control endpoint from processes that cannot read the private lifecycle locator.

It is not an isolation boundary against arbitrary processes running under the same compromised operating-system account or against an administrator capable of reading that account's private files or process memory.

Remote Worker control is not supported.

## Protocol and locator version policy (MUST)

The control request schema and private locator schema both remain version `1` while the project is pre-stable.

Version `1` means the current authenticated schema. Unauthenticated historical shapes are not supported and MUST be rejected by exact key validation.

## Verification (MUST)

Tests MUST prove:

- generated credentials are exact 64-character lowercase hexadecimal values;
- missing and malformed credentials are rejected;
- a valid-shaped incorrect credential is rejected;
- the correct credential creates a typed control session;
- a credential from a previous supervisor instance is rejected;
- an invalid request does not make the listener unusable;
- child recycle does not rotate the credential;
- a new supervisor instance rotates the credential;
- TCP remains loopback-only;
- Unix socket mode is `0600` and creation uses restrictive umask;
- the private locator temporary file is created under restrictive umask and verified as `0600` before credential bytes are written;
- a POSIX locator with broader permissions is rejected;
- the credential is absent from state, responses, CLI output, observability, diagnostics, and exceptions.
