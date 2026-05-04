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

# ADR-0013: Secrets port

## Status

Accepted.

## Context

Epic `1.180.0` introduces the stable secrets contract under:

```text
framework/packages/core/contracts/src/Secrets/
```

Runtime packages and downstream packages need a shared contracts-level boundary for requesting secret values without coupling `core/contracts` to a concrete secret backend.

Expected future secret backends may include:

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

These backends have different configuration models, credential models, failure modes, caching behavior, rotation behavior, and operational diagnostics. Those differences must not leak into `core/contracts`.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
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
- vendor concrete clients
- concrete service container implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- generated architecture artifacts

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/secrets-contracts.md
```

Secrets diagnostics and redaction behavior must also follow:

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

Phase 0 cemented the baseline no-secrets output policy, safe explain policy, missing-vs-empty distinction, deterministic json-like payload behavior, and float-forbidden payload model.

Secrets contracts must preserve those invariants before any runtime secret backend exists.

## Decision

Coretsia will introduce one canonical secrets resolver port in `core/contracts`:

```text
Coretsia\Contracts\Secrets\SecretsResolverInterface
```

The implementation path is:

```text
framework/packages/core/contracts/src/Secrets/SecretsResolverInterface.php
```

The canonical interface shape is:

```text
resolve(string $ref): string
```

`resolve()` accepts a non-empty secret reference and returns the resolved secret value as a string.

The PHPDoc input shape is:

```php
@param non-empty-string $ref
```

The PHPDoc output shape is:

```php
@return string
```

The contracts package defines only:

- the stable secrets resolver port;
- the stable scalar secret reference boundary;
- the non-null resolved value boundary;
- redaction requirements for resolved secret values;
- implementation-side safe diagnostic allowances;
- dependency and ownership boundaries for future runtime packages.

The contracts package does not implement:

- secret storage;
- env lookup;
- dotenv lookup;
- file lookup;
- Vault lookup;
- cloud secret manager lookup;
- backend selection;
- backend discovery;
- secret caching;
- secret rotation;
- secret encryption;
- secret decryption;
- credential discovery;
- config loading;
- DI registration;
- debug output;
- health checks;
- exception mapping;
- observability emission;
- generated artifacts.

## Secret reference decision

A secret reference is a stable logical identifier used to request a secret value from a runtime-owned resolver.

A secret reference is not the secret value.

The canonical parameter name is:

```text
ref
```

Examples of safe secret references include:

```text
database.main.password
mail.smtp.password
oauth.github.client-secret
vault:mail/default/api-key
env:APP_SECRET
```

A secret reference must be non-empty.

A secret reference should be stable, deterministic, and safe for owner-approved diagnostics.

A secret reference must not contain:

- raw secret values;
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

Runtime owners may define stricter reference grammars.

The contracts package defines only the scalar reference boundary.

## Resolved value decision

A resolved secret value is sensitive by default.

Resolved secret values may include:

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

Resolved secret values must not be logged, printed, traced, exported, rendered, serialized into public diagnostics, copied into error descriptor extensions, or exposed through health checks.

Resolved secret values must not be used as:

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

Safe implementation diagnostics may expose only derived information such as:

```text
ref
hash(value)
len(value)
```

`hash(value)` must be deterministic and non-reversible according to owner policy.

`len(value)` must expose only length, not content.

Safe derivations must not expose raw secret values or allow reconstruction of secret values.

## Missing vs empty decision

Missing and present-empty secret values must remain distinguishable.

The resolver contract intentionally returns:

```text
string
```

and not:

```text
?string
```

`null` is not used to represent a missing secret.

A returned empty string represents an explicitly resolved empty secret value when the runtime owner and backend allow empty secret values.

A missing secret, inaccessible secret, denied secret, invalid reference, backend failure, or policy failure is runtime-owned failure behavior.

Runtime owners may throw owner-defined exceptions for missing or failed secret resolution.

Owner-defined exceptions and diagnostics must preserve the redaction policy defined by:

```text
docs/ssot/secrets-contracts.md
```

Epic `1.180.0` does not introduce:

- `SecretNotFoundException`
- `SecretException`
- failure enum
- optional result object
- nullable result

## No backend-specific API decision

`SecretsResolverInterface` must not expose backend-specific objects or response types.

It must not expose:

- backend clients;
- env repositories;
- config repositories;
- filesystem handles;
- Vault clients;
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

Backend choice is implementation-owned.

A future env-backed resolver, Vault-backed resolver, cloud-backed resolver, test resolver, null resolver, or composite resolver must be able to implement the same interface without changing the contracts API.

## Redaction and diagnostics decision

Secrets redaction is mandatory across all diagnostic surfaces.

Debug outputs must redact secret values.

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

Debug outputs must not print resolved secret values.

Debug outputs must not print backend credentials.

Debug outputs must not print provider payloads or provider responses when those payloads may contain secrets.

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

If a debug output cannot prove safety, it must omit the value.

Runtime owners must prefer omission over unsafe emission.

## Observability decision

Secrets contracts introduce no observability signals and no metric label keys.

Future runtime implementations may emit logs, spans, metrics, or profiling signals only when they follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/profiling-ports.md
```

