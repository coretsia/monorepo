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

# Secrets Contracts SSoT

## Scope

This document is the Single Source of Truth for Coretsia secrets contracts, secret resolver port semantics, secret reference policy, secret value redaction rules, diagnostics safety, and runtime ownership boundaries.

This document governs contracts introduced by epic `1.180.0` under:

```text
framework/packages/core/contracts/src/Secrets/
```

The canonical secrets contract introduced by this epic is:

```text
Coretsia\Contracts\Secrets\SecretsResolverInterface
```

The implementation path is:

```text
framework/packages/core/contracts/src/Secrets/SecretsResolverInterface.php
```

It complements:

```text
docs/ssot/config-and-env.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/dto-policy.md
docs/ssot/config-roots.md
docs/ssot/tags.md
docs/ssot/artifacts.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Any package must be able to request secrets through a stable contracts-level port without depending on a concrete secret backend and without any risk of secret values leaking to logs, metrics, traces, CLI output, HTTP debug output, health output, generated artifacts, or unsafe diagnostics.

The contract introduced by this epic defines only:

- a single secrets resolver port;
- a stable secret reference input boundary;
- a strict non-null resolved value boundary;
- hard redaction policy for resolved secret values;
- implementation-side safe diagnostic allowances;
- dependency and ownership boundaries for future runtime packages.

The contracts package MUST NOT implement secret storage, env lookup, vault lookup, cloud secret manager access, secret caching, rotation, encryption, config loading, DI registration, backend discovery, generated artifacts, observability emission, health checks, debug endpoints, or error mapping.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.20.0` no-secrets output policy applies to resolved secret values, diagnostics, logs, spans, metrics, CLI output, health output, debug output, worker output, and backend errors.
- `0.60.0` missing vs empty MUST remain distinguishable when runtime owners resolve secrets from env, files, vaults, cloud providers, generated config, or another backend.
- `0.70.0` json-like payloads forbid floats, including `NaN`, `INF`, and `-INF`, when future owners expose safe metadata around secret resolution.
- `0.70.0` lists preserve order and maps use deterministic key ordering when json-like metadata is exposed.
- `0.90.0` safe diagnostics MUST NOT expose raw values and MAY expose only safe derivations such as `hash(value)` or `len(value)`.

Epic `1.180.0` itself introduces no secret implementation, no env-backed implementation, no vault-backed implementation, no cloud secret manager implementation, no config root, no generated artifact, and no DI tag.

## Contract boundary

Secrets contracts are format-neutral and backend-neutral.

They define a stable port and boundary policy only.

The contracts package MUST NOT implement:

- secret storage;
- secret backend access;
- `.env` lookup;
- process environment lookup;
- file-based secret lookup;
- vault lookup;
- cloud secret manager lookup;
- secret reference parsing beyond the public contract input shape;
- secret caching;
- secret rotation;
- secret encryption or decryption;
- secret materialization policy;
- credential discovery;
- backend discovery;
- backend selection;
- debug output;
- CLI commands;
- health checks;
- logger integration;
- metrics integration;
- tracing integration;
- config defaults;
- config rules;
- DI registration;
- generated artifacts;
- package providers.

Runtime owner packages implement concrete behavior later.

## Contract package dependency policy

