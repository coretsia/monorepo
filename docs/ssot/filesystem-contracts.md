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

# Filesystem Contracts SSoT

## Scope

This document is the Single Source of Truth for Coretsia filesystem contracts, logical disk path semantics, filesystem port boundaries, deterministic listing policy, dependency restrictions, and filesystem redaction rules.

This document governs contracts introduced by epic `1.140.0` under:

```text
framework/packages/core/contracts/src/Filesystem/
```

The canonical filesystem contract introduced by this epic is:

```text
Coretsia\Contracts\Filesystem\DiskInterface
```

The implementation path is:

```text
framework/packages/core/contracts/src/Filesystem/DiskInterface.php
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Filesystem access must be expressed through a single stable contracts-level disk port so platform packages and integration drivers can interoperate without leaking vendor-specific filesystem APIs into `core/contracts`.

The contracts introduced by this epic define only:

- a format-neutral filesystem port;
- logical disk path semantics;
- deterministic path listing expectations;
- redaction and observability constraints for filesystem diagnostics;
- dependency and ownership boundaries for future runtime packages.

The contracts package MUST NOT implement filesystem behavior, path normalization, path safety validation, driver selection, disk configuration, filesystem adapters, upload handling, session storage, lock storage, DI registration, config defaults, config rules, or generated artifacts.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.20.0` no-secrets output policy applies to filesystem diagnostics.
- `0.60.0` missing vs empty MUST remain distinguishable where file contents are read.
- `0.70.0` lists preserve order and maps use deterministic key ordering when json-like metadata is introduced by future owners.
- `0.90.0` safe diagnostics MUST NOT expose raw values and MAY expose only safe derivations such as `hash(value)` or `len(value)`.

Epic `1.140.0` itself introduces no filesystem implementation, no driver implementation, no config root, no generated artifact, and no DI tag.

## Contract boundary

Filesystem contracts are format-neutral.

They define a stable port and boundary policy only.

The contracts package MUST NOT implement:

- local filesystem access;
- cloud object storage access;
- vendor filesystem adapters;
- path normalization;
- path traversal protection;
- symlink policy;
- upload handling;
- temporary URL generation;
- visibility/ACL management;
- directory creation policy;
- lock-file semantics;
- session file semantics;
- cache file semantics;
- filesystem exception mapping;
- filesystem health checks;
- DI registration;
- runtime discovery;
- config defaults;
- config rules;
- generated filesystem artifacts.

Runtime owner packages implement concrete filesystem behavior later.

## Contract package dependency policy

Filesystem contracts MUST remain dependency-free beyond PHP itself.

They MUST NOT depend on:

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
- PDO concrete APIs
- Redis concrete APIs
- S3 concrete APIs
- cloud SDK clients
- vendor-specific filesystem clients
- framework HTTP runtime packages
- framework CLI runtime packages
- worker runtime packages
- queue vendor clients
- scheduler vendor clients
- concrete service container implementations
- concrete middleware implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- framework tooling packages
- generated architecture artifacts

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

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

## DTO terminology boundary

This document uses the terms `contract`, `port`, and `runtime boundary` according to:

```text
docs/ssot/dto-policy.md
```

`DiskInterface` is a contracts interface.

It is not a DTO-marker class.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Interfaces introduced by epic `1.140.0` MUST NOT be treated as DTOs.

## Disk interface

`DiskInterface` is the canonical contracts-level filesystem boundary.

The implementation path is:

```text
framework/packages/core/contracts/src/Filesystem/DiskInterface.php
```

The canonical interface shape is:

```text
exists(string $path): bool
read(string $path): ?string
write(string $path, string $contents): void
delete(string $path): void
listPaths(string $prefix = ''): array
```

`DiskInterface` represents a single logical disk.

A logical disk MAY be backed by:

- local filesystem storage;
- object storage;
- in-memory storage;
- test storage;
- encrypted storage;
- remote storage;
- another implementation-owned storage backend.

The contracts package does not define the backend.

