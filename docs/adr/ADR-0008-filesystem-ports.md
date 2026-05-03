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

# ADR-0008: Filesystem ports

## Status

Accepted.

## Context

Epic `1.140.0` introduces a stable filesystem contract under:

```text
framework/packages/core/contracts/src/Filesystem/
```

Platform packages and integration drivers need a shared filesystem boundary without coupling `core/contracts` to platform implementations, integration packages, HTTP abstractions, local filesystem details, cloud storage SDKs, or vendor-specific filesystem APIs.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Message\StreamInterface`
- `League\Flysystem\*`
- `Symfony\Component\Filesystem\*`
- `SplFileInfo`
- `FilesystemIterator`
- `DirectoryIterator`
- `RecursiveDirectoryIterator`
- cloud SDK clients
- vendor-specific filesystem clients
- framework tooling packages
- generated architecture artifacts

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/filesystem-contracts.md
```

The existing SSoT baseline already defines the relevant supporting rules:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/dto-policy.md
```

Filesystem paths are sensitive by default.

The observability baseline forbids `path` as a metric label and forbids raw path emission in unsafe diagnostics.

## Decision

Coretsia will introduce one canonical filesystem port in `core/contracts`:

```text
Coretsia\Contracts\Filesystem\DiskInterface
```

The implementation path is:

```text
framework/packages/core/contracts/src/Filesystem/DiskInterface.php
```

`DiskInterface` is the single contracts-level boundary for logical disk operations.

The canonical interface shape is:

```text
exists(string $path): bool
read(string $path): ?string
write(string $path, string $contents): void
delete(string $path): void
listPaths(string $prefix = ''): array
```

The contracts package defines only the port and boundary policy.

It does not implement:

- local filesystem access;
- cloud object storage access;
- vendor filesystem adapters;
- path normalization;
- path safety enforcement;
- disk registry behavior;
- upload handling;
- session storage;
- lock storage;
- filesystem exception mapping;
- DI registration;
- config defaults;
- config rules;
- generated artifacts.

Concrete filesystem behavior belongs to future runtime owner packages and integration drivers.

## Single DiskInterface decision

Coretsia will use one canonical `DiskInterface` instead of separate contracts for local disks, cloud disks, upload disks, session disks, lock disks, or vendor-backed disks.

A single port prevents drift between platform packages and integrations.

The same contract can be consumed by future packages such as:

```text
platform/filesystem
platform/session
platform/uploads
platform/lock
```

and implemented by future drivers under:

```text
integrations/*
```

This gives the runtime layer one stable filesystem abstraction without forcing `core/contracts` to know about storage backends.

The disk represents one logical storage boundary.

How disks are named, selected, configured, mounted, authenticated, discovered, cached, or wired is runtime-owned.

## Logical path decision

`DiskInterface` operates on logical disk paths.

A logical path is a storage-relative identifier inside one configured disk boundary.

It is not an absolute local filesystem path.

Valid logical path examples:

```text
avatar.png
users/123/profile.json
sessions/session-id-hash
uploads/tenant-hash/object-hash
locks/job-hash.lock
```

Invalid logical path examples:

```text
/tmp/app/file.txt
/home/user/project/file.txt
C:\Users\User\project\file.txt
C:/Users/User/project/file.txt
\\server\share\file.txt
file:///tmp/file.txt
s3://bucket/object
```

The disk implementation may internally use a local root, object-storage bucket, memory store, encrypted volume, remote service, or another backend-specific source.

That backend-specific root must not leak into the contracts API.

## No vendor concretes decision

`DiskInterface` will not expose vendor filesystem abstractions.

The contract must not expose:

- Flysystem adapters;
- Symfony filesystem objects;
- PSR-7 streams;
- cloud SDK clients;
- storage SDK result objects;
- `SplFileInfo`;
- iterators;
- resources;
- streams;
- file handles;
- backend metadata objects.

This keeps `core/contracts` independent of a specific filesystem stack.

A future runtime owner may internally use Flysystem, Symfony Filesystem, native PHP functions, S3 SDKs, another object-storage SDK, or a custom implementation.

That implementation choice must not become part of the contracts package API.

## No platform or integration imports decision

`core/contracts` must not import or depend on platform or integration packages.

Allowed direction:

```text
platform/filesystem → core/contracts
platform/session → core/contracts
platform/uploads → core/contracts
platform/lock → core/contracts
integrations/* filesystem drivers → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/filesystem
core/contracts → platform/session
core/contracts → platform/uploads
core/contracts → platform/lock
core/contracts → integrations/*
```

This preserves the contracts package as a stable low-level boundary.

Runtime packages may consume the contract.

Integration packages may implement the contract.

The contract itself must remain independent of both.

## Path safety ownership decision

Path normalization and path safety enforcement are owned by future `platform/filesystem`, not by `core/contracts`.

`core/contracts` defines only the logical path boundary.

The contracts package does not implement:

- separator normalization;
- traversal checks;
- absolute path rejection;
- root containment checks;
- symlink policy;
- hidden file policy;
- reserved filename policy;
- case-sensitivity policy;
- backend-specific path canonicalization.

This prevents the contracts package from prematurely freezing runtime security policy.

A future `platform/filesystem` owner must define and enforce concrete path safety behavior.

Integration drivers must follow the normalized logical path contract provided by the runtime owner.

## Read/write decision

`read()` and `write()` operate on string byte contents.

The contract intentionally does not expose stream resources or vendor stream abstractions.

The canonical missing-vs-empty behavior is:

| state                   | `read()` result    |
|-------------------------|--------------------|
| missing path            | `null`             |
| existing empty file     | `""`               |
| existing non-empty file | non-empty `string` |

`read()` must not return `false`.

This preserves the Phase 0 missing-vs-empty invariant and avoids PHP truthiness ambiguity.

Append, partial writes, chunked writes, streamed writes, atomic writes, temporary files, and backend-specific write options are out of scope for this contracts epic.

Future runtime owners may add higher-level APIs through their own epics.

## Listing decision

`listPaths()` returns logical paths under a logical prefix.

The canonical return shape is:

```text
list<string>
```

Returned paths must be logical disk paths, not absolute backend paths.

Returned paths must be deterministic.

The canonical listing order is:

```text
path ascending using byte-order strcmp
```

The order must not depend on:

- filesystem traversal order;
- object storage API return order;
- PHP hash-map insertion side effects;
- process locale;
- host platform;
- timestamps;
- random values.

No matching paths return an empty list.

Backend failure behavior is implementation-owned.

## Directory semantics decision

The contracts package will not define first-class directory objects.

A logical directory is represented only by path-prefix convention.

Backends with real directories may map them to prefix behavior.

Backends without real directories may implement listing through object-key prefix semantics.

The contracts package does not define:

- directory creation;
- recursive directory deletion;
- empty directory persistence;
- directory metadata;
- directory permissions;
- symlink behavior;
- mount behavior.

These concerns belong to future runtime owner packages.

## Metadata and visibility decision

Epic `1.140.0` does not introduce filesystem metadata models.

`DiskInterface` does not expose:

- file size;
- MIME type;
- timestamps;
- ETags;
- checksums;
- ACLs;
- visibility flags;
- public URLs;
- temporary URLs;
- storage class metadata;
- backend object metadata;
- vendor handles.

Future owner epics may introduce safe metadata contracts only through SSoT and ADR updates.

Any future metadata shape must remain format-neutral, deterministic, and safe-by-default.

## Error handling decision

Epic `1.140.0` does not introduce a filesystem exception hierarchy.

Filesystem failure handling is runtime-owned.

Concrete implementations may throw owner-defined exceptions.

Those exceptions and diagnostics must not expose:

- absolute backend paths;
- raw logical paths by default;
- credentials;
- tokens;
- private keys;
- request bodies;
- response bodies;
- raw SQL;
- private customer data;
- vendor object dumps;
- stack traces by default.

If filesystem errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

The contracts package does not implement filesystem exception mapping.

The contracts package does not require `ErrorDescriptor` construction inside filesystem contracts.

## Runtime ownership decision

A future `platform/filesystem` package is expected to own:

- concrete disk registry behavior;
- path normalization;
- path safety policy;
- local disk implementation;
- safe filesystem diagnostics;
- filesystem configuration roots, if introduced by its own epic;
- filesystem integration with observability and error handling.

Future runtime packages such as:

```text
platform/session
platform/uploads
platform/lock
```

are expected to use `DiskInterface` for filesystem-backed behavior when they need filesystem access.

These packages consume the policy later.

Epic `1.140.0` does not create or modify those packages.

## Integration driver decision

Drivers live outside `core/contracts`.

Future filesystem drivers under:

```text
integrations/*
```

must implement `DiskInterface` when they provide filesystem disk behavior.

This keeps backend-specific dependencies isolated.

Examples of future driver families may include local filesystem, object storage, encrypted storage, in-memory storage, or remote storage.

The contracts package must not depend on any of those driver packages.

## DI tag decision

Epic `1.140.0` introduces no DI tags.

The contracts package must not declare public filesystem tag constants.

The contracts package must not define package-local mirror constants for filesystem tags.

The contracts package must not define filesystem tag metadata keys, tag priority semantics, or discovery semantics.

If a future runtime owner needs filesystem DI tags, that owner must introduce them through:

```text
docs/ssot/tags.md
```

according to tag registry rules.

## Config decision

Epic `1.140.0` introduces no config roots and no config keys.

The contracts package must not add package config defaults or config rules for filesystem contracts.

Filesystem disks, roots, credentials, adapters, visibility defaults, path policies, and driver options are runtime configuration concerns.

Future runtime owner packages may introduce filesystem config only through their own owner epics and the config roots registry process.

## Artifact decision

Epic `1.140.0` introduces no artifacts.

The contracts package must not generate filesystem artifacts, disk artifacts, path artifacts, upload artifacts, session artifacts, lock artifacts, or runtime lifecycle artifacts.

Future runtime owners may introduce artifacts only through their own owner epics and the artifact registry process.

## Observability and redaction decision

Filesystem paths and file contents are sensitive by default.

Runtime diagnostics, logs, spans, metrics, health output, CLI output, error descriptors, worker failure output, and unsafe debug output must not expose raw filesystem paths or file contents by default.

Safe diagnostics may expose only derived information such as:

```text
hash(path)
len(path)
operation
outcome
driver
```

The baseline metric label allowlist is governed by:

```text
docs/ssot/observability.md
```

Epic `1.140.0` introduces no new metric label keys.

Filesystem paths, prefixes, object names, upload names, session ids, tenant ids, user ids, request ids, and correlation ids must not become metric labels under the baseline policy.

## Security decision

Filesystem contracts must not require storing secrets.

Filesystem contracts must not require passing credentials, tokens, keys, DSNs, bucket names, absolute roots, or backend connection objects through `DiskInterface`.

Secret-backed filesystem behavior belongs to runtime owner packages and integration drivers.

Raw logical paths and file contents must not be copied into exception messages, error descriptor extensions, logs, spans, metrics, health output, CLI output, or worker failure output by default.

## Consequences

Positive consequences:

- Platform packages and integration drivers share one filesystem contract.
- `core/contracts` stays independent of vendor filesystem libraries.
- Runtime owners can define path safety without changing the contracts package.
- Driver implementations can evolve independently in `integrations/*`.
- Session, upload, lock, and filesystem platform packages can consume the same port later.
- Missing file and existing empty file remain distinguishable.
- Listing behavior can be deterministic across operating systems and backends.
- Filesystem diagnostics stay compatible with the global redaction law.

Trade-offs:

- Contracts do not provide a concrete filesystem implementation.
- Contracts do not provide path normalization or traversal protection.
- Contracts do not expose streaming APIs in this epic.
- Contracts do not expose metadata, visibility, temporary URL, or directory APIs.
- Runtime owners must implement disk registry, path policy, drivers, configuration, and error handling later.

## Rejected alternatives

### Put Flysystem types in contracts

Rejected.

Flysystem is an implementation choice.

Putting `League\Flysystem\*` in `core/contracts` would make the contracts package depend on a vendor-specific filesystem abstraction and would prevent alternative implementations from remaining dependency-light.

### Put Symfony Filesystem types in contracts

Rejected.

Symfony Filesystem is a concrete implementation library.

Contracts must define the framework boundary, not expose a specific local filesystem implementation.

### Put PSR-7 streams in filesystem contracts

Rejected.

PSR-7 streams would introduce HTTP-oriented package coupling and stream semantics into a general filesystem port.

Filesystem contracts must be usable by HTTP, CLI, worker, scheduler, queue consumer, and custom runtimes without requiring PSR-7.

### Expose PHP resources or file handles

Rejected.

Resources and file handles are runtime/backend-specific and unsafe as public contracts API.

They are not portable across local filesystems, object storage, encrypted stores, remote stores, or in-memory stores.

They also complicate deterministic testing and redaction policy.

### Expose SplFileInfo or filesystem iterators

Rejected.

`SplFileInfo`, `FilesystemIterator`, `DirectoryIterator`, and `RecursiveDirectoryIterator` are local filesystem concepts.

They would leak local filesystem assumptions into a contract that must also support object storage and other backends.

### Put path normalization into core/contracts

Rejected.

Path normalization and path safety enforcement require concrete runtime policy.

They involve backend roots, separators, traversal behavior, symlink behavior, case-sensitivity behavior, and security decisions.

Those responsibilities belong to `platform/filesystem`, not `core/contracts`.

### Accept absolute paths in DiskInterface

Rejected.

Absolute paths would leak backend implementation details and environment-specific bytes.

The contract uses logical disk paths only.

Backend roots are implementation-owned and must not cross the contracts boundary.

### Create separate ports for uploads, sessions, locks, and generic files

Rejected.

Separate filesystem-like ports would create boundary drift and duplicate driver obligations.

`DiskInterface` is the single filesystem storage port.

Higher-level session, upload, and lock semantics belong to their future platform packages.

### Add filesystem DI tags in contracts

Rejected.

Epic `1.140.0` does not need a new discovery tag.

DI tag ownership is governed by `docs/ssot/tags.md`.

A future runtime owner may introduce filesystem tags through its own owner epic.

### Add filesystem config roots in contracts

Rejected.

Filesystem configuration is runtime/platform policy.

The contracts package introduces no config roots and no package config files.

### Add filesystem generated artifacts

Rejected.

Generated artifacts require owner-defined schema semantics, source discovery, deterministic serialization, and runtime integration.

Those responsibilities belong to future runtime owner packages.

## Non-goals

This ADR does not implement:

- concrete filesystem implementation;
- local disk adapter;
- cloud disk adapter;
- memory disk adapter;
- encrypted disk adapter;
- disk registry;
- filesystem manager;
- path normalizer;
- path traversal protection;
- symlink policy;
- absolute path policy implementation;
- upload service;
- session storage implementation;
- lock storage implementation;
- temporary URL generation;
- visibility or ACL policy;
- filesystem metadata model;
- filesystem exception hierarchy;
- filesystem exception mapper;
- filesystem config roots;
- filesystem config defaults;
- package config files;
- DI tags;
- DI registration;
- generated filesystem artifacts;
- vendor driver implementation;
- `platform/filesystem` implementation;
- `platform/session` integration;
- `platform/uploads` integration;
- `platform/lock` integration;
- `integrations/*` driver implementation.

## Related SSoT

- `docs/ssot/filesystem-contracts.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/dto-policy.md`

## Related epic

- `1.140.0 Contracts: Filesystem ports`