Secrets contracts MUST remain dependency-free beyond PHP itself.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Message\RequestInterface`
- `Psr\Http\Message\ResponseInterface`
- Vault concrete APIs
- AWS SDK concrete APIs
- Google Cloud concrete APIs
- Azure SDK concrete APIs
- Symfony Secrets concrete APIs
- dotenv concrete APIs
- HTTP client concrete APIs
- filesystem concrete APIs
- database concrete APIs
- Redis concrete APIs
- cache concrete APIs
- lock concrete APIs
- clock concrete APIs
- concrete service container implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- framework tooling packages
- generated architecture artifacts
- vendor-specific runtime clients

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

Allowed direction:

```text
platform/secrets → core/contracts
platform/config → core/contracts
platform/mail → core/contracts
platform/database → core/contracts
platform/http → core/contracts
integrations/* secret backends → core/contracts
application runtime packages → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/secrets
core/contracts → platform/config
core/contracts → platform/mail
core/contracts → platform/database
core/contracts → platform/http
core/contracts → integrations/*
```

## DTO terminology boundary

This document uses the terms `contract`, `port`, `reference`, `secret value`, and `runtime boundary` according to:

```text
docs/ssot/dto-policy.md
```

`SecretsResolverInterface` is a contracts interface.

It is not a DTO-marker class.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Interfaces introduced by epic `1.180.0` MUST NOT be treated as DTOs.

## Secret reference policy

A secret reference is a stable logical identifier used to request a secret value from a runtime-owned resolver.

A secret reference is not the secret value.

The canonical input parameter name is:

```text
ref
```

The canonical PHPDoc shape for a secret reference input is:

```php
@param non-empty-string $ref
```

A secret reference MUST be non-empty.

A secret reference SHOULD be safe, stable, deterministic, and suitable for owner-approved diagnostics.

Examples of safe secret references:

```text
database.main.password
mail.smtp.password
oauth.github.client-secret
vault:mail/default/api-key
env:APP_SECRET
```

A secret reference MUST NOT contain:

- the raw secret value;
- passwords;
- tokens;
- private keys;
- bearer credentials;
- raw DSNs containing credentials;
- raw authorization headers;
- raw cookies;
- request bodies;
- response bodies;
- private customer data;
- raw SQL;
- absolute local paths;
- timestamps;
- random values;
- process ids;
- host machine identifiers.

Runtime owners MAY define stricter reference grammars.

The contracts package defines only the stable scalar reference boundary.

## Secret reference diagnostics

The epic-level allowed diagnostic values are:

```text
ref
hash(value)
len(value)
```

A secret reference MAY be emitted only when it is safe and owner-approved.

When a reference itself may reveal sensitive deployment details, tenant data, customer data, file paths, or credential material, runtime owners MUST redact it or derive a safe representation instead.

Safe reference diagnostics MAY use:

```text
ref
hash(ref)
len(ref)
```

Runtime owners MUST prefer omission over unsafe emission.

## Secret value sensitivity

A resolved secret value is sensitive by default.

Resolved secret values MAY include:

- passwords;
- API keys;
- OAuth tokens;
- bearer tokens;
- private keys;
- database credentials;
- SMTP credentials;
- signing keys;
- encryption keys;
- DSNs;
- webhook secrets;
- cloud provider credentials;
- backend-specific credential material.

Resolved secret values MUST NOT be treated as public diagnostics.

Resolved secret values MUST NOT be used as:

- metric labels;
- span name fragments;
- raw span attributes;
- log messages;
- error descriptor extensions;
- health output fields;
- CLI output fields;
- HTTP debug output fields;
- generated artifact identities;
- public debug output;
- exception messages;
- config explain output values.

Raw resolved secret values MUST NOT be logged, printed, traced, exported, rendered, serialized into public diagnostics, copied into error descriptor extensions, or exposed through health checks.

Safe implementation diagnostics MAY expose only derived information such as:

```text
ref
hash(value)
len(value)
```

`hash(value)` MUST be deterministic and non-reversible according to owner policy.

`len(value)` MUST expose only length, not content.

Safe derivations MUST NOT expose raw secret values or allow reconstruction of secret values.

## Missing vs empty semantics

Missing and present-empty secret values MUST remain distinguishable.

The resolver contract intentionally uses:

```text
resolve(string $ref): string
```

and not:

```text
resolve(string $ref): ?string
```

`null` MUST NOT be used to represent a missing secret.

A returned empty string represents an explicitly resolved empty secret value when the runtime owner and backend allow empty secret values.

A missing secret, inaccessible secret, denied secret, invalid reference, backend failure, or policy failure is runtime-owned failure behavior.

Runtime owners MAY throw owner-defined exceptions for missing or failed secret resolution.

Owner-defined exceptions and diagnostics MUST preserve the redaction policy in this SSoT.

The contracts package does not introduce a `SecretNotFoundException`, `SecretException`, optional result object, or nullable result in epic `1.180.0`.

## Secrets resolver interface

`SecretsResolverInterface` is the canonical contracts-level secrets resolution port.

The implementation path is:

```text
framework/packages/core/contracts/src/Secrets/SecretsResolverInterface.php
```

The canonical interface shape is:

```text
resolve(string $ref): string
```

`resolve()` receives a non-empty secret reference and returns the resolved secret value as a string.

The PHPDoc shape for `resolve()` input MUST be:

```php
@param non-empty-string $ref
```

The PHPDoc shape for `resolve()` output MUST be:

```php
@return string
```

`resolve()` MUST NOT return `null`.

`resolve()` MUST NOT return objects, arrays, streams, resources, closures, vendor secret handles, or runtime wiring objects.

`resolve()` MUST NOT expose backend-specific response objects.

`resolve()` MUST NOT expose raw secret values in exception messages, logs, metrics, spans, health output, CLI output, HTTP debug output, config explain output, generated artifacts, or unsafe diagnostics.

Runtime implementations MAY resolve from:

```text
env
dotenv
file
vault
cloud-secret-manager
in-memory
test
null
composite
```

Those implementations are runtime-owned and outside `core/contracts`.

`SecretsResolverInterface` MUST NOT expose:

- backend clients;
- env repositories;
- config repositories;
- filesystem handles;
- vault clients;
- cloud SDK clients;
- HTTP clients;
- service containers;
- logger objects;
- tracer objects;
- metric objects;
- credentials;
- DSNs;
- provider responses;
- provider debug payloads.

## Resolver failure policy

Secret resolution failure handling is implementation-owned.

Possible failure categories include:

```text
missing
invalid_ref
access_denied
backend_unavailable
backend_timeout
backend_error
policy_denied
```

These categories are examples only.

Epic `1.180.0` does not introduce a canonical failure enum, exception hierarchy, error descriptor mapping, or result object.

Runtime owner packages MAY introduce owner-defined exceptions and failure codes later.

Owner-defined exceptions and diagnostics MUST NOT expose:

- raw secret values;
- credentials;
- tokens;
- private keys;
- raw DSNs with credentials;
- backend request payloads;
- backend response payloads;
- provider object dumps;
- environment-specific hostnames when unsafe;
- tenant ids;
- user ids;
- request ids;
- correlation ids;
- absolute local paths;
- private customer data;
- stack traces by default.

If secret errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

The contracts package MUST NOT implement secret exception mapping.

The contracts package MUST NOT require `ErrorDescriptor` construction inside secrets contracts.

## Runtime ownership policy

Secrets runtime behavior is expected to be owned by future runtime packages.

`platform/secrets` is expected to bind a concrete resolver using this contract.

Possible future resolver implementations include:

```text
null
env
dotenv
file
vault
cloud-secret-manager
composite
test
```

Possible future responsibilities of `platform/secrets` include:

- selecting the effective resolver;
- resolving references against configured backends;
- backend priority and fallback policy;
- secret caching policy;
- secret rotation policy;
- secret value lifetime policy;
- safe secret diagnostics;
- safe debug output policy;
- backend health behavior;
- configuration loading;
- resolving backend credentials through owner-approved mechanisms;
- registering DI services.

This is documented runtime policy only.

Epic `1.180.0` does not create or modify `platform/secrets`.

## Downstream usage policy

Downstream packages that need secrets MUST depend only on:

```text
Coretsia\Contracts\Secrets\SecretsResolverInterface
```

Downstream packages MUST NOT depend on concrete secret backends when a secrets resolver is sufficient.

Downstream packages MUST NOT read environment variables directly when secret resolution is runtime-owned.

Downstream packages MUST NOT require Vault, cloud secret manager, dotenv, filesystem, or env-specific concrete APIs in their public contract surfaces.

A downstream package MAY request a secret by reference and use the returned value for its implementation-owned runtime behavior.

A downstream package MUST NOT log, print, trace, export, render, or otherwise emit the returned secret value.

## Credentials and backend secrets policy

Secret backend credentials are runtime-owned.

Secrets contracts MUST NOT expose or require backend credentials.

Secrets contracts MUST NOT contain:

- backend usernames;
- backend passwords;
- API keys;
- private keys;
- OAuth tokens;
- bearer tokens;
- Vault tokens;
- cloud provider credentials;
- DSNs;
- connection strings;
- raw environment values;
- credential objects;
- secret backend clients.

Backend credentials MUST be resolved by runtime owner packages through an owner-approved secret mechanism.

Backend credentials MUST never be printed, logged, traced, exported, rendered, copied into error descriptor extensions, or exposed through health, CLI, HTTP debug, or config explain output.

## Config policy

Epic `1.180.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for secrets contracts.

No files under package `config/` are introduced by this epic.

Possible future runtime config paths may include:

```text
secrets.default_resolver
secrets.resolvers
secrets.backends
```

These paths are documented here only as future runtime policy context.

They are not config roots or config keys introduced by `core/contracts`.

Secret backend selection, backend credentials, cache policy, rotation policy, failure policy, and debug policy are runtime configuration concerns.

Future runtime owner packages MAY introduce secrets config only through their own owner epics and the config roots registry process.

## DI tag policy

Epic `1.180.0` introduces no DI tags.

The contracts package MUST NOT declare public secrets tag constants.

The contracts package MUST NOT define package-local mirror constants for secrets tags.

The contracts package MUST NOT define secrets tag metadata keys, resolver discovery semantics, backend discovery semantics, priority semantics, or policy discovery semantics.

If a future runtime owner needs secrets DI tags, that owner MUST introduce them through:

```text
docs/ssot/tags.md
```

according to tag registry rules.

## Artifact policy

Epic `1.180.0` introduces no artifacts.

The contracts package MUST NOT generate:

- secrets artifacts;
- secret reference artifacts;
- secret value artifacts;
- backend artifacts;
- resolver artifacts;
- credential artifacts;
- debug artifacts;
- runtime lifecycle artifacts.

A future runtime owner MAY introduce generated secrets artifacts only through its own owner epic and the artifact registry process.

Any future artifact MUST follow:

```text
docs/ssot/artifacts.md
```

Generated artifacts MUST NOT contain raw secret values.

Generated artifacts MUST NOT contain backend credentials.

Generated artifacts MUST NOT contain raw provider payloads.

## Observability and diagnostics policy

Secrets contracts do not define observability signals.

Runtime implementations MAY emit logs, spans, metrics, or profiling signals around secret resolution only when those signals follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/profiling-ports.md
```

Metric labels MUST remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.180.0` introduces no new metric label keys.

The baseline allowed metric label keys are:

```text
method
status
driver
operation
table
outcome
```

For secrets, runtime owners MAY use only safe, bounded-cardinality values for allowed labels.

Reasonable safe label usage MAY include:

```text
operation=resolve
outcome=resolved
outcome=missing
outcome=denied
outcome=error
driver=env
driver=vault
driver=file
driver=cloud
driver=null
driver=test
```

only when these values are safe, stable, bounded, and owner-approved.

Secret implementations MUST NOT use the following as metric labels:

- raw secret value;
- hash of secret value;
- secret length;
- secret reference unless explicitly owner-approved as safe and bounded;
- backend credential;
- backend token;
- backend path when unsafe;
- tenant id;
- user id;
- request id;
- correlation id;
- raw path;
- raw query;
- header value;
- cookie value;
- token;
- session identifier;
- provider response id;
- policy value that is user-controlled or high-cardinality.

The label keys `request_id`, `correlation_id`, `tenant_id`, and `user_id` remain forbidden under the baseline policy.

Secret-value hashes and lengths are safe diagnostics, not metric labels by default.

If a future owner wants secret reference or backend-specific metric labels, that owner MUST update the observability SSoT explicitly and prove bounded-cardinality and privacy safety.

### Safe diagnostics

Safe implementation diagnostics MAY expose:

```text
ref
hash(ref)
len(ref)
hash(value)
len(value)
operation
outcome
driver
```

only when values are safe for the target output and do not create high-cardinality leakage.

`hash(value)` and `len(value)` are allowed safe derivations for implementation diagnostics.

Raw values MUST NOT be printed or logged.

Runtime owners MUST prefer omission over unsafe emission.

Safe derivations MUST NOT expose raw secret values or allow reconstruction of sensitive values.

## Debug output policy

Debug outputs are sensitive by default.

Debug outputs include:

- CLI debug commands;
- HTTP debug endpoints;
- config explain output;
- health details;
- diagnostic dumps;
- trace dumps;
- test diagnostics;
- worker diagnostics;
- exception rendering.

Debug outputs MUST redact secret values.

Debug outputs MUST NOT print resolved secret values.

Debug outputs MUST NOT print backend credentials.

Debug outputs MUST NOT print provider payloads or provider responses when those payloads may contain secrets.

Allowed debug output around secret resolution is limited to safe data such as:

```text
ref
hash(ref)
len(ref)
hash(value)
len(value)
operation
outcome
driver
```

only when owner-approved and safe for the specific output.

If a debug output cannot prove safety, it MUST omit the value.

## Error and exception policy

Epic `1.180.0` does not introduce a secrets exception hierarchy.

Secret resolution failure handling is runtime-owned.

Concrete implementations MAY throw owner-defined exceptions.

Owner-defined exceptions and diagnostics MUST NOT expose:

- raw secret values;
- credentials;
- tokens;
- API keys;
- passwords;
- private keys;
- DSNs containing credentials;
- backend request payloads;
- backend response payloads;
- provider object dumps;
- hostnames when environment-specific;
- connection strings;
- tenant ids;
- user ids;
- request ids;
- correlation ids;
- raw paths when unsafe;
- raw queries;
- cookies;
- private customer data;
- absolute local paths;
- stack traces by default.

If secrets errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

A future runtime mapper MAY map owner-defined secret exceptions to normalized errors without requiring `core/contracts` to construct or own `ErrorDescriptor`.

Mapper-owned `ErrorDescriptor.extensions` MAY include safe secrets metadata such as operation, outcome, driver, safe reference, reference hash, reference length, value hash, or value length.

Mapper-owned extensions MUST NOT include raw secret values, credentials, backend payloads, provider responses, DSNs containing credentials, tokens, cookies, private customer data, absolute local paths, request ids, correlation ids, tenant ids, or user ids.

Mapper-owned extensions MUST obey the `ErrorDescriptor.extensions` json-like and redaction policy.

The contracts package MUST NOT implement secrets exception mapping.

The contracts package MUST NOT require `ErrorDescriptor` construction inside secrets contracts.

## Contract surface restrictions

Secrets public method signatures MUST NOT contain:

- `Psr\Http\Message\*`;
- `Psr\Http\Message\RequestInterface`;
- `Psr\Http\Message\ResponseInterface`;
- `Coretsia\Platform\*`;
- `Coretsia\Integrations\*`;
- Vault concrete classes;
- cloud secret manager concrete classes;
- dotenv concrete classes;
- HTTP client concrete classes;
- filesystem concrete classes;
- database concrete classes;
- Redis concrete classes;
- cache concrete classes;
- lock concrete classes;
- vendor clients;
- vendor command objects;
- provider response objects;
- resources;
- closures;
- streams;
- iterators;
- generators;
- concrete service container objects;
- runtime wiring objects.

Secrets public method signatures introduced by this SSoT MUST use only:

- PHP built-in scalar types;
- PHP `void` where applicable.

Epic `1.180.0` introduces only one public method:

```text
resolve(string $ref): string
```

Secrets contracts MUST NOT introduce provider-specific, backend-specific, credential-specific, tenant-specific, user-specific, request-specific, or correlation-specific error code constants in epic `1.180.0`.

## What this epic MUST NOT create

Epic `1.180.0` MUST NOT create:

```text
framework/packages/platform/secrets/*
framework/packages/platform/config/*
framework/packages/platform/http/*
framework/packages/integrations/*
config/*.php
provider/module wiring files
secret resolver implementation
env secret resolver implementation
dotenv secret resolver implementation
file secret resolver implementation
vault secret resolver implementation
cloud secret manager implementation
secret cache implementation
secret rotation implementation
secret health check implementation
secret debug endpoint
secret CLI command
secret exception mapper
DI tag constants
artifact files
```

These are runtime-owned concerns for future owner epics.

## Acceptance scenario

When a future runtime package needs a secret:

1. downstream code depends only on `SecretsResolverInterface`;
2. downstream code calls `resolve()` with a non-empty safe secret reference;
3. the runtime-owned resolver resolves the reference through its implementation-owned backend policy;
4. the resolver returns a string secret value and never returns `null`;
5. a present-empty secret value remains distinguishable from a missing secret;
6. missing secret behavior is handled by runtime-owned failure policy;
7. the returned secret value is used only for implementation-owned runtime behavior;
8. diagnostics may expose only safe reference data or safe value derivations such as `hash(value)` or `len(value)`;
9. raw secret values are not logged, traced, emitted as metric labels, copied into error descriptor extensions, printed to CLI output, exposed in health output, exposed in HTTP debug output, or written to generated artifacts;
10. the concrete resolver can be changed from env-backed to Vault-backed or cloud-backed without changing the contracts API;
11. no platform or integration package is required by `core/contracts`.

This acceptance scenario is policy intent.

The concrete resolver implementation, backend selection, caching, rotation, configuration, failure handling, debug output, health output, observability integration, and error mapping are runtime-owned.

## Verification evidence

Contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/SecretsResolverInterfaceShapeContractTest.php
```

This test is expected to verify:

- `SecretsResolverInterface` exists;
- `SecretsResolverInterface` is an interface;
- `SecretsResolverInterface` exposes the canonical method surface;
- `resolve()` accepts `string $ref`;
- `resolve()` returns `string`;
- `resolve()` has PHPDoc `@param non-empty-string $ref`;
- `resolve()` has PHPDoc `@return string`;
- `resolve()` is not nullable;
- secrets contracts do not depend on platform packages;
- secrets contracts do not depend on integration packages;
- secrets contracts do not depend on `Psr\Http\Message\*`;
- secrets contracts do not depend on Vault, cloud SDK, dotenv, filesystem, database, Redis, cache, lock, or vendor concretes;
- secrets contracts do not expose streams, resources, iterators, generators, closures, vendor clients, backend objects, or runtime wiring objects;
- secrets contracts do not declare DI tag constants;
- secrets contracts do not introduce config roots, config keys, or artifact concepts;
- secrets contracts do not introduce a secrets exception hierarchy;
- secrets contracts do not expose `float` as an accepted value or returned result value.

Architecture gates are expected to verify that `core/contracts` does not introduce forbidden compile-time dependencies.

## Non-goals

This SSoT does not define:

- concrete secret resolver implementation;
- env-backed resolver;
- dotenv-backed resolver;
- file-backed resolver;
- Vault-backed resolver;
- cloud secret manager resolver;
- composite resolver;
- resolver registry;
- backend registry;
- secret cache;
- secret rotation;
- secret encryption;
- secret decryption;
- secret materialization policy;
- secret value lifetime policy;
- backend credential resolution;
- backend health checks;
- debug endpoints;
- CLI commands;
- HTTP integration;
- worker integration;
- config roots;
- config defaults;
- config rules;
- DI tags;
- DI registration;
- generated artifacts;
- exception hierarchy;
- exception mapper;
- metrics schema;
- tracing schema;
- logging backend behavior;
- `platform/secrets` implementation;
- `integrations/*` secret backend implementation.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Config and env SSoT](./config-and-env.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [DTO Policy](./dto-policy.md)
- [Config Roots Registry](./config-roots.md)
- [Tag Registry](./tags.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