The contracts package does not define how a disk is configured, named, selected, mounted, authenticated, discovered, or wired.

Concrete disk implementations belong to future runtime owner packages and integration drivers.

## Logical path model

`DiskInterface` methods operate on logical disk paths.

A logical disk path is a storage-relative identifier inside one configured disk boundary.

A logical path is not an absolute local filesystem path.

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

Disk configuration MAY internally point to an absolute local path, cloud bucket, memory store, container volume, or another backend-specific root.

That backend root MUST NOT be passed through `DiskInterface` path arguments.

Path root ownership belongs to the implementation.

## Path sensitivity and redaction

Filesystem paths are sensitive by default.

A logical disk path MAY contain user-controlled names, tenant identifiers, upload object names, session identifiers, lock identifiers, or other high-cardinality application data.

Runtime diagnostics, logs, spans, metrics, health output, CLI output, error descriptors, worker failure output, and unsafe debug output MUST NOT expose raw filesystem paths by default.

Safe diagnostics MAY expose only derived information such as:

```text
hash(path)
len(path)
pathKind
operation
outcome
```

Safe derivations MUST NOT expose raw values or allow reconstruction of sensitive paths.

Filesystem paths MUST NOT become metric labels under the baseline observability policy.

The label key `path` is forbidden by:

```text
docs/ssot/observability.md
```

Runtime owners MUST prefer omission over unsafe emission.

## Path safety ownership

Path normalization and path safety enforcement are owned by `platform/filesystem`, not by `core/contracts`.

`core/contracts` defines only the logical path boundary.

The contracts package MUST NOT implement:

- path normalization;
- path canonicalization;
- path traversal checks;
- absolute path rejection logic;
- separator conversion;
- symlink resolution;
- disk root containment checks;
- hidden file policy;
- reserved filename policy;
- case-sensitivity policy.

Future `platform/filesystem` owners MUST define and enforce concrete path safety policy.

Integration drivers MUST follow the normalized logical path contract provided by the runtime owner.

## Path behavior requirements

Callers SHOULD pass stable logical paths using `/` as the logical separator.

Implementations MAY reject invalid paths.

Implementations MUST NOT silently treat an absolute local filesystem path as a valid logical disk path.

Implementations MUST NOT leak absolute backend paths through exceptions, diagnostics, logs, spans, metrics, health output, CLI output, error descriptor extensions, or worker failure output.

The empty string path is not a valid file path for `exists()`, `read()`, `write()`, or `delete()` unless a future owner SSoT explicitly allows root-object semantics.

For `listPaths()`, an empty prefix represents the logical disk root.

## File contents model

`read()` and `write()` operate on string byte contents.

The contracts package intentionally does not expose streams, resources, file handles, or vendor stream interfaces.

The canonical missing-vs-empty behavior is:

| state                   | `read()` result    |
|-------------------------|--------------------|
| missing path            | `null`             |
| existing empty file     | `""`               |
| existing non-empty file | non-empty `string` |

A missing path and an existing empty file MUST remain distinguishable.

This preserves the Phase 0 missing-vs-empty invariant.

`read()` MUST NOT return `false`.

`write()` writes or replaces the full contents for a logical path.

Append, partial writes, chunked writes, streamed writes, temporary files, atomic writes, and backend-specific write options are outside this contracts epic.

Future runtime owners MAY add higher-level APIs through their own epics.

## Existence behavior

`exists()` returns whether a logical path exists in the disk.

`exists()` MUST distinguish absence from existing empty contents.

`exists()` MUST NOT expose backend-specific metadata.

`exists()` MUST NOT require callers to handle vendor-specific exceptions for ordinary missing paths.

Invalid path handling is implementation-owned.

## Delete behavior

`delete()` removes a logical path according to implementation-owned backend semantics.

Deleting a missing path SHOULD be noop-safe.

Failure handling for invalid paths, backend errors, permissions, or transient storage failures is implementation-owned.