Metric labels must remain within the canonical allowlist:

```text
method
status
driver
operation
table
outcome
```

For secrets, runtime owners may use only safe, bounded-cardinality values for allowed labels.

Reasonable safe label usage may include:

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

Secret implementations must not use the following as metric labels:

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

Secret-value hashes and lengths are safe diagnostics, not metric labels by default.

If a future owner wants secret reference or backend-specific metric labels, that owner must update the observability SSoT explicitly and prove bounded-cardinality and privacy safety.

## Error handling decision

Epic `1.180.0` does not introduce a secrets exception hierarchy.

Secret resolution failure handling is runtime-owned.

Possible runtime failure categories include:

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

Runtime owner packages may introduce owner-defined exceptions and failure codes later.

Owner-defined exceptions and diagnostics must not expose:

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

The contracts package must not implement secret exception mapping.

The contracts package must not require `ErrorDescriptor` construction inside secrets contracts.

## Runtime ownership decision

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

This ADR records runtime policy intent only.

It does not create or modify `platform/secrets`.

## Downstream usage decision

Downstream packages that need secrets must depend only on:

```text
Coretsia\Contracts\Secrets\SecretsResolverInterface
```

Downstream packages must not depend on concrete secret backends when a secrets resolver is sufficient.

Downstream packages must not read environment variables directly when secret resolution is runtime-owned.

Downstream packages must not require Vault, cloud secret manager, dotenv, filesystem, or env-specific concrete APIs in their public contract surfaces.

A downstream package may request a secret by reference and use the returned value for its implementation-owned runtime behavior.

A downstream package must not log, print, trace, export, render, or otherwise emit the returned secret value.

## Config, DI tag, and artifact decision

Epic `1.180.0` introduces no config roots, no config keys, no DI tags, and no artifacts.

The contracts package must not introduce:

- secrets config roots;
- package config files;
- config defaults;
- config rules;
- secrets DI tag constants;
- package-local mirror constants for secrets tags;
- secrets tag metadata keys;
- resolver discovery semantics;
- backend discovery semantics;
- secrets artifacts;
- secret reference artifacts;
- secret value artifacts;
- backend artifacts;
- resolver artifacts;
- credential artifacts;
- debug artifacts;
- runtime lifecycle artifacts.

Possible future runtime config paths may include:

```text
secrets.default_resolver
secrets.resolvers
secrets.backends
```

These paths are documented only as future runtime policy context.

They are not config roots or config keys introduced by `core/contracts`.

Future runtime owner packages may introduce secrets config, DI tags, or artifacts only through their own owner epics and the corresponding SSoT registry process.

Any future generated artifact must not contain raw secret values, backend credentials, or raw provider payloads.

## DTO boundary decision

`SecretsResolverInterface` is a contracts interface.

It is not a DTO-marker class.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Interfaces introduced by epic `1.180.0` must not be treated as DTOs.

## Consequences

Positive consequences:

- Any package can request secrets through a stable contracts-level port.
- Backend selection remains runtime-owned and swappable.
- `core/contracts` remains independent of platform packages, integration packages, PSR-7, Vault APIs, cloud SDKs, dotenv libraries, filesystem APIs, and vendor clients.
- Missing and present-empty secret values remain distinguishable.
- Resolved secret values are sensitive by design.
- Debug output and diagnostics are forced into a redaction-first model.
- Downstream packages do not need to know whether secrets come from env, files, Vault, cloud providers, tests, or another backend.

Trade-offs:

- Contracts do not provide a concrete resolver implementation.
- Contracts do not define a secret reference grammar beyond the non-empty scalar boundary.
- Contracts do not define caching, rotation, backend priority, or backend health policy.
- Contracts do not define a canonical secrets exception hierarchy.
- Runtime owners must implement resolver binding, backend behavior, diagnostics, configuration, and error mapping later.

## Rejected alternatives

### Return `?string` from the resolver

Rejected.

A nullable result would collapse missing and present-empty semantics too easily.

`null` would represent missing, while an empty string would represent present-empty, but downstream code would be tempted to rely on truthiness.

The contract uses `resolve(string $ref): string`.

Missing secret behavior is runtime-owned failure behavior.

### Introduce a `SecretValue` object

Rejected.

A value object would add a contracts model before runtime owners define materialization, lifetime, masking, zeroization, serialization, and debugging policy.

The first boundary is intentionally minimal and scalar:

```text
resolve(string $ref): string
```

Future owners may introduce richer safe models only through SSoT and ADR updates.

### Introduce a secrets exception hierarchy in contracts

Rejected.

Failure categories, backend errors, access policy, retry behavior, and error mapping are implementation-owned.

Introducing contracts exceptions now would prematurely freeze backend failure semantics.

Runtime owners may define owner-specific exceptions later.

### Put env lookup into contracts

Rejected.

Environment access is a runtime implementation choice.

The contracts package must not read `.env`, process env, files, config repositories, or global state.

A future env-backed resolver belongs to runtime owner packages.

### Put Vault or cloud SDK types in contracts

Rejected.

Vault and cloud secret managers are possible implementations, not the contracts boundary.

Exposing vendor SDK types would make `core/contracts` depend on specific infrastructure and would prevent alternative implementations from remaining dependency-light.

### Expose backend credentials in the contract

Rejected.

Backend credentials are runtime-owned secrets.

They must never cross the contracts API as public method parameters, return values, diagnostics, logs, health output, or generated artifacts.

### Make secret references metric labels by default

Rejected.

Secret references may reveal deployment details, backend paths, tenant data, or internal policy names.

They may also be high-cardinality.

Secret references may be emitted only when safe and owner-approved.

They are not baseline metric labels.

### Emit `hash(value)` as a metric label

Rejected.

A secret-value hash is a safe diagnostic derivation only when owner-approved for a specific output.

It is not a metric label by default and may still create high cardinality or privacy risk.

Metrics must use only safe, bounded values from the observability allowlist.

### Introduce secrets config roots in contracts

Rejected.

Secrets configuration belongs to runtime owner packages.

This epic introduces no config roots and no config keys.

Future runtime owners may introduce secrets config only through their own owner epics and the config roots registry process.

### Introduce secrets DI tags in contracts

Rejected.

Resolver discovery, backend discovery, priority semantics, and policy discovery are runtime-owned.

If a future runtime owner needs secrets DI tags, that owner must introduce them through the tag registry.

### Generate secrets artifacts from contracts

Rejected.

Generated artifacts require owner-defined schema semantics, source discovery, deterministic serialization, runtime integration, and redaction proof.

Those responsibilities belong to future runtime owner packages, not `core/contracts`.

## What this epic must not create

Epic `1.180.0` must not create:

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

## Non-goals

This ADR does not implement:

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

## Related SSoT

- `docs/ssot/secrets-contracts.md`
- `docs/ssot/config-and-env.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/dto-policy.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/tags.md`
- `docs/ssot/artifacts.md`

## Related epic

- `1.180.0 Contracts: Secrets port`