`delete()` MUST NOT expose raw backend paths or vendor diagnostics through unsafe outputs.

## Listing behavior

`listPaths()` returns logical paths known to the disk under a logical prefix.

The canonical return shape is:

```text
list<string>
```

The prefix argument is a logical path prefix.

An empty prefix means the logical disk root.

`listPaths()` MUST return logical disk paths, not absolute backend paths.

Returned paths MUST be deterministic.

The canonical listing order is:

```text
path ascending using byte-order strcmp
```

Listing order MUST NOT depend on:

- filesystem traversal order;
- object storage API return order;
- PHP hash-map insertion side effects;
- process locale;
- host platform;
- timestamps;
- random values.

`listPaths()` MUST preserve the distinction between no matching paths and backend failure.

No matching paths return an empty list.

Backend failure behavior is implementation-owned.

`listPaths()` MUST NOT return `SplFileInfo`, iterators, resources, stream handles, vendor file descriptors, or backend-specific object metadata.

## Directory semantics

The contracts package does not define first-class directory objects.

A logical directory is represented only by path-prefix convention.

Backends that have real directories MAY map them to prefix behavior.

Backends that do not have real directories MAY still implement `listPaths()` through object-key prefix semantics.

The contracts package does not define:

- directory creation;
- recursive directory deletion;
- empty directory persistence;
- directory metadata;
- directory permissions;
- symlink behavior;
- mount behavior.

Those concerns belong to future runtime owner packages.

## Metadata and visibility policy

Epic `1.140.0` does not introduce filesystem metadata models.

`DiskInterface` MUST NOT expose:

- file size metadata;
- MIME metadata;
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

Future owner epics MAY introduce safe metadata contracts only through SSoT and ADR updates.

Any future metadata shape MUST remain format-neutral, deterministic, json-like where applicable, and safe-by-default.

## Exception and error policy

Epic `1.140.0` does not introduce a filesystem exception hierarchy.

Filesystem failure handling is runtime-owned.

Concrete implementations MAY throw owner-defined exceptions.

Owner-defined exceptions and diagnostics MUST NOT expose:

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

The contracts package MUST NOT implement filesystem exception mapping.

The contracts package MUST NOT require `ErrorDescriptor` construction inside filesystem contracts.

## Runtime usage policy

`DiskInterface` is the contracts boundary for future filesystem-aware runtime packages.

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

Future integration drivers under:

```text
integrations/*
```

MUST implement `DiskInterface` when providing filesystem disk drivers.

This is documented runtime policy only.

Epic `1.140.0` does not create or modify those runtime packages.

## DI tag policy

Epic `1.140.0` introduces no DI tags.

The contracts package MUST NOT declare public filesystem tag constants.

The contracts package MUST NOT define package-local mirror constants for filesystem tags.

The contracts package MUST NOT define filesystem tag metadata keys, tag priority semantics, or discovery semantics.

If a future runtime owner needs filesystem DI tags, that owner MUST introduce them through:

```text
docs/ssot/tags.md
```

according to tag registry rules.

## Config policy

Epic `1.140.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for filesystem contracts.

No files under package `config/` are introduced by this epic.

Future runtime owner packages MAY introduce filesystem config roots or config keys only through their own owner epics and the config roots registry process.

Filesystem disks, roots, credentials, adapters, visibility defaults, path policies, and driver options are runtime configuration concerns.

They are outside `core/contracts`.

## Artifact policy

Epic `1.140.0` introduces no artifacts.

The contracts package MUST NOT generate filesystem artifacts, disk artifacts, path artifacts, upload artifacts, session artifacts, lock artifacts, or runtime lifecycle artifacts.

A future runtime owner MAY introduce generated artifacts only through its own owner epic and the artifact registry process.

## Observability and diagnostics policy

Filesystem contracts do not define observability signals.

Runtime implementations MAY emit logs, spans, metrics, or profiling signals around filesystem operations only when those signals follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
```

Diagnostics MUST NOT expose:

- `.env` values;
- passwords;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- session identifiers;
- raw logical filesystem paths by default;
- absolute backend paths;
- request bodies;
- response bodies;
- raw queue messages;
- raw worker payloads;
- raw SQL;
- profile payloads;
- private customer data.

Safe implementation diagnostics MAY use:

```text
hash(path)
len(path)
operation
outcome
driver
```

Metric labels MUST remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.140.0` introduces no new metric label keys.

Filesystem paths, prefixes, object names, upload names, session ids, tenant ids, user ids, request ids, and correlation ids MUST NOT become metric labels under the baseline policy.

## Security and redaction

Filesystem contracts MUST NOT require storing secrets.

Filesystem contracts MUST NOT require passing credentials, tokens, keys, DSNs, bucket names, absolute roots, or backend connection objects through `DiskInterface`.

Filesystem implementations MUST NOT leak backend root paths or raw logical paths through unsafe diagnostics.

Runtime owners MUST prefer omission over unsafe emission.

Any safe derived diagnostic MUST be deterministic and non-reversible where applicable.

Filesystem implementations MUST NOT copy raw path strings or raw file contents into exception messages, error descriptor extensions, logs, spans, metrics, health output, CLI output, or worker failure output by default.

File contents are application data and MUST be treated as sensitive by default.

## Contract surface restrictions

`DiskInterface` public method signatures MUST NOT contain:

- `Psr\Http\Message\*`;
- `Psr\Http\Message\StreamInterface`;
- `Coretsia\Platform\*`;
- `Coretsia\Integrations\*`;
- `League\Flysystem\*`;
- `Symfony\Component\Filesystem\*`;
- `SplFileInfo`;
- `FilesystemIterator`;
- `DirectoryIterator`;
- `RecursiveDirectoryIterator`;
- resources;
- closures;
- vendor SDK objects;
- concrete service container objects;
- runtime wiring objects.

`DiskInterface` public method signatures MUST use only PHP built-in scalar/array/null/void types unless a future contracts epic introduces dedicated format-neutral contracts models.

## Acceptance scenario

When a future runtime package needs filesystem access:

1. the runtime owner receives or constructs a `DiskInterface` implementation;
2. the caller uses logical disk paths only;
3. the disk implementation resolves logical paths inside its implementation-owned backend root;
4. missing file and existing empty file remain distinguishable through `read()`;
5. path listing returns deterministic logical paths sorted by byte-order `strcmp`;
6. no vendor filesystem API appears in `core/contracts`;
7. no platform or integration package is required by `core/contracts`;
8. no raw path or file contents are emitted through unsafe diagnostics.

This acceptance scenario is policy intent.

The concrete filesystem adapter, path policy, disk registry, error mapping, configuration, observability integration, upload integration, session integration, lock integration, and driver implementation are runtime-owned.

## Verification evidence

Contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/FilesystemDiskInterfaceShapeContractTest.php
```

This test is expected to verify:

- `DiskInterface` exists;
- `DiskInterface` is an interface;
- `DiskInterface` exposes the canonical method surface;
- public method signatures do not depend on platform packages;
- public method signatures do not depend on integration packages;
- public method signatures do not depend on `Psr\Http\Message\*`;
- public method signatures do not depend on vendor filesystem concretes;
- public method signatures do not expose streams, resources, iterators, or `SplFileInfo`;
- filesystem contracts do not declare DI tag constants.

Architecture gates are expected to verify that `core/contracts` does not introduce forbidden compile-time dependencies.

## Non-goals

This SSoT does not define:

- concrete filesystem implementation;
- local disk adapter;
- cloud disk adapter;
- memory disk adapter;
- disk registry;
- filesystem manager;
- path normalizer;
- path traversal protection implementation;
- symlink policy;
- absolute path policy implementation;
- upload service;
- session storage implementation;
- lock storage implementation;
- temporary URL generation;
- file visibility / ACL policy;
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

## Cross-references

- [SSoT Index](./INDEX.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [DTO Policy](./dto-policy.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
